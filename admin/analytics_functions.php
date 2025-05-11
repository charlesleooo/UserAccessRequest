<?php
// Function to get analytics stats
function getDashboardStats($pdo, $filters = []) {
    // Build WHERE clauses based on filters
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['start_date'])) {
        $whereClause .= " AND created_at >= :start_date";
        $params[':start_date'] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $whereClause .= " AND created_at <= :end_date";
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
    
    if (!empty($filters['system_type'])) {
        $whereClause .= " AND system_type = :system_type";
        $params[':system_type'] = $filters['system_type'];
    }

    // Get total requests from approval history with filters
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM approval_history $whereClause");
    $stmt->execute($params);
    $totalRequests = $stmt->fetchColumn();
    
    // Get total approved requests with filters
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM approval_history $whereClause AND action = 'approved'");
    $stmt->execute($params);
    $totalApproved = $stmt->fetchColumn();
    
    // Get total declined requests with filters
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM approval_history $whereClause AND action = 'rejected'");
    $stmt->execute($params);
    $totalDeclined = $stmt->fetchColumn();
    
    // Calculate approval rate
    $approvalRate = $totalRequests > 0 ? round(($totalApproved / $totalRequests) * 100, 2) : 0;
    
    // Calculate decline rate
    $declineRate = $totalRequests > 0 ? round(($totalDeclined / $totalRequests) * 100, 2) : 0;
    
    return [
        'total' => $totalRequests,
        'approved' => $totalApproved,
        'approval_rate' => $approvalRate,
        'decline_rate' => $declineRate
    ];
}

// Function to get analytics data
function getAnalyticsData($pdo, $filters = []) {
    $data = [];
    
    // Build WHERE clauses based on filters
    $whereClause = "WHERE 1=1";
    $params = [];
    
    if (!empty($filters['start_date'])) {
        $whereClause .= " AND created_at >= :start_date";
        $params[':start_date'] = $filters['start_date'];
    }
    
    if (!empty($filters['end_date'])) {
        $whereClause .= " AND created_at <= :end_date";
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
    
    if (!empty($filters['system_type'])) {
        $whereClause .= " AND system_type = :system_type";
        $params[':system_type'] = $filters['system_type'];
    }

    // Get unique filter options
    $stmt = $pdo->query("SELECT DISTINCT business_unit FROM approval_history WHERE business_unit IS NOT NULL ORDER BY business_unit");
    $data['businessUnits'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->query("SELECT DISTINCT department FROM approval_history WHERE department IS NOT NULL ORDER BY department");
    $data['departments'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->query("SELECT DISTINCT system_type FROM approval_history WHERE system_type IS NOT NULL ORDER BY system_type");
    $data['systemTypes'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 1. Access Type Distribution
    $stmt = $pdo->prepare("SELECT 
        access_type,
        COUNT(*) as count
        FROM approval_history
        $whereClause
        GROUP BY access_type
        ORDER BY count DESC");
    $stmt->execute($params);
    $data['accessTypeDistribution'] = $stmt->fetchAll();

    // 2. Business Unit Analysis
    $stmt = $pdo->prepare("SELECT 
        business_unit,
        COUNT(*) as total_requests,
        SUM(CASE WHEN action = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN action = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM approval_history
        $whereClause
        GROUP BY business_unit
        ORDER BY total_requests DESC");
    $stmt->execute($params);
    $data['businessUnitAnalysis'] = $stmt->fetchAll();

    // 3. Department Analysis
    $stmt = $pdo->prepare("SELECT 
        department,
        COUNT(*) as total_requests,
        SUM(CASE WHEN action = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN action = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM approval_history
        $whereClause
        GROUP BY department
        ORDER BY total_requests DESC");
    $stmt->execute($params);
    $data['departmentAnalysis'] = $stmt->fetchAll();

    return $data;
} 