<?php
// verify_fix.php - Script para verificar se as correções funcionaram

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "=== VERIFICAÇÃO DAS CORREÇÕES ===\n\n";

// Verificar se a coluna food_type existe
$check_column = "SHOW COLUMNS FROM sf_food_items LIKE 'food_type'";
$result = $conn->query($check_column);

if ($result->num_rows > 0) {
    echo "✅ Coluna 'food_type' existe na tabela sf_food_items\n";
} else {
    echo "❌ Coluna 'food_type' NÃO existe!\n";
    exit(1);
}

// Verificar alguns alimentos líquidos específicos
$liquid_foods = [
    'Coca-Cola',
    'Refrigerante tipo cola',
    'Refrigerante tipo guaraná',
    'Refrigerante tipo laranja',
    'Refrigerante tipo limão',
    'Refrigerante tipo água tônica',
    'Chá mate infusão 5%',
    'Chá preto infusão 5%',
    'Coco água de'
];

echo "\n=== VERIFICANDO ALIMENTOS LÍQUIDOS ===\n";

foreach ($liquid_foods as $food_name) {
    $sql = "SELECT id, name_pt, food_type FROM sf_food_items WHERE name_pt LIKE ?";
    $stmt = $conn->prepare($sql);
    $search_term = "%{$food_name}%";
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $status = ($row['food_type'] === 'líquido') ? '✅' : '❌';
        echo "{$status} {$row['name_pt']} -> {$row['food_type']}\n";
    } else {
        echo "⚠️  {$food_name} não encontrado\n";
    }
}

// Verificar conversões de um alimento líquido
echo "\n=== VERIFICANDO CONVERSÕES DE COCA-COLA ===\n";

$sql = "SELECT f.name_pt, f.food_type, u.abbreviation, fu.factor, fu.unit 
        FROM sf_food_items f 
        JOIN sf_food_units fu ON f.id = fu.food_id 
        JOIN sf_units u ON fu.unit_id = u.id 
        WHERE f.name_pt LIKE '%cola%' AND f.food_type = 'líquido'
        ORDER BY fu.is_default DESC, u.abbreviation";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "Conversões encontradas:\n";
    while ($row = $result->fetch_assoc()) {
        $default = $row['is_default'] ? ' (PADRÃO)' : '';
        echo "  - {$row['abbreviation']}: {$row['factor']}{$row['unit']}{$default}\n";
    }
} else {
    echo "❌ Nenhuma conversão encontrada para Coca-Cola!\n";
}

// Verificar se existe unidade 'l' (litro)
echo "\n=== VERIFICANDO UNIDADE LITRO ===\n";

$sql = "SELECT id, name, abbreviation FROM sf_units WHERE abbreviation = 'l'";
$result = $conn->query($sql);

if ($row = $result->fetch_assoc()) {
    echo "✅ Unidade 'Litro' encontrada: ID {$row['id']} - {$row['name']}\n";
} else {
    echo "❌ Unidade 'Litro' NÃO encontrada!\n";
}

echo "\n=== RESUMO DA VERIFICAÇÃO ===\n";
echo "Se todos os itens acima estão ✅, as correções funcionaram!\n";
echo "Agora teste no add_food_to_diary.php para ver se Coca-Cola aparece com Litro.\n";
?>
