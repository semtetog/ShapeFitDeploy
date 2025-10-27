<?php
// debug_abacate_categories.php - Debug das categorias do abacate

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "=== DEBUG DAS CATEGORIAS DO ABACATE ===\n\n";

// Buscar o abacate
$abacate_sql = "SELECT id, name_pt, food_type FROM sf_food_items WHERE name_pt LIKE '%abacate%' LIMIT 1";
$abacate_result = $conn->query($abacate_sql);
$abacate = $abacate_result->fetch_assoc();

if ($abacate) {
    echo "Abacate encontrado:\n";
    echo "ID: {$abacate['id']}\n";
    echo "Nome: {$abacate['name_pt']}\n";
    echo "Tipo primário: {$abacate['food_type']}\n\n";
    
    // Verificar categorias múltiplas
    $categories_sql = "SELECT category_type, is_primary FROM sf_food_categories WHERE food_id = ? ORDER BY is_primary DESC";
    $stmt = $conn->prepare($categories_sql);
    $stmt->bind_param("i", $abacate['id']);
    $stmt->execute();
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "Categorias múltiplas:\n";
    if (empty($categories)) {
        echo "❌ NENHUMA CATEGORIA MÚLTIPLA ENCONTRADA!\n";
    } else {
        foreach ($categories as $cat) {
            echo "- {$cat['category_type']}" . ($cat['is_primary'] ? ' (PRIMÁRIA)' : ' (SECUNDÁRIA)') . "\n";
        }
    }
    
    echo "\n";
    
    // Verificar unidades
    $units_sql = "SELECT fu.*, u.abbreviation, u.name as unit_name 
                  FROM sf_food_units fu 
                  JOIN sf_units u ON fu.unit_id = u.id 
                  WHERE fu.food_id = ? 
                  ORDER BY fu.is_default DESC, u.abbreviation";
    $stmt = $conn->prepare($units_sql);
    $stmt->bind_param("i", $abacate['id']);
    $stmt->execute();
    $units = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "Unidades atuais:\n";
    if (empty($units)) {
        echo "❌ NENHUMA UNIDADE ENCONTRADA!\n";
    } else {
        foreach ($units as $unit) {
            $default = $unit['is_default'] ? ' (PADRÃO)' : '';
            echo "- {$unit['abbreviation']} ({$unit['unit_name']}): {$unit['factor']}{$unit['unit']}{$default}\n";
        }
    }
    
    echo "\n";
    
    // Verificar se precisa aplicar unidades baseado nas categorias múltiplas
    if (!empty($categories)) {
        echo "=== APLICANDO UNIDADES BASEADO NAS CATEGORIAS MÚLTIPLAS ===\n";
        
        // Conversões para cada categoria
        $conversions = [
            'líquido' => [
                'ml' => ['factor' => 1.0, 'unit' => 'ml'],
                'l' => ['factor' => 1000.0, 'unit' => 'ml'],
                'cs' => ['factor' => 15.0, 'unit' => 'ml'],
                'cc' => ['factor' => 5.0, 'unit' => 'ml'],
                'xc' => ['factor' => 240.0, 'unit' => 'ml'],
            ],
            'fatias_pedacos' => [
                'fat' => ['factor' => 30.0, 'unit' => 'g'],
                'g' => ['factor' => 1.0, 'unit' => 'g'],
                'kg' => ['factor' => 1000.0, 'unit' => 'g'],
            ]
        ];
        
        // Buscar IDs das unidades
        $units_sql = "SELECT id, abbreviation FROM sf_units WHERE abbreviation IN ('ml', 'l', 'cs', 'cc', 'xc', 'fat', 'g', 'kg')";
        $units_result = $conn->query($units_sql);
        $unit_ids = [];
        while ($unit = $units_result->fetch_assoc()) {
            $unit_ids[$unit['abbreviation']] = $unit['id'];
        }
        
        echo "Unidades disponíveis:\n";
        foreach ($unit_ids as $abbr => $id) {
            echo "- {$abbr}: ID {$id}\n";
        }
        echo "\n";
        
        // Combinar unidades de todas as categorias
        $combined_units = [];
        foreach ($categories as $cat) {
            $category_type = $cat['category_type'];
            if (isset($conversions[$category_type])) {
                foreach ($conversions[$category_type] as $unit_abbr => $conversion) {
                    if (!isset($combined_units[$unit_abbr])) {
                        $combined_units[$unit_abbr] = $conversion;
                    }
                }
            }
        }
        
        echo "Unidades que deveriam estar disponíveis:\n";
        foreach ($combined_units as $unit_abbr => $conversion) {
            echo "- {$unit_abbr}: {$conversion['factor']}{$conversion['unit']}\n";
        }
        echo "\n";
        
        // Aplicar unidades
        $is_first = true;
        $added = 0;
        foreach ($combined_units as $unit_abbr => $conversion) {
            if (!isset($unit_ids[$unit_abbr])) {
                echo "⚠️  Unidade '{$unit_abbr}' não encontrada, pulando...\n";
                continue;
            }
            
            $unit_id = $unit_ids[$unit_abbr];
            $factor = $conversion['factor'];
            $unit = $conversion['unit'];
            $is_default = $is_first ? 1 : 0;
            
            // Verificar se já existe
            $check_sql = "SELECT id FROM sf_food_units WHERE food_id = ? AND unit_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ii", $abacate['id'], $unit_id);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->fetch_assoc();
            
            if (!$exists) {
                // Adicionar conversão
                $insert_sql = "INSERT INTO sf_food_units (food_id, unit_id, factor, unit, is_default) VALUES (?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iidsi", $abacate['id'], $unit_id, $factor, $unit, $is_default);
                
                if ($insert_stmt->execute()) {
                    echo "✅ Adicionado: {$unit_abbr} = {$factor}{$unit}" . ($is_default ? " (PADRÃO)" : "") . "\n";
                    $added++;
                } else {
                    echo "❌ Erro ao adicionar: {$unit_abbr} - " . $insert_stmt->error . "\n";
                }
            } else {
                echo "⏭️  Conversão já existe: {$unit_abbr}\n";
            }
            
            $is_first = false;
        }
        
        echo "\nUnidades adicionadas: {$added}\n";
    }
    
} else {
    echo "❌ Abacate não encontrado!\n";
}

echo "\n✅ Debug concluído!\n";
?>
