<?php
// Suppress all warnings and notices
error_reporting(E_ERROR);
ini_set('display_errors', 0);

// Buffer all output to prevent any content being sent before PDF
ob_start();

// TCPDF configuration constants - only define if not already defined
if (!defined('K_TCPDF_CALLS_IN_HTML')) {
    define('K_TCPDF_CALLS_IN_HTML', true);
}
if (!defined('K_TCPDF_THROW_EXCEPTION_ERROR')) {
    define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
}

// Start session and include required files
session_start();
require_once '../config.php';
require_once '../vendor/autoload.php';

if (!isset($_SESSION['requestor_id'])) {
    header("Location: login.php");
    exit();
}

// Check if history ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: request_history.php");
    exit();
}

$historyId = intval($_GET['id']);
$username = $_SESSION['username'] ?? 'User';

// Fetch the request history details with proper employee name joins and original justification
try {
    $query = "SELECT ah.*, 
              admin.username as admin_username,
              admin_emp.employee_name as admin_employee_name,
              tech_emp.employee_name as technical_employee_name,
              superior_emp.employee_name as superior_employee_name,
              helpdesk_emp.employee_name as helpdesk_employee_name,
              process_emp.employee_name as process_owner_employee_name,
              ISNULL(ir.justification, ISNULL(gr.justification, ah.justification)) as original_justification
              FROM uar.approval_history ah
              LEFT JOIN uar.admin_users admin ON ah.admin_id = admin.id
              LEFT JOIN uar.employees admin_emp ON admin.username = admin_emp.employee_id
              LEFT JOIN uar.admin_users tech_admin ON ah.technical_id = tech_admin.id
              LEFT JOIN uar.employees tech_emp ON tech_admin.username = tech_emp.employee_id
              LEFT JOIN uar.admin_users superior_admin ON ah.superior_id = superior_admin.id
              LEFT JOIN uar.employees superior_emp ON superior_admin.username = superior_emp.employee_id
              LEFT JOIN uar.admin_users helpdesk_admin ON ah.help_desk_id = helpdesk_admin.id
              LEFT JOIN uar.employees helpdesk_emp ON helpdesk_admin.username = helpdesk_emp.employee_id
              LEFT JOIN uar.admin_users process_admin ON ah.process_owner_id = process_admin.id
              LEFT JOIN uar.employees process_emp ON process_admin.username = process_emp.employee_id
              LEFT JOIN uar.individual_requests ir ON ah.access_request_number = ir.access_request_number
              LEFT JOIN uar.group_requests gr ON ah.access_request_number = gr.access_request_number
              WHERE ah.history_id = :history_id AND ah.requestor_name = :requestor_name";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':history_id' => $historyId,
        ':requestor_name' => $username
    ]);

    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        // Request not found or doesn't belong to the user
        header("Location: request_history.php");
        exit();
    }

    // Format the date
    $currentDate = date('F d, Y');
    $requestNumber = $request['access_request_number'] ?? 'N/A';
} catch (PDOException $e) {
    error_log("Error fetching request history details: " . $e->getMessage());
    header("Location: request_history.php?error=db");
    exit();
}

// Create new TCPDF instance
class UARPDF extends TCPDF
{
    public function Header()
    {
        // Empty header to override default behavior
    }

    public function Footer()
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }

    // Helper function to create a row with dynamic height
    public function createDynamicRow($data, $startY, $minHeight = 8)
    {
        $maxHeight = $minHeight;

        // Calculate required height for each cell
        foreach ($data as $item) {
            $this->SetFont($item['font'], $item['style'], $item['size']);
            $cellHeight = $this->getStringHeight($item['width'], $item['text']);
            if ($cellHeight > $maxHeight) {
                $maxHeight = $cellHeight;
            }
        }

        // Draw all cells with the calculated height
        $currentX = 10;
        foreach ($data as $item) {
            $this->SetFont($item['font'], $item['style'], $item['size']);
            $this->SetXY($currentX, $startY);
            $this->MultiCell($item['width'], $maxHeight, $item['text'], 1, $item['align'], false, 0, '', '', true, 0, false, true, $maxHeight, 'M');
            $currentX += $item['width'];
        }

        return $maxHeight;
    }
}

