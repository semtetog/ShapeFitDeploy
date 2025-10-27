<?php
/**
 * Script para testar se as marcas estão sendo exibidas corretamente
 * Acesse: https://appshapefit.com/testar_marcas.php
 */

// ✅ CREDENCIAIS DO BANCO
$host = '127.0.0.1:3306';
$dbname = 'u785537399_shapefit';
$username = 'u785537399_shapefit';
$password = 'Gameroficial2*';

echo "<h2>🧪 Testando Exibição de Marcas</h2>";

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

// 1. Testa busca AJAX simulada
echo "<h3>🔍 Teste 1: Simulação da Busca AJAX</h3>";
$term = 'whey';
$local_term = '%' . $term . '%';
$start_term = $term . '%';

$sql_local = "SELECT taco_id, name_pt, brand, energy_kcal_100g, protein_g_100g, carbohydrate_g_100g, fat_g_100g FROM sf_food_items WHERE name_pt LIKE ? ORDER BY CASE WHEN name_pt LIKE ? THEN 1 ELSE 2 END, LENGTH(name_pt), name_pt LIMIT 5";

$stmt_local = $conn->prepare($sql_local);
if ($stmt_local) {
    $stmt_local->bind_param("ss", $local_term, $start_term);
    $stmt_local->execute();
    $result_set = $stmt_local->get_result();

    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h4>Resultados da busca por '$term':</h4>";
    
    while ($row = $result_set->fetch_assoc()) {
        // Formata o nome com a marca se disponível
        $display_name = $row['name_pt'];
        if (!empty($row['brand']) && $row['brand'] !== 'TACO') {
            $display_name = $row['name_pt'] . ' - ' . $row['brand'];
        }
        
        echo "<div style='padding: 8px; border-bottom: 1px solid #dee2e6;'>";
        echo "<strong>Nome:</strong> " . htmlspecialchars($display_name) . "<br>";
        echo "<strong>Marca:</strong> " . htmlspecialchars($row['brand'] ?: 'TACO') . "<br>";
        echo "<strong>Calorias:</strong> " . $row['energy_kcal_100g'] . " kcal<br>";
        echo "</div>";
    }
    echo "</div>";
    $stmt_local->close();
}

// 2. Testa produtos FatSecret
echo "<h3>🏷️ Teste 2: Produtos FatSecret com Marca</h3>";
$query_fatsecret = "SELECT name_pt, brand, source_table FROM sf_food_items WHERE source_table = 'FatSecret' AND brand IS NOT NULL AND brand != '' LIMIT 10";
$result_fatsecret = $conn->query($query_fatsecret);

if ($result_fatsecret->num_rows > 0) {
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h4>✅ Produtos FatSecret com marca:</h4>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Nome</th><th>Marca</th><th>Fonte</th></tr>";
    while ($row = $result_fatsecret->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['name_pt']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($row['brand']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['source_table']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
} else {
    echo "<p>❌ Nenhum produto FatSecret com marca encontrado!</p>";
}

// 3. Testa produtos sem marca
echo "<h3>⚠️ Teste 3: Produtos FatSecret SEM Marca</h3>";
$query_sem_marca = "SELECT name_pt, brand, source_table FROM sf_food_items WHERE source_table = 'FatSecret' AND (brand IS NULL OR brand = '') LIMIT 5";
$result_sem_marca = $conn->query($query_sem_marca);

if ($result_sem_marca->num_rows > 0) {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
    echo "<h4>⚠️ Produtos FatSecret SEM marca:</h4>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Nome</th><th>Marca</th><th>Fonte</th></tr>";
    while ($row = $result_sem_marca->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['name_pt']) . "</td>";
        echo "<td style='color: red;'>" . ($row['brand'] ?: 'VAZIO') . "</td>";
        echo "<td>" . htmlspecialchars($row['source_table']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
} else {
    echo "<p>✅ Todos os produtos FatSecret têm marca!</p>";
}

$conn->close();

echo "<hr>";
echo "<h3>🎯 Próximos Passos:</h3>";
echo "<ol>";
echo "<li><strong>Teste a busca no app:</strong> Acesse o app e pesquise por 'whey' ou 'protein'</li>";
echo "<li><strong>Verifique o admin:</strong> <a href='admin/foods_management_new.php?source=FatSecret' target='_blank'>Ver alimentos FatSecret no admin</a></li>";
echo "<li><strong>Se ainda não aparecer:</strong> Execute <a href='corrigir_suplementos.php'>corrigir_suplementos.php</a> novamente</li>";
echo "</ol>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h2 { color: #333; }
h3 { color: #666; }
h4 { color: #888; }
table { background: white; margin: 10px 0; }
th, td { padding: 8px; text-align: left; }
th { background: #f8f9fa; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
