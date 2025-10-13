<?php
// Get Service Catalog API
session_start();

// Root path for includes
$root_path = dirname(__DIR__);

// Check if user is logged in as employee
require_once $root_path . '/config/session/employee_session.php';

if (!is_employee_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Check if user has cashier or admin role
$employee_role = get_employee_session('role_name');
if (!in_array($employee_role, ['cashier', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Cashier or admin role required.']);
    exit();
}

// Include database connection
require_once $root_path . '/config/db.php';

// Set content type
header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get service categories
    $services_sql = "
        SELECT 
            s.service_id,
            s.name as service_name,
            s.description as service_description,
            s.is_billable
        FROM services s
        WHERE s.is_billable = 1
        ORDER BY s.name
    ";
    
    $services_stmt = $pdo->prepare($services_sql);
    $services_stmt->execute();
    $services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get service items with pricing
    $items_sql = "
        SELECT 
            si.item_id,
            si.service_id,
            si.item_name,
            si.price_php,
            si.unit,
            s.name as service_name,
            s.description as service_description
        FROM service_items si
        JOIN services s ON si.service_id = s.service_id
        WHERE si.is_active = 1 
        AND s.is_billable = 1
        ORDER BY s.name, si.item_name
    ";
    
    $items_stmt = $pdo->prepare($items_sql);
    $items_stmt->execute();
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group items by service
    $grouped_items = [];
    foreach ($items as $item) {
        $service_id = $item['service_id'];
        if (!isset($grouped_items[$service_id])) {
            $grouped_items[$service_id] = [
                'service_id' => $service_id,
                'service_name' => $item['service_name'],
                'service_description' => $item['service_description'],
                'items' => []
            ];
        }
        
        $grouped_items[$service_id]['items'][] = [
            'item_id' => $item['item_id'],
            'item_name' => $item['item_name'],
            'price_php' => floatval($item['price_php']),
            'formatted_price' => number_format($item['price_php'], 2),
            'unit' => $item['unit']
        ];
    }
    
    // Convert to indexed array
    $service_catalog = array_values($grouped_items);
    
    // Add summary statistics
    $total_services = count($services);
    $total_items = count($items);
    $price_range = [
        'min' => 0,
        'max' => 0
    ];
    
    if (!empty($items)) {
        $prices = array_column($items, 'price_php');
        $price_range['min'] = min($prices);
        $price_range['max'] = max($prices);
    }
    
    echo json_encode([
        'success' => true,
        'service_catalog' => $service_catalog,
        'summary' => [
            'total_services' => $total_services,
            'total_items' => $total_items,
            'price_range' => [
                'min' => floatval($price_range['min']),
                'max' => floatval($price_range['max']),
                'formatted_min' => number_format($price_range['min'], 2),
                'formatted_max' => number_format($price_range['max'], 2)
            ]
        ],
        'service_types' => [
            'consultation' => 'Medical Consultation',
            'laboratory' => 'Laboratory Test',
            'pharmacy' => 'Medication/Prescription',
            'dental' => 'Dental Service',
            'prenatal' => 'Prenatal Care',
            'immunization' => 'Immunization/Vaccination',
            'emergency' => 'Emergency Care',
            'family_planning' => 'Family Planning',
            'nutrition' => 'Nutrition Counseling',
            'other' => 'Other Services'
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Service Catalog Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred while retrieving service catalog'
    ]);
} catch (Exception $e) {
    error_log("Service Catalog Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An unexpected error occurred'
    ]);
}
?>