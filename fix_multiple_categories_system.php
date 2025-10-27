<?php
// fix_multiple_categories_system.php - Corrigir sistema de múltiplas categorias

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "=== CORRIGINDO SISTEMA DE MÚLTIPLAS CATEGORIAS ===\n\n";

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

// Buscar IDs das unidades
$units_sql = "SELECT id, abbreviation FROM sf_units";
$units_result = $conn->query($units_sql);
$unit_ids = [];
while ($unit = $units_result->fetch_assoc()) {
    $unit_ids[$unit['abbreviation']] = $unit['id'];
}

echo "Unidades disponíveis: " . count($unit_ids) . "\n\n";

// Buscar TODOS os alimentos com categorias múltiplas
$sql = "SELECT fi.id, fi.name_pt, fi.food_type, 
               GROUP_CONCAT(fc.category_type ORDER BY fc.is_primary DESC) as categories
        FROM sf_food_items fi
        LEFT JOIN sf_food_categories fc ON fi.id = fc.food_id
        GROUP BY fi.id, fi.name_pt, fi.food_type
        HAVING categories IS NOT NULL AND categories != ''
        ORDER BY fi.name_pt";
$result = $conn->query($sql);
$foods = $result->fetch_all(MYSQLI_ASSOC);

echo "Alimentos com categorias encontrados: " . count($foods) . "\n\n";

$total_processed = 0;
$total_added = 0;

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
    
    // Remover unidades existentes para recriar
    $delete_sql = "DELETE FROM sf_food_units WHERE food_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $food['id']);
    $delete_stmt->execute();
    
    // Aplicar todas as unidades combinadas
    $is_first = true;
    $added = 0;
    foreach ($combined_units as $unit_abbr => $conversion) {
        if (!isset($unit_ids[$unit_abbr])) {
            echo "  ⚠️  Unidade '{$unit_abbr}' não encontrada, pulando...\n";
            continue;
        }
        
        $unit_id = $unit_ids[$unit_abbr];
        $factor = $conversion['factor'];
        $unit = $conversion['unit'];
        $is_default = $is_first ? 1 : 0;
        
        // Adicionar conversão
        $insert_sql = "INSERT INTO sf_food_units (food_id, unit_id, factor, unit, is_default) VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iidsi", $food['id'], $unit_id, $factor, $unit, $is_default);
        
        if ($insert_stmt->execute()) {
            echo "  ✅ Adicionado: {$unit_abbr} = {$factor}{$unit}" . ($is_default ? " (PADRÃO)" : "") . "\n";
            $added++;
        } else {
            echo "  ❌ Erro ao adicionar: {$unit_abbr} - " . $insert_stmt->error . "\n";
        }
        
        $is_first = false;
    }
    
    echo "  📊 Unidades adicionadas: {$added}\n\n";
    $total_processed++;
    $total_added += $added;
}

echo "=== RESUMO FINAL ===\n";
echo "Alimentos processados: {$total_processed}\n";
echo "Total de unidades adicionadas: {$total_added}\n";

echo "\n✅ SISTEMA DE MÚLTIPLAS CATEGORIAS CORRIGIDO!\n";
echo "Agora todos os alimentos com múltiplas categorias terão todas as unidades!\n";
?>
