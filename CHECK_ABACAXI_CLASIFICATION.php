<?php
// CHECK_ABACAXI_CLASSIFICATION.php - Verificar classificação real do Abacaxi

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "=== VERIFICANDO CLASSIFICAÇÃO REAL DO ABACAXI ===\n\n";

// Buscar Abacaxi
$food_sql = "SELECT id, name_pt, food_type FROM sf_food_items WHERE name_pt = 'Abacaxi' LIMIT 1";
$result = $conn->query($food_sql);
if ($food = $result->fetch_assoc()) {
    echo "🍍 Alimento: {$food['name_pt']} (ID: {$food['id']})\n";
    echo "📋 Food Type (primário): {$food['food_type']}\n";
    
    // Buscar categorias múltiplas
    $categories_sql = "SELECT category_type, is_primary FROM sf_food_categories WHERE food_id = ? ORDER BY is_primary DESC, category_type ASC";
    $stmt = $conn->prepare($categories_sql);
    $stmt->bind_param("i", $food['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "\n📝 Categorias múltiplas:\n";
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "- {$row['category_type']} (" . ($row['is_primary'] ? 'PRIMÁRIA' : 'SECUNDÁRIA') . ")\n";
        }
    } else {
        echo "❌ Nenhuma categoria múltipla encontrada!\n";
    }
    
    // Verificar unidades atuais
    $units_sql = "SELECT sfic.*, smu.name, smu.abbreviation 
                  FROM sf_food_item_conversions sfic 
                  JOIN sf_measurement_units smu ON sfic.unit_id = smu.id 
                  WHERE sfic.food_item_id = ?";
    $stmt = $conn->prepare($units_sql);
    $stmt->bind_param("i", $food['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "\n🔍 Unidades atuais:\n";
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "- {$row['name']} ({$row['abbreviation']}) - Factor: {$row['conversion_factor']} " . ($row['is_default'] ? '(PADRÃO)' : '') . "\n";
        }
    } else {
        echo "❌ Nenhuma unidade encontrada!\n";
    }
    
} else {
    echo "❌ Abacaxi não encontrado!\n";
}

echo "\n=== VERIFICAÇÃO CONCLUÍDA ===\n";
$conn->close();
?>
