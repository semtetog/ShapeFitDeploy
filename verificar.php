<?php
/**
 * Script para corrigir suplementos existentes
 * Acesse: https://appshapefit.com/corrigir_suplementos.php
 */

// ✅ CREDENCIAIS DO BANCO
$host = '127.0.0.1:3306';
$dbname = 'u785537399_shapefit';
$username = 'u785537399_shapefit';
$password = 'Gameroficial2*';

echo "<h2>🔧 Corrigindo Suplementos do FatSecret</h2>";

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("❌ Erro de conexão: " . $conn->connect_error);
    }
    echo "<p>✅ Conectado ao banco de dados</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    die();
}

// Lê o arquivo JSON original
$json_file = 'suplementos_fatsecret_final_20251021_131514.json';

if (!file_exists($json_file)) {
    echo "<p>❌ Arquivo JSON não encontrado: $json_file</p>";
    die();
}

$json_content = file_get_contents($json_file);
$products = json_decode($json_content, true);

if (!$products) {
    echo "<p>❌ Erro ao decodificar JSON</p>";
    die();
}

echo "<p>📊 Total de produtos no JSON: " . count($products) . "</p>";

$updated = 0;
$not_found = 0;
$errors = 0;

// Prepara query de atualização
$update_sql = "UPDATE sf_food_items SET 
    brand = ?, 
    source_table = 'FatSecret',
    updated_at = NOW()
    WHERE name_pt = ? AND source_table = 'FatSecret'";

$stmt = $conn->prepare($update_sql);

if (!$stmt) {
    echo "<p>❌ Erro ao preparar query: " . $conn->error . "</p>";
    die();
}

echo "<p>🔄 Corrigindo produtos...</p>";

foreach ($products as $index => $product) {
    try {
        // Atualiza marca e fonte
        $stmt->bind_param("ss", $product['brand'], $product['name']);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                if ($index < 10) { // Mostra apenas os primeiros 10
                    echo "<p>✅ [CORRIGIDO] {$product['name']} - {$product['brand']}</p>";
                }
                $updated++;
            } else {
                $not_found++;
            }
        } else {
            echo "<p>❌ [ERRO] {$product['name']}: " . $stmt->error . "</p>";
            $errors++;
        }
        
    } catch (Exception $e) {
        echo "<p>❌ [ERRO] {$product['name']}: " . $e->getMessage() . "</p>";
        $errors++;
    }
    
    // Mostra progresso a cada 100 produtos
    if (($index + 1) % 100 == 0) {
        echo "<p>📊 Progresso: " . ($index + 1) . "/" . count($products) . " produtos processados</p>";
    }
}

$stmt->close();

echo "<hr>";
echo "<h3>📈 Resumo da Correção:</h3>";
echo "<p><strong>✅ Atualizados:</strong> $updated produtos</p>";
echo "<p><strong>⚠️ Não encontrados:</strong> $not_found produtos</p>";
echo "<p><strong>❌ Erros:</strong> $errors produtos</p>";

if ($updated > 0) {
    echo "<p style='color: green; font-weight: bold; font-size: 18px;'>🎉 CORREÇÃO CONCLUÍDA! $updated produtos corrigidos!</p>";
    echo "<p><a href='verificar_suplementos.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Verificar Resultado</a></p>";
}

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h2 { color: #333; }
h3 { color: #666; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
