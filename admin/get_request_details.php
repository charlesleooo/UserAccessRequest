<?php
session_start();
require_once '../config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['admin_id']) || !in_array($_SESSION['role'], ['superior', 'technical', 'technical_support', 'process_owner', 'admin'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Check if request ID is provided
if (!isset($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Request ID is required'
    ]);
    exit();
}

try {
    // Check if we're looking for history or regular request
    $isHistoryRequest = isset($_GET['type']) && $_GET['type'] === 'history';
    
    if ($isHistoryRequest) {
        // Get history details
        $stmt = $pdo->prepare("
            SELECT h.*, a.username as admin_username 
            FROM approval_history h 
            LEFT JOIN admin_users a ON h.admin_id = a.id 
            WHERE h.history_id = ?
        ");
        
        $stmt->execute([$_GET['id']]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($request) {
            // Format dates
            if ($request['start_date']) {
                $request['start_date'] = date('Y-m-d', strtotime($request['start_date']));
            }
            if ($request['end_date']) {
                $request['end_date'] = date('Y-m-d', strtotime($request['end_date']));
            }
            if ($request['created_at']) {
                $request['created_at'] = date('Y-m-d H:i:s', strtotime($request['created_at']));
            }
            
            echo json_encode($request);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'History record not found'
            ]);
        }
    } else {
        // Get regular request details
        $stmt = $pdo->prepare("
            SELECT ar.*,
                   CASE 
                       WHEN ar.status = 'pending_superior' THEN 'Pending Superior Review'
                       WHEN ar.status = 'pending_technical' THEN 'Pending Technical Review'
                       WHEN ar.status = 'pending_process_owner' THEN 'Pending Process Owner Review'
                       WHEN ar.status = 'pending_admin' THEN 'Pending Admin Review'
                       WHEN ar.status = 'pending_testing' THEN 'Pending Testing'
                       WHEN ar.status = 'approved' THEN 'Approved'
                       WHEN ar.status = 'rejected' THEN 'Rejected'
                       ELSE ar.status
                   END as status_display,
                   COALESCE(ar.superior_notes, '') as superior_review_notes,
                   COALESCE(ar.technical_notes, '') as technical_review_notes,
                   COALESCE(ar.process_owner_notes, '') as process_owner_review_notes,
                   COALESCE(ar.admin_notes, '') as admin_review_notes,
                   COALESCE(ar.superior_review_date, '') as superior_review_date,
                   COALESCE(ar.technical_review_date, '') as technical_review_date,
                   COALESCE(ar.process_owner_review_date, '') as process_owner_review_date,
                   COALESCE(ar.admin_review_date, '') as admin_review_date
            FROM access_requests ar
            WHERE ar.id = ?
        ");
        
        $stmt->execute([$_GET['id']]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($request) {
            // Format dates
            if ($request['start_date']) {
                $request['start_date'] = date('Y-m-d', strtotime($request['start_date']));
            }
            if ($request['end_date']) {
                $request['end_date'] = date('Y-m-d', strtotime($request['end_date']));
            }
            if ($request['submission_date']) {
                $request['submission_date'] = date('Y-m-d H:i:s', strtotime($request['submission_date']));
            }

            // Build review history array
            $review_history = [];
            
            // Add Superior review if exists
            if ($request['superior_review_date']) {
                $review_history[] = [
                    'role' => 'Superior',
                    'action' => $request['status'] === 'rejected' ? 'Declined' : 'Recommended',
                    'note' => $request['superior_review_notes'],
                    'date' => date('Y-m-d H:i:s', strtotime($request['superior_review_date']))
                ];
            }
            
            // Add Technical review if exists
            if ($request['technical_review_date']) {
                $review_history[] = [
                    'role' => 'Technical Support',
                    'action' => $request['status'] === 'rejected' ? 'Declined' : 'Recommended',
                    'note' => $request['technical_review_notes'],
                    'date' => date('Y-m-d H:i:s', strtotime($request['technical_review_date']))
                ];
            }
            
            // Add Process Owner review if exists
            if ($request['process_owner_review_date']) {
                $review_history[] = [
                    'role' => 'Process Owner',
                    'action' => $request['status'] === 'rejected' ? 'Declined' : 'Recommended',
                    'note' => $request['process_owner_review_notes'],
                    'date' => date('Y-m-d H:i:s', strtotime($request['process_owner_review_date']))
                ];
            }
            
            // Add Admin review if exists
            if ($request['admin_review_date']) {
                $review_history[] = [
                    'role' => 'Admin',
                    'action' => $request['status'] === 'rejected' ? 'Declined' : 'Approved',
                    'note' => $request['admin_review_notes'],
                    'date' => date('Y-m-d H:i:s', strtotime($request['admin_review_date']))
                ];
            }

            $request['review_history'] = $review_history;

            echo json_encode([
                'success' => true,
                'data' => $request
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Request not found'
            ]);
        }
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>