<?php
header('Content-Type: application/json');

// --- CARREGAMENTO E SEGURANÇA ---
require_once '../includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin(); // Garante que apenas usuários logados podem salvar um token
require_once APP_ROOT_PATH . '/includes/db.php';

// Valida o token CSRF enviado pelo JavaScript
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Validação de segurança falhou.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$push_token = $_POST['push_token'] ?? null;

if (empty($push_token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token de notificação não fornecido.']);
    exit();
}

// Salva o token no banco de dados
$stmt = $conn->prepare("UPDATE sf_users SET push_token = ? WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("si", $push_token, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Token salvo.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar o token no banco de dados.']);
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor.']);
}
?>