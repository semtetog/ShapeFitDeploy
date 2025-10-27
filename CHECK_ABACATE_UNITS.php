<?php
// check_abacate_units.php - Verificar unidades do abacate

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "=== VERIFICANDO UNIDADES DO ABACATE ===\n\n";

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
        echo "Preciso aplicar as conversões primeiro.\n";
    } else {
        foreach ($conversions as $conv) {
            $default = $conv['is_default'] ? ' (PADRÃO)' : '';
            echo "- {$conv['abbreviation']} ({$conv['unit_name']}): {$conv['factor']}{$conv['unit']}{$default}\n";
        }
    }
} else {
    echo "❌ Abacate não encontrado!\n";
}

echo "\n=== APLICANDO CONVERSÕES PARA LÍQUIDOS ===\n";

// Aplicar conversões para líquidos
$liquids_sql = "SELECT id, name_pt FROM sf_food_items WHERE food_type = 'líquido'";
$liquids_result = $conn->query($liquids_sql);
$liquids = $liquids_result->fetch_all(MYSQLI_ASSOC);

echo "Líquidos encontrados: " . count($liquids) . "\n";

// Conversões para líquidos
$liquid_conversions = [
    'ml' => ['factor' => 1.0, 'unit' => 'ml'],
    'l' => ['factor' => 1000.0, 'unit' => 'ml'],
    'cs' => ['factor' => 15.0, 'unit' => 'ml'],
    'cc' => ['factor' => 5.0, 'unit' => 'ml'],
    'xc' => ['factor' => 240.0, 'unit' => 'ml'],
];

// Buscar IDs das unidades
$units_sql = "SELECT id, abbreviation FROM sf_units WHERE abbreviation IN ('ml', 'l', 'cs', 'cc', 'xc')";
$units_result = $conn->query($units_sql);
$unit_ids = [];
while ($unit = $units_result->fetch_assoc()) {
    $unit_ids[$unit['abbreviation']] = $unit['id'];
}

echo "Unidades líquidas disponíveis:\n";
foreach ($unit_ids as $abbr => $id) {
    echo "- {$abbr}: ID {$id}\n";
}
echo "\n";

$total_added = 0;

foreach ($liquids as $liquid) {
    echo "Processando: {$liquid['name_pt']}\n";
    
    $is_first = true;
    foreach ($liquid_conversions as $unit_abbr => $conversion) {
        if (!isset($unit_ids[$unit_abbr])) {
            echo "  ⚠️  Unidade '{$unit_abbr}' não encontrada\n";
            continue;
        }
        
        $unit_id = $unit_ids[$unit_abbr];
        $factor = $conversion['factor'];
        $unit = $conversion['unit'];
        $is_default = $is_first;
        
        // Verificar se já existe
        $check_sql = "SELECT id FROM sf_food_units WHERE food_id = ? AND unit_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $liquid['id'], $unit_id);
        $check_stmt->execute();
        $exists = $check_stmt->get_result()->fetch_assoc();
        
        if (!$exists) {
            // Adicionar conversão
            $insert_sql = "INSERT INTO sf_food_units (food_id, unit_id, factor, unit, is_default) VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iidsi", $liquid['id'], $unit_id, $factor, $unit, $is_default);
            
            if ($insert_stmt->execute()) {
                echo "  ✅ Adicionado: {$unit_abbr} = {$factor}{$unit}" . ($is_default ? " (PADRÃO)" : "") . "\n";
                $total_added++;
            } else {
                echo "  ❌ Erro ao adicionar: {$unit_abbr}\n";
            }
        } else {
            echo "  ⏭️  Conversão já existe: {$unit_abbr}\n";
        }
        
        $is_first = false;
    }
    echo "\n";
}

echo "=== RESUMO ===\n";
echo "Conversões adicionadas: {$total_added}\n";
echo "Total de líquidos processados: " . count($liquids) . "\n";

echo "\n✅ Conversões aplicadas!\n";
echo "Agora teste no add_food_to_diary.php\n";
?>
