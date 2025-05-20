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

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Check if history ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: approval_history.php");
    exit();
}

$historyId = intval($_GET['id']);

// Fetch the request history details
try {
    $query = "SELECT ah.*, admin.username as admin_username, e.employee_name as admin_name
              FROM approval_history ah
              LEFT JOIN admin_users admin ON ah.admin_id = admin.id
              LEFT JOIN employees e ON admin.username = e.employee_id
              WHERE ah.history_id = :history_id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':history_id' => $historyId
    ]);
    
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        // Request not found
        header("Location: approval_history.php");
        exit();
    }
    
    // Format the date
    $currentDate = date('F d, Y');
    $requestNumber = $request['access_request_number'] ?? 'N/A';
    
} catch (PDOException $e) {
    error_log("Error fetching request history details: " . $e->getMessage());
    header("Location: approval_history.php?error=db");
    exit();
}

// Check if required extensions are available
$hasGD = extension_loaded('gd');
$hasImagick = extension_loaded('imagick');

// Create new TCPDF instance
class UARPDF extends TCPDF {
    public function Header() {
        // Empty header to override default behavior
    }
    
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        
        // Page number
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

try {
    // Create new PDF document
    $pdf = new UARPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

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
    $pdf->SetMargins(10, 15, 10);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, 15);

    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Set font
    $pdf->SetFont('helvetica', '', 10);

    // Add a page
    $pdf->AddPage();

    // Define border style for consistency
    $borderStyle = array('width' => 0.1, 'color' => array(0, 0, 0));
    $pdf->SetLineStyle($borderStyle);

    // UAR Header - More consistent border
    $pdf->Rect(10, 10, 190, 20, 'D', array('all' => $borderStyle));

    // Title in the center
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetXY(10, 10);
    $pdf->Cell(190, 10, 'USER ACCESS REQUEST (UAR)', 0, 0, 'C');

    // Subtitle
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetXY(10, 18);
    $pdf->Cell(190, 5, 'ABU Information Technology', 0, 0, 'C');

    // Right side text
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY(160, 12);
    $pdf->Cell(40, 5, 'IT-UAR-001', 0, 1, 'R');
    $pdf->SetXY(160, 16);
    $pdf->Cell(40, 5, 'Revision No. 00', 0, 1, 'R');

