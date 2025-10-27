<?php
// add_food_type_column.php - Script para adicionar coluna food_type na tabela sf_food_items

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "=== ADICIONANDO COLUNA FOOD_TYPE ===\n\n";

try {
    // Adicionar coluna food_type se não existir
    $sql = "ALTER TABLE sf_food_items ADD COLUMN food_type ENUM('líquido', 'granular', 'fruta', 'proteina') DEFAULT 'granular' AFTER food_group_pt";
    
    if ($conn->query($sql)) {
        echo "✅ Coluna 'food_type' adicionada com sucesso!\n";
    } else {
        // Verificar se a coluna já existe
        $check_sql = "SHOW COLUMNS FROM sf_food_items LIKE 'food_type'";
        $result = $conn->query($check_sql);
        
        if ($result->num_rows > 0) {
            echo "ℹ️  Coluna 'food_type' já existe.\n";
        } else {
            echo "❌ Erro ao adicionar coluna: " . $conn->error . "\n";
            exit(1);
        }
    }
    
    echo "\n=== VERIFICAÇÃO ===\n";
    
    // Verificar estrutura da tabela
    $desc_sql = "DESCRIBE sf_food_items";
    $result = $conn->query($desc_sql);
    
    echo "Colunas da tabela sf_food_items:\n";
    while ($row = $result->fetch_assoc()) {
        if ($row['Field'] === 'food_type') {
            echo "✅ {$row['Field']} - {$row['Type']} - {$row['Default']}\n";
        }
    }
    
    echo "\n✅ Processo concluído!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
?>
