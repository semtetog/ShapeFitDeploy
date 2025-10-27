<?php
// CLEAN_ALL_UNITS.php - Limpar TODAS as unidades de medida existentes

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "=== LIMPANDO TODAS AS UNIDADES DE MEDIDA ===\n\n";

// Remover todas as conversões existentes
$delete_conversions_sql = "DELETE FROM sf_food_units";
if ($conn->query($delete_conversions_sql) === TRUE) {
    echo "✅ Todas as conversões de unidades removidas!\n";
} else {
    echo "❌ Erro ao remover conversões: " . $conn->error . "\n";
}

// Resetar todas as classificações para 'granular'
$reset_classifications_sql = "UPDATE sf_food_items SET food_type = 'granular'";
if ($conn->query($reset_classifications_sql) === TRUE) {
    echo "✅ Todas as classificações resetadas para 'granular'!\n";
} else {
    echo "❌ Erro ao resetar classificações: " . $conn->error . "\n";
}

// Limpar tabela de categorias múltiplas
$delete_categories_sql = "DELETE FROM sf_food_categories";
if ($conn->query($delete_categories_sql) === TRUE) {
    echo "✅ Tabela de categorias múltiplas limpa!\n";
} else {
    echo "❌ Erro ao limpar categorias: " . $conn->error . "\n";
}

echo "\n=== SISTEMA LIMPO! ===\n";
echo "✅ Todas as unidades removidas\n";
echo "✅ Todas as classificações resetadas\n";
echo "✅ Sistema pronto para classificação manual\n\n";

echo "🎯 AGORA AS ESTAGIÁRIAS PODEM CLASSIFICAR DO ZERO!\n";
echo "Acesse: /admin/food_classification.php\n";
?>
