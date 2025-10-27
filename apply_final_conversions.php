<?php
// apply_final_conversions.php - Aplica conversões FINAIS baseadas nas categorias corretas

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/units_manager.php';

echo "=== APLICANDO CONVERSÕES FINAIS ===\n\n";

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

// Conversões FINAIS baseadas nas categorias corretas
$final_conversions = [
    'líquido' => [
        'ml' => ['factor' => 1.0, 'unit' => 'ml'],     // 1ml = 1ml
        'l' => ['factor' => 1000.0, 'unit' => 'ml'],   // 1L = 1000ml
        'cs' => ['factor' => 15.0, 'unit' => 'ml'],    // 1 colher sopa = 15ml
        'cc' => ['factor' => 5.0, 'unit' => 'ml'],     // 1 colher chá = 5ml
        'xc' => ['factor' => 240.0, 'unit' => 'ml'],   // 1 xícara = 240ml
    ],
    
    'semi_liquido' => [
        'cs' => ['factor' => 15.0, 'unit' => 'g'],     // 1 colher sopa = 15g
        'cc' => ['factor' => 5.0, 'unit' => 'g'],      // 1 colher chá = 5g
        'xc' => ['factor' => 200.0, 'unit' => 'g'],    // 1 xícara = 200g
        'g' => ['factor' => 1.0, 'unit' => 'g'],       // 1g = 1g
        'ml' => ['factor' => 1.0, 'unit' => 'ml'],     // 1ml = 1ml
    ],
    
    'granular' => [
        'g' => ['factor' => 1.0, 'unit' => 'g'],       // 1g = 1g
        'kg' => ['factor' => 1000.0, 'unit' => 'g'],   // 1kg = 1000g
        'cs' => ['factor' => 12.0, 'unit' => 'g'],     // 1 colher sopa = 12g
        'cc' => ['factor' => 4.0, 'unit' => 'g'],      // 1 colher chá = 4g
        'xc' => ['factor' => 200.0, 'unit' => 'g'],    // 1 xícara = 200g
    ],
    
    'unidade_inteira' => [
        'un' => ['factor' => 150.0, 'unit' => 'g'],    // 1 unidade = 150g
        'g' => ['factor' => 1.0, 'unit' => 'g'],       // 1g = 1g
        'kg' => ['factor' => 1000.0, 'unit' => 'g'],   // 1kg = 1000g
    ],
    
    'fatias_pedacos' => [
        'fat' => ['factor' => 30.0, 'unit' => 'g'],    // 1 fatia = 30g
        'g' => ['factor' => 1.0, 'unit' => 'g'],       // 1g = 1g
        'kg' => ['factor' => 1000.0, 'unit' => 'g'],   // 1kg = 1000g
    ],
    
    'corte_porcao' => [
        'g' => ['factor' => 1.0, 'unit' => 'g'],       // 1g = 1g
        'kg' => ['factor' => 1000.0, 'unit' => 'g'],   // 1kg = 1000g
        'un' => ['factor' => 100.0, 'unit' => 'g'],    // 1 unidade = 100g
    ],
    
    'colher_cremoso' => [
        'cs' => ['factor' => 15.0, 'unit' => 'g'],     // 1 colher sopa = 15g
        'cc' => ['factor' => 5.0, 'unit' => 'g'],      // 1 colher chá = 5g
        'g' => ['factor' => 1.0, 'unit' => 'g'],       // 1g = 1g
    ],
    
    'condimentos' => [
        'cc' => ['factor' => 2.0, 'unit' => 'g'],      // 1 colher chá = 2g
        'cs' => ['factor' => 6.0, 'unit' => 'g'],      // 1 colher sopa = 6g
        'g' => ['factor' => 1.0, 'unit' => 'g'],       // 1g = 1g
    ],
    
    'oleos_gorduras' => [
        'cs' => ['factor' => 15.0, 'unit' => 'ml'],    // 1 colher sopa = 15ml
        'cc' => ['factor' => 5.0, 'unit' => 'ml'],     // 1 colher chá = 5ml
        'ml' => ['factor' => 1.0, 'unit' => 'ml'],     // 1ml = 1ml
        'l' => ['factor' => 1000.0, 'unit' => 'ml'],   // 1L = 1000ml
    ],
    
    'preparacoes_compostas' => [
        'g' => ['factor' => 1.0, 'unit' => 'g'],       // 1g = 1g
        'kg' => ['factor' => 1000.0, 'unit' => 'g'],   // 1kg = 1000g
        'un' => ['factor' => 200.0, 'unit' => 'g'],    // 1 unidade = 200g
    ]
];

// Buscar alimentos com suas categorias
$sql = "SELECT id, name_pt, food_type FROM sf_food_items WHERE food_type IS NOT NULL ORDER BY name_pt";
$result = $conn->query($sql);
$foods = $result->fetch_all(MYSQLI_ASSOC);

echo "Alimentos encontrados: " . count($foods) . "\n\n";

$total_added = 0;
$total_skipped = 0;
$category_counts = [];

foreach ($foods as $food) {
    $food_type = $food['food_type'];
    $conversions = $final_conversions[$food_type] ?? $final_conversions['granular'];
    
    // Contar por categoria
    $category_counts[$food_type] = ($category_counts[$food_type] ?? 0) + 1;
    
    echo "Processando: {$food['name_pt']} (categoria: {$food_type})\n";
    
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
echo "Total de alimentos processados: " . count($foods) . "\n\n";

echo "=== DISTRIBUIÇÃO POR CATEGORIA ===\n";
foreach ($category_counts as $category => $count) {
    echo "{$category}: {$count} alimentos\n";
}

echo "\n✅ Processo FINAL concluído!\n";
echo "Agora todos os alimentos têm conversões corretas baseadas em suas categorias!\n";
echo "Teste no add_food_to_diary.php para verificar se as unidades aparecem corretamente.\n";
?>
