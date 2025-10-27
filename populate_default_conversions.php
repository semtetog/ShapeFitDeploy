<?php
// populate_default_conversions.php - Script para popular conversões padrão automaticamente

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/units_manager.php';

echo "=== POPULANDO CONVERSÕES PADRÃO AUTOMATICAMENTE ===\n\n";

$units_manager = new UnitsManager($conn);

// Buscar IDs das unidades universais
$universal_units = $units_manager->getAllUnits();
$unit_ids = [];
foreach ($universal_units as $unit) {
    $unit_ids[$unit['abbreviation']] = $unit['id'];
}

echo "Unidades encontradas:\n";
foreach ($unit_ids as $abbr => $id) {
    echo "- {$abbr}: ID {$id}\n";
}
echo "\n";

// Buscar todos os alimentos
$sql = "SELECT id, name_pt as name FROM sf_food_items ORDER BY name_pt";
$result = $conn->query($sql);
$foods = [];
while ($row = $result->fetch_assoc()) {
    $foods[] = $row;
}

echo "Alimentos encontrados: " . count($foods) . "\n\n";

// Conversões padrão baseadas no tipo de alimento
$default_conversions = [
    // Líquidos (óleos, azeites, etc.)
    'líquido' => [
        'cs' => ['factor' => 15.0, 'unit' => 'ml'], // 1 colher sopa = 15ml
        'cc' => ['factor' => 5.0, 'unit' => 'ml'],  // 1 colher chá = 5ml
        'xc' => ['factor' => 240.0, 'unit' => 'ml'], // 1 xícara = 240ml
        'g' => ['factor' => 1.0, 'unit' => 'g'],     // 1g = 1g
        'ml' => ['factor' => 1.0, 'unit' => 'ml'],   // 1ml = 1ml
    ],
    
    // Sólidos granulares (açúcar, farinha, arroz, etc.)
    'granular' => [
        'cs' => ['factor' => 12.0, 'unit' => 'g'],   // 1 colher sopa = 12g
        'cc' => ['factor' => 4.0, 'unit' => 'g'],    // 1 colher chá = 4g
        'xc' => ['factor' => 200.0, 'unit' => 'g'],  // 1 xícara = 200g
        'g' => ['factor' => 1.0, 'unit' => 'g'],     // 1g = 1g
        'kg' => ['factor' => 1000.0, 'unit' => 'g'], // 1kg = 1000g
    ],
    
    // Frutas e vegetais
    'fruta' => [
        'un' => ['factor' => 150.0, 'unit' => 'g'],  // 1 unidade = 150g
        'fat' => ['factor' => 50.0, 'unit' => 'g'],  // 1 fatia = 50g
        'g' => ['factor' => 1.0, 'unit' => 'g'],     // 1g = 1g
    ],
    
    // Carnes e proteínas
    'proteina' => [
        'un' => ['factor' => 100.0, 'unit' => 'g'],  // 1 unidade = 100g
        'fat' => ['factor' => 30.0, 'unit' => 'g'],  // 1 fatia = 30g
        'g' => ['factor' => 1.0, 'unit' => 'g'],     // 1g = 1g
        'kg' => ['factor' => 1000.0, 'unit' => 'g'], // 1kg = 1000g
    ]
];

