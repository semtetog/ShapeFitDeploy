<?php
// EXECUTE_CLEANUP.php - Executar limpeza diretamente

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "=== EXECUTANDO LIMPEZA COMPLETA ===\n\n";

// 1. Remover todas as conversões
echo "1. Removendo conversões...\n";
$conn->query("DELETE FROM sf_food_units");
echo "✅ Conversões removidas\n\n";

// 2. Resetar classificações
echo "2. Resetando classificações...\n";
$conn->query("UPDATE sf_food_items SET food_type = 'granular'");
echo "✅ Classificações resetadas\n\n";

// 3. Limpar categorias múltiplas
echo "3. Limpando categorias múltiplas...\n";
$conn->query("DELETE FROM sf_food_categories");
echo "✅ Categorias múltiplas limpas\n\n";

echo "🎉 LIMPEZA COMPLETA!\n";
echo "Sistema pronto para classificação manual pelas estagiárias!\n";
?>
