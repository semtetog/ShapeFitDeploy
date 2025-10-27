<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth_admin.php';

requireAdminLogin();

try {
    // Remover coluna total_weight_g
    $sql = "ALTER TABLE sf_recipes DROP COLUMN total_weight_g";
    
    if ($conn->query($sql)) {
        echo "✅ Coluna total_weight_g removida com sucesso da tabela sf_recipes!<br>";
        echo "✅ Sistema simplificado para usar apenas serving_size_g<br>";
    } else {
        echo "❌ Erro ao remover coluna: " . $conn->error . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}
?>
