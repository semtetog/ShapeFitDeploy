<?php
// admin/ajax_get_food_units.php - API para buscar unidades de um alimento

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/units_manager.php';

requireAdminLogin();

header('Content-Type: application/json');

try {
    $food_id = $_GET['food_id'] ?? null;
    
    if (!$food_id) {
        throw new Exception('ID do alimento não fornecido');
    }
    
    $units_manager = new UnitsManager($conn);
    $units = $units_manager->getUnitsForFood($food_id);
    
    echo json_encode([
        'success' => true,
        'data' => $units
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
