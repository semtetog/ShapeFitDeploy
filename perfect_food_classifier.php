<?php
// perfect_food_classifier.php - Script PERFEITO para classificar CADA alimento individualmente

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "=== CLASSIFICADOR PERFEITO DE ALIMENTOS ===\n\n";

// Função ULTRA MELHORADA para detectar tipo de alimento
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

// Buscar todos os alimentos
$sql = "SELECT id, name_pt as name FROM sf_food_items ORDER BY name_pt";
$result = $conn->query($sql);
$foods = $result->fetch_all(MYSQLI_ASSOC);

echo "Alimentos encontrados: " . count($foods) . "\n\n";

$liquid_count = 0;
$granular_count = 0;
$fruit_count = 0;
$protein_count = 0;

foreach ($foods as $food) {
    $food_type = detectFoodTypePerfect($food['name']);
    
    // Atualizar no banco
    $update_sql = "UPDATE sf_food_items SET food_type = ? WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("si", $food_type, $food['id']);
    $stmt->execute();
    
    // Contar tipos
    switch ($food_type) {
        case 'líquido': $liquid_count++; break;
        case 'granular': $granular_count++; break;
        case 'fruta': $fruit_count++; break;
        case 'proteina': $protein_count++; break;
    }
    
    echo "✅ {$food['name']} -> {$food_type}\n";
}

echo "\n=== RESUMO ===\n";
echo "Líquidos: {$liquid_count}\n";
echo "Granulares: {$granular_count}\n";
echo "Frutas: {$fruit_count}\n";
echo "Proteínas: {$protein_count}\n";
echo "Total: " . count($foods) . "\n";

echo "\n✅ Classificação concluída!\n";
?>
