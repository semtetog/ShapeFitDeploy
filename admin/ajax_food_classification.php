<?php
// admin/ajax_food_classification.php - Handler AJAX para classificação de alimentos

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/units_manager.php';

requireAdminLogin();

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'save_classifications':
        saveClassifications();
        break;
    case 'declassify_food':
        declassifyFood();
        break;
    case 'get_food_details':
        getFoodDetails();
        break;
    case 'bulk_classify':
        bulkClassify();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
}

function declassifyFood() {
    global $conn;
    
    $food_id = (int)($_POST['food_id'] ?? 0);
    
    if ($food_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de alimento inválido']);
        return;
    }
    
    // Usar a função processDeclassification existente
    processDeclassification([$food_id], true);
}

function saveClassifications() {
    global $conn;
    
    $classifications = json_decode($_POST['classifications'] ?? '{}', true);
    $all_food_ids = json_decode($_POST['all_food_ids'] ?? '[]', true);
    $declassify_unselected = isset($_POST['declassify_unselected']) && $_POST['declassify_unselected'] === '1';
    
    // Debug logs
    error_log("Classificações recebidas: " . json_encode($classifications));
    error_log("IDs de alimentos: " . json_encode($all_food_ids));
    error_log("Classificações vazias: " . (empty($classifications) ? 'SIM' : 'NÃO'));
    error_log("IDs vazios: " . (empty($all_food_ids) ? 'SIM' : 'NÃO'));
    
    // Opcional: desclassificar explicitamente itens não selecionados APENAS quando indicado
    // Por padrão, NÃO desclassificamos nada automaticamente para evitar apagar unidades de outros itens
    $declassification_result = null;
    if ($declassify_unselected && !empty($all_food_ids)) {
        $classified_food_ids = array_keys($classifications);
        $unclassified_food_ids = array_diff($all_food_ids, $classified_food_ids);
        if (!empty($unclassified_food_ids)) {
            error_log("Processando desclassificação (explícita) de " . count($unclassified_food_ids) . " alimentos: " . implode(', ', $unclassified_food_ids));
            $declassification_result = processDeclassification($unclassified_food_ids, false); // Não ecoar
        }
    }
    
    // 2. Se não há classificações, apenas reportar sucesso (não desclassificar por padrão)
    if (empty($classifications)) {
        if ($declassification_result) {
            echo json_encode($declassification_result);
        } else {
            echo json_encode(['success' => true, 'message' => 'Nenhuma alteração necessária!']);
        }
        return;
    }
    
    $valid_categories = [
        'líquido', 'semi_liquido', 'granular', 'unidade_inteira', 
        'fatias_pedacos', 'corte_porcao', 'colher_cremoso', 
        'condimentos', 'oleos_gorduras', 'preparacoes_compostas'
    ];
    
    $saved = 0;
    $errors = [];
    
    $conn->begin_transaction();
    
    try {
        foreach ($classifications as $food_id => $categories) {
            $food_id = (int)$food_id;
            if ($food_id <= 0) {
                $errors[] = "ID de alimento inválido: {$food_id}";
                continue;
            }
            
            // Se não há categorias, definir como 'granular' (padrão) e limpar categorias múltiplas
            if (empty($categories)) {
                $sql = "UPDATE sf_food_items SET food_type = 'granular' WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $food_id);
                $stmt->execute();
                
                // Remover todas as categorias múltiplas
                $delete_sql = "DELETE FROM sf_food_categories WHERE food_id = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("i", $food_id);
                $delete_stmt->execute();
                
                // Remover todas as unidades de conversão para este alimento
                $delete_units_sql = "DELETE FROM sf_food_item_conversions WHERE food_item_id = ?";
                $delete_units_stmt = $conn->prepare($delete_units_sql);
                $delete_units_stmt->bind_param("i", $food_id);
                $delete_units_stmt->execute();
                
                $saved++;
                continue;
            }
            
            // Validar todas as categorias
            $valid_categories_list = array_filter($categories, function($cat) use ($valid_categories) {
                return in_array($cat, $valid_categories);
            });
            
            if (empty($valid_categories_list)) {
                $errors[] = "Nenhuma categoria válida para alimento ID {$food_id}";
                continue;
            }
            
            // Usar primeira categoria como primária
            $primary_category = $valid_categories_list[0];
            
            // Atualizar categoria primária na tabela principal
            $sql = "UPDATE sf_food_items SET food_type = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $primary_category, $food_id);
            
            if (!$stmt->execute()) {
                $errors[] = "Erro ao atualizar categoria primária para alimento ID {$food_id}";
                continue;
            }
            
            // Limpar categorias existentes
            $delete_sql = "DELETE FROM sf_food_categories WHERE food_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $food_id);
            $delete_stmt->execute();
            
            // Inserir todas as categorias
            foreach ($valid_categories_list as $index => $category) {
                $is_primary = ($index === 0) ? 1 : 0;
                $insert_sql = "INSERT INTO sf_food_categories (food_id, category_type, is_primary) VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("isi", $food_id, $category, $is_primary);
                $insert_stmt->execute();
            }
            
            $saved++;
        }
        
        $conn->commit();
        
        // Aplicar unidades automaticamente para os alimentos classificados
        error_log("Aplicando unidades para " . count($classifications) . " alimentos");
        applyUnitsToClassifiedFoods($classifications);
        error_log("Unidades aplicadas com sucesso");
        
        // Combinar resultados de desclassificação e classificação
        $total_saved = $saved;
        $total_errors = $errors;
        $messages = ["{$saved} classificações salvas com sucesso!"];
        
        if ($declassification_result && $declassification_result['success']) {
            $total_saved += $declassification_result['saved'];
            $total_errors = array_merge($total_errors, $declassification_result['errors']);
            $messages[] = $declassification_result['message'];
        }
        
        echo json_encode([
            'success' => true,
            'saved' => $total_saved,
            'errors' => $total_errors,
            'message' => implode(' | ', $messages)
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Erro no banco de dados: ' . $e->getMessage()
        ]);
    }
}

