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
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM uar.approval_history $whereClause");
    $stmt->execute($params);
    $totalRequests = $stmt->fetchColumn();
    
    // Get total approved requests with filters
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM uar.approval_history $whereClause AND action = 'approved'");
    $stmt->execute($params);
    $totalApproved = $stmt->fetchColumn();
    
    // Get total declined requests with filters
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM uar.approval_history $whereClause AND action = 'rejected'");
    $stmt->execute($params);
    $totalDeclined = $stmt->fetchColumn();
    
    // Get total employees
    try {
        $empStmt = $pdo->query("SELECT COUNT(*) FROM uar.employees");
        $totalEmployees = (int)$empStmt->fetchColumn();
    } catch (PDOException $e) {
        $totalEmployees = 0;
    }
    
    // Calculate approval rate
    $approvalRate = $totalRequests > 0 ? round(($totalApproved / $totalRequests) * 100, 2) : 0;
    
    // Calculate decline rate
    $declineRate = $totalRequests > 0 ? round(($totalDeclined / $totalRequests) * 100, 2) : 0;
    
    return [
        'total' => (int)$totalRequests,
        'approved' => (int)$totalApproved,
        'rejected' => (int)$totalDeclined,
        'total_employees' => $totalEmployees,
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
    $stmt = $pdo->query("SELECT DISTINCT business_unit FROM uar.approval_history WHERE business_unit IS NOT NULL ORDER BY business_unit");
    $data['businessUnits'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->query("SELECT DISTINCT department FROM uar.approval_history WHERE department IS NOT NULL ORDER BY department");
    $data['departments'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get unique system types (split comma-separated values)
    $stmt = $pdo->query("SELECT DISTINCT system_type FROM uar.approval_history WHERE system_type IS NOT NULL");
    $allSystemTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $uniqueSystemTypes = [];
    foreach ($allSystemTypes as $systemType) {
        $systems = array_map('trim', explode(',', $systemType));
        foreach ($systems as $system) {
            if (!empty($system) && !in_array($system, $uniqueSystemTypes)) {
                $uniqueSystemTypes[] = $system;
            }
        }
    }
    sort($uniqueSystemTypes);
    $data['systemTypes'] = $uniqueSystemTypes;

    // 1. Access Type Distribution
    $stmt = $pdo->prepare("SELECT 
        access_type,
        COUNT(*) as count
        FROM uar.approval_history
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
        FROM uar.approval_history
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
        FROM uar.approval_history
        $whereClause
        GROUP BY department
        ORDER BY total_requests DESC");
    $stmt->execute($params);
    $data['departmentAnalysis'] = $stmt->fetchAll();

    // 4. System Type Distribution (split comma-separated values)
    $stmt = $pdo->prepare("
        SELECT system_type
        FROM uar.approval_history
        $whereClause AND system_type IS NOT NULL
    ");
    $stmt->execute($params);
    $systemTypeRows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $systemTypeCounts = [];
    foreach ($systemTypeRows as $systemType) {
        $systems = array_map('trim', explode(',', $systemType));
        foreach ($systems as $system) {
            if (!empty($system)) {
                if (!isset($systemTypeCounts[$system])) {
                    $systemTypeCounts[$system] = 0;
                }
                $systemTypeCounts[$system]++;
            }
        }
    }
    
    // Sort by count and convert to array format
    arsort($systemTypeCounts);
    $data['systemTypeDistribution'] = [];
    foreach ($systemTypeCounts as $system => $count) {
        $data['systemTypeDistribution'][] = [
            'system_type' => $system,
            'count' => $count
        ];
    }

    // 5. Daily Requests (for chart)
    $stmt = $pdo->prepare("
        SELECT 
            CAST(created_at AS DATE) as request_date,
            COUNT(*) as count
        FROM uar.approval_history
        $whereClause
        GROUP BY CAST(created_at AS DATE)
        ORDER BY request_date DESC
    ");
    $stmt->execute($params);
    $data['dailyRequests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $data;
} 