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
            handleGetParticipants($conn, $user_id);
            break;
        case 'POST':
            handleAddParticipant($conn, $user_id);
            break;
        case 'DELETE':
            handleRemoveParticipant($conn, $user_id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGetParticipants($conn, $user_id) {
    $liga_id = $_GET['liga_id'] ?? null;
    
    if (!$liga_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da liga é obrigatório']);
        return;
    }
    
    // Verificar se o usuário é admin da liga
    $stmt = $conn->prepare("
        SELECT cr.created_by 
        FROM sf_challenge_rooms cr
        INNER JOIN sf_admins a ON cr.created_by = a.id
        WHERE cr.id = ? AND a.user_id = ?
    ");
    $stmt->bind_param("ii", $liga_id, $user_id);
    $stmt->execute();
    $liga = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$liga) {
        http_response_code(403);
        echo json_encode(['error' => 'Apenas o administrador da liga pode ver os participantes']);
        return;
    }
    
    // Buscar participantes da liga
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
    $stmt->bind_param("i", $liga_id);
    $stmt->execute();
    $participants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode(['participants' => $participants]);
}

function handleAddParticipant($conn, $user_id) {
    // Verificar se é admin
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(403);
        echo json_encode(['error' => 'Apenas administradores podem adicionar participantes']);
        return;
    }
    
    $admin_id = $_SESSION['admin_id'] ?? null;
    if (!$admin_id) {
        http_response_code(403);
        echo json_encode(['error' => 'ID do administrador não encontrado']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $liga_id = $data['liga_id'] ?? null;
    $participant_id = $data['user_id'] ?? null;
    
    if (!$liga_id || !$participant_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da liga e do usuário são obrigatórios']);
        return;
    }
    
    // Verificar se o admin é dono da liga
    $stmt = $conn->prepare("SELECT id FROM sf_challenge_rooms WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ii", $liga_id, $admin_id);
    $stmt->execute();
    $liga = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$liga) {
        http_response_code(403);
        echo json_encode(['error' => 'Você não é o administrador desta liga']);
        return;
    }
    
    // Verificar se o usuário já é participante
    $stmt = $conn->prepare("SELECT id FROM sf_challenge_room_members WHERE challenge_room_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $liga_id, $participant_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($existing) {
        http_response_code(400);
        echo json_encode(['error' => 'Usuário já é participante desta liga']);
        return;
    }
    
    // Adicionar participante
    $stmt = $conn->prepare("
        INSERT INTO sf_challenge_room_members (challenge_room_id, user_id, status)
        VALUES (?, ?, 'active')
    ");
    $stmt->bind_param("ii", $liga_id, $participant_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Participante adicionado com sucesso']);
    } else {
        throw new Exception('Erro ao adicionar participante');
    }
    $stmt->close();
}

function handleRemoveParticipant($conn, $user_id) {
    // Verificar se é admin
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(403);
        echo json_encode(['error' => 'Apenas administradores podem remover participantes']);
        return;
    }
    
    $admin_id = $_SESSION['admin_id'] ?? null;
    if (!$admin_id) {
        http_response_code(403);
        echo json_encode(['error' => 'ID do administrador não encontrado']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $liga_id = $data['liga_id'] ?? null;
    $participant_id = $data['user_id'] ?? null;
    
    if (!$liga_id || !$participant_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da liga e do usuário são obrigatórios']);
        return;
    }
    
    // Verificar se o admin é dono da liga
    $stmt = $conn->prepare("SELECT id FROM sf_challenge_rooms WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ii", $liga_id, $admin_id);
    $stmt->execute();
    $liga = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$liga) {
        http_response_code(403);
        echo json_encode(['error' => 'Você não é o administrador desta liga']);
        return;
    }
    
    // Remover participante
    $stmt = $conn->prepare("DELETE FROM sf_challenge_room_members WHERE challenge_room_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $liga_id, $participant_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Participante removido com sucesso']);
    } else {
        throw new Exception('Erro ao remover participante');
    }
    $stmt->close();
}
?>
