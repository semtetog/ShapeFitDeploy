<?php
// CHECK_TABLES_STATUS.php - Verificar status das tabelas

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "🔍 VERIFICANDO STATUS DAS TABELAS 🔍\n\n";

// Verificar sf_food_units
$count_food_units = $conn->query("SELECT COUNT(*) as count FROM sf_food_units")->fetch_assoc()['count'];
echo "sf_food_units: {$count_food_units} registros\n";

// Verificar sf_food_categories  
$count_food_categories = $conn->query("SELECT COUNT(*) as count FROM sf_food_categories")->fetch_assoc()['count'];
echo "sf_food_categories: {$count_food_categories} registros\n";

// Verificar sf_food_items
$count_food_items = $conn->query("SELECT COUNT(*) as count FROM sf_food_items")->fetch_assoc()['count'];
echo "sf_food_items: {$count_food_items} registros\n";

// Verificar sf_units
$count_units = $conn->query("SELECT COUNT(*) as count FROM sf_units")->fetch_assoc()['count'];
echo "sf_units: {$count_units} registros\n";

echo "\n=== TESTE DE BUSCA ===\n";

// Testar busca de um alimento específico
$test_food = $conn->query("SELECT id, name_pt FROM sf_food_items LIMIT 1")->fetch_assoc();
if ($test_food) {
    echo "Testando alimento: {$test_food['name_pt']} (ID: {$test_food['id']})\n";
    
    // Verificar se tem unidades
    $units_sql = "SELECT fu.*, u.abbreviation, u.name as unit_name 
                  FROM sf_food_units fu 
                  JOIN sf_units u ON fu.unit_id = u.id 
                  WHERE fu.food_id = ?";
    $stmt = $conn->prepare($units_sql);
    $stmt->bind_param("i", $test_food['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $units_count = $result->num_rows;
    
    echo "Unidades encontradas para este alimento: {$units_count}\n";
    
    if ($units_count > 0) {
        echo "⚠️ PROBLEMA: Este alimento tem unidades mesmo após o reset!\n";
        while ($unit = $result->fetch_assoc()) {
            echo "  - {$unit['abbreviation']} ({$unit['unit_name']})\n";
        }
    } else {
        echo "✅ OK: Este alimento não tem unidades (correto após reset)\n";
    }
}

echo "\n=== VERIFICAÇÃO COMPLETA ===\n";
$conn->close();
?>
