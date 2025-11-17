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
            // Resolver IDs com prefixo (ex.: taco_66, off_7890123) para o ID interno
            $resolved_food_id = null;
            if (is_numeric($food_id)) {
                $resolved_food_id = (int)$food_id;
                // Se não houver conversões para este ID, tentar tratar como taco_id
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM sf_food_item_conversions WHERE food_item_id = ?");
                if ($check_stmt) {
                    $check_stmt->bind_param("i", $resolved_food_id);
                    $check_stmt->execute();
                    $check_stmt->bind_result($cnt);
                    if ($check_stmt->fetch() && (int)$cnt === 0) {
                        $check_stmt->close();
                        $stmt_find = $conn->prepare("SELECT id FROM sf_food_items WHERE taco_id = ? LIMIT 1");
                        if ($stmt_find) {
                            $identifier = (string)$food_id;
                            $stmt_find->bind_param("s", $identifier);
                            $stmt_find->execute();
                            $stmt_find->bind_result($found_id);
                            if ($stmt_find->fetch()) {
                                $resolved_food_id = (int)$found_id;
                            }
                            $stmt_find->close();
                        }
                    } else {
                        $check_stmt->close();
                    }
                }
            } else {
                $id_parts = explode('_', $food_id, 2);
                if (count($id_parts) === 2) {
                    $prefix = $id_parts[0];
                    $identifier = $id_parts[1];
                    if ($prefix === 'taco' && is_numeric($identifier)) {
                        $stmt_find = $conn->prepare("SELECT id FROM sf_food_items WHERE taco_id = ? LIMIT 1");
                        if ($stmt_find) {
                            $stmt_find->bind_param("s", $identifier);
                            $stmt_find->execute();
                            $stmt_find->bind_result($found_id);
                            if ($stmt_find->fetch()) {
                                $resolved_food_id = (int)$found_id;
                            }
                            $stmt_find->close();
                        }
                    } elseif ($prefix === 'off' && is_numeric($identifier)) {
                        $stmt_find = $conn->prepare("SELECT id FROM sf_food_items WHERE barcode = ? LIMIT 1");
                        if ($stmt_find) {
                            $stmt_find->bind_param("s", $identifier);
                            $stmt_find->execute();
                            $stmt_find->bind_result($found_id);
                            if ($stmt_find->fetch()) {
                                $resolved_food_id = (int)$found_id;
                            }
                            $stmt_find->close();
                        }
                    }
                }
            }
            if (!$resolved_food_id) {
                // Se mesmo após todas as tentativas não resolver, retorna um erro claro.
                throw new Exception('Alimento não encontrado ou ID inválido: ' . $food_id);
            }
            
            $units = $units_manager->getUnitsForFood($resolved_food_id);

            // FALLBACK: Se nenhuma unidade específica for encontrada, busca as unidades padrão.
            if (empty($units)) {
                error_log("Nenhuma unidade específica para food_id {$resolved_food_id}. Buscando unidades padrão.");
                $units = $units_manager->getDefaultUnits();
            }

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

