<?php
/**
 * Teste simples da API
 * Acesse: https://appshapefit.com/teste_simples.php
 */

echo "<h2>🧪 TESTE SIMPLES DA API</h2>";

// Teste direto da API
$api_url = "https://appshapefit.com/api/ajax_search_food.php?term=whey";
echo "<p><strong>Testando:</strong> <a href='$api_url' target='_blank'>$api_url</a></p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<p><strong>HTTP Code:</strong> $http_code</p>";
echo "<p><strong>Resposta:</strong></p>";
echo "<pre style='background: #e9ecef; padding: 10px; border-radius: 4px; overflow-x: auto; max-height: 400px;'>";
echo htmlspecialchars($response);
echo "</pre>";
echo "</div>";

if ($http_code == 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['data']) && count($data['data']) > 0) {
        echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
        echo "<h4>✅ SUCESSO! API funcionando!</h4>";
        echo "<p><strong>Total de resultados:</strong> " . count($data['data']) . "</p>";
        
        echo "<h5>Primeiros 5 resultados:</h5>";
        for ($i = 0; $i < min(5, count($data['data'])); $i++) {
            $item = $data['data'][$i];
            echo "<div style='padding: 8px; border-bottom: 1px solid #dee2e6; background: white; margin: 5px 0; border-radius: 4px;'>";
            echo "<strong>Nome:</strong> " . htmlspecialchars($item['name']) . "<br>";
            echo "<strong>Marca:</strong> " . htmlspecialchars($item['brand']) . "<br>";
            echo "<strong>Calorias:</strong> " . $item['kcal_100g'] . " kcal<br>";
            echo "</div>";
        }
        echo "</div>";
        
        echo "<p style='color: green; font-weight: bold; font-size: 18px;'>🎉 PERFEITO! As marcas estão aparecendo!</p>";
        echo "<p><strong>Próximo passo:</strong> Teste no app real para ver se as marcas aparecem na busca!</p>";
    } else {
        echo "<p>❌ API retornou dados vazios</p>";
    }
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ API não funcionou (HTTP $http_code)</p>";
    echo "<p><strong>Problema:</strong> A API ainda não está funcionando corretamente.</p>";
    echo "<p><strong>Solução:</strong> Verificar se o arquivo foi uploadado corretamente.</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h2 { color: #333; }
h4 { color: #666; }
h5 { color: #888; }
pre { font-size: 12px; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
