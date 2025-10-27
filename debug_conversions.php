<?php
// debug_conversions.php - Debug das conversões

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "=== DEBUG DAS CONVERSÕES ===\n\n";

// Buscar o abacate
$sql = "SELECT id, name_pt, food_type FROM sf_food_items WHERE name_pt LIKE '%abacate%' LIMIT 1";
$result = $conn->query($sql);
$abacate = $result->fetch_assoc();

if ($abacate) {
    echo "Abacate encontrado:\n";
    echo "ID: {$abacate['id']}\n";
    echo "Nome: {$abacate['name_pt']}\n";
    echo "Tipo: {$abacate['food_type']}\n\n";
    
    // Buscar conversões do abacate
    $conversions_sql = "SELECT fu.*, u.abbreviation, u.name as unit_name 
                        FROM sf_food_units fu 
                        JOIN sf_units u ON fu.unit_id = u.id 
                        WHERE fu.food_id = ? 
                        ORDER BY fu.is_default DESC, u.abbreviation";
    $stmt = $conn->prepare($conversions_sql);
    $stmt->bind_param("i", $abacate['id']);
    $stmt->execute();
    $conversions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "Conversões do abacate:\n";
    if (empty($conversions)) {
        echo "❌ NENHUMA CONVERSÃO ENCONTRADA!\n";
    } else {
        foreach ($conversions as $conv) {
            $default = $conv['is_default'] ? ' (PADRÃO)' : '';
            echo "- {$conv['abbreviation']} ({$conv['unit_name']}): {$conv['factor']}{$conv['unit']}{$default}\n";
        }
    }
} else {
    echo "❌ Abacate não encontrado!\n";
}

echo "\n=== VERIFICANDO UNIDADES DISPONÍVEIS ===\n";

// Buscar todas as unidades
$units_sql = "SELECT id, abbreviation, name FROM sf_units ORDER BY abbreviation";
$units_result = $conn->query($units_sql);
$units = $units_result->fetch_all(MYSQLI_ASSOC);

echo "Unidades no sistema:\n";
foreach ($units as $unit) {
    echo "- {$unit['abbreviation']} ({$unit['name']}): ID {$unit['id']}\n";
}

echo "\n=== VERIFICANDO ALIMENTOS LÍQUIDOS ===\n";

// Buscar alimentos líquidos
$liquid_sql = "SELECT id, name_pt FROM sf_food_items WHERE food_type = 'líquido' LIMIT 5";
$liquid_result = $conn->query($liquid_sql);
$liquids = $liquid_result->fetch_all(MYSQLI_ASSOC);

echo "Alimentos líquidos:\n";
foreach ($liquids as $liquid) {
    echo "- {$liquid['name_pt']} (ID: {$liquid['id']})\n";
}

echo "\n=== VERIFICANDO CONVERSÕES DE LÍQUIDOS ===\n";

if (!empty($liquids)) {
    $first_liquid = $liquids[0];
    $liquid_conversions_sql = "SELECT fu.*, u.abbreviation, u.name as unit_name 
                               FROM sf_food_units fu 
                               JOIN sf_units u ON fu.unit_id = u.id 
                               WHERE fu.food_id = ? 
                               ORDER BY fu.is_default DESC, u.abbreviation";
    $stmt = $conn->prepare($liquid_conversions_sql);
    $stmt->bind_param("i", $first_liquid['id']);
    $stmt->execute();
    $liquid_conversions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "Conversões de {$first_liquid['name_pt']}:\n";
    if (empty($liquid_conversions)) {
        echo "❌ NENHUMA CONVERSÃO ENCONTRADA!\n";
    } else {
        foreach ($liquid_conversions as $conv) {
            $default = $conv['is_default'] ? ' (PADRÃO)' : '';
            echo "- {$conv['abbreviation']} ({$conv['unit_name']}): {$conv['factor']}{$conv['unit']}{$default}\n";
        }
    }
}
?>