    // Left side - Organization info
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetXY(15, 12);
    $pdf->Cell(50, 5, 'Alcantara Business Unit', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY(15, 17);
    $pdf->Cell(50, 5, 'Information Technology', 0, 1, 'L');

    // Requestor Information Section Header
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetY(32);
    $pdf->Cell(190, 6, 'Requestor Information', 1, 1, 'L', true);

    // Requestor Information Content
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', '', 9);

    // Name, Date, Ref No row - Using consistent borders
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(95, 5, 'Name:', 1, 0);
    $pdf->Cell(47.5, 5, 'Date:', 1, 0);
    $pdf->Cell(47.5, 5, 'UAR Ref. No:', 1, 1);

    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(95, 5, $request['requestor_name'] ?? 'N/A', 1, 0);
    $pdf->Cell(47.5, 5, $currentDate, 1, 0);
    $pdf->Cell(47.5, 5, $requestNumber, 1, 1);

    // BU/Department row
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(190, 5, 'BU/Department:', 1, 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(190, 5, ($request['business_unit'] ?? 'N/A') . ' / ' . ($request['department'] ?? 'N/A'), 1, 1);

    // Request Details Section Header
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(190, 6, 'Request Details', 1, 1, 'L', true);

    // Individual Access Section - Always used regardless of access type
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(190, 5, 'For Individual Access', 1, 1, 'C');
    
    // Create a more spacious layout with fewer columns and multiple rows
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255);
    $pdf->SetFont('helvetica', 'B', 9);
    
    // First row - User and System details
    $rowHeight = 8;
    $pdf->Cell(60, $rowHeight, 'User Name', 1, 0, 'C', true);
    $pdf->Cell(65, $rowHeight, 'Application/System', 1, 0, 'C', true);
    $pdf->Cell(65, $rowHeight, 'Access Type', 1, 1, 'C', true);
    
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', '', 9);
    $dataRowHeight = 10;
    $pdf->Cell(60, $dataRowHeight, $request['requestor_name'] ?? 'N/A', 1, 0, 'L');
    
    // Display both system_type and access_type in the Application/System field
    $applicationSystem = ($request['system_type'] ?? 'N/A');
    $accessType = ($request['access_type'] ?? '');
    if (!empty($accessType)) {
        $applicationSystem .= ' - ' . $accessType;
    }
    $pdf->Cell(65, $dataRowHeight, $applicationSystem, 1, 0, 'L');
    
    $pdf->Cell(65, $dataRowHeight, $request['role_access_type'] ?? 'N/A', 1, 1, 'L');
    
    // Second row - Timing details - Keep exact same column widths for alignment
    $pdf->SetTextColor(255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(60, $rowHeight, 'Duration', 1, 0, 'C', true);
    $pdf->Cell(65, $rowHeight, 'Date Required', 1, 0, 'C', true);
    $pdf->Cell(65, $rowHeight, 'Processed By', 1, 1, 'C', true);
    
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', '', 9);
    
    // Duration
    $durationText = '';
    if ($request['duration_type'] === 'permanent') {
        $durationText = 'Permanent';
    } else {
        $endDate = new DateTime($request['end_date']);
        $durationText = 'Temporary, until ' . $endDate->format('M d, Y');
    }
    
    // Date required
    $requiredDate = new DateTime($request['created_at']);
    
    $pdf->Cell(60, $dataRowHeight, $durationText, 1, 0, 'L');
    $pdf->Cell(65, $dataRowHeight, $requiredDate->format('M d, Y'), 1, 0, 'L');
    $pdf->Cell(65, $dataRowHeight, $request['admin_name'] ?? $request['admin_username'] ?? 'N/A', 1, 1, 'L');
    
    // Third row - Justification and comments - Using consistent column width distribution
    $pdf->SetTextColor(255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(95, $rowHeight, 'Justification', 1, 0, 'C', true);
    $pdf->Cell(95, $rowHeight, 'Remark/IT Evaluation', 1, 1, 'C', true);
    
    // Reset text color for content
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', '', 9);
    
    // Get content
    $justification = $request['justification'] ?? 'N/A';
    $comments = $request['comments'] ?? '';
    
    // Ensure the text won't overflow by wrapping it
    $justification = $pdf->unhtmlentities($justification);
    $comments = $pdf->unhtmlentities($comments);
    
    // Calculate height needed for both cells (minimum 15mm)
    $justHeight = $pdf->getStringHeight(95, $justification);
    $commentsHeight = $pdf->getStringHeight(95, $comments);
    $cellHeight = max($justHeight, $commentsHeight, 15);
    
    // First cell - Justification
    $pdf->MultiCell(95, $cellHeight, $justification, 1, 'L', false, 0, '', '', true, 0, false, true, $cellHeight, 'T', true);
    
    // Second cell - Comments/Remarks
    $pdf->MultiCell(95, $cellHeight, $comments, 1, 'L', false, 1, '', '', true, 0, false, true, $cellHeight, 'T', true);

    // Add some space before signatures
    $pdf->Ln(10);

    // Determine the approval status text based on the action
    $approvalText = 'Approved by:';
    if (isset($request['action']) && strtolower($request['action']) === 'rejected') {
        $approvalText = 'Declined by:';
    }

    // Signature section - improved layout with consistent borders
    $pdf->SetFillColor(0, 51, 102);
    $pdf->SetTextColor(255);
    $pdf->SetFont('helvetica', 'B', 9);
    $signWidth = 63.33; // Make equal widths (190/3)
    $pdf->Cell($signWidth, 8, 'Requested by:', 1, 0, 'C', true);
    $pdf->Cell($signWidth, 8, $approvalText, 1, 0, 'C', true);
    $pdf->Cell($signWidth, 8, 'Noted by:', 1, 1, 'C', true);

    // Empty space for signatures with consistent borders
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', '', 9);
    $signHeight = 20;
    $pdf->Cell($signWidth, $signHeight, '', 1, 0, 'C');
    $pdf->Cell($signWidth, $signHeight, '', 1, 0, 'C');
    $pdf->Cell($signWidth, $signHeight, '', 1, 1, 'C');

    // Name lines with consistent borders
    $pdf->Cell($signWidth, 6, $request['requestor_name'] ?? 'N/A', 1, 0, 'C');
    $pdf->Cell($signWidth, 6, $request['admin_name'] ?? $request['admin_username'] ?? 'N/A', 1, 0, 'C');
    $pdf->Cell($signWidth, 6, 'IT Manager', 1, 1, 'C');

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
    echo "<p><a href='approval_history.php' style='color:#0284c7; text-decoration:none;'>Return to Approval History</a></p>";
    echo "</div>";
}
?> 