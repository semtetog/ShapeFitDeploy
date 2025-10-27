<?php
// apply_perfect_conversions.php - Aplica conversões PERFEITAS baseadas nos tipos corretos

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/units_manager.php';

echo "=== APLICANDO CONVERSÕES PERFEITAS ===\n\n";

$units_manager = new UnitsManager($conn);

// Buscar IDs das unidades
$universal_units = $units_manager->getAllUnits();
$unit_ids = [];
foreach ($universal_units as $unit) {
    $unit_ids[$unit['abbreviation']] = $unit['id'];
}

// Conversões PERFEITAS baseadas no tipo
$perfect_conversions = [
    'líquido' => [
        'ml' => ['factor' => 1.0, 'unit' => 'ml'],     // 1ml = 1ml
        'l' => ['factor' => 1000.0, 'unit' => 'ml'],   // 1L = 1000ml
        'cs' => ['factor' => 15.0, 'unit' => 'ml'],    // 1 colher sopa = 15ml
        'cc' => ['factor' => 5.0, 'unit' => 'ml'],     // 1 colher chá = 5ml
        'xc' => ['factor' => 240.0, 'unit' => 'ml'],   // 1 xícara = 240ml
        'g' => ['factor' => 1.0, 'unit' => 'g'],       // 1g = 1g (para densidade)
    ],
    
    'granular' => [
        'g' => ['factor' => 1.0, 'unit' => 'g'],       // 1g = 1g
        'kg' => ['factor' => 1000.0, 'unit' => 'g'],   // 1kg = 1000g
        'cs' => ['factor' => 12.0, 'unit' => 'g'],     // 1 colher sopa = 12g
        'cc' => ['factor' => 4.0, 'unit' => 'g'],      // 1 colher chá = 4g
        'xc' => ['factor' => 200.0, 'unit' => 'g'],    // 1 xícara = 200g
    ],
    
    'fruta' => [
        'un' => ['factor' => 150.0, 'unit' => 'g'],    // 1 unidade = 150g
        'fat' => ['factor' => 50.0, 'unit' => 'g'],    // 1 fatia = 50g
        'g' => ['factor' => 1.0, 'unit' => 'g'],       // 1g = 1g
        'kg' => ['factor' => 1000.0, 'unit' => 'g'],   // 1kg = 1000g
    ],
    
    'proteina' => [
        'un' => ['factor' => 100.0, 'unit' => 'g'],    // 1 unidade = 100g
        'fat' => ['factor' => 30.0, 'unit' => 'g'],    // 1 fatia = 30g
        'g' => ['factor' => 1.0, 'unit' => 'g'],       // 1g = 1g
        'kg' => ['factor' => 1000.0, 'unit' => 'g'],   // 1kg = 1000g
    ]
];

// Buscar alimentos com seus tipos
$sql = "SELECT id, name_pt, food_type FROM sf_food_items WHERE food_type IS NOT NULL ORDER BY name_pt";
$result = $conn->query($sql);
$foods = $result->fetch_all(MYSQLI_ASSOC);

echo "Alimentos com tipo: " . count($foods) . "\n\n";

$total_added = 0;
$total_skipped = 0;

foreach ($foods as $food) {
    $food_type = $food['food_type'];
    $conversions = $perfect_conversions[$food_type] ?? $perfect_conversions['granular'];
    
    echo "Processando: {$food['name_pt']} (tipo: {$food_type})\n";
    
    $is_first = true;
    foreach ($conversions as $unit_abbr => $conversion) {
        if (!isset($unit_ids[$unit_abbr])) {
            echo "  ⚠️  Unidade '{$unit_abbr}' não encontrada, pulando...\n";
            continue;
        }
        
        $unit_id = $unit_ids[$unit_abbr];
        $factor = $conversion['factor'];
        $unit = $conversion['unit'];
        $is_default = $is_first;
        
        // Verificar se já existe
        $check_sql = "SELECT id FROM sf_food_units WHERE food_id = ? AND unit_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $food['id'], $unit_id);
        $check_stmt->execute();
        $exists = $check_stmt->get_result()->fetch_assoc();
        
        if ($exists) {
            echo "  ⏭️  Conversão já existe, pulando...\n";
            $total_skipped++;
        } else {
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
        }
        
        $is_first = false;
    }
    echo "\n";
}

echo "=== RESUMO FINAL ===\n";
echo "Conversões adicionadas: {$total_added}\n";
echo "Conversões já existentes: {$total_skipped}\n";
echo "Total de alimentos processados: " . count($foods) . "\n";

echo "\n✅ Processo PERFEITO concluído!\n";
echo "Agora Coca-Cola e outros líquidos devem aparecer com LITRO!\n";
?>
