<?php
// debug_restrictions.php - Verificar se as tabelas de restrições existem

require_once 'includes/config.php';
$conn = require 'includes/db.php';

echo "<h2>Debug - Verificação das Tabelas de Restrições</h2>";

// Verificar se as tabelas existem
$tables_to_check = [
    'sf_user_selected_restrictions',
    'sf_dietary_restrictions_options'
];

foreach ($tables_to_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✅ Tabela '$table' existe</p>";
        
        // Mostrar estrutura da tabela
        $structure = $conn->query("DESCRIBE $table");
        echo "<h4>Estrutura da tabela $table:</h4>";
        echo "<table border='1'><tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $structure->fetch_assoc()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td><td>{$row['Default']}</td></tr>";
        }
        echo "</table>";
        
        // Mostrar dados da tabela
        $data = $conn->query("SELECT * FROM $table LIMIT 10");
        echo "<h4>Dados da tabela $table (primeiros 10 registros):</h4>";
        if ($data->num_rows > 0) {
            echo "<table border='1'>";
            $first_row = true;
            while ($row = $data->fetch_assoc()) {
                if ($first_row) {
                    echo "<tr>";
                    foreach (array_keys($row) as $header) {
                        echo "<th>$header</th>";
                    }
                    echo "</tr>";
                    $first_row = false;
                }
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>$value</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'>⚠️ Tabela vazia</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Tabela '$table' NÃO existe</p>";
    }
    echo "<br>";
}

// Verificar se há dados de restrições para o usuário atual
if (isset($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
    echo "<h3>Verificando restrições para usuário ID: $user_id</h3>";
    
    $stmt = $conn->prepare("SELECT restriction_id FROM sf_user_selected_restrictions WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $restrictions = [];
        while ($row = $result->fetch_assoc()) {
            $restrictions[] = $row['restriction_id'];
        }
        $stmt->close();
        
        echo "<p>Restrições encontradas para o usuário: " . implode(', ', $restrictions) . "</p>";
    } else {
        echo "<p style='color: red;'>Erro ao preparar query: " . $conn->error . "</p>";
    }
}

echo "<hr>";
echo "<p><a href='edit_profile.php'>Voltar ao Edit Profile</a></p>";
echo "<p><strong>Para testar com seu usuário:</strong> <a href='debug_restrictions.php?user_id=75'>debug_restrictions.php?user_id=75</a></p>";
?>