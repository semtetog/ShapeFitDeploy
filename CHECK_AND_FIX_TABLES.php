<?php
// CHECK_AND_FIX_TABLES.php - Script para verificar e corrigir tabelas

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "=== VERIFICANDO E CORRIGINDO TABELAS ===\n\n";

// 1. Verificar se sf_food_item_conversions existe
$check_table_sql = "SHOW TABLES LIKE 'sf_food_item_conversions'";
$result = $conn->query($check_table_sql);

if ($result->num_rows > 0) {
    echo "✅ Tabela 'sf_food_item_conversions' existe\n";
    
    // Verificar estrutura
    echo "\n📋 Estrutura atual:\n";
    $describe_sql = "DESCRIBE sf_food_item_conversions";
    $result = $conn->query($describe_sql);
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['Field']}: {$row['Type']} " . ($row['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . " " . ($row['Key'] ? "({$row['Key']})" : '') . "\n";
    }
} else {
    echo "❌ Tabela 'sf_food_item_conversions' NÃO existe, criando...\n";
    
    // Criar tabela
    $create_table_sql = "
    CREATE TABLE `sf_food_item_conversions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `food_item_id` INT(11) NOT NULL,
        `unit_id` INT(11) NOT NULL,
        `conversion_factor` DECIMAL(10,4) NOT NULL,
        `is_default` BOOLEAN NOT NULL DEFAULT FALSE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_food_unit_unique` (`food_item_id`, `unit_id`),
        KEY `idx_food_item_conversions_food_id` (`food_item_id`),
        CONSTRAINT `fk_food_item_conversions_food_id` FOREIGN KEY (`food_item_id`) REFERENCES `sf_food_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_food_item_conversions_unit_id` FOREIGN KEY (`unit_id`) REFERENCES `sf_measurement_units` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    if ($conn->query($create_table_sql) === TRUE) {
        echo "✅ Tabela 'sf_food_item_conversions' criada com sucesso!\n";
    } else {
        echo "❌ Erro ao criar tabela: " . $conn->error . "\n";
    }
}

// 2. Verificar se sf_measurement_units existe
echo "\n=== VERIFICANDO sf_measurement_units ===\n";
$check_units_sql = "SHOW TABLES LIKE 'sf_measurement_units'";
$result = $conn->query($check_units_sql);

if ($result->num_rows > 0) {
    echo "✅ Tabela 'sf_measurement_units' existe\n";
    
    $count_units_sql = "SELECT COUNT(*) as total FROM sf_measurement_units WHERE is_active = TRUE";
    $result = $conn->query($count_units_sql);
    $count_units = $result->fetch_assoc()['total'];
    echo "📊 Total de unidades ativas: {$count_units}\n";
    
    if ($count_units > 0) {
        echo "\n📝 Primeiras 5 unidades ativas:\n";
        $sample_units_sql = "SELECT id, name, abbreviation, conversion_factor FROM sf_measurement_units WHERE is_active = TRUE LIMIT 5";
        $result = $conn->query($sample_units_sql);
        while ($row = $result->fetch_assoc()) {
            echo "- ID: {$row['id']}, Nome: {$row['name']}, Abrev: {$row['abbreviation']}, Factor: {$row['conversion_factor']}\n";
        }
    }
} else {
    echo "❌ Tabela 'sf_measurement_units' NÃO existe!\n";
}

// 3. Verificar se sf_food_categories existe
echo "\n=== VERIFICANDO sf_food_categories ===\n";
$check_categories_sql = "SHOW TABLES LIKE 'sf_food_categories'";
$result = $conn->query($check_categories_sql);

if ($result->num_rows > 0) {
    echo "✅ Tabela 'sf_food_categories' existe\n";
    
    $count_categories_sql = "SELECT COUNT(*) as total FROM sf_food_categories";
    $result = $conn->query($count_categories_sql);
    $count_categories = $result->fetch_assoc()['total'];
    echo "📊 Total de categorias: {$count_categories}\n";
} else {
    echo "❌ Tabela 'sf_food_categories' NÃO existe!\n";
}

// 4. Testar inserção de dados de exemplo
echo "\n=== TESTANDO INSERÇÃO DE DADOS ===\n";

// Buscar um alimento (Abacaxi)
$food_sql = "SELECT id, name_pt FROM sf_food_items WHERE name_pt LIKE '%Abacaxi%' LIMIT 1";
$result = $conn->query($food_sql);
if ($food = $result->fetch_assoc()) {
    echo "🍍 Alimento encontrado: {$food['name_pt']} (ID: {$food['id']})\n";
    
    // Buscar uma unidade (g)
    $unit_sql = "SELECT id, name, abbreviation FROM sf_measurement_units WHERE abbreviation = 'g' AND is_active = TRUE LIMIT 1";
    $result = $conn->query($unit_sql);
    if ($unit = $result->fetch_assoc()) {
        echo "📏 Unidade encontrada: {$unit['name']} ({$unit['abbreviation']}) (ID: {$unit['id']})\n";
        
        // Tentar inserir conversão de teste
        $test_sql = "INSERT INTO sf_food_item_conversions (food_item_id, unit_id, conversion_factor, is_default) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($test_sql);
        $stmt->bind_param("iidi", $food['id'], $unit['id'], 1.0, 1);
        
        if ($stmt->execute()) {
            echo "✅ Teste de inserção bem-sucedido!\n";
            
            // Verificar se foi inserido
            $check_sql = "SELECT COUNT(*) as count FROM sf_food_item_conversions WHERE food_item_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $food['id']);
            $check_stmt->execute();
            $count = $check_stmt->get_result()->fetch_assoc()['count'];
            echo "📊 Total de conversões para {$food['name_pt']}: {$count}\n";
        } else {
            echo "❌ Erro no teste de inserção: " . $stmt->error . "\n";
        }
    } else {
        echo "❌ Unidade 'g' não encontrada!\n";
    }
} else {
    echo "❌ Abacaxi não encontrado!\n";
}

echo "\n=== VERIFICAÇÃO CONCLUÍDA ===\n";
$conn->close();
?>
