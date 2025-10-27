<?php
// FIX_SEARCH_ONLY_CLASSIFIED.php - Corrigir busca para mostrar apenas alimentos classificados

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "🔧 CORRIGINDO BUSCA PARA MOSTRAR APENAS ALIMENTOS CLASSIFICADOS 🔧\n\n";

// Verificar quantos alimentos têm unidades
$count_with_units = $conn->query("SELECT COUNT(DISTINCT food_id) as count FROM sf_food_units")->fetch_assoc()['count'];
$count_total = $conn->query("SELECT COUNT(*) as count FROM sf_food_items")->fetch_assoc()['count'];

echo "Alimentos com unidades: {$count_with_units}\n";
echo "Total de alimentos: {$count_total}\n\n";

if ($count_with_units == 0) {
    echo "✅ Perfeito! Nenhum alimento tem unidades, então a busca deve estar vazia.\n";
    echo "O problema pode estar no JavaScript usando unidades padrão como fallback.\n\n";
} else {
    echo "⚠️ Ainda há {$count_with_units} alimentos com unidades. Vamos verificar quais são:\n";
    
    $sql = "SELECT fi.name_pt, COUNT(fu.id) as unit_count 
            FROM sf_food_items fi 
            JOIN sf_food_units fu ON fi.id = fu.food_id 
            GROUP BY fi.id, fi.name_pt 
            ORDER BY unit_count DESC 
            LIMIT 10";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['name_pt']}: {$row['unit_count']} unidades\n";
    }
}

echo "\n=== VERIFICAÇÃO DO JAVASCRIPT ===\n";
echo "O problema pode estar no arquivo assets/js/add_food_logic.js\n";
echo "Ele está usando unidades padrão quando não encontra unidades no banco.\n";
echo "Vamos verificar se o fallback está sendo usado incorretamente.\n";

$conn->close();
?>
