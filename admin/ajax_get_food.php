<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

header('Content-Type: application/json');

$food_id = (int)($_GET['id'] ?? 0);

if ($food_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$stmt = $conn->prepare("SELECT * FROM sf_food_items WHERE id = ?");
$stmt->bind_param("i", $food_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Alimento não encontrado']);
    exit;
}

$food = $result->fetch_assoc();
$stmt->close();

echo json_encode(['success' => true, 'food' => $food]);
?>

