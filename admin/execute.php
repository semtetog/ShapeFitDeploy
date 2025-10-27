<?php
// Script para executar alterações no banco de dados
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth_admin.php';

requireAdminLogin();

try {
    // Adicionar campos para quantidade e unidade na tabela de ingredientes
    $sql = "ALTER TABLE sf_recipe_ingredients 
            ADD COLUMN quantity_value DECIMAL(10,2) DEFAULT NULL AFTER ingredient_description,
            ADD COLUMN quantity_unit VARCHAR(50) DEFAULT NULL AFTER quantity_value";
    
    if ($conn->query($sql)) {
        echo "✅ Campos adicionados com sucesso na tabela sf_recipe_ingredients!<br>";
    } else {
        echo "❌ Erro ao adicionar campos: " . $conn->error . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "<br>";
}

echo "<br><a href='recipes.php'>← Voltar para Receitas</a>";
?>
