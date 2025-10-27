<?php
// debug_categories_issue.php - Script para debugar o problema de categorias

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "=== DEBUG DO PROBLEMA DE CATEGORIAS ===\n\n";

// Testar com um alimento específico (ex: Abacaxi)
$food_name = "Abacaxi";
$food_id = null;

// 1. Encontrar o ID do Abacaxi
$stmt_food = $conn->prepare("SELECT id, name_pt, food_type FROM sf_food_items WHERE name_pt = ? LIMIT 1");
if ($stmt_food) {
    $stmt_food->bind_param("s", $food_name);
    $stmt_food->execute();
    $stmt_food->bind_result($food_id, $name, $food_type);
    $stmt_food->fetch();
    $stmt_food->close();
}

if ($food_id) {
    echo "Alimento encontrado: ID: {$food_id} Nome: {$name} Tipo: {$food_type}\n\n";
    
    // 2. Verificar categorias na tabela sf_food_categories
    echo "--- CATEGORIAS NA TABELA sf_food_categories ---\n";
    $stmt_categories = $conn->prepare("SELECT category_type, is_primary FROM sf_food_categories WHERE food_id = ?");
    if ($stmt_categories) {
        $stmt_categories->bind_param("i", $food_id);
        $stmt_categories->execute();
        $result_categories = $stmt_categories->get_result();
        
        if ($result_categories->num_rows > 0) {
            while ($row = $result_categories->fetch_assoc()) {
                echo "Categoria: {$row['category_type']} (Primária: " . ($row['is_primary'] ? 'Sim' : 'Não') . ")\n";
            }
        } else {
            echo "Nenhuma categoria encontrada na tabela sf_food_categories\n";
        }
        $stmt_categories->close();
    }
    
    // 3. Testar a query exata que está sendo usada no painel
    echo "\n--- TESTE DA QUERY DO PAINEL ---\n";
    $test_sql = "
        SELECT 
            sfi.id, 
            sfi.name_pt, 
            sfi.food_type, 
            sfi.energy_kcal_100g, 
            sfi.protein_g_100g, 
            sfi.carbohydrate_g_100g, 
            sfi.fat_g_100g,
            GROUP_CONCAT(sfc.category_type ORDER BY sfc.is_primary DESC, sfc.category_type ASC) as categories
        FROM sf_food_items sfi
        LEFT JOIN sf_food_categories sfc ON sfi.id = sfc.food_id
        WHERE sfi.id = ?
        GROUP BY sfi.id
    ";
    
    $stmt_test = $conn->prepare($test_sql);
    if ($stmt_test) {
        $stmt_test->bind_param("i", $food_id);
        $stmt_test->execute();
        $result_test = $stmt_test->get_result();
        
        if ($food = $result_test->fetch_assoc()) {
            echo "Resultado da query:\n";
            echo "ID: {$food['id']}\n";
            echo "Nome: {$food['name_pt']}\n";
            echo "Tipo: {$food['food_type']}\n";
            echo "Categorias (raw): " . var_export($food['categories'], true) . "\n";
            echo "Categorias (empty check): " . (empty($food['categories']) ? 'TRUE' : 'FALSE') . "\n";
            echo "Categorias (is_null): " . (is_null($food['categories']) ? 'TRUE' : 'FALSE') . "\n";
            echo "Categorias (=== null): " . ($food['categories'] === null ? 'TRUE' : 'FALSE') . "\n";
        }
        $stmt_test->close();
    }
    
} else {
    echo "Alimento '{$food_name}' não encontrado!\n";
}

echo "\n=== DEBUG CONCLUÍDO ===\n";
$conn->close();
?>
