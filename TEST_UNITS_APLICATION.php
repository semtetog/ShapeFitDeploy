<?php
// TEST_UNITS_APPLICATION.php - Testar aplicação de unidades

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/units_manager.php';

echo "=== TESTANDO APLICAÇÃO DE UNIDADES ===\n\n";

$units_manager = new UnitsManager($conn);

// Buscar Abacaxi
$food_sql = "SELECT id, name_pt FROM sf_food_items WHERE name_pt = 'Abacaxi' LIMIT 1";
$result = $conn->query($food_sql);
if ($food = $result->fetch_assoc()) {
    echo "🍍 Alimento: {$food['name_pt']} (ID: {$food['id']})\n";
    
    // Simular classificação: líquido + granular
    $classifications = [
        $food['id'] => ['líquido', 'granular']
    ];
    
    echo "📋 Categorias: líquido + granular\n";
    
    // Aplicar unidades
    $category_units_map = [
        'líquido' => ['ml', 'l', 'cs', 'cc', 'xc'],
        'granular' => ['g', 'kg', 'cs', 'cc', 'xc']
    ];
    
    $food_id = $food['id'];
    
    // Limpar unidades existentes
    $delete_sql = "DELETE FROM sf_food_item_conversions WHERE food_item_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $food_id);
    $delete_stmt->execute();
    echo "🗑️ Unidades existentes removidas\n";
    
    // Coletar unidades únicas
    $unique_units = [];
    foreach (['líquido', 'granular'] as $category) {
        if (isset($category_units_map[$category])) {
            foreach ($category_units_map[$category] as $unit_abbr) {
                $unique_units[$unit_abbr] = true;
            }
        }
    }
    
    echo "📏 Unidades a aplicar: " . implode(', ', array_keys($unique_units)) . "\n";
    
    // Aplicar unidades
    $applied_count = 0;
    foreach (array_keys($unique_units) as $unit_abbr) {
        $unit_data = $units_manager->getUnitByAbbreviation($unit_abbr);
        if ($unit_data) {
            $unit_id = $unit_data['id'];
            $conversion_factor = $units_manager->getConversionFactor($unit_abbr);
            $is_default = ($unit_abbr === 'g' || $unit_abbr === 'ml' || $unit_abbr === 'un');
            
            $insert_sql = "INSERT INTO sf_food_item_conversions (food_item_id, unit_id, conversion_factor, is_default) VALUES (?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iidi", $food_id, $unit_id, $conversion_factor, $is_default ? 1 : 0);
            
            if ($insert_stmt->execute()) {
                echo "✅ {$unit_data['name']} ({$unit_abbr}) - Factor: {$conversion_factor} " . ($is_default ? '(PADRÃO)' : '') . "\n";
                $applied_count++;
            } else {
                echo "❌ Erro ao inserir {$unit_abbr}: " . $insert_stmt->error . "\n";
            }
        } else {
            echo "⚠️ Unidade '{$unit_abbr}' não encontrada\n";
        }
    }
    
    echo "\n📊 Total de unidades aplicadas: {$applied_count}\n";
    
    // Verificar se foi inserido corretamente
    $check_sql = "SELECT sfic.*, smu.name, smu.abbreviation 
                  FROM sf_food_item_conversions sfic 
                  JOIN sf_measurement_units smu ON sfic.unit_id = smu.id 
                  WHERE sfic.food_item_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $food_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    echo "\n🔍 Verificação final:\n";
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "- {$row['name']} ({$row['abbreviation']}) - Factor: {$row['conversion_factor']} " . ($row['is_default'] ? '(PADRÃO)' : '') . "\n";
        }
    } else {
        echo "❌ Nenhuma unidade encontrada!\n";
    }
    
} else {
    echo "❌ Abacaxi não encontrado!\n";
}

echo "\n=== TESTE CONCLUÍDO ===\n";
$conn->close();
?>
