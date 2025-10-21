<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php';
require_once(__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php');

// Authentication check (same as helpdesk/analytics.php)
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

// Build where clause for filters
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($filters['start_date'])) {
    $whereClause .= " AND CAST(created_at AS DATE) >= :start_date";
    $params[':start_date'] = $filters['start_date'];
}
if (!empty($filters['end_date'])) {
    $whereClause .= " AND CAST(created_at AS DATE) <= :end_date";
    $params[':end_date'] = $filters['end_date'];
}
if (!empty($filters['business_unit'])) {
    $whereClause .= " AND business_unit = :business_unit";
    $params[':business_unit'] = $filters['business_unit'];
}
if (!empty($filters['department'])) {
    $whereClause .= " AND department = :department";
    $params[':department'] = $filters['department'];
}
// Note: Intentionally not filtering by system_type here unless present in approval_history schema

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM uar.approval_history $whereClause");
$stmt->execute($params);
$totalRequests = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM uar.approval_history $whereClause AND action = 'approved'");
$stmt->execute($params);
$approvedRequests = $stmt->fetchColumn();

$approvalRate = $totalRequests > 0 ? round(($approvedRequests / $totalRequests) * 100, 1) : 0;
$declineRate = $totalRequests > 0 ? round((($totalRequests - $approvedRequests) / $totalRequests) * 100, 1) : 0;

$statsData = [
    'total' => $totalRequests,
    'approved' => $approvedRequests,
    'approval_rate' => $approvalRate,
    'decline_rate' => $declineRate
];

