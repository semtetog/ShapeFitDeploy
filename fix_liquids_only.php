<?php
// fix_liquids_only.php - Corrige APENAS os alimentos líquidos

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "=== CORRIGINDO ALIMENTOS LÍQUIDOS ===\n\n";

// Lista específica de alimentos líquidos que devem ser corrigidos
$liquid_foods = [
    'Refrigerante tipo cola',
    'Refrigerante tipo guaraná', 
    'Refrigerante tipo laranja',
    'Refrigerante tipo limão',
    'Refrigerante tipo água tônica',
    'Chá mate infusão 5%',
    'Chá preto infusão 5%',
    'Coco água de',
    'Chá erva-doce infusão 5%'
];

$updated = 0;
$not_found = 0;

foreach ($liquid_foods as $food_name) {
    // Buscar o alimento
    $sql = "SELECT id, name_pt FROM sf_food_items WHERE name_pt = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $food_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Atualizar para líquido
        $update_sql = "UPDATE sf_food_items SET food_type = 'líquido' WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $row['id']);
        
        if ($update_stmt->execute()) {
            echo "✅ {$row['name_pt']} -> líquido\n";
            $updated++;
        } else {
            echo "❌ Erro ao atualizar {$row['name_pt']}\n";
        }
    } else {
        echo "⚠️  {$food_name} não encontrado\n";
        $not_found++;
    }
}

echo "\n=== RESUMO ===\n";
echo "Atualizados: {$updated}\n";
echo "Não encontrados: {$not_found}\n";

// Verificar se funcionou
echo "\n=== VERIFICAÇÃO ===\n";
$sql = "SELECT COUNT(*) as total FROM sf_food_items WHERE food_type = 'líquido'";
$result = $conn->query($sql);
$count = $result->fetch_assoc()['total'];
echo "Total de líquidos no banco: {$count}\n";

if ($count > 0) {
    echo "✅ Sucesso! Agora execute o script de conversões.\n";
} else {
    echo "❌ Ainda não funcionou. Vamos debugar mais.\n";
}

echo "\n✅ Processo concluído!\n";
?>
