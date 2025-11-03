<?php
// Suppress warnings and notices to avoid corrupting PDF output
error_reporting(E_ERROR);
ini_set('display_errors', 0);

// Buffer output to prevent accidental output before PDF headers
ob_start();

// Removed custom TCPDF constant defines to avoid redefinition with vendor config

session_start();
require_once '../config.php';
require_once '../vendor/autoload.php';

// Help desk auth
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'help_desk') {
    header('Location: ../admin/login.php');
    exit();
}

// Accept either history id or access_request_number
$historyId = isset($_GET['id']) ? intval($_GET['id']) : null;
$accessRequestNumber = isset($_GET['access_request_number']) ? $_GET['access_request_number'] : null;

if (!$historyId && !$accessRequestNumber) {
    header('Location: review_history.php');
    exit();
}

try {
    if ($historyId) {
        $query = "SELECT ah.*, 
                  admin.username as admin_username,
                  admin_emp.employee_name as admin_employee_name,
                  tech_emp.employee_name as technical_employee_name,
                  superior_emp.employee_name as superior_employee_name,
                  helpdesk_emp.employee_name as helpdesk_employee_name,
                  process_emp.employee_name as process_owner_employee_name,
                  COALESCE(ir.access_type, gr.access_type, ah.access_type) as effective_access_type,
                  COALESCE(ir.justification, gr.justification, ah.justification) as original_justification,
                  (SELECT STRING_AGG(username, ', ') FROM uar.individual_requests WHERE access_request_number = ah.access_request_number) as individual_usernames,
                  (SELECT STRING_AGG(username, ', ') FROM uar.group_requests WHERE access_request_number = ah.access_request_number) as group_usernames
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
                  WHERE ah.history_id = :history_id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':history_id' => $historyId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Get the most recent history row for this ARN
        $query = "SELECT TOP 1 ah.*, 
                  admin.username as admin_username,
                  admin_emp.employee_name as admin_employee_name,
                  tech_emp.employee_name as technical_employee_name,
                  superior_emp.employee_name as superior_employee_name,
                  helpdesk_emp.employee_name as helpdesk_employee_name,
                  process_emp.employee_name as process_owner_employee_name,
                  COALESCE(ir.access_type, gr.access_type, ah.access_type) as effective_access_type,
                  COALESCE(ir.justification, gr.justification, ah.justification) as original_justification,
                  (SELECT STRING_AGG(username, ', ') FROM uar.individual_requests WHERE access_request_number = ah.access_request_number) as individual_usernames,
                  (SELECT STRING_AGG(username, ', ') FROM uar.group_requests WHERE access_request_number = ah.access_request_number) as group_usernames
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
                  WHERE ah.access_request_number = :arn
                  ORDER BY ah.created_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':arn' => $accessRequestNumber]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$request) {
        header('Location: review_history.php');
        exit();
    }

    // Fetch all group request rows individually
    $groupRequests = [];
    $query = "SELECT * FROM uar.group_requests WHERE access_request_number = :arn ORDER BY id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':arn' => $request['access_request_number']]);
    $groupRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch all individual request rows individually  
    $individualRequests = [];
    $query = "SELECT * FROM uar.individual_requests WHERE access_request_number = :arn ORDER BY id";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':arn' => $request['access_request_number']]);
    $individualRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $currentDate = date('F d, Y');
$requestNumber = $request['access_request_number'] ?? 'N/A';
} catch (PDOException $e) {
    error_log('Error fetching request history details: ' . $e->getMessage());
    header('Location: review_history.php?error=db');
    exit();
}

// Normalize access type into one of: Full, Read, Admin (or N/A)
function normalizeAccessType($value) {
    $text = strtolower(trim((string)$value));
    if ($text === '') { return 'N/A'; }
    if (strpos($text, 'admin') !== false) { return 'Admin'; }
    if (strpos($text, 'full') !== false) { return 'Full'; }
    if (strpos($text, 'read') !== false || strpos($text, 'view') !== false) { return 'Read'; }
    if ($text === 'system application' || $text === 'system_application' || $text === 'system') { return 'N/A'; }
    return ucfirst($text);
}

