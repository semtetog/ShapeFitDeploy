<?php
/**
 * Teste final da API
 * Acesse: https://appshapefit.com/testar_api_final.php
 */

echo "<h2>🧪 TESTE FINAL DA API</h2>";

// Teste 1: API sem autenticação
echo "<h3>🔍 Teste 1: API sem autenticação</h3>";
$api_url1 = "https://appshapefit.com/test_api_sem_auth.php?term=whey";
echo "<p><strong>URL:</strong> <a href='$api_url1' target='_blank'>$api_url1</a></p>";

$ch1 = curl_init();
curl_setopt($ch1, CURLOPT_URL, $api_url1);
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch1, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch1, CURLOPT_TIMEOUT, 10);

$response1 = curl_exec($ch1);
$http_code1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
curl_close($ch1);

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<p><strong>HTTP Code:</strong> $http_code1</p>";
echo "<p><strong>Resposta:</strong></p>";
echo "<pre style='background: #e9ecef; padding: 10px; border-radius: 4px; overflow-x: auto; max-height: 300px;'>";
echo htmlspecialchars($response1);
echo "</pre>";
echo "</div>";

// Teste 2: API com autenticação (simulando login)
echo "<h3>🔍 Teste 2: API com autenticação</h3>";
$api_url2 = "https://appshapefit.com/api/ajax_search_food.php?term=whey";
echo "<p><strong>URL:</strong> <a href='$api_url2' target='_blank'>$api_url2</a></p>";

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, $api_url2);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
curl_setopt($ch2, CURLOPT_COOKIE, 'PHPSESSID=test'); // Simula sessão

$response2 = curl_exec($ch2);
$http_code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<p><strong>HTTP Code:</strong> $http_code2</p>";
echo "<p><strong>Resposta:</strong></p>";
echo "<pre style='background: #e9ecef; padding: 10px; border-radius: 4px; overflow-x: auto; max-height: 300px;'>";
echo htmlspecialchars($response2);
echo "</pre>";
echo "</div>";

// Teste 3: Verificar se os dados estão corretos
echo "<h3>🔍 Teste 3: Verificação dos dados</h3>";
if ($http_code1 == 200) {
    $data = json_decode($response1, true);
    if ($data && isset($data['data'])) {
        echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h4>✅ Dados encontrados:</h4>";
        echo "<p><strong>Total de resultados:</strong> " . count($data['data']) . "</p>";
        
        if (count($data['data']) > 0) {
            echo "<h5>Primeiros 3 resultados:</h5>";
            for ($i = 0; $i < min(3, count($data['data'])); $i++) {
                $item = $data['data'][$i];
                echo "<div style='padding: 8px; border-bottom: 1px solid #dee2e6;'>";
                echo "<strong>Nome:</strong> " . htmlspecialchars($item['name']) . "<br>";
                echo "<strong>Marca:</strong> " . htmlspecialchars($item['brand']) . "<br>";
                echo "<strong>Calorias:</strong> " . $item['kcal_100g'] . " kcal<br>";
                echo "</div>";
            }
        }
        echo "</div>";
    } else {
        echo "<p>❌ Erro ao decodificar JSON</p>";
    }
} else {
    echo "<p>❌ API não funcionou (HTTP $http_code1)</p>";
}

echo "<hr>";
echo "<h3>🎯 DIAGNÓSTICO FINAL:</h3>";

if ($http_code1 == 200 && $response1) {
    echo "<p style='color: green; font-weight: bold;'>✅ API FUNCIONANDO! Os dados estão corretos.</p>";
    echo "<p><strong>Problema:</strong> A API original requer autenticação, mas o frontend não está enviando a sessão corretamente.</p>";
    echo "<p><strong>Solução:</strong> Precisamos corrigir a autenticação ou usar a API sem autenticação.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ API NÃO FUNCIONANDO!</p>";
    echo "<p><strong>Problema:</strong> A API não está retornando dados corretos.</p>";
    echo "<p><strong>Solução:</strong> Verificar se os arquivos foram uploadados corretamente.</p>";
}

echo "<h3>🚀 PRÓXIMOS PASSOS:</h3>";
echo "<ol>";
echo "<li><strong>Se a API sem autenticação funcionou:</strong> Usar <code>test_api_sem_auth.php</code> como base</li>";
echo "<li><strong>Se a API com autenticação não funcionou:</strong> Corrigir o sistema de autenticação</li>";
echo "<li><strong>Testar no frontend:</strong> Verificar se o JavaScript está chamando a API correta</li>";
echo "</ol>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h2 { color: #333; }
h3 { color: #666; }
h4 { color: #888; }
h5 { color: #aaa; }
pre { font-size: 12px; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
