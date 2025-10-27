<?php
// MASTER_CLEAN_AND_APPLY.php - Script master para limpar e aplicar unidades

echo "🚀 INICIANDO LIMPEZA E APLICAÇÃO DE UNIDADES 🚀\n\n";

// Passo 1: Limpar tudo
echo "=== PASSO 1: LIMPANDO TUDO ===\n";
require_once 'CLEAN_ALL_UNITS.php';

echo "\n" . str_repeat("=", 50) . "\n\n";

// Passo 2: Aplicar unidades baseado nas classificações
echo "=== PASSO 2: APLICANDO UNIDADES ===\n";
require_once 'APPLY_UNITS_FROM_CLASSIFICATION.php';

echo "\n" . str_repeat("=", 50) . "\n\n";

echo "🎉 SISTEMA LIMPO E APLICADO! 🎉\n\n";

echo "✨ O QUE FOI FEITO:\n";
echo "✅ Todas as unidades antigas removidas\n";
echo "✅ Todas as classificações resetadas\n";
echo "✅ Unidades aplicadas baseadas nas classificações atuais\n";
echo "✅ Sistema pronto para classificação manual\n\n";

echo "📋 PRÓXIMOS PASSOS:\n";
echo "1. Acesse: /admin/food_classification.php\n";
echo "2. Classifique os alimentos manualmente\n";
echo "3. Execute: php APPLY_UNITS_FROM_CLASSIFICATION.php\n";
echo "4. Teste no add_food_to_diary.php\n\n";

echo "🎯 AGORA AS ESTAGIÁRIAS PODEM CLASSIFICAR DO ZERO!\n";
echo "Sistema limpo e funcional!\n";
?>