class UARPDF extends TCPDF
{
    public function Header() {}
    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C');
    }
    public function createDynamicRow($data, $startY, $minHeight = 8)
    {
        $maxHeight = $minHeight;
        foreach ($data as $item) {
            $this->SetFont($item['font'], $item['style'], $item['size']);
            $cellHeight = $this->getStringHeight($item['width'], $item['text']);
            if ($cellHeight > $maxHeight) { $maxHeight = $cellHeight; }
        }
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
    $pdf = new UARPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('UAR System');
    $pdf->SetAuthor('IT Department');
    $pdf->SetTitle('User Access Request - ' . $requestNumber);
    $pdf->SetSubject('UAR Form');
    $pdf->SetKeywords('UAR, Access, Request');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetHeaderMargin(5);
    $pdf->SetFooterMargin(10);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->AddPage();

    $darkBlue = array(0, 51, 102);
    $lightBlue = array(173, 216, 230);
    $yellow = array(255, 255, 153);

    $pdf->Rect(10, 10, 277, 20, 'D');

    // Add logo filling the header box
    $logoPath = __DIR__ . '/../logo.png';
    if (file_exists($logoPath)) {
        // Compact logo inside the header: small badge at the left with padding
        $pdf->Image($logoPath, 12, 11, 56, 18, '', '', '', false, 300);
    }

    // Removed BU text to keep only the logo in the header box
    // $pdf->SetXY(26, 12);
    // $pdf->SetFont('helvetica', 'B', 8);
    // $pdf->Cell(60, 8, 'Alcantara Business Unit', 0, 0, 'L');

    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetXY(10, 14);
    $pdf->Cell(277, 6, 'USER ACCESS REQUEST (UAR)', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetXY(10, 20);
    $pdf->Cell(277, 6, 'ABU Information Technology', 0, 0, 'C');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY(237, 12);

    $pdf->SetY(35);
    $pdf->SetFillColor($darkBlue[0], $darkBlue[1], $darkBlue[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(277, 6, 'Requestor Information', 1, 1, 'C', true);

    $pdf->SetTextColor(0, 0, 0);
    // Single row: Name | BU/Department | Date | UAR Ref. No.
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(20, 6, 'Name:', 1, 0, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(90, 6, ($request['requestor_name'] ?? ''), 1, 0, 'L');

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(28, 6, 'BU/Department:', 1, 0, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(70, 6, (($request['business_unit'] ?? '') . ' / ' . ($request['department'] ?? '')), 1, 0, 'L');

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(12, 6, 'Date:', 1, 0, 'L');
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(25, 6, $currentDate, 1, 0, 'L');

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(32, 6, 'UAR Ref. No. ' . $requestNumber, 1, 1, 'L');

    $pdf->Ln(2);
    $pdf->SetFillColor($darkBlue[0], $darkBlue[1], $darkBlue[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(277, 6, 'Request Details', 1, 1, 'C', true);

    // Determine request type based on whether we have group requests or individual requests
    $isGroupRequest = !empty($groupRequests) && count($groupRequests) > 0;
    $requestType = $isGroupRequest ? 'group' : 'individual';
    
    $servedByName = $request['admin_employee_name'] ?? $request['admin_username'] ?? '';
    $testPerformedByName = $request['technical_employee_name'] ?? '';

    // Helper function to format access duration
    $formatAccessDuration = function($req) {
        if (!empty($req['access_duration'])) {
            if ($req['access_duration'] === 'permanent') {
                return 'Permanent';
            } elseif ($req['access_duration'] === 'temporary' && !empty($req['end_date'])) {
                $endDate = new DateTime($req['end_date']);
                return 'Until ' . $endDate->format('M d, Y');
            } else {
                return ucfirst($req['access_duration']);
            }
        } elseif (!empty($req['duration_type'])) {
            if ($req['duration_type'] === 'permanent') {
                return 'Permanent';
            } elseif ($req['duration_type'] === 'temporary' && !empty($req['end_date'])) {
                $endDate = new DateTime($req['end_date']);
                return 'Until ' . $endDate->format('M d, Y');
            } else {
                return ucfirst($req['duration_type']);
            }
        }
        return 'N/A';
    };

    if ($requestType === 'group') {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(8, 6, 'II.', 1, 0, 'C');
        $pdf->Cell(269, 6, 'For Group Access', 1, 1, 'L');
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
        $pdf->Cell(72, 10, 'Backup Performed By', 1, 1, 'C', true);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        
        // Loop through each group request row to create separate rows for each user
        foreach ($groupRequests as $groupReq) {
            $groupRowStartY = $pdf->GetY();
            $groupRowData = array(
                array('text' => $groupReq['username'] ?? '', 'width' => 27, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                array('text' => $groupReq['application_system'] ?? $request['system_type'] ?? '', 'width' => 27, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                array('text' => normalizeAccessType($groupReq['access_type'] ?? $request['effective_access_type'] ?? $request['access_type'] ?? ''), 'width' => 20, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                array('text' => $formatAccessDuration($groupReq), 'width' => 25, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                array('text' => $groupReq['justification'] ?? $request['original_justification'] ?? $request['justification'] ?? 'No justification provided', 'width' => 30, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                array('text' => $request['comments'] ?? '', 'width' => 32, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                array('text' => $servedByName, 'width' => 20, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                array('text' => $testPerformedByName, 'width' => 24, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                array('text' => '', 'width' => 72, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L')
            );
            $groupRowHeight = $pdf->createDynamicRow($groupRowData, $groupRowStartY, 8);
            $pdf->SetY($groupRowStartY + $groupRowHeight);
        }
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
        $pdf->Cell(72, 10, 'Backup Performed By', 1, 1, 'C', true);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        
        // Loop through individual requests if we have them, otherwise use the main request data
        if (!empty($individualRequests) && count($individualRequests) > 0) {
            // Create a separate row for each individual request
            foreach ($individualRequests as $indReq) {
                $dataRowStartY = $pdf->GetY();
                $applicationSystem = ($indReq['application_system'] ?? $request['system_type'] ?? '');
                $accessTypeData = ($indReq['access_type'] ?? $request['access_type'] ?? '');
                if (!empty($accessTypeData)) {
                    $applicationSystem .= ($applicationSystem ? ' - ' : '') . $accessTypeData;
                }
                $dataRowData = array(
                    array('text' => ($indReq['username'] ?? $request['requestor_name'] ?? ''), 'width' => 27, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                    array('text' => $applicationSystem, 'width' => 27, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                    array('text' => normalizeAccessType($indReq['access_type'] ?? $request['effective_access_type'] ?? $request['access_type'] ?? ''), 'width' => 20, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                    array('text' => $formatAccessDuration($indReq), 'width' => 25, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                    array('text' => $indReq['justification'] ?? $request['original_justification'] ?? $request['justification'] ?? 'No justification provided', 'width' => 30, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                    array('text' => $request['comments'] ?? '', 'width' => 32, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                    array('text' => $servedByName, 'width' => 20, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                    array('text' => $testPerformedByName, 'width' => 24, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                    array('text' => '', 'width' => 72, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L')
                );
                $dataRowHeight = $pdf->createDynamicRow($dataRowData, $dataRowStartY, 8);
                $pdf->SetY($dataRowStartY + $dataRowHeight);
            }
        } else {
            // Fallback to main request data if no individual requests found
            $dataRowStartY = $pdf->GetY();
            $applicationSystem = ($request['system_type'] ?? '');
            $accessTypeData = ($request['access_type'] ?? '');
            if (!empty($accessTypeData)) {
                $applicationSystem .= ($applicationSystem ? ' - ' : '') . $accessTypeData;
            }
            $dataRowData = array(
                array('text' => ($request['individual_usernames'] ?? $request['requestor_name'] ?? ''), 'width' => 27, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                array('text' => $applicationSystem, 'width' => 27, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                array('text' => normalizeAccessType($request['effective_access_type'] ?? $request['access_type'] ?? ''), 'width' => 20, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                array('text' => $formatAccessDuration($request), 'width' => 25, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                array('text' => $request['original_justification'] ?? $request['justification'] ?? 'No justification provided', 'width' => 30, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                array('text' => $request['comments'] ?? '', 'width' => 32, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                array('text' => $servedByName, 'width' => 20, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                array('text' => $testPerformedByName, 'width' => 24, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L'),
                array('text' => '', 'width' => 72, 'font' => 'helvetica', 'style' => '', 'size' => 7, 'align' => 'L')
            );
            $dataRowHeight = $pdf->createDynamicRow($dataRowData, $dataRowStartY, 8);
            $pdf->SetY($dataRowStartY + $dataRowHeight);
        }
    }


    // Prepare names for signature sections
    $superiorName = $request['superior_employee_name'] ?? '';
    $processOwnerName = $request['process_owner_employee_name'] ?? '';
    $itLeaderName = $request['admin_employee_name'] ?? ($request['admin_username'] ?? '');
    $requestorName = $request['requestor_name'] ?? '';
    $itSecurityOfficerName = $request['technical_employee_name'] ?? ($request['helpdesk_employee_name'] ?? '');

    $pdf->Ln(3);
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(92, 5, 'Recommended by:', 1, 0, 'L');
    $approvalText = 'Approved by:';
    if (isset($request['action']) && strtolower($request['action']) === 'rejected') {
        $approvalText = 'Declined by:';
    }
    $pdf->Cell(92, 5, $approvalText, 1, 0, 'L');
    $pdf->Cell(93, 5, '', 1, 1, 'L');
    $pdf->SetFont('helvetica', '', 7);
    // Fill signature cells with names
    $pdf->Cell(92, 15, $superiorName, 1, 0, 'C');
    $pdf->Cell(92, 15, $processOwnerName, 1, 0, 'C');
    $pdf->Cell(93, 15, $itLeaderName, 1, 1, 'C');
    $pdf->Cell(92, 5, 'Name/Signature/Date', 1, 0, 'C');
    $pdf->Cell(92, 5, 'Name/Signature/Date', 1, 0, 'C');
    $pdf->Cell(93, 5, 'Name/Signature/Date', 1, 1, 'C');
    $pdf->SetFont('helvetica', 'I', 6);
    $pdf->Cell(92, 4, '(Immediate Superior)', 1, 0, 'C');
    $pdf->Cell(92, 4, '(Process Owner/Authorized Representative)', 1, 0, 'C');
    $pdf->Cell(93, 4, '(IT Leader/Authorized Representative)', 1, 1, 'C');

    $pdf->Ln(2);
    $pdf->SetFillColor($darkBlue[0], $darkBlue[1], $darkBlue[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(277, 6, 'Sign-Off', 1, 1, 'C', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 7);
    // Fill sign-off cells with names
    $pdf->Cell(92, 15, $requestorName, 1, 0, 'C');
    $pdf->Cell(92, 15, $itSecurityOfficerName, 1, 0, 'C');
    $pdf->Cell(93, 15, $itLeaderName, 1, 1, 'C');
    $pdf->Cell(92, 5, 'Name/Signature/Date', 1, 0, 'C');
    $pdf->Cell(92, 5, 'Name/Signature/Date', 1, 0, 'C');
    $pdf->Cell(93, 5, 'Name/Signature/Date', 1, 1, 'C');
    $pdf->SetFont('helvetica', 'I', 6);
    $pdf->Cell(92, 4, '(Requestor)', 1, 0, 'C');
    $pdf->Cell(92, 4, '(IT Technical Support)', 1, 0, 'C');
    $pdf->Cell(93, 4, '(IT Leader/Authorized Representative)', 1, 1, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'I', 7);
    $pdf->Cell(277, 4, 'Note: Data Privacy Disclaimer - All information provided is subject to data privacy regulations and confidentiality agreements.', 0, 1, 'L');

    ob_clean();
    $pdf->Output('UAR_' . $requestNumber . '.pdf', 'I');
} catch (Exception $e) {
    error_log('PDF Generation Error: ' . $e->getMessage());
    ob_clean();
    echo "<div style='text-align:center; margin-top:50px; font-family:Arial, sans-serif;'>";
    echo "<h2>Error Generating PDF</h2>";
    echo "<p>There was a problem creating your PDF. Please try again later.</p>";
    echo "<p><a href='review_history.php' style='color:#0284c7; text-decoration:none;'>Return to Review History</a></p>";
    echo "</div>";
}