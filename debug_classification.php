<?php
// debug_classification.php - Script para debugar a classificação

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "=== DEBUG DA CLASSIFICAÇÃO ===\n\n";

// Função de detecção (mesma do script original)
function detectFoodTypePerfect($food_name) {
    $name_lower = strtolower($food_name);
    
    // MARCAS DE LÍQUIDOS - Lista COMPLETA
    $liquid_brands = [
        'coca', 'pepsi', 'fanta', 'sprite', 'guaraná', 'guarana', 'antarctica', 
        'skol', 'brahma', 'heineken', 'stella', 'corona', 'budweiser', 'amstel',
        'devassa', 'bohemia', 'crystal', 'água', 'agua', 'indaiá', 'são lourenço',
        'sao lourenco', 'nestlé', 'nestle', 'danone', 'activia', 'danoninho',
        'yakult', 'chamyto', 'toddynho', 'nescau', 'nescafé', 'nescafe', 'pilão',
        'melitta', 'três corações', 'tres coracoes', 'leão', 'leao', 'lipton',
        'tang', 'clight', 'gatorade', 'powerade', 'red bull', 'monster', 'rockstar',
        'expresso', 'cappuccino', 'latte', 'mocha', 'ades', 'suco', 'refrigerante',
        'cerveja', 'vinho', 'café', 'cafe', 'chá', 'cha', 'mate', 'água', 'agua'
    ];
    
    // Verificar marcas de líquidos PRIMEIRO
    foreach ($liquid_brands as $brand) {
        if (strpos($name_lower, $brand) !== false) {
            return 'líquido';
        }
    }
    
    // PALAVRAS-CHAVE LÍQUIDOS
    $liquid_keywords = [
        'óleo', 'azeite', 'vinagre', 'leite', 'suco', 'água', 'agua', 'refrigerante',
        'cerveja', 'vinho', 'café', 'cafe', 'chá', 'cha', 'caldo', 'molho',
        'manteiga', 'margarina', 'iogurte', 'bebida', 'líquido', 'liquido',
        'drink', 'shake', 'vitamina', 'smoothie', 'néctar', 'nectar', 'polpa',
        'concentrado', 'extrato', 'essência', 'essencia', 'sorvete', 'gelado'
    ];
    
    foreach ($liquid_keywords as $keyword) {
        if (strpos($name_lower, $keyword) !== false) {
            return 'líquido';
        }
    }
    
    // PROTEÍNAS
    $protein_keywords = [
        'carne', 'frango', 'peixe', 'ovo', 'queijo', 'presunto', 'bacon',
        'salsicha', 'linguiça', 'linguiça', 'peito', 'coxa', 'sobrecoxa',
        'bife', 'filé', 'file', 'costela', 'pernil', 'lombo', 'músculo',
        'fígado', 'figado', 'coração', 'coracao', 'rim', 'língua', 'lingua'
    ];
    
    foreach ($protein_keywords as $keyword) {
        if (strpos($name_lower, $keyword) !== false) {
            return 'proteina';
        }
    }
    
    // FRUTAS
    $fruit_keywords = [
        'maçã', 'maca', 'banana', 'laranja', 'tomate', 'fruta', 'uva',
        'morango', 'abacaxi', 'manga', 'limão', 'limao', 'pera', 'pêssego',
        'pessego', 'kiwi', 'melão', 'melao', 'melancia', 'abacate', 'goiaba'
    ];
    
    foreach ($fruit_keywords as $keyword) {
        if (strpos($name_lower, $keyword) !== false) {
            return 'fruta';
        }
    }
    
    // GRANULARES
    $granular_keywords = [
        'açúcar', 'acucar', 'farinha', 'arroz', 'feijão', 'feijao', 'sal',
        'macarrão', 'macarrao', 'pão', 'pao', 'cereal', 'aveia', 'trigo',
        'milho', 'soja', 'biscoito', 'bolo', 'torta', 'pizza', 'sanduíche',
        'sanduiche', 'hambúrguer', 'hamburguer', 'batata', 'mandioca'
    ];
    
    foreach ($granular_keywords as $keyword) {
        if (strpos($name_lower, $keyword) !== false) {
            return 'granular';
        }
    }
    
    // Padrão: granular
    return 'granular';
}

// Testar com alguns alimentos específicos
$test_foods = [
    'Refrigerante tipo cola',
    'Refrigerante tipo guaraná', 
    'Refrigerante tipo laranja',
    'Refrigerante tipo limão',
    'Refrigerante tipo água tônica',
    'Chá mate infusão 5%',
    'Chá preto infusão 5%',
    'Coco água de',
    'Arroz integral cozido',
    'Aveia flocos crua',
    'Banana',
    'Carne bovina'
];

echo "=== TESTANDO CLASSIFICAÇÃO ===\n";
foreach ($test_foods as $food) {
    $type = detectFoodTypePerfect($food);
    echo "{$food} -> {$type}\n";
}

echo "\n=== VERIFICANDO NO BANCO ===\n";

// Buscar alguns alimentos do banco
$sql = "SELECT id, name_pt, food_type FROM sf_food_items WHERE name_pt LIKE '%refrigerante%' OR name_pt LIKE '%chá%' OR name_pt LIKE '%coco%' LIMIT 10";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    echo "Banco: {$row['name_pt']} -> {$row['food_type']}\n";
}

echo "\n=== VERIFICANDO SE HÁ ALIMENTOS LÍQUIDOS NO BANCO ===\n";

$sql = "SELECT COUNT(*) as total FROM sf_food_items WHERE food_type = 'líquido'";
$result = $conn->query($sql);
$count = $result->fetch_assoc()['total'];
echo "Total de líquidos no banco: {$count}\n";

if ($count == 0) {
    echo "\n❌ PROBLEMA: Nenhum alimento foi classificado como líquido!\n";
    echo "Vamos verificar alguns nomes específicos...\n\n";
    
    $sql = "SELECT name_pt FROM sf_food_items WHERE name_pt LIKE '%refrigerante%' LIMIT 5";
    $result = $conn->query($sql);
    
    while ($row = $result->fetch_assoc()) {
        $detected = detectFoodTypePerfect($row['name_pt']);
        echo "Testando: '{$row['name_pt']}' -> {$detected}\n";
    }
}
?>
