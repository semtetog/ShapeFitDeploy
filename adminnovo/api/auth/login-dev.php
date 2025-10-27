<?php
// adminnovo/api/auth/login-dev.php - Login de desenvolvimento

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

// Login de desenvolvimento - aceita admin/admin
if ($username === 'admin' && $password === 'admin') {
    echo json_encode([
        'success' => true,
        'message' => 'Login realizado com sucesso',
        'admin' => [
            'id' => 1,
            'name' => 'Administrador',
            'username' => 'admin'
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Credenciais inválidas']);
}
?>
