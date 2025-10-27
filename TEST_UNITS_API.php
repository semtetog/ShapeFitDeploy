<?php
// TEST_UNITS_API.php - Testar a API de unidades

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "🧪 TESTANDO API DE UNIDADES 🧪\n\n";

// Buscar um alimento para testar
$test_food = $conn->query("SELECT id, name_pt FROM sf_food_items LIMIT 1")->fetch_assoc();
if ($test_food) {
    echo "Testando alimento: {$test_food['name_pt']} (ID: {$test_food['id']})\n";
    
    // Simular a chamada da API
    $food_id_string = 'taco_' . $test_food['id'];
    echo "Food ID String: {$food_id_string}\n";
    
    // Extrair ID do alimento (mesmo código da API)
    $food_db_id = null;
    $id_parts = explode('_', $food_id_string, 2);
    if (count($id_parts) === 2) {
        $prefix = $id_parts[0];
        $identifier = $id_parts[1];

        if ($prefix === 'taco' && is_numeric($identifier)) {
            $stmt_find = $conn->prepare("SELECT id FROM sf_food_items WHERE taco_id = ? LIMIT 1");
            if ($stmt_find) {
                $stmt_find->bind_param("s", $identifier);
                $stmt_find->execute();
                $stmt_find->bind_result($found_id);
                if ($stmt_find->fetch()) {
                    $food_db_id = $found_id;
                }
                $stmt_find->close();
            }
        }
    }
    
    echo "Food DB ID encontrado: {$food_db_id}\n";
    
    $units = [];
    if ($food_db_id) {
        // Buscar unidades do alimento (mesmo código da API)
        $units_sql = "SELECT fu.*, u.abbreviation, u.name as unit_name 
                      FROM sf_food_units fu 
                      JOIN sf_units u ON fu.unit_id = u.id 
                      WHERE fu.food_id = ? 
                      ORDER BY fu.is_default DESC, u.abbreviation";
        $stmt = $conn->prepare($units_sql);
        $stmt->bind_param("i", $food_db_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $units[] = [
                'abbreviation' => $row['abbreviation'],
                'name' => $row['unit_name'],
                'factor' => $row['factor'],
                'unit' => $row['unit'],
                'is_default' => (bool)$row['is_default']
            ];
        }
        $stmt->close();
    }
    
    echo "Unidades encontradas: " . count($units) . "\n";
    
    if (empty($units)) {
        echo "✅ CORRETO: Nenhuma unidade encontrada (alimento não classificado)\n";
        echo "Resposta da API seria: " . json_encode(['success' => true, 'data' => []]) . "\n";
    } else {
        echo "❌ PROBLEMA: Encontrou unidades mesmo após reset!\n";
        foreach ($units as $unit) {
            echo "  - {$unit['abbreviation']} ({$unit['name']})\n";
        }
    }
}

echo "\n=== TESTE CONCLUÍDO ===\n";
$conn->close();
?>
