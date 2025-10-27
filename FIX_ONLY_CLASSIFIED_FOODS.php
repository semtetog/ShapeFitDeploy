<?php
// FIX_ONLY_CLASSIFIED_FOODS.php - Corrigir APENAS alimentos classificados

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "🔧 CORRIGINDO APENAS ALIMENTOS CLASSIFICADOS 🔧\n\n";

// 1. PRIMEIRO: Limpar TODAS as unidades existentes
echo "🗑️ Limpando todas as unidades existentes...\n";
$conn->query("DELETE FROM sf_food_units");
echo "✅ Unidades limpas!\n\n";

// 2. Buscar APENAS alimentos que foram classificados (têm categorias múltiplas)
$sql = "SELECT fi.id, fi.name_pt, 
               GROUP_CONCAT(fc.category_type ORDER BY fc.is_primary DESC) as categories
        FROM sf_food_items fi
        JOIN sf_food_categories fc ON fi.id = fc.food_id
        GROUP BY fi.id, fi.name_pt
        HAVING COUNT(fc.category_type) > 0
        ORDER BY fi.name_pt";
$result = $conn->query($sql);
$foods = $result->fetch_all(MYSQLI_ASSOC);

echo "Alimentos classificados encontrados: " . count($foods) . "\n\n";

// Conversões por categoria
$conversions = [
    'líquido' => ['ml', 'l', 'cs', 'cc', 'xc'],
    'semi_liquido' => ['g', 'ml', 'cs', 'cc', 'xc'],
    'granular' => ['g', 'kg', 'cs', 'cc', 'xc'],
    'unidade_inteira' => ['un', 'g', 'kg'],
    'fatias_pedacos' => ['fat', 'g', 'kg'],
    'corte_porcao' => ['g', 'kg', 'un'],
    'colher_cremoso' => ['cs', 'cc', 'g'],
    'condimentos' => ['cc', 'cs', 'g'],
    'oleos_gorduras' => ['cs', 'cc', 'ml', 'l'],
    'preparacoes_compostas' => ['g', 'kg', 'un']
];

// Mapeamento de unidades
$unit_map = [
    'ml' => 10, 'l' => 9, 'cs' => 1, 'cc' => 2, 'xc' => 3,
    'g' => 8, 'kg' => 7, 'fat' => 12, 'un' => 11
];

$total_processed = 0;

foreach ($foods as $food) {
    $categories = explode(',', $food['categories']);
    echo "Processando: {$food['name_pt']} (categorias: " . implode(', ', $categories) . ")\n";
    
    // Combinar unidades de todas as categorias do alimento
    $all_units = [];
    foreach ($categories as $cat) {
        $cat = trim($cat);
        if (isset($conversions[$cat])) {
            $all_units = array_merge($all_units, $conversions[$cat]);
        }
    }
    $all_units = array_unique($all_units);
    
    if (empty($all_units)) {
        echo "  ⚠️  Nenhuma unidade encontrada\n";
        continue;
    }
    
    // Adicionar unidades para este alimento específico
    $is_first = true;
    $added = 0;
    foreach ($all_units as $unit_abbr) {
        if (isset($unit_map[$unit_abbr])) {
            $unit_id = $unit_map[$unit_abbr];
            $factor = 1.0;
            $unit = in_array($unit_abbr, ['ml', 'l', 'cs', 'cc', 'xc']) ? 'ml' : 'g';
            $is_default = $is_first ? 1 : 0;
            
            $sql = "INSERT INTO sf_food_units (food_id, unit_id, factor, unit, is_default) 
                    VALUES ({$food['id']}, {$unit_id}, {$factor}, '{$unit}', {$is_default})";
            
            if ($conn->query($sql)) {
                if ($is_first) {
                    echo "  ✅ {$unit_abbr} (PADRÃO)";
                    $is_first = false;
                } else {
                    echo ", {$unit_abbr}";
                }
                $added++;
            }
        }
    }
    echo " ({$added} unidades)\n";
    $total_processed++;
}

echo "\n🎉 CORREÇÃO CONCLUÍDA!\n";
echo "Alimentos processados: {$total_processed}\n";
echo "Agora apenas alimentos CLASSIFICADOS terão unidades!\n";
echo "Alimentos não classificados não aparecerão no add_food_to_diary.php\n";
?>