function processDeclassification($food_ids, $echo_response = true) {
    global $conn;
    
    $saved = 0;
    $errors = [];
    
    $conn->begin_transaction();
    
    try {
        foreach ($food_ids as $food_id) {
            $food_id = (int)$food_id;
            if ($food_id <= 0) {
                $errors[] = "ID de alimento inválido: {$food_id}";
                continue;
            }
            
            // Definir como 'granular' (padrão)
            $sql = "UPDATE sf_food_items SET food_type = 'granular' WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $food_id);
            $stmt->execute();
            
            // Remover todas as categorias múltiplas
            $delete_sql = "DELETE FROM sf_food_categories WHERE food_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $food_id);
            $delete_stmt->execute();
            
            // Remover todas as unidades de conversão para este alimento
            $delete_units_sql = "DELETE FROM sf_food_item_conversions WHERE food_item_id = ?";
            $delete_units_stmt = $conn->prepare($delete_units_sql);
            $delete_units_stmt->bind_param("i", $food_id);
            $delete_units_stmt->execute();
            
            $saved++;
        }
        
        $conn->commit();
        
        $result = [
            'success' => true,
            'saved' => $saved,
            'errors' => $errors,
            'message' => "{$saved} alimentos desclassificados com sucesso!"
        ];
        
        if ($echo_response) {
            echo json_encode($result);
        }
        
        return $result;
        
    } catch (Exception $e) {
        $conn->rollback();
        $result = [
            'success' => false,
            'message' => 'Erro no banco de dados: ' . $e->getMessage()
        ];
        
        if ($echo_response) {
            echo json_encode($result);
        }
        
        return $result;
    }
}

function getFoodDetails() {
    global $conn;
    
    $food_id = (int)($_POST['food_id'] ?? 0);
    
    if ($food_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de alimento inválido']);
        return;
    }
    
    $sql = "SELECT id, name_pt, food_type, energy_kcal_100g, protein_g_100g, carbohydrate_g_100g, fat_g_100g 
            FROM sf_food_items WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $food_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($food = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'food' => $food
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Alimento não encontrado'
        ]);
    }
}

