<?php
// APPLY_UNITS_FROM_CLASSIFICATION.php - Aplicar unidades baseado nas classificações

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/units_manager.php';

echo "=== APLICANDO UNIDADES BASEADO NAS CLASSIFICAÇÕES ===\n\n";

$units_manager = new UnitsManager($conn);

// Buscar IDs das unidades
$universal_units = $units_manager->getAllUnits();
$unit_ids = [];
foreach ($universal_units as $unit) {
    $unit_ids[$unit['abbreviation']] = $unit['id'];
}

echo "Unidades disponíveis:\n";
foreach ($unit_ids as $abbr => $id) {
    echo "- {$abbr}: ID {$id}\n";
}
echo "\n";

// Conversões baseadas nas categorias
$conversions = [
    'líquido' => [
        'ml' => ['factor' => 1.0, 'unit' => 'ml'],
        'l' => ['factor' => 1000.0, 'unit' => 'ml'],
        'cs' => ['factor' => 15.0, 'unit' => 'ml'],
        'cc' => ['factor' => 5.0, 'unit' => 'ml'],
        'xc' => ['factor' => 240.0, 'unit' => 'ml'],
    ],
    'semi_liquido' => [
        'g' => ['factor' => 1.0, 'unit' => 'g'],
        'ml' => ['factor' => 1.0, 'unit' => 'ml'],
        'cs' => ['factor' => 15.0, 'unit' => 'g'],
        'cc' => ['factor' => 5.0, 'unit' => 'g'],
        'xc' => ['factor' => 200.0, 'unit' => 'g'],
    ],
    'granular' => [
        'g' => ['factor' => 1.0, 'unit' => 'g'],
        'kg' => ['factor' => 1000.0, 'unit' => 'g'],
        'cs' => ['factor' => 12.0, 'unit' => 'g'],
        'cc' => ['factor' => 4.0, 'unit' => 'g'],
        'xc' => ['factor' => 200.0, 'unit' => 'g'],
    ],
    'unidade_inteira' => [
        'un' => ['factor' => 150.0, 'unit' => 'g'],
        'g' => ['factor' => 1.0, 'unit' => 'g'],
        'kg' => ['factor' => 1000.0, 'unit' => 'g'],
    ],
    'fatias_pedacos' => [
        'fat' => ['factor' => 30.0, 'unit' => 'g'],
        'g' => ['factor' => 1.0, 'unit' => 'g'],
        'kg' => ['factor' => 1000.0, 'unit' => 'g'],
    ],
    'corte_porcao' => [
        'g' => ['factor' => 1.0, 'unit' => 'g'],
        'kg' => ['factor' => 1000.0, 'unit' => 'g'],
        'un' => ['factor' => 100.0, 'unit' => 'g'],
    ],
    'colher_cremoso' => [
        'cs' => ['factor' => 15.0, 'unit' => 'g'],
        'cc' => ['factor' => 5.0, 'unit' => 'g'],
        'g' => ['factor' => 1.0, 'unit' => 'g'],
    ],
    'condimentos' => [
        'cc' => ['factor' => 2.0, 'unit' => 'g'],
        'cs' => ['factor' => 6.0, 'unit' => 'g'],
        'g' => ['factor' => 1.0, 'unit' => 'g'],
    ],
    'oleos_gorduras' => [
        'cs' => ['factor' => 15.0, 'unit' => 'ml'],
        'cc' => ['factor' => 5.0, 'unit' => 'ml'],
        'ml' => ['factor' => 1.0, 'unit' => 'ml'],
        'l' => ['factor' => 1000.0, 'unit' => 'ml'],
    ],
    'preparacoes_compostas' => [
        'g' => ['factor' => 1.0, 'unit' => 'g'],
        'kg' => ['factor' => 1000.0, 'unit' => 'g'],
        'un' => ['factor' => 200.0, 'unit' => 'g'],
    ]
];

// Buscar alimentos classificados (não granular)
$sql = "SELECT id, name_pt, food_type FROM sf_food_items WHERE food_type != 'granular' ORDER BY name_pt";
$result = $conn->query($sql);
$foods = $result->fetch_all(MYSQLI_ASSOC);

echo "Alimentos classificados encontrados: " . count($foods) . "\n\n";

$total_added = 0;
$total_skipped = 0;

foreach ($foods as $food) {
    $food_type = $food['food_type'];
    $food_conversions = $conversions[$food_type] ?? $conversions['granular'];
    
    echo "Processando: {$food['name_pt']} (categoria: {$food_type})\n";
    
    $is_first = true;
    foreach ($food_conversions as $unit_abbr => $conversion) {
        if (!isset($unit_ids[$unit_abbr])) {
            echo "  ⚠️  Unidade '{$unit_abbr}' não encontrada, pulando...\n";
            continue;
        }
        
        $unit_id = $unit_ids[$unit_abbr];
        $factor = $conversion['factor'];
        $unit = $conversion['unit'];
        $is_default = $is_first;
        
        // Adicionar conversão
        $success = $units_manager->addFoodUnit(
            $food['id'], 
            $unit_id, 
            $factor, 
            $unit, 
            $is_default
        );
        
        if ($success) {
            echo "  ✅ Adicionado: {$unit_abbr} = {$factor}{$unit}" . ($is_default ? " (PADRÃO)" : "") . "\n";
            $total_added++;
        } else {
            echo "  ❌ Erro ao adicionar: {$unit_abbr}\n";
        }
        
        $is_first = false;
    }
    echo "\n";
}

echo "=== RESUMO ===\n";
echo "Conversões adicionadas: {$total_added}\n";
echo "Total de alimentos processados: " . count($foods) . "\n";

echo "\n✅ Unidades aplicadas baseadas nas classificações!\n";
echo "Agora teste no add_food_to_diary.php\n";
?>