// Função para detectar tipo de alimento baseado no nome
function detectFoodType($food_name) {
    $name_lower = strtolower($food_name);
    
    // Marcas conhecidas de líquidos/bebidas
    $liquid_brands = [
        'ades', 'coca', 'pepsi', 'fanta', 'sprite', 'guaraná', 'guarana', 'antarctica', 'skol', 'brahma',
        'heineken', 'stella', 'corona', 'budweiser', 'amstel', 'devassa', 'bohemia', 'crystal', 'água',
        'agua', 'crystal', 'indaiá', 'indaiá', 'são lourenço', 'sao lourenco', 'nestlé', 'nestle',
        'danone', 'activia', 'danoninho', 'yakult', 'chamyto', 'toddynho', 'nescau', 'nescafé',
        'nescafe', 'pilão', 'melitta', 'três corações', 'tres coracoes', 'café', 'cafe', 'chá', 'cha',
        'mate', 'leão', 'leao', 'lipton', 'tang', 'clight', 'gatorade', 'powerade', 'red bull',
        'monster', 'rockstar', 'café', 'cafe', 'expresso', 'cappuccino', 'latte', 'mocha'
    ];
    
    // Verificar se contém alguma marca de líquido
    foreach ($liquid_brands as $brand) {
        if (strpos($name_lower, $brand) !== false) {
            return 'líquido';
        }
    }
    
    // Líquidos - expandir lista de palavras-chave
    if (strpos($name_lower, 'óleo') !== false || 
        strpos($name_lower, 'azeite') !== false ||
        strpos($name_lower, 'vinagre') !== false ||
        strpos($name_lower, 'leite') !== false ||
        strpos($name_lower, 'suco') !== false ||
        strpos($name_lower, 'água') !== false ||
        strpos($name_lower, 'agua') !== false ||
        strpos($name_lower, 'refrigerante') !== false ||
        strpos($name_lower, 'cerveja') !== false ||
        strpos($name_lower, 'vinho') !== false ||
        strpos($name_lower, 'café') !== false ||
        strpos($name_lower, 'cafe') !== false ||
        strpos($name_lower, 'chá') !== false ||
        strpos($name_lower, 'cha') !== false ||
        strpos($name_lower, 'caldo') !== false ||
        strpos($name_lower, 'molho') !== false ||
        strpos($name_lower, 'azeite') !== false ||
        strpos($name_lower, 'manteiga') !== false ||
        strpos($name_lower, 'margarina') !== false ||
        strpos($name_lower, 'iogurte') !== false ||
        strpos($name_lower, 'bebida') !== false ||
        strpos($name_lower, 'líquido') !== false ||
        strpos($name_lower, 'liquido') !== false ||
        strpos($name_lower, 'drink') !== false ||
        strpos($name_lower, 'shake') !== false ||
        strpos($name_lower, 'vitamina') !== false ||
        strpos($name_lower, 'smoothie') !== false ||
        strpos($name_lower, 'néctar') !== false ||
        strpos($name_lower, 'nectar') !== false ||
        strpos($name_lower, 'polpa') !== false ||
        strpos($name_lower, 'concentrado') !== false ||
        strpos($name_lower, 'extrato') !== false ||
        strpos($name_lower, 'essência') !== false ||
        strpos($name_lower, 'essencia') !== false) {
        return 'líquido';
    }
    
    // Granulares
    if (strpos($name_lower, 'açúcar') !== false || 
        strpos($name_lower, 'acucar') !== false ||
        strpos($name_lower, 'farinha') !== false ||
        strpos($name_lower, 'arroz') !== false ||
        strpos($name_lower, 'feijão') !== false ||
        strpos($name_lower, 'feijao') !== false ||
        strpos($name_lower, 'sal') !== false ||
        strpos($name_lower, 'açúcar') !== false ||
        strpos($name_lower, 'macarrão') !== false ||
        strpos($name_lower, 'macarrao') !== false ||
        strpos($name_lower, 'pão') !== false ||
        strpos($name_lower, 'pao') !== false ||
        strpos($name_lower, 'cereal') !== false ||
        strpos($name_lower, 'aveia') !== false ||
        strpos($name_lower, 'trigo') !== false ||
        strpos($name_lower, 'milho') !== false ||
        strpos($name_lower, 'soja') !== false) {
        return 'granular';
    }
    
    // Frutas
    if (strpos($name_lower, 'maçã') !== false || 
        strpos($name_lower, 'maca') !== false ||
        strpos($name_lower, 'banana') !== false ||
        strpos($name_lower, 'laranja') !== false ||
        strpos($name_lower, 'tomate') !== false ||
        strpos($name_lower, 'fruta') !== false ||
        strpos($name_lower, 'uva') !== false ||
        strpos($name_lower, 'morango') !== false ||
        strpos($name_lower, 'abacaxi') !== false ||
        strpos($name_lower, 'manga') !== false ||
        strpos($name_lower, 'limão') !== false ||
        strpos($name_lower, 'limao') !== false) {
        return 'fruta';
    }
    
    // Proteínas
    if (strpos($name_lower, 'carne') !== false || 
        strpos($name_lower, 'frango') !== false ||
        strpos($name_lower, 'peixe') !== false ||
        strpos($name_lower, 'ovo') !== false ||
        strpos($name_lower, 'queijo') !== false ||
        strpos($name_lower, 'presunto') !== false ||
        strpos($name_lower, 'bacon') !== false ||
        strpos($name_lower, 'salsicha') !== false ||
        strpos($name_lower, 'linguiça') !== false ||
        strpos($name_lower, 'linguiça') !== false ||
        strpos($name_lower, 'peito') !== false ||
        strpos($name_lower, 'coxa') !== false ||
        strpos($name_lower, 'sobrecoxa') !== false) {
        return 'proteina';
    }
    
    // Padrão: granular
    return 'granular';
}

$total_added = 0;
$total_skipped = 0;

foreach ($foods as $food) {
    $food_type = detectFoodType($food['name']);
    $conversions = $default_conversions[$food_type];
    
    echo "Processando: {$food['name']} (tipo: {$food_type})\n";
    
    $is_first = true;
    foreach ($conversions as $unit_abbr => $conversion) {
        if (!isset($unit_ids[$unit_abbr])) {
            echo "  ⚠️  Unidade '{$unit_abbr}' não encontrada, pulando...\n";
            continue;
        }
        
        $unit_id = $unit_ids[$unit_abbr];
        $factor = $conversion['factor'];
        $unit = $conversion['unit'];
        $is_default = $is_first; // Primeira unidade é padrão
        
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

echo "\n=== VERIFICAÇÃO ===\n";
$verify_sql = "SELECT COUNT(*) as total FROM v_food_units_complete";
$verify_result = $conn->query($verify_sql);
$total_conversions = $verify_result->fetch_assoc()['total'];
echo "Total de conversões no sistema: {$total_conversions}\n";

echo "\n✅ Processo concluído!\n";
?>
