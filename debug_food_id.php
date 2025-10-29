<?php
// debug_food_id.php - Debug do ID do alimento

require_once 'includes/config.php';
require_once 'includes/db.php';

$conn = require 'includes/db.php';

echo "=== DEBUG DO ID DO ALIMENTO ===\n\n";

// Buscar alimento "Abobrinha-italiana (frita)"
$stmt = $conn->prepare("SELECT id, name_pt FROM sf_food_items WHERE name_pt LIKE ?");
$search = '%Abobrinha%';
$stmt->bind_param('s', $search);
$stmt->execute();
$result = $stmt->get_result();

echo "Alimentos encontrados com 'Abobrinha':\n";
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']} - Nome: {$row['name_pt']}\n";
}

echo "\n";

// Buscar especificamente "Abobrinha-italiana (frita)"
$stmt = $conn->prepare("SELECT id, name_pt FROM sf_food_items WHERE name_pt = ?");
$exact_name = 'Abobrinha-italiana (frita)';
$stmt->bind_param('s', $exact_name);
$stmt->execute();
$result = $stmt->get_result();

echo "Alimento exato 'Abobrinha-italiana (frita)':\n";
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']} - Nome: {$row['name_pt']}\n";
}

echo "\n";

// Verificar se há conversões para este alimento
$stmt = $conn->prepare("SELECT * FROM sf_food_item_conversions WHERE food_item_id = ?");
$food_id = 623; // ID do log
$stmt->bind_param('i', $food_id);
$stmt->execute();
$result = $stmt->get_result();

echo "Conversões para food_id = 623:\n";
while ($row = $result->fetch_assoc()) {
    echo "Unit ID: {$row['unit_id']} - Factor: {$row['conversion_factor']} - Default: {$row['is_default']}\n";
}

echo "\n";

// Verificar se há conversões para ID 'taco'
$stmt = $conn->prepare("SELECT * FROM sf_food_item_conversions WHERE food_item_id = ?");
$food_id = 'taco';
$stmt->bind_param('s', $food_id);
$stmt->execute();
$result = $stmt->get_result();

echo "Conversões para food_id = 'taco':\n";
while ($row = $result->fetch_assoc()) {
    echo "Unit ID: {$row['unit_id']} - Factor: {$row['conversion_factor']} - Default: {$row['is_default']}\n";
}

echo "\n=== FIM DEBUG ===\n";
?>
