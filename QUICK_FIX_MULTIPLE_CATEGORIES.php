<?php
// QUICK_FIX_MULTIPLE_CATEGORIES.php - Correção rápida do sistema

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "🔧 CORREÇÃO RÁPIDA DO SISTEMA DE MÚLTIPLAS CATEGORIAS 🔧\n\n";

// Buscar alimentos com múltiplas categorias
$sql = "SELECT fi.id, fi.name_pt, 
               GROUP_CONCAT(fc.category_type ORDER BY fc.is_primary DESC) as categories
        FROM sf_food_items fi
        JOIN sf_food_categories fc ON fi.id = fc.food_id
        GROUP BY fi.id, fi.name_pt
        HAVING COUNT(fc.category_type) > 1
        ORDER BY fi.name_pt";
$result = $conn->query($sql);
$foods = $result->fetch_all(MYSQLI_ASSOC);

echo "Alimentos com múltiplas categorias: " . count($foods) . "\n\n";

// Conversões simplificadas
$conversions = [
    'líquido' => ['ml', 'l', 'cs', 'cc', 'xc'],
    'fatias_pedacos' => ['fat', 'g', 'kg'],
    'granular' => ['g', 'kg', 'cs', 'cc', 'xc'],
    'unidade_inteira' => ['un', 'g', 'kg'],
    'semi_liquido' => ['g', 'ml', 'cs', 'cc', 'xc']
];

// Buscar IDs das unidades
$unit_map = [
    'ml' => 10, 'l' => 9, 'cs' => 1, 'cc' => 2, 'xc' => 3,
    'g' => 8, 'kg' => 7, 'fat' => 12, 'un' => 11
];

$total_fixed = 0;

foreach ($foods as $food) {
    $categories = explode(',', $food['categories']);
    echo "Corrigindo: {$food['name_pt']} (categorias: " . implode(', ', $categories) . ")\n";
    
    // Combinar unidades de todas as categorias
    $all_units = [];
    foreach ($categories as $cat) {
        if (isset($conversions[$cat])) {
            $all_units = array_merge($all_units, $conversions[$cat]);
        }
    }
    $all_units = array_unique($all_units);
    
    // Remover unidades existentes
    $conn->query("DELETE FROM sf_food_units WHERE food_id = {$food['id']}");
    
    // Adicionar todas as unidades
    $is_first = true;
    foreach ($all_units as $unit_abbr) {
        if (isset($unit_map[$unit_abbr])) {
            $unit_id = $unit_map[$unit_abbr];
            $factor = 1.0;
            $unit = in_array($unit_abbr, ['ml', 'l', 'cs', 'cc', 'xc']) ? 'ml' : 'g';
            $is_default = $is_first ? 1 : 0;
            
            $sql = "INSERT INTO sf_food_units (food_id, unit_id, factor, unit, is_default) 
                    VALUES ({$food['id']}, {$unit_id}, {$factor}, '{$unit}', {$is_default})";
            $conn->query($sql);
            
            if ($is_first) {
                echo "  ✅ {$unit_abbr} (PADRÃO)";
                $is_first = false;
            } else {
                echo ", {$unit_abbr}";
            }
        }
    }
    echo "\n";
    $total_fixed++;
}

echo "\n🎉 CORREÇÃO CONCLUÍDA!\n";
echo "Alimentos corrigidos: {$total_fixed}\n";
echo "Agora teste no add_food_to_diary.php!\n";
?>
