<?php
// create_multiple_categories_table.php - Criar tabela para múltiplas categorias

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "=== CRIANDO SISTEMA DE MÚLTIPLAS CATEGORIAS ===\n\n";

try {
    // Criar tabela para múltiplas categorias
    $sql = "CREATE TABLE IF NOT EXISTS sf_food_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        food_id INT NOT NULL,
        category_type ENUM('líquido', 'semi_liquido', 'granular', 'unidade_inteira', 'fatias_pedacos', 'corte_porcao', 'colher_cremoso', 'condimentos', 'oleos_gorduras', 'preparacoes_compostas') NOT NULL,
        is_primary BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (food_id) REFERENCES sf_food_items(id) ON DELETE CASCADE,
        UNIQUE KEY unique_food_category (food_id, category_type)
    )";
    
    if ($conn->query($sql)) {
        echo "✅ Tabela 'sf_food_categories' criada com sucesso!\n";
    } else {
        echo "❌ Erro ao criar tabela: " . $conn->error . "\n";
        exit(1);
    }
    
    // Migrar dados existentes da coluna food_type
    echo "\n=== MIGRANDO DADOS EXISTENTES ===\n";
    
    $migrate_sql = "SELECT id, food_type FROM sf_food_items WHERE food_type IS NOT NULL AND food_type != ''";
    $result = $conn->query($migrate_sql);
    $migrated = 0;
    
    while ($row = $result->fetch_assoc()) {
        $insert_sql = "INSERT INTO sf_food_categories (food_id, category_type, is_primary) VALUES (?, ?, TRUE) 
                      ON DUPLICATE KEY UPDATE is_primary = TRUE";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("is", $row['id'], $row['food_type']);
        
        if ($stmt->execute()) {
            $migrated++;
        }
    }
    
    echo "✅ {$migrated} alimentos migrados para o novo sistema!\n";
    
    // Criar índices para performance
    $index_sql = "CREATE INDEX idx_food_categories_food_id ON sf_food_categories(food_id)";
    $conn->query($index_sql);
    
    $index_sql2 = "CREATE INDEX idx_food_categories_category ON sf_food_categories(category_type)";
    $conn->query($index_sql2);
    
    echo "✅ Índices criados para melhor performance!\n";
    
    echo "\n=== SISTEMA DE MÚLTIPLAS CATEGORIAS CRIADO! ===\n";
    echo "Agora cada alimento pode ter múltiplas categorias!\n";
    echo "A categoria primária é marcada com is_primary = TRUE\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
?>
