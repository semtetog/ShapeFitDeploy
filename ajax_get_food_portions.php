<?php
// Arquivo: ajax_get_food_portions.php
// Busca as porções/medidas caseiras para um determinado alimento.

header('Content-Type: application/json; charset=utf-8');
require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

$food_id_string = isset($_GET['food_id']) ? trim($_GET['food_id']) : '';

if (empty($food_id_string)) {
    echo json_encode(['success' => true, 'data' => []]); // Retorna sucesso com array vazio
    exit;
}

$portions = [];
$food_db_id = null;

// Divide o ID "taco_123" ou "off_789"
$id_parts = explode('_', $food_id_string, 2);
if (count($id_parts) === 2) {
    $prefix = $id_parts[0];
    $identifier = $id_parts[1];

    // Prepara a query para encontrar o ID interno do alimento
    if ($prefix === 'taco' && is_numeric($identifier)) {
        $stmt_find = $conn->prepare("SELECT id FROM sf_food_items WHERE taco_id = ? LIMIT 1");
    } elseif ($prefix === 'off' && is_numeric($identifier)) {
        $stmt_find = $conn->prepare("SELECT id FROM sf_food_items WHERE barcode = ? LIMIT 1");
    } else {
        $stmt_find = false;
    }
    
    if ($stmt_find) {
        $stmt_find->bind_param("s", $identifier);
        $stmt_find->execute();
        $stmt_find->bind_result($found_id);
        if ($stmt_find->fetch()) {
            $food_db_id = $found_id;
        }
        $stmt_find->close();
    }
}

// Se o ID interno foi encontrado, busca as porções associadas a ele
if ($food_db_id) {
    $stmt_portions = $conn->prepare("SELECT id, description, gram_weight FROM sf_food_item_portions WHERE food_item_id = ? ORDER BY gram_weight");
    if ($stmt_portions) {
        $stmt_portions->bind_param("i", $food_db_id);
        $stmt_portions->execute();
        $result = $stmt_portions->get_result();
        while ($row = $result->fetch_assoc()) {
            $portions[] = $row;
        }
        $stmt_portions->close();
    }
}

echo json_encode(['success' => true, 'data' => $portions]);
?>