<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'analytics_functions.php';
require_once(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php');

// Authentication check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get filters from POST request
$filters = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['start_date'])) $filters['start_date'] = $_POST['start_date'];
    if (!empty($_POST['end_date'])) $filters['end_date'] = $_POST['end_date'];
    if (!empty($_POST['business_unit'])) $filters['business_unit'] = $_POST['business_unit'];
    if (!empty($_POST['department'])) $filters['department'] = $_POST['department'];
    if (!empty($_POST['system_type'])) $filters['system_type'] = $_POST['system_type'];
}

// Get analytics data
$analyticsData = getAnalyticsData($pdo, $filters);
$statsData = getDashboardStats($pdo, $filters);

// Create new PDF document
class MYPDF extends TCPDF {
    public function Header() {
        // Logo
        $image_file = '../logo.png';
        $this->Image($image_file, 15, 10, 50, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        
        // Title
        $this->SetFont('helvetica', 'B', 24);
        $this->SetTextColor(31, 41, 55); // Dark gray
        $this->Cell(0, 30, 'UAR Analytics Report', 0, false, 'C', 0, '', 0, false, 'M', 'M');
        
        // Add a line under the header
        $this->SetLineStyle(array('width' => 0.5, 'color' => array(229, 231, 235)));
        $this->Line(15, 35, $this->getPageWidth() - 15, 35);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(107, 114, 128); // Gray
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C');
    }
}

// Create new PDF document
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('UAR System');
$pdf->SetAuthor('UAR System');
$pdf->SetTitle('Analytics Report');

// Remove default header/footer
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);

// Set margins
$pdf->SetMargins(15, 40, 15);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set colors
$pdf->SetFillColor(249, 250, 251); // Light gray background
$pdf->SetTextColor(31, 41, 55); // Dark gray text

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 12);

// Add filter information
$pdf->SetFillColor(243, 244, 246); // Lighter gray for section backgrounds
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 12, 'Filter Criteria', 0, 1, 'L', true);
$pdf->Ln(4);

$pdf->SetFont('helvetica', '', 11);
$pdf->SetTextColor(75, 85, 99); // Medium gray for regular text
$filters_text = array(
    'Date Range' => ($filters['start_date'] ?? 'All') . ' to ' . ($filters['end_date'] ?? 'All'),
    'Business Unit' => $filters['business_unit'] ?? 'All',
    'Department' => $filters['department'] ?? 'All',
    'System Type' => $filters['system_type'] ?? 'All'
);

foreach ($filters_text as $label => $value) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(40, 8, $label . ':', 0, 0);
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 8, $value, 0, 1);
}
$pdf->Ln(8);

// Add summary statistics in a modern grid layout
$pdf->SetFillColor(243, 244, 246);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 12, 'Summary Statistics', 0, 1, 'L', true);
$pdf->Ln(4);

// Create a 2x2 grid for statistics
$stats = array(
    array('Total Requests', $statsData['total'], ''),
    array('Approved Requests', $statsData['approved'], ''),
    array('Approval Rate', $statsData['approval_rate'], '%'),
    array('Decline Rate', $statsData['decline_rate'], '%')
);

$col_width = 85;
$row_height = 25;
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(31, 41, 55);

for ($i = 0; $i < 2; $i++) {
    for ($j = 0; $j < 2; $j++) {
        $index = ($i * 2) + $j;
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        
        // Draw stat box with border
        $pdf->SetLineStyle(array('width' => 0.1, 'color' => array(229, 231, 235)));
        $pdf->RoundedRect($x, $y, $col_width, $row_height, 2, '1111', 'DF');
        
        // Add stat label
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(107, 114, 128);
        $pdf->SetXY($x, $y + 4);
        $pdf->Cell($col_width, 6, $stats[$index][0], 0, 1, 'C');
        
        // Add stat value
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(31, 41, 55);
        $pdf->SetXY($x, $y + 12);
        $pdf->Cell($col_width, 8, $stats[$index][1] . $stats[$index][2], 0, 1, 'C');
        
        // Move to next column
        $pdf->SetXY($x + $col_width + 10, $y);
    }
    // Move to next row
    $pdf->SetY($y + $row_height + 10);
    $pdf->SetX(15);
}

$pdf->Ln(10);

// Add department analysis table with modern styling
$pdf->SetFillColor(243, 244, 246);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 12, 'Department Performance Analysis', 0, 1, 'L', true);
$pdf->Ln(4);

// Table header with background color
$pdf->SetFillColor(249, 250, 251);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetTextColor(31, 41, 55);

// Adjust column widths
$col_widths = array(80, 25, 25, 25, 25);
$pdf->Cell($col_widths[0], 10, 'Department', 1, 0, 'L', true);
$pdf->Cell($col_widths[1], 10, 'Total', 1, 0, 'C', true);
$pdf->Cell($col_widths[2], 10, 'Approved', 1, 0, 'C', true);
$pdf->Cell($col_widths[3], 10, 'Rejected', 1, 0, 'C', true);
$pdf->Cell($col_widths[4], 10, 'Rate', 1, 1, 'C', true);

// Table data
$pdf->SetFont('helvetica', '', 11);
$pdf->SetFillColor(255, 255, 255);
$fill = false;

foreach ($analyticsData['departmentAnalysis'] as $dept) {
    $rate = round(($dept['approved'] / $dept['total_requests']) * 100, 1);
    
    // Set row background color
    $fill = !$fill;
    $pdf->SetFillColor($fill ? 249 : 255, $fill ? 250 : 255, $fill ? 251 : 255);
    
    $pdf->Cell($col_widths[0], 10, $dept['department'], 1, 0, 'L', true);
    $pdf->Cell($col_widths[1], 10, $dept['total_requests'], 1, 0, 'C', true);
    $pdf->Cell($col_widths[2], 10, $dept['approved'], 1, 0, 'C', true);
    $pdf->Cell($col_widths[3], 10, $dept['rejected'], 1, 0, 'C', true);
    $pdf->Cell($col_widths[4], 10, $rate . '%', 1, 1, 'C', true);
}

// Generate the PDF
$filename = 'UAR_Analytics_Report_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'D'); 