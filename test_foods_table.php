<?php
// Script para testar se há dados na tabela sf_food_items

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "<h2>Teste da Tabela sf_food_items</h2>";

// Verificar se a tabela existe
$result = $conn->query("SHOW TABLES LIKE 'sf_food_items'");
if ($result->num_rows == 0) {
    echo "<p style='color: red;'>❌ Tabela sf_food_items não existe!</p>";
    exit;
}

echo "<p style='color: green;'>✅ Tabela sf_food_items existe</p>";

// Verificar estrutura da tabela
echo "<h3>Estrutura da tabela:</h3>";
$result = $conn->query("DESCRIBE sf_food_items");
echo "<table border='1'>";
echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Chave</th><th>Padrão</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Contar registros
$result = $conn->query("SELECT COUNT(*) as total FROM sf_food_items");
$count = $result->fetch_assoc()['total'];
echo "<p><strong>Total de registros:</strong> " . $count . "</p>";

if ($count == 0) {
    echo "<p style='color: red;'>❌ Nenhum registro na tabela sf_food_items!</p>";
} else {
    echo "<p style='color: green;'>✅ " . $count . " registros encontrados</p>";
    
    // Mostrar alguns registros
    echo "<h3>Primeiros 5 registros:</h3>";
    $result = $conn->query("SELECT id, name, kcal_per_serving, protein_g_per_serving FROM sf_food_items LIMIT 5");
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Kcal</th><th>Proteína</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['name'] . "</td>";
        echo "<td>" . $row['kcal_per_serving'] . "</td>";
        echo "<td>" . $row['protein_g_per_serving'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Testar busca
    echo "<h3>Teste de busca por 'arroz':</h3>";
    $stmt = $conn->prepare("SELECT id, name, kcal_per_serving, protein_g_per_serving FROM sf_food_items WHERE name LIKE ? LIMIT 5");
    $search_term = '%arroz%';
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✅ Encontrados " . $result->num_rows . " resultados para 'arroz'</p>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Nome</th><th>Kcal</th><th>Proteína</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['name'] . "</td>";
            echo "<td>" . $row['kcal_per_serving'] . "</td>";
            echo "<td>" . $row['protein_g_per_serving'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ Nenhum resultado para 'arroz'</p>";
    }
    $stmt->close();
}

$conn->close();
?>
