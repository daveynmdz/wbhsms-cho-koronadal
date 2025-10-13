<?php
/**
 * Get Service Catalog API
 * Returns available service items for billing (shared utility)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Root path for includes
$root_path = dirname(dirname(dirname(__DIR__)));

// Check if user is logged in (either patient or employee)
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/session/patient_session.php';
require_once $root_path . '/config/db.php';

// Authentication check - allow both employee and patient access
$is_employee = is_employee_logged_in();
$is_patient = is_patient_logged_in();

if (!$is_employee && !$is_patient) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit();
}

try {
    // Get optional filters
    $filters = [
        'category' => $_GET['category'] ?? 'all',
        'status' => $_GET['status'] ?? 'active',
        'search' => $_GET['search'] ?? '',
        'price_min' => $_GET['price_min'] ?? null,
        'price_max' => $_GET['price_max'] ?? null,
        'limit' => min(500, max(10, intval($_GET['limit'] ?? 100)))
    ];
    
    // Build base query
    $sql = "
        SELECT 
            service_item_id,
            item_name,
            description,
            category,
            price,
            status,
            created_at,
            updated_at
        FROM service_items
        WHERE 1=1
    ";
    
    $params = [];
    
    // Add status filter
    if ($filters['status'] !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $filters['status'];
    }
    
    // Add category filter
    if ($filters['category'] !== 'all') {
        $sql .= " AND category = ?";
        $params[] = $filters['category'];
    }
    
    // Add search filter
    if (!empty($filters['search'])) {
        $search_term = '%' . $filters['search'] . '%';
        $sql .= " AND (item_name LIKE ? OR description LIKE ?)";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    // Add price filters
    if ($filters['price_min'] !== null) {
        $sql .= " AND price >= ?";
        $params[] = floatval($filters['price_min']);
    }
    
    if ($filters['price_max'] !== null) {
        $sql .= " AND price <= ?";
        $params[] = floatval($filters['price_max']);
    }
    
    // Add ordering and limit
    $sql .= " ORDER BY category, item_name ASC LIMIT ?";
    $params[] = $filters['limit'];
    
    // Execute query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $service_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data
    $formatted_items = [];
    foreach ($service_items as $item) {
        $formatted_item = [
            'service_item_id' => intval($item['service_item_id']),
            'item_name' => $item['item_name'],
            'description' => $item['description'],
            'category' => $item['category'],
            'price' => floatval($item['price']),
            'status' => $item['status'],
            'is_active' => $item['status'] === 'active'
        ];
        
        // Only show pricing to employees (patients see services but not prices for transparency)
        if (!$is_employee) {
            unset($formatted_item['price']);
        }
        
        $formatted_items[] = $formatted_item;
    }
    
    // Get categories for filtering
    $categories_sql = "
        SELECT DISTINCT category, COUNT(*) as item_count
        FROM service_items 
        WHERE status = 'active'
        GROUP BY category
        ORDER BY category
    ";
    $stmt = $pdo->prepare($categories_sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted_categories = array_map(function($cat) {
        return [
            'category' => $cat['category'],
            'item_count' => intval($cat['item_count'])
        ];
    }, $categories);
    
    // Get statistics
    $stats_sql = "
        SELECT 
            COUNT(*) as total_items,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_items,
            COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_items,
            AVG(CASE WHEN status = 'active' THEN price END) as avg_price,
            MIN(CASE WHEN status = 'active' THEN price END) as min_price,
            MAX(CASE WHEN status = 'active' THEN price END) as max_price
        FROM service_items
    ";
    $stmt = $pdo->prepare($stats_sql);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $response_data = [
        'items' => $formatted_items,
        'categories' => $formatted_categories,
        'filters_applied' => $filters,
        'total_results' => count($formatted_items)
    ];
    
    // Only include pricing statistics for employees
    if ($is_employee) {
        $response_data['statistics'] = [
            'total_items' => intval($stats['total_items']),
            'active_items' => intval($stats['active_items']),
            'inactive_items' => intval($stats['inactive_items']),
            'pricing' => [
                'average_price' => floatval($stats['avg_price']),
                'min_price' => floatval($stats['min_price']),
                'max_price' => floatval($stats['max_price'])
            ]
        ];
    }
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'data' => $response_data
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    error_log("Service catalog API error: " . $e->getMessage());
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>