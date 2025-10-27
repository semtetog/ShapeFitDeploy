<?php
// TEST_API_RESPONSE.php - Testar resposta da API

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "🧪 TESTANDO RESPOSTA DA API 🧪\n\n";

// Simular chamada da API para um alimento
$food_id_string = 'taco_1'; // Arroz integral cozido
echo "Testando com food_id: {$food_id_string}\n\n";

// Extrair ID do alimento (mesmo código da API)
$food_db_id = null;
$id_parts = explode('_', $food_id_string, 2);
if (count($id_parts) === 2) {
    $prefix = $id_parts[0];
    $identifier = $id_parts[1];

    if ($prefix === 'taco' && is_numeric($identifier)) {
        $stmt_find = $conn->prepare("SELECT id FROM sf_food_items WHERE taco_id = ? LIMIT 1");
    } elseif ($prefix === 'off' && is_numeric($identifier)) {
        $stmt_find = $conn->prepare("SELECT id FROM sf_food_items WHERE barcode = ? LIMIT 1");
    } else {
        $stmt_find = false;
    }
    
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

echo "Food DB ID encontrado: {$food_db_id}\n\n";

$units = [];

if ($food_db_id) {
    // Buscar unidades ESPECÍFICAS do alimento (mesmo código da API)
    $units_sql = "SELECT fu.*, mu.name as unit_name, mu.abbreviation, mu.conversion_factor, mu.conversion_unit
                  FROM sf_food_units fu 
                  JOIN sf_measurement_units mu ON fu.unit_id = mu.id 
                  WHERE fu.food_id = ? 
                  ORDER BY fu.is_default DESC, mu.abbreviation";
    $stmt = $conn->prepare($units_sql);
    $stmt->bind_param("i", $food_db_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $units[] = [
            'abbreviation' => $row['abbreviation'],
            'name' => $row['unit_name'],
            'factor' => $row['conversion_factor'],
            'unit' => $row['conversion_unit'],
            'is_default' => (bool)$row['is_default']
        ];
    }
    $stmt->close();
}

echo "Unidades encontradas: " . count($units) . "\n";

if (empty($units)) {
    echo "✅ CORRETO: Array vazio - API retornaria: " . json_encode(['success' => true, 'data' => []]) . "\n";
    echo "✅ JavaScript deveria mostrar mensagem de 'não classificado'\n";
} else {
    echo "❌ PROBLEMA: Encontrou unidades mesmo após reset!\n";
    foreach ($units as $unit) {
        echo "  - {$unit['abbreviation']} ({$unit['name']})\n";
    }
}

echo "\n=== VERIFICANDO TABELA sf_food_units ===\n";
$check_sql = "SELECT COUNT(*) as count FROM sf_food_units WHERE food_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $food_db_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$count = $check_result->fetch_assoc()['count'];
echo "Registros na sf_food_units para este alimento: {$count}\n";

echo "\n=== TESTE CONCLUÍDO ===\n";
$conn->close();
?>
