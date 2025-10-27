<?php
// apply_multiple_categories_units.php - Aplicar unidades baseado em múltiplas categorias

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/units_manager.php';

echo "=== APLICANDO UNIDADES BASEADO EM MÚLTIPLAS CATEGORIAS ===\n\n";

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

// Conversões para cada categoria
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

// Buscar alimentos com suas categorias múltiplas
$sql = "SELECT fi.id, fi.name_pt, fi.food_type, 
               GROUP_CONCAT(fc.category_type ORDER BY fc.is_primary DESC) as categories
        FROM sf_food_items fi
        LEFT JOIN sf_food_categories fc ON fi.id = fc.food_id
        GROUP BY fi.id, fi.name_pt, fi.food_type
        HAVING categories IS NOT NULL
        ORDER BY fi.name_pt";
$result = $conn->query($sql);
$foods = $result->fetch_all(MYSQLI_ASSOC);

echo "Alimentos com categorias encontrados: " . count($foods) . "\n\n";

$total_added = 0;
$total_skipped = 0;

foreach ($foods as $food) {
    $categories = explode(',', $food['categories']);
    echo "Processando: {$food['name_pt']} (categorias: " . implode(', ', $categories) . ")\n";
    
    // Combinar unidades de todas as categorias
    $combined_units = [];
    foreach ($categories as $category) {
        if (isset($conversions[$category])) {
            foreach ($conversions[$category] as $unit_abbr => $conversion) {
                if (!isset($combined_units[$unit_abbr])) {
                    $combined_units[$unit_abbr] = $conversion;
                }
            }
        }
    }
    
    if (empty($combined_units)) {
        echo "  ⚠️  Nenhuma unidade encontrada para as categorias\n";
        continue;
    }
    
    $is_first = true;
    foreach ($combined_units as $unit_abbr => $conversion) {
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

echo "=== RESUMO ===\n";
echo "Conversões adicionadas: {$total_added}\n";
echo "Conversões já existentes: {$total_skipped}\n";
echo "Total de alimentos processados: " . count($foods) . "\n";

echo "\n✅ Unidades aplicadas baseadas em múltiplas categorias!\n";
echo "Agora teste no add_food_to_diary.php\n";
?>
