<?php
// DEBUG_FULL_FLOW.php - Debug completo do fluxo

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "🔍 DEBUG COMPLETO DO FLUXO 🔍\n\n";

echo "=== 1. VERIFICAR TABELAS ===\n";
$check_food_units = $conn->query("SELECT COUNT(*) as count FROM sf_food_units")->fetch_assoc()['count'];
$check_measurement_units = $conn->query("SELECT COUNT(*) as count FROM sf_measurement_units")->fetch_assoc()['count'];
echo "sf_food_units: {$check_food_units} registros\n";
echo "sf_measurement_units: {$check_measurement_units} registros\n\n";

echo "=== 2. VERIFICAR API ajax_get_food_units.php ===\n";
$api_content = file_get_contents('api/ajax_get_food_units.php');
if (strpos($api_content, 'sf_measurement_units') !== false) {
    echo "❌ PROBLEMA: API está buscando na tabela sf_measurement_units!\n";
    echo "A API deveria buscar APENAS na tabela sf_food_units\n";
} else {
    echo "✅ OK: API NÃO está buscando na tabela sf_measurement_units\n";
}

if (strpos($api_content, 'sf_food_units') !== false) {
    echo "✅ OK: API está buscando na tabela sf_food_units\n";
} else {
    echo "❌ PROBLEMA: API NÃO está buscando na tabela sf_food_units!\n";
}

echo "\n=== 3. VERIFICAR JavaScript add_food_logic.js ===\n";
$js_content = file_get_contents('assets/js/add_food_logic.js');
if (strpos($js_content, 'showNoUnitsMessage') !== false) {
    echo "✅ OK: JavaScript tem função showNoUnitsMessage\n";
} else {
    echo "❌ PROBLEMA: JavaScript NÃO tem função showNoUnitsMessage!\n";
}

if (strpos($js_content, 'loadFoodUnits') !== false) {
    echo "✅ OK: JavaScript tem função loadFoodUnits\n";
} else {
    echo "❌ PROBLEMA: JavaScript NÃO tem função loadFoodUnits!\n";
}

if (strpos($js_content, 'useDefaultUnits') !== false) {
    echo "❌ PROBLEMA: JavaScript está usando unidades hardcoded como fallback!\n";
    echo "Isso está causando o problema!\n";
} else {
    echo "✅ OK: JavaScript NÃO está usando unidades hardcoded como fallback\n";
}

echo "\n=== 4. TESTE REAL DA API ===\n";
echo "Simulando chamada da API para food_id=taco_1...\n";

$_SESSION['user_id'] = 1; // Simular usuário logado
$_GET['food_id'] = 'taco_1';

ob_start();
include 'api/ajax_get_food_units.php';
$api_response = ob_get_clean();

echo "Resposta da API: {$api_response}\n";

$decoded = json_decode($api_response, true);
if ($decoded && isset($decoded['data']) && empty($decoded['data'])) {
    echo "✅ OK: API retornou array vazio (correto)\n";
} else {
    echo "❌ PROBLEMA: API retornou dados inesperados!\n";
}

echo "\n=== DIAGNÓSTICO COMPLETO ===\n";
$conn->close();
?>
