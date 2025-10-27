<?php
// RESET_EVERYTHING_CLEAN.php - ZERAR TUDO E COMEÇAR DO ZERO

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "🧹 ZERANDO TUDO PARA COMEÇAR DO ZERO 🧹\n\n";

$conn->begin_transaction();

try {
    // 1. LIMPAR TODAS AS UNIDADES DE MEDIDA
    echo "🗑️ Removendo TODAS as unidades de medida...\n";
    $conn->query("DELETE FROM sf_food_units");
    echo "✅ Todas as unidades removidas!\n\n";
    
    // 2. LIMPAR TODAS AS CATEGORIAS MÚLTIPLAS
    echo "🗑️ Removendo TODAS as categorias múltiplas...\n";
    $conn->query("DELETE FROM sf_food_categories");
    echo "✅ Todas as categorias múltiplas removidas!\n\n";
    
    // 3. RESETAR TODOS OS FOOD_TYPE PARA 'granular' (padrão)
    echo "🔄 Resetando todos os food_type para 'granular'...\n";
    $conn->query("UPDATE sf_food_items SET food_type = 'granular'");
    echo "✅ Todos os food_type resetados!\n\n";
    
    // 4. VERIFICAR SE FICOU LIMPO
    $check_units = $conn->query("SELECT COUNT(*) as count FROM sf_food_units")->fetch_assoc()['count'];
    $check_categories = $conn->query("SELECT COUNT(*) as count FROM sf_food_categories")->fetch_assoc()['count'];
    $check_food_types = $conn->query("SELECT COUNT(*) as count FROM sf_food_items WHERE food_type != 'granular'")->fetch_assoc()['count'];
    
    echo "📊 VERIFICAÇÃO FINAL:\n";
    echo "- Unidades de medida: {$check_units} (deve ser 0)\n";
    echo "- Categorias múltiplas: {$check_categories} (deve ser 0)\n";
    echo "- Food_types diferentes de 'granular': {$check_food_types} (deve ser 0)\n\n";
    
    if ($check_units == 0 && $check_categories == 0 && $check_food_types == 0) {
        echo "🎉 SISTEMA COMPLETAMENTE LIMPO!\n";
        echo "✅ Agora NENHUM alimento tem unidades de medida\n";
        echo "✅ Agora NENHUM alimento tem categorias\n";
        echo "✅ Agora TODOS os alimentos estão como 'granular' (padrão)\n\n";
        echo "🚀 AS ESTAGIÁRIAS PODEM COMEÇAR A CLASSIFICAR DO ZERO!\n";
        echo "📝 Cada alimento que elas classificarem vai aparecer no add_food_to_diary.php\n";
        echo "📝 Alimentos não classificados NÃO vão aparecer\n";
    } else {
        echo "❌ ALGO DEU ERRADO! Verifique os números acima.\n";
    }
    
    $conn->commit();
    
} catch (Exception $e) {
    $conn->rollback();
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}

echo "\n=== RESET COMPLETO FINALIZADO ===\n";
$conn->close();
?>
