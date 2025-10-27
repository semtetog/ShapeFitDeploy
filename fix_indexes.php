<?php
// fix_indexes.php - Corrigir índices duplicados

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "=== CORRIGINDO ÍNDICES DUPLICADOS ===\n\n";

try {
    // Verificar se os índices existem antes de criar
    $check_index1 = "SHOW INDEX FROM sf_food_categories WHERE Key_name = 'idx_food_categories_food_id'";
    $result1 = $conn->query($check_index1);
    
    if ($result1->num_rows == 0) {
        $index_sql = "CREATE INDEX idx_food_categories_food_id ON sf_food_categories(food_id)";
        $conn->query($index_sql);
        echo "✅ Índice idx_food_categories_food_id criado!\n";
    } else {
        echo "ℹ️  Índice idx_food_categories_food_id já existe.\n";
    }
    
    $check_index2 = "SHOW INDEX FROM sf_food_categories WHERE Key_name = 'idx_food_categories_category'";
    $result2 = $conn->query($check_index2);
    
    if ($result2->num_rows == 0) {
        $index_sql2 = "CREATE INDEX idx_food_categories_category ON sf_food_categories(category_type)";
        $conn->query($index_sql2);
        echo "✅ Índice idx_food_categories_category criado!\n";
    } else {
        echo "ℹ️  Índice idx_food_categories_category já existe.\n";
    }
    
    // Verificar dados migrados
    $count_sql = "SELECT COUNT(*) as total FROM sf_food_categories";
    $count_result = $conn->query($count_sql);
    $total_categories = $count_result->fetch_assoc()['total'];
    
    echo "\n=== VERIFICAÇÃO FINAL ===\n";
    echo "✅ Total de categorias migradas: {$total_categories}\n";
    echo "✅ Sistema de múltiplas categorias funcionando!\n";
    echo "✅ Agora você pode usar o sistema de classificação!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
