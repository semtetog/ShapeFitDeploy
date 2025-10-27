<?php
header('Content-Type: application/json');
require_once '../includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/auth.php'; // Para usar getUserByAuthToken e regenerateSession

// Pega o token enviado pelo JavaScript
$data = json_decode(file_get_contents('php://input'), true);
$token = $data['token'] ?? null;

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Token não fornecido.']);
    exit();
}

$user = getUserByAuthToken($conn, $token);

if ($user) {
    // Token é válido! CRIAMOS A SESSÃO AQUI.
    if (session_status() == PHP_SESSION_NONE) { session_start(); }
    regenerateSession();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['user_name'] = $user['name'];
    
    echo json_encode(['success' => true]);
} else {
    // Token inválido ou expirado
    echo json_encode(['success' => false, 'message' => 'Sessão inválida.']);
}
exit();
?>