<?php
// Define o tipo de conteúdo da resposta como JSON
header('Content-Type: application/json');

// Inclui os arquivos necessários
require_once '../includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

// Pega o corpo da requisição POST (que será um JSON)
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

$token = $data['token'] ?? null;

// Verifica se o token foi enviado
if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Token não fornecido.']);
    exit();
}

// Prepara a consulta para buscar o usuário pelo token
$stmt = $conn->prepare("SELECT id, email, name FROM sf_users WHERE auth_token = ? AND auth_token_expires_at > NOW()");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Erro de servidor.']);
    exit();
}

$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Se encontrou um usuário com o token válido...
if ($user) {
    // ...inicia uma sessão para ele!
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    session_regenerate_id(true); // Segurança extra
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['user_name'] = $user['name'];

    // Responde que deu tudo certo
    echo json_encode(['success' => true]);
} else {
    // Responde que o token é inválido
    echo json_encode(['success' => false, 'message' => 'Sessão inválida ou expirada.']);
}

exit();
?>