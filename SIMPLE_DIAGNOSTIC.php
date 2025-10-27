<?php
// SIMPLE_DIAGNOSTIC.php - Diagnóstico simples do problema

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "🔍 DIAGNÓSTICO SIMPLES 🔍\n\n";

echo "=== 1. VERIFICAR TABELAS ===\n";
$units_count = $conn->query("SELECT COUNT(*) as c FROM sf_food_units")->fetch_assoc()['c'];
echo "sf_food_units: {$units_count} registros " . ($units_count == 0 ? "✅" : "❌") . "\n\n";

echo "=== 2. TESTE SIMPLES ===\n";
echo "Quando você busca um alimento no add_food_to_diary.php e clica nele:\n";
echo "- Você vê opções de unidades (ml, g, cs, etc)? SIM ou NÃO?\n";
echo "- Ou aparece a mensagem 'Este alimento ainda não foi classificado'?\n\n";

echo "=== 3. PRÓXIMOS PASSOS ===\n";
echo "Por favor, teste agora:\n";
echo "1. Vá em appshapefit.com/add_food_to_diary.php\n";
echo "2. Busque por 'arroz'\n";
echo "3. Clique em um resultado\n";
echo "4. Me diga EXATAMENTE o que aparece na tela\n";
echo "5. Tire um print se possível\n\n";

echo "💡 IMPORTANTE:\n";
echo "- Se aparecer unidades: há um bug no JavaScript\n";
echo "- Se aparecer a mensagem de não classificado: está funcionando correto!\n";

$conn->close();
?>