try {
    // Create new PDF document - LANDSCAPE orientation
    $pdf = new UARPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('UAR System');
    $pdf->SetAuthor('IT Department');
    $pdf->SetTitle('User Access Request - ' . $requestNumber);
    $pdf->SetSubject('UAR Form');
    $pdf->SetKeywords('UAR, Access, Request');

    // Set header/footer display
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);

    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // Set margins
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);

    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Set font
    $pdf->SetFont('helvetica', '', 9);

    // Add a page
    $pdf->AddPage();

    // Define colors
    $darkBlue = array(0, 51, 102);
    $lightBlue = array(173, 216, 230);
    $yellow = array(255, 255, 153);

    // Header Section with border
    $pdf->Rect(10, 10, 277, 20, 'D');

    // Left section - Logo placeholder (you can add logo here if needed)
    $pdf->SetXY(12, 12);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(60, 8, 'Alcantara Business Unit', 0, 0, 'L');

    // Center - Title
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetXY(10, 14);
    $pdf->Cell(277, 6, 'USER ACCESS REQUEST (UAR)', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetXY(10, 20);
    $pdf->Cell(277, 6, 'ABU Information Technology', 0, 0, 'C');

    // Right section - Form info
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY(237, 12);
    $pdf->Cell(45, 4, 'IT-UAR-001', 0, 1, 'R');
    $pdf->SetXY(237, 16);
    $pdf->Cell(45, 4, '', 0, 1, 'R');
    $pdf->SetXY(237, 20);
    $pdf->Cell(45, 4, 'Revision No. 00', 0, 0, 'R');

    // Requestor Information Section
    $pdf->SetY(35);
    $pdf->SetFillColor($darkBlue[0], $darkBlue[1], $darkBlue[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(277, 6, 'Requestor Information', 1, 1, 'C', true);

    // Requestor Info Row - IMPROVED WITH DYNAMIC HEIGHT
    $pdf->SetTextColor(0, 0, 0);
    $startY = $pdf->GetY();

    $requestorRowData = array(
        array('text' => 'Name:', 'width' => 15, 'font' => 'helvetica', 'style' => 'B', 'size' => 8, 'align' => 'L'),
        array('text' => $request['requestor_name'] ?? '', 'width' => 120, 'font' => 'helvetica', 'style' => '', 'size' => 8, 'align' => 'L'),
        array('text' => 'BU/Department:', 'width' => 30, 'font' => 'helvetica', 'style' => 'B', 'size' => 8, 'align' => 'L'),
        array('text' => ($request['business_unit'] ?? '') . ' / ' . ($request['department'] ?? ''), 'width' => 60, 'font' => 'helvetica', 'style' => '', 'size' => 8, 'align' => 'L'),
        array('text' => 'Date:', 'width' => 12, 'font' => 'helvetica', 'style' => 'B', 'size' => 8, 'align' => 'L'),
        array('text' => $currentDate, 'width' => 40, 'font' => 'helvetica', 'style' => '', 'size' => 8, 'align' => 'L')
    );

    $rowHeight = $pdf->createDynamicRow($requestorRowData, $startY, 8);
    $pdf->SetY($startY + $rowHeight);

    // UAR Ref No Row
    $pdf->Cell(277, 6, 'UAR Ref. No. ' . $requestNumber, 1, 1, 'R');

    // Request Details Section
    $pdf->Ln(2);
    $pdf->SetFillColor($darkBlue[0], $darkBlue[1], $darkBlue[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(277, 6, 'Request Details', 1, 1, 'C', true);

    // Get employee names from the joined data
    $requestType = strtolower($request['request_type'] ?? 'individual');
    $servedByName = $request['admin_employee_name'] ?? $request['admin_username'] ?? '';
    $testPerformedByName = $request['technical_employee_name'] ?? '';

    // Format access duration properly
    $accessDuration = '';
    if (!empty($request['duration_type'])) {
        if ($request['duration_type'] === 'permanent') {
            $accessDuration = 'Permanent';
        } elseif ($request['duration_type'] === 'temporary' && !empty($request['end_date'])) {
            $endDate = new DateTime($request['end_date']);
            $accessDuration = 'Until ' . $endDate->format('M d, Y');
        } else {
            $accessDuration = ucfirst($request['duration_type']);
        }
    } else {
        $accessDuration = 'N/A';
    }

    // DEBUG: Uncomment to see what data is being used
    // file_put_contents('debug_tcpdf.txt', print_r($request, true));

    // Additional debug info for troubleshooting
    // file_put_contents('debug_tcpdf_detailed.txt', 
    //     "Access Duration: " . $accessDuration . "\n" .
    //     "Justification (from approval_history): " . ($request['justification'] ?? 'EMPTY') . "\n" .
    //     "Original Justification (from request tables): " . ($request['original_justification'] ?? 'EMPTY') . "\n" .
    //     "Final Justification Used: " . ($request['original_justification'] ?? $request['justification'] ?? 'No justification provided') . "\n" .
    //     "Served By: " . $servedByName . "\n" .
    //     "Test Performed By: " . $testPerformedByName . "\n" .
    //     "Duration Type: " . ($request['duration_type'] ?? 'EMPTY') . "\n" .
    //     "End Date: " . ($request['end_date'] ?? 'EMPTY') . "\n"
    // );

    if ($requestType === 'group') {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(8, 6, 'II.', 1, 0, 'C');
        $pdf->Cell(269, 6, 'For Group Access', 1, 1, 'L');

        $pdf->SetFillColor($lightBlue[0], $lightBlue[1], $lightBlue[2]);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell(27, 10, 'Application/System', 1, 0, 'C', true);
        $pdf->Cell(27, 10, 'User Name', 1, 0, 'C', true);
        $pdf->Cell(20, 10, 'Access Type', 1, 0, 'C', true);
        $pdf->Cell(25, 10, 'Access Duration', 1, 0, 'C', true);
        $pdf->Cell(30, 10, 'Justification', 1, 0, 'C', true);
        $pdf->SetFillColor($yellow[0], $yellow[1], $yellow[2]);
        $pdf->Cell(32, 10, 'Remark/IT Evaluation', 1, 0, 'C', true);
        $pdf->SetFillColor($lightBlue[0], $lightBlue[1], $lightBlue[2]);
        $pdf->Cell(20, 10, 'Served By', 1, 0, 'C', true);
        $pdf->Cell(24, 10, 'Test Performed By', 1, 0, 'C', true);
        $pdf->SetFillColor($yellow[0], $yellow[1], $yellow[2]);
        $pdf->Cell(52, 10, 'Backup Performed By', 1, 1, 'C', true);

        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        $groupRowStartY = $pdf->GetY();
        $groupRowData = array(
            array('text' => $request['system_type'] ?? '', 'width' => 27, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
            array('text' => $request['group_usernames'] ?? '', 'width' => 27, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
            array('text' => $request['access_type'] ?? '', 'width' => 20, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
            array('text' => $accessDuration, 'width' => 25, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
            array('text' => $request['original_justification'] ?? $request['justification'] ?? 'No justification provided', 'width' => 30, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
            array('text' => $request['comments'] ?? '', 'width' => 32, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
            array('text' => $servedByName, 'width' => 20, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
            array('text' => $testPerformedByName, 'width' => 24, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
            array('text' => '', 'width' => 52, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L')
        );
        $groupRowHeight = $pdf->createDynamicRow($groupRowData, $groupRowStartY, 8);
        $pdf->SetY($groupRowStartY + $groupRowHeight);
    } else {
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(8, 6, 'I.', 1, 0, 'C');
        $pdf->Cell(269, 6, 'For Individual Access', 1, 1, 'L');

        $pdf->SetFillColor($lightBlue[0], $lightBlue[1], $lightBlue[2]);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell(27, 10, 'User Name', 1, 0, 'C', true);
        $pdf->Cell(27, 10, 'Application/System', 1, 0, 'C', true);
        $pdf->Cell(20, 10, 'Access Type', 1, 0, 'C', true);
        $pdf->Cell(25, 10, 'Access Duration', 1, 0, 'C', true);
        $pdf->Cell(30, 10, 'Justification', 1, 0, 'C', true);
        $pdf->SetFillColor($yellow[0], $yellow[1], $yellow[2]);
        $pdf->Cell(32, 10, 'Remark/IT Evaluation', 1, 0, 'C', true);
        $pdf->SetFillColor($lightBlue[0], $lightBlue[1], $lightBlue[2]);
        $pdf->Cell(20, 10, 'Served By', 1, 0, 'C', true);
        $pdf->Cell(24, 10, 'Test Performed By', 1, 0, 'C', true);
        $pdf->SetFillColor($yellow[0], $yellow[1], $yellow[2]);
        $pdf->Cell(52, 10, 'Backup Performed By', 1, 1, 'C', true);

        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        $applicationSystem = ($request['system_type'] ?? '');
        $accessTypeData = ($request['access_type'] ?? '');
        if (!empty($accessTypeData)) {
            $applicationSystem .= ($applicationSystem ? ' - ' : '') . $accessTypeData;
        }
        $dataRowStartY = $pdf->GetY();
        $dataRowData = array(
            array('text' => $request['requestor_name'] ?? '', 'width' => 27, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
            array('text' => $applicationSystem, 'width' => 27, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
            array('text' => $request['access_type'] ?? '', 'width' => 20, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
            array('text' => $accessDuration, 'width' => 25, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
            array('text' => $request['original_justification'] ?? $request['justification'] ?? 'No justification provided', 'width' => 30, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
            array('text' => $request['comments'] ?? '', 'width' => 32, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
            array('text' => $servedByName, 'width' => 20, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
            array('text' => $testPerformedByName, 'width' => 24, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
            array('text' => '', 'width' => 52, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L')
        );
        $dataRowHeight = $pdf->createDynamicRow($dataRowData, $dataRowStartY, 8);
        $pdf->SetY($dataRowStartY + $dataRowHeight);
    }

    // Display Admin Employee Name
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(50, 6, 'Admin Employee Name: ' . $servedByName, 0, 1, 'L');

    // Recommended by Section
    $pdf->Ln(3);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(92, 5, 'Recommended by:', 1, 0, 'L');

    // Determine approval text
    $approvalText = 'Approved by:';
    if (isset($request['action']) && strtolower($request['action']) === 'rejected') {
        $approvalText = 'Declined by:';
    }

    $pdf->Cell(92, 5, $approvalText, 1, 0, 'L');
    $pdf->Cell(93, 5, '', 1, 1, 'L');

    // Signature spaces
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell(92, 15, '', 1, 0, 'C');
    $pdf->Cell(92, 15, '', 1, 0, 'C');
    $pdf->Cell(93, 15, '', 1, 1, 'C');

    // Labels under signature spaces
    $pdf->Cell(92, 5, 'Name/Signature/Date', 1, 0, 'C');
    $pdf->Cell(92, 5, 'Name/Signature/Date', 1, 0, 'C');
    $pdf->Cell(93, 5, 'Name/Signature/Date', 1, 1, 'C');

    $pdf->SetFont('helvetica', 'I', 6);
    $pdf->Cell(92, 4, '(Immediate Superior)', 1, 0, 'C');
    $pdf->Cell(92, 4, '(Process Owner/Authorized Representative)', 1, 0, 'C');
    $pdf->Cell(93, 4, '(IT Leader/Authorized Representative)', 1, 1, 'C');

    // Sign-Off Section
    $pdf->Ln(2);
    $pdf->SetFillColor($darkBlue[0], $darkBlue[1], $darkBlue[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(277, 6, 'Sign-Off', 1, 1, 'C', true);

    // Sign-off signature spaces
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell(92, 15, '', 1, 0, 'C');
    $pdf->Cell(92, 15, '', 1, 0, 'C');
    $pdf->Cell(93, 15, '', 1, 1, 'C');

    // Labels and names
    $pdf->Cell(92, 5, 'Name/Signature/Date', 1, 0, 'C');
    $pdf->Cell(92, 5, 'Name/Signature/Date', 1, 0, 'C');
    $pdf->Cell(93, 5, 'Name/Signature/Date', 1, 1, 'C');

    $pdf->SetFont('helvetica', 'I', 6);
    $pdf->Cell(92, 4, '(Requestor)', 1, 0, 'C');
    $pdf->Cell(92, 4, '(IT Security Officer)', 1, 0, 'C');
    $pdf->Cell(93, 4, '(IT Leader/Authorized Representative)', 1, 1, 'C');

    // Note at bottom
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->Cell(277, 4, 'Note: Data Privacy Disclaimer - All information provided is subject to data privacy regulations and confidentiality agreements.', 0, 1, 'L');

    // Clear any output buffered content
    ob_clean();

    // Output the PDF
    $pdf->Output('UAR_' . $requestNumber . '.pdf', 'I');
} catch (Exception $e) {
    // Log error
    error_log("PDF Generation Error: " . $e->getMessage());

    // Clear buffer
    ob_clean();

    // Display user-friendly error
    echo "<div style='text-align:center; margin-top:50px; font-family:Arial, sans-serif;'>";
    echo "<h2>Error Generating PDF</h2>";
    echo "<p>There was a problem creating your PDF. Please try again later.</p>";
    echo "<p><a href='request_history.php' style='color:#0284c7; text-decoration:none;'>Return to Request History</a></p>";
    echo "</div>";
}
