<?php
// admin/ajax_users.php - AJAX handler para toggle de status de usuários

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
$conn = require __DIR__ . '/../includes/db.php';

requireAdminLogin();

header('Content-Type: application/json');

// Verificar se a coluna status existe, se não, criar
$check_status = $conn->query("SHOW COLUMNS FROM sf_users LIKE 'status'");
$has_status_column = $check_status && $check_status->num_rows > 0;
if ($check_status) $check_status->free();

if (!$has_status_column) {
    // Adicionar coluna status se não existir
    $conn->query("ALTER TABLE sf_users ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER points");
    $has_status_column = true;
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['action'])) {
        throw new Exception('Ação não especificada');
    }
    
    $action = $data['action'];
    
    switch ($action) {
        case 'toggle_status':
            toggleUserStatus($data);
            break;
        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}

function toggleUserStatus($data) {
    global $conn, $has_status_column;
    
    $user_id = (int)($data['user_id'] ?? 0);
    $new_status = $data['status'] ?? 'active';
    
    if ($user_id <= 0) {
        throw new Exception('ID do usuário inválido');
    }
    
    if (!in_array($new_status, ['active', 'inactive'])) {
        throw new Exception('Status inválido');
    }
    
    // Verificar se o usuário existe
    $stmt_check = $conn->prepare("SELECT id FROM sf_users WHERE id = ?");
    $stmt_check->bind_param("i", $user_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Usuário não encontrado');
    }
    $stmt_check->close();
    
    // Atualizar status
    $stmt = $conn->prepare("UPDATE sf_users SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $user_id);
    
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Erro ao atualizar status: ' . $error);
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Status atualizado com sucesso!',
        'status' => $new_status
    ]);
}