// Get department analysis
$stmt = $pdo->prepare("
    SELECT 
        department,
        COUNT(*) as total_requests,
        SUM(CASE WHEN action = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN action = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM uar.approval_history
    $whereClause
    GROUP BY department
    ORDER BY total_requests DESC
;");
$stmt->execute($params);
$departmentAnalysis = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get daily requests for line chart (SQL Server compatible)
$stmt = $pdo->prepare("
    SELECT TOP 30
        CAST(created_at AS DATE) as request_date,
        COUNT(*) as count
    FROM uar.approval_history
    $whereClause
    GROUP BY CAST(created_at AS DATE)
    ORDER BY request_date ASC
");
$stmt->execute($params);
$dailyRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get system type distribution for pie chart (SQL Server compatible - simplified)
$stmt = $pdo->prepare("
    SELECT TOP 6
        LEFT(system_type, CHARINDEX(',', system_type + ',') - 1) as system_name,
        COUNT(*) as count
    FROM uar.approval_history
    $whereClause AND system_type IS NOT NULL AND system_type != ''
    GROUP BY LEFT(system_type, CHARINDEX(',', system_type + ',') - 1)
    ORDER BY count DESC
");
$stmt->execute($params);
$systemTypeData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$analyticsData = [
    'departmentAnalysis' => $departmentAnalysis,
    'dailyRequests' => $dailyRequests,
    'systemTypeDistribution' => $systemTypeData
];

// Note: Charts are now generated using TCPDF native drawing functions (no GD extension required)

// Create new PDF document
class MYPDF extends TCPDF {
    public function Header() {
        // Logo with graceful fallback
        $logoPng = '../logo.png';
        $logoJpg = '../logo.jpg';
        $canUseAlphaPng = extension_loaded('gd') || extension_loaded('imagick');
        $logoToUse = null;
        if ($canUseAlphaPng && file_exists($logoPng)) {
            $logoToUse = $logoPng;
        } elseif (file_exists($logoJpg)) {
            $logoToUse = $logoJpg;
        }
        if ($logoToUse !== null) {
            try {
                $this->Image($logoToUse, 15, 10, 35, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
            } catch (Exception $e) {
                // Skip logo if not renderable
            }
        }

        // Title - clean and simple
        $this->SetFont('helvetica', 'B', 18);
        $this->SetTextColor(31, 41, 55); // Dark gray
        $this->SetXY(15, 10);
        $this->Cell(0, 10, 'Help Desk Analytics Report', 0, false, 'R');
        
        // Date subtitle
        $this->SetFont('helvetica', '', 9);
        $this->SetTextColor(107, 114, 128); // Medium gray
        $this->SetXY(15, 18);
        $this->Cell(0, 8, date('F d, Y'), 0, false, 'R');
        
        // Simple line separator
        $this->SetLineStyle(array('width' => 0.5, 'color' => array(229, 231, 235)));
        $this->Line(15, 30, $this->getPageWidth() - 15, 30);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(156, 163, 175); // Light gray
        
        // Simple centered page number
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().' of '.$this->getAliasNbPages(), 0, false, 'C');
    }
}

// Create new PDF document
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Document info
$pdf->SetCreator('UAR System');
$pdf->SetAuthor('UAR System');
$pdf->SetTitle('Help Desk Analytics Report');

// Header/footer
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);

// Margins - narrower for more space
$pdf->SetMargins(12, 40, 12);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Fonts and colors
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetFillColor(249, 250, 251);
$pdf->SetTextColor(31, 41, 55);

// Page
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// Filters section - compact horizontal layout
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetTextColor(31, 41, 55);
$pdf->Cell(0, 6, 'Report Filters', 0, 1, 'L');
$pdf->Ln(1);

$pdf->SetFont('helvetica', '', 9);
$filters_text = array(
    'Date Range' => (($filters['start_date'] ?? 'All') . ' to ' . ($filters['end_date'] ?? 'All')),
    'Business Unit' => $filters['business_unit'] ?? 'All',
    'Department' => $filters['department'] ?? 'All',
    'System Type' => $filters['system_type'] ?? 'All'
);

// Display filters in 2 columns for better space usage
$col1_width = 90;
$col2_width = 90;
$i = 0;
foreach ($filters_text as $label => $value) {
    if ($i % 2 == 0) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(75, 85, 99);
        $pdf->Cell(30, 5, $label . ':', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(31, 41, 55);
        $pdf->Cell($col1_width - 30, 5, $value, 0, 0, 'L');
    } else {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(75, 85, 99);
        $pdf->Cell(30, 5, $label . ':', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(31, 41, 55);
        $pdf->Cell($col2_width - 30, 5, $value, 0, 1, 'L');
    }
    $i++;
}

// Light separator line
$pdf->Ln(2);
$pdf->SetDrawColor(229, 231, 235);
$pdf->SetLineWidth(0.3);
$pdf->Line(12, $pdf->GetY(), $pdf->getPageWidth() - 12, $pdf->GetY());
$pdf->Ln(5);

// Summary statistics - horizontal cards using full width
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetTextColor(31, 41, 55);
$pdf->Cell(0, 6, 'Summary', 0, 1, 'L');
$pdf->Ln(2);

$stats = array(
    array('Total Requests', $statsData['total'], ''),
    array('Approved', $statsData['approved'], ''),
    array('Approval Rate', $statsData['approval_rate'], '%'),
    array('Decline Rate', $statsData['decline_rate'], '%')
);

// Use full page width for 4 cards in a row
$page_width = $pdf->getPageWidth() - 24; // Account for margins
$card_width = ($page_width - 9) / 4; // 4 cards with 3px gaps
$card_height = 18;

$x_start = $pdf->GetX();
$y_start = $pdf->GetY();

for ($i = 0; $i < 4; $i++) {
    $x = $x_start + ($card_width + 3) * $i;
    
    // Clean card with subtle border
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(229, 231, 235);
    $pdf->SetLineWidth(0.3);
    $pdf->RoundedRect($x, $y_start, $card_width, $card_height, 2, '1111', 'DF');
    
    // Label
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(107, 114, 128);
    $pdf->SetXY($x + 3, $y_start + 3);
    $pdf->Cell($card_width - 6, 4, $stats[$i][0], 0, 1, 'L');
    
    // Value
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(31, 41, 55);
    $pdf->SetXY($x + 3, $y_start + 8);
    $pdf->Cell($card_width - 6, 6, $stats[$i][1] . $stats[$i][2], 0, 1, 'L');
}

$pdf->SetY($y_start + $card_height + 5);

// Department performance table - full width usage
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetTextColor(31, 41, 55);
$pdf->Cell(0, 6, 'Department Performance', 0, 1, 'L');
$pdf->Ln(2);

// Calculate column widths to use full page width
$page_width = $pdf->getPageWidth() - 24;
$col_widths = array(
    $page_width * 0.45,  // Department - 45%
    $page_width * 0.15,  // Total - 15%
    $page_width * 0.15,  // Approved - 15%
    $page_width * 0.15,  // Rejected - 15%
    $page_width * 0.10   // Rate - 10%
);

// Simple table header
$pdf->SetFillColor(249, 250, 251);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(75, 85, 99);
$pdf->SetDrawColor(229, 231, 235);
$pdf->SetLineWidth(0.2);

$pdf->Cell($col_widths[0], 7, 'Department', 1, 0, 'L', true);
$pdf->Cell($col_widths[1], 7, 'Total', 1, 0, 'C', true);
$pdf->Cell($col_widths[2], 7, 'Approved', 1, 0, 'C', true);
$pdf->Cell($col_widths[3], 7, 'Rejected', 1, 0, 'C', true);
$pdf->Cell($col_widths[4], 7, 'Rate', 1, 1, 'C', true);

// Table rows - clean and readable
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(31, 41, 55);

foreach ($analyticsData['departmentAnalysis'] as $dept) {
    $rate = $dept['total_requests'] > 0 ? round(($dept['approved'] / $dept['total_requests']) * 100, 1) : 0;
    
    $pdf->Cell($col_widths[0], 6, $dept['department'], 1, 0, 'L', true);
    $pdf->Cell($col_widths[1], 6, $dept['total_requests'], 1, 0, 'C', true);
    $pdf->Cell($col_widths[2], 6, $dept['approved'], 1, 0, 'C', true);
    $pdf->Cell($col_widths[3], 6, $dept['rejected'], 1, 0, 'C', true);
    $pdf->Cell($col_widths[4], 6, $rate . '%', 1, 1, 'C', true);
}

$pdf->Ln(8);

// Add charts section - Line Graph and Pie Chart (no GD required)
if (!empty($analyticsData['dailyRequests']) || !empty($analyticsData['systemTypeDistribution'])) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(31, 41, 55);
    $pdf->Cell(0, 6, 'Charts & Visualizations', 0, 1, 'L');
    $pdf->Ln(3);
    
    $x_start = $pdf->GetX();
    $y_start = $pdf->GetY();
    
    // Draw Line Graph - Requests per Day (left side)
    if (!empty($analyticsData['dailyRequests'])) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(31, 41, 55);
        $pdf->SetXY($x_start, $y_start);
        $pdf->Cell(90, 5, 'Requests per Day', 0, 1, 'L');
        
        $recentDays = array_slice($analyticsData['dailyRequests'], -14);
        if (!empty($recentDays)) {
            $chart_x = $x_start;
            $chart_y = $y_start + 8;
            $chart_width = 90;
            $chart_height = 50;
            $padding_left = 12;
            $padding_right = 5;
            $padding_top = 5;
            $padding_bottom = 12;
            $actual_chart_width = $chart_width - $padding_left - $padding_right;
            $actual_chart_height = $chart_height - $padding_top - $padding_bottom;
            
            // Find max value for scaling
            $max_count = max(array_column($recentDays, 'count'));
            if ($max_count == 0) $max_count = 1;
            
            // Draw white background with light border
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($chart_x, $chart_y, $chart_width, $chart_height, 'F');
            
            // Draw horizontal grid lines
            $pdf->SetDrawColor(229, 231, 235);
            $pdf->SetLineWidth(0.2);
            for ($i = 0; $i <= 4; $i++) {
                $grid_y = $chart_y + $padding_top + ($actual_chart_height / 4) * $i;
                $pdf->Line($chart_x + $padding_left, $grid_y, $chart_x + $chart_width - $padding_right, $grid_y);
                
                // Add Y-axis labels
                $value = (int)($max_count * (4 - $i) / 4);
                $pdf->SetFont('helvetica', '', 6);
                $pdf->SetTextColor(107, 114, 128);
                $pdf->SetXY($chart_x + 1, $grid_y - 1.5);
                $pdf->Cell($padding_left - 2, 3, $value, 0, 0, 'R');
            }
            
            // Calculate points for line graph
            $points = [];
            $step_x = count($recentDays) > 1 ? $actual_chart_width / (count($recentDays) - 1) : 0;
            
            foreach ($recentDays as $index => $day) {
                if (count($recentDays) == 1) {
                    // If only one data point, center it
                    $x = $chart_x + $padding_left + ($actual_chart_width / 2);
                } else {
                    $x = $chart_x + $padding_left + ($index * $step_x);
                }
                $y_ratio = $day['count'] / $max_count;
                $y = $chart_y + $padding_top + $actual_chart_height - ($y_ratio * $actual_chart_height);
                $points[] = ['x' => $x, 'y' => $y, 'count' => $day['count']];
            }
            
            // Draw area under line (gradient effect)
            $pdf->SetAlpha(0.1);
            $pdf->SetFillColor(59, 130, 246);
            if (count($points) > 1) {
                // Create polygon for area fill
                $area_points = [];
                foreach ($points as $point) {
                    $area_points[] = $point['x'];
                    $area_points[] = $point['y'];
                }
                // Add bottom right corner
                $area_points[] = $points[count($points)-1]['x'];
                $area_points[] = $chart_y + $padding_top + $actual_chart_height;
                // Add bottom left corner
                $area_points[] = $points[0]['x'];
                $area_points[] = $chart_y + $padding_top + $actual_chart_height;
                
                $pdf->Polygon($area_points, 'F');
            }
            $pdf->SetAlpha(1);
            
            // Draw line connecting points
            $pdf->SetDrawColor(59, 130, 246);
            $pdf->SetLineWidth(0.8);
            for ($i = 0; $i < count($points) - 1; $i++) {
                $pdf->Line($points[$i]['x'], $points[$i]['y'], $points[$i+1]['x'], $points[$i+1]['y']);
            }
            
            // Draw points (circles)
            $pdf->SetFillColor(59, 130, 246);
            foreach ($points as $point) {
                $pdf->Circle($point['x'], $point['y'], 1.2, 0, 360, 'F');
                
                // Draw white center for donut effect
                $pdf->SetFillColor(255, 255, 255);
                $pdf->Circle($point['x'], $point['y'], 0.6, 0, 360, 'F');
                $pdf->SetFillColor(59, 130, 246);
            }
            
            // Draw outer border
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->SetLineWidth(0.3);
            $pdf->Rect($chart_x, $chart_y, $chart_width, $chart_height, 'D');
            
            // Add X-axis date labels
            $pdf->SetFont('helvetica', '', 5.5);
            $pdf->SetTextColor(100, 116, 139);
            
            // Show every other date to avoid crowding
            for ($i = 0; $i < count($recentDays); $i += 2) {
                if ($i < count($points)) {
                    $date_label = date('m/d', strtotime($recentDays[$i]['request_date']));
                    $pdf->SetXY($points[$i]['x'] - 5, $chart_y + $chart_height - $padding_bottom + 2);
                    $pdf->Cell(10, 3, $date_label, 0, 0, 'C');
                }
            }
        }
    }
    
    // Draw Pie Chart - System Type Distribution (right side)
    if (!empty($analyticsData['systemTypeDistribution'])) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(31, 41, 55);
        $pdf->SetXY($x_start + 95, $y_start);
        $pdf->Cell(90, 5, 'System Type Distribution', 0, 1, 'L');
        
        $topSystems = array_slice($analyticsData['systemTypeDistribution'], 0, 6);
        if (!empty($topSystems)) {
            $chart_x = $x_start + 95;
            $chart_y = $y_start + 8;
            
            // Calculate total
            $total = array_sum(array_column($topSystems, 'count'));
            if ($total == 0) $total = 1;
            
            // Pie chart settings
            $center_x = $chart_x + 25;
            $center_y = $chart_y + 25;
            $radius = 20;
            
            // Colors for pie slices
            $colors = [
                [59, 130, 246],    // Blue
                [16, 185, 129],    // Emerald
                [249, 115, 22],    // Orange
                [139, 92, 246],    // Purple
                [236, 72, 153],    // Pink
                [234, 179, 8]      // Yellow
            ];
            
            // Draw white background circle for depth
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Circle($center_x, $center_y, $radius + 1, 0, 360, 'F');
            
            // Draw pie slices
            $start_angle = -90; // Start from top
            foreach ($topSystems as $index => $system) {
                $percentage = ($system['count'] / $total) * 100;
                $angle = ($percentage / 100) * 360;
                
                $color = $colors[$index % count($colors)];
                $pdf->SetFillColor($color[0], $color[1], $color[2]);
                
                // Draw pie slice using PieSector
                $pdf->PieSector($center_x, $center_y, $radius, $start_angle, $start_angle + $angle, 'F');
                
                $start_angle += $angle;
            }
            
            // Draw white center circle for donut effect
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Circle($center_x, $center_y, $radius * 0.5, 0, 360, 'F');
            
            // Draw total in center
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(31, 41, 55);
            $pdf->SetXY($center_x - 10, $center_y - 3);
            $pdf->Cell(20, 4, $total, 0, 0, 'C');
            $pdf->SetFont('helvetica', '', 6);
            $pdf->SetTextColor(107, 114, 128);
            $pdf->SetXY($center_x - 10, $center_y + 1);
            $pdf->Cell(20, 3, 'Total', 0, 0, 'C');
            
            // Draw legend
            $legend_x = $chart_x + 52;
            $legend_y = $chart_y + 5;
            
            foreach ($topSystems as $index => $system) {
                $item_y = $legend_y + ($index * 7);
                $color = $colors[$index % count($colors)];
                $percentage = ($system['count'] / $total) * 100;
                
                // Color box
                $pdf->SetFillColor($color[0], $color[1], $color[2]);
                $pdf->RoundedRect($legend_x, $item_y, 3, 3, 0.5, '1111', 'F');
                
                // System name and count
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetTextColor(51, 65, 85);
                $system_name = strlen($system['system_name']) > 14 
                    ? substr($system['system_name'], 0, 11) . '...' 
                    : $system['system_name'];
                $pdf->SetXY($legend_x + 5, $item_y - 0.5);
                $pdf->Cell(25, 4, $system_name, 0, 0, 'L');
                
                // Percentage
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->SetTextColor($color[0], $color[1], $color[2]);
                $pdf->SetXY($legend_x + 30, $item_y - 0.5);
                $pdf->Cell(8, 4, number_format($percentage, 0) . '%', 0, 0, 'R');
            }
        }
    }
}

// Output PDF - Open in new tab (inline) instead of auto-download
$filename = 'UAR_HelpDesk_Analytics_Report_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'I');