function bulkClassify() {
    global $conn;
    
    $food_ids = json_decode($_POST['food_ids'] ?? '[]', true);
    $category = $_POST['category'] ?? '';
    
    if (empty($food_ids) || empty($category)) {
        echo json_encode(['success' => false, 'message' => 'IDs de alimentos e categoria são obrigatórios']);
        return;
    }
    
    $valid_categories = [
        'líquido', 'semi_liquido', 'granular', 'unidade_inteira', 
        'fatias_pedacos', 'corte_porcao', 'colher_cremoso', 
        'condimentos', 'oleos_gorduras', 'preparacoes_compostas'
    ];
    
    if (!in_array($category, $valid_categories)) {
        echo json_encode(['success' => false, 'message' => 'Categoria inválida']);
        return;
    }
    
    $saved = 0;
    $errors = [];
    
    $conn->begin_transaction();
    
    try {
        foreach ($food_ids as $food_id) {
            $food_id = (int)$food_id;
            if ($food_id <= 0) continue;
            
            $sql = "UPDATE sf_food_items SET food_type = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $category, $food_id);
            
            if ($stmt->execute()) {
                $saved++;
            } else {
                $errors[] = "Erro ao atualizar alimento ID {$food_id}";
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'saved' => $saved,
            'errors' => $errors,
            'message' => "{$saved} alimentos classificados como {$category}!"
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Erro no banco de dados: ' . $e->getMessage()
        ]);
    }
}

function applyUnitsToClassifiedFoods($classifications) {
    global $conn;
    
    try {
        error_log("Iniciando applyUnitsToClassifiedFoods com " . count($classifications) . " classificações");
        $units_manager = new UnitsManager($conn);
        
        // Definir unidades para cada categoria
        $category_units_map = [
            'líquido' => ['ml', 'l', 'cs', 'cc', 'xc'],
            'semi_liquido' => ['g', 'ml', 'cs', 'cc', 'xc'],
            'granular' => ['g', 'kg', 'cs', 'cc', 'xc'],
            'unidade_inteira' => ['un', 'g', 'kg'],
            'fatias_pedacos' => ['fat', 'g', 'kg', 'un'],
            'corte_porcao' => ['g', 'kg', 'un', 'fat'],
            'colher_cremoso' => ['cs', 'cc', 'g'],
            'condimentos' => ['cc', 'cs', 'g'],
            'oleos_gorduras' => ['cs', 'cc', 'ml', 'l'],
            'preparacoes_compostas' => ['g', 'kg', 'un']
        ];
        
        foreach ($classifications as $food_id => $categories) {
            $food_id = (int)$food_id;
            if ($food_id <= 0 || empty($categories)) continue;
            
            error_log("Processando food_id {$food_id} com categorias: " . implode(', ', $categories));
            
            // Limpar unidades existentes para este alimento
            $delete_sql = "DELETE FROM sf_food_item_conversions WHERE food_item_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $food_id);
            $delete_stmt->execute();
            
            // Coletar todas as unidades únicas das categorias
            $unique_units = [];
            foreach ($categories as $category) {
                if (isset($category_units_map[$category])) {
                    foreach ($category_units_map[$category] as $unit_abbr) {
                        $unique_units[$unit_abbr] = true;
                    }
                }
            }
            
            // Aplicar unidades ao alimento
            foreach (array_keys($unique_units) as $unit_abbr) {
                $unit_data = $units_manager->getUnitByAbbreviation($unit_abbr);
                if ($unit_data) {
                    $unit_id = $unit_data['id'];
                    $conversion_factor = $units_manager->getConversionFactor($unit_abbr);
                    $is_default = ($unit_abbr === 'g' || $unit_abbr === 'ml' || $unit_abbr === 'un');
                    $is_default_int = $is_default ? 1 : 0; // Converter para int
                    
                    $insert_sql = "INSERT INTO sf_food_item_conversions (food_item_id, unit_id, conversion_factor, is_default) VALUES (?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bind_param("iidi", $food_id, $unit_id, $conversion_factor, $is_default_int);
                    $insert_stmt->execute();
                }
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao aplicar unidades: " . $e->getMessage());
        // Não interromper o processo principal se houver erro na aplicação de unidades
    }
}
?>
