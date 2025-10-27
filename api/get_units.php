<?php
// api/get_units.php - API para buscar unidades de medida

require_once '../includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/units_manager.php';

header('Content-Type: application/json');

try {
    $units_manager = new UnitsManager($conn);
    
    $action = $_GET['action'] ?? 'all';
    $food_id = $_GET['food_id'] ?? null;
    $category = $_GET['category'] ?? null;
    
    switch ($action) {
        case 'all':
            $units = $units_manager->getAllUnits();
            break;
            
        case 'by_category':
            if (!$category) {
                throw new Exception('Categoria não fornecida');
            }
            $units = $units_manager->getUnitsByCategory($category);
            break;
            
        case 'for_food':
            if (!$food_id) {
                throw new Exception('ID do alimento não fornecido');
            }
            $units = $units_manager->getUnitsForFood($food_id);
            break;
            
        case 'suggested':
            $food_name = $_GET['food_name'] ?? '';
            $units = $units_manager->getSuggestedUnits($food_name);
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
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

