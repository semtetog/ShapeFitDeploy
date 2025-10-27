<?php
// Teste direto da API

echo "<h2>Teste Direto da API</h2>";

// Simular parâmetros GET
$_GET['term'] = 'arroz';
$_GET['type'] = 'foods';

// Simular sessão
session_start();
$_SESSION['user_id'] = 1;

echo "<h3>Testando API com termo 'arroz' e tipo 'foods':</h3>";

// Capturar output da API
ob_start();
include 'api/ajax_search_foods_recipes.php';
$api_output = ob_get_clean();

echo "<h4>Saída da API:</h4>";
echo "<pre>" . htmlspecialchars($api_output) . "</pre>";

// Tentar decodificar JSON
$data = json_decode($api_output, true);
if ($data) {
    echo "<h4>JSON Decodificado:</h4>";
    echo "<pre>" . print_r($data, true) . "</pre>";
    
    if (isset($data['success']) && $data['success']) {
        echo "<p style='color: green;'>✅ API retornou sucesso</p>";
        echo "<p><strong>Total de resultados:</strong> " . count($data['data']) . "</p>";
    } else {
        echo "<p style='color: red;'>❌ API retornou erro: " . ($data['message'] ?? 'Erro desconhecido') . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Resposta não é JSON válido</p>";
}
?>
