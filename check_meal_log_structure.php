<?php
// Script para verificar estrutura da tabela sf_user_meal_log

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "<h2>Estrutura da tabela sf_user_meal_log</h2>";

$result = $conn->query("DESCRIBE sf_user_meal_log");

if ($result) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Chave</th><th>Padrão</th><th>Extra</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "Erro ao consultar estrutura da tabela: " . $conn->error;
}

$conn->close();
?>
