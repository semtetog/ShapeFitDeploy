<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit();
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetMembers($conn, $user_id);
            break;
        case 'POST':
            handleAddMember($conn, $user_id);
            break;
        case 'DELETE':
            handleRemoveMember($conn, $user_id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGetMembers($conn, $user_id) {
    $room_id = $_GET['room_id'] ?? null;
    
    if (!$room_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da sala é obrigatório']);
        return;
    }
    
    // Verificar se o usuário é admin da sala
    $stmt = $conn->prepare("
        SELECT cr.admin_id 
        FROM sf_challenge_rooms cr
        INNER JOIN sf_admins a ON cr.admin_id = a.id
        WHERE cr.id = ? AND a.user_id = ?
    ");
    $stmt->bind_param("ii", $room_id, $user_id);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$room) {
        http_response_code(403);
        echo json_encode(['error' => 'Apenas o administrador da sala pode ver os membros']);
        return;
    }
    
    // Buscar membros da sala
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.name,
            u.email,
            up.profile_image_filename,
            crm.joined_at,
            crm.total_points,
            crm.status
        FROM sf_challenge_room_members crm
        INNER JOIN sf_users u ON crm.user_id = u.id
        LEFT JOIN sf_user_profiles up ON u.id = up.user_id
        WHERE crm.challenge_room_id = ?
        ORDER BY crm.total_points DESC
    ");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode(['members' => $members]);
}

function handleAddMember($conn, $user_id) {
    // Verificar se é admin
    $stmt = $conn->prepare("SELECT id FROM sf_admins WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$admin) {
        http_response_code(403);
        echo json_encode(['error' => 'Apenas administradores podem adicionar membros']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $room_id = $data['room_id'] ?? null;
    $member_id = $data['user_id'] ?? null;
    
    if (!$room_id || !$member_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da sala e do usuário são obrigatórios']);
        return;
    }
    
    // Verificar se o admin é dono da sala
    $stmt = $conn->prepare("SELECT id FROM sf_challenge_rooms WHERE id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $room_id, $admin['id']);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$room) {
        http_response_code(403);
        echo json_encode(['error' => 'Você não é o administrador desta sala']);
        return;
    }
    
    // Verificar se o usuário já é membro
    $stmt = $conn->prepare("SELECT id FROM sf_challenge_room_members WHERE challenge_room_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $room_id, $member_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existing) {
        http_response_code(400);
        echo json_encode(['error' => 'Usuário já é membro desta sala']);
        return;
    }
    
    // Adicionar membro
    $stmt = $conn->prepare("
        INSERT INTO sf_challenge_room_members (challenge_room_id, user_id, status)
        VALUES (?, ?, 'active')
    ");
    $stmt->bind_param("ii", $room_id, $member_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Membro adicionado com sucesso']);
    } else {
        throw new Exception('Erro ao adicionar membro');
    }
    $stmt->close();
}

function handleRemoveMember($conn, $user_id) {
    // Verificar se é admin
    $stmt = $conn->prepare("SELECT id FROM sf_admins WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$admin) {
        http_response_code(403);
        echo json_encode(['error' => 'Apenas administradores podem remover membros']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $room_id = $data['room_id'] ?? null;
    $member_id = $data['user_id'] ?? null;
    
    if (!$room_id || !$member_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da sala e do usuário são obrigatórios']);
        return;
    }
    
    // Verificar se o admin é dono da sala
    $stmt = $conn->prepare("SELECT id FROM sf_challenge_rooms WHERE id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $room_id, $admin['id']);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$room) {
        http_response_code(403);
        echo json_encode(['error' => 'Você não é o administrador desta sala']);
        return;
    }
    
    // Remover membro
    $stmt = $conn->prepare("DELETE FROM sf_challenge_room_members WHERE challenge_room_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $room_id, $member_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Membro removido com sucesso']);
    } else {
        throw new Exception('Erro ao remover membro');
    }
    $stmt->close();
}
?>
