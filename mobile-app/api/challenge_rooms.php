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
            handleGetRooms($conn, $user_id);
            break;
        case 'POST':
            handleCreateRoom($conn, $user_id);
            break;
        case 'PUT':
            handleUpdateRoom($conn, $user_id);
            break;
        case 'DELETE':
            handleDeleteRoom($conn, $user_id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGetRooms($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT 
            cr.*,
            crm.joined_at,
            COALESCE(crm.total_points, 0) as total_points,
            crm.status as membership_status,
            COUNT(crm2.user_id) as total_members
        FROM sf_challenge_rooms cr
        INNER JOIN sf_challenge_room_members crm ON cr.id = crm.challenge_room_id
        LEFT JOIN sf_challenge_room_members crm2 ON cr.id = crm2.challenge_room_id
        WHERE crm.user_id = ? AND cr.status = 'active'
        GROUP BY cr.id
        ORDER BY cr.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $rooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode(['rooms' => $rooms]);
}

function handleCreateRoom($conn, $user_id) {
    // Verificar se é admin
    $stmt = $conn->prepare("SELECT id FROM sf_admins WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$admin) {
        http_response_code(403);
        echo json_encode(['error' => 'Apenas administradores podem criar salas']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = $data['name'] ?? '';
    $description = $data['description'] ?? '';
    $start_date = $data['start_date'] ?? '';
    $end_date = $data['end_date'] ?? '';
    $max_participants = $data['max_participants'] ?? 50;
    $goals = json_encode($data['goals'] ?? []);
    
    $stmt = $conn->prepare("
        INSERT INTO sf_challenge_rooms (name, description, admin_id, start_date, end_date, max_participants, goals)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssissis", $name, $description, $admin['id'], $start_date, $end_date, $max_participants, $goals);
    
    if ($stmt->execute()) {
        $room_id = $conn->insert_id;
        echo json_encode(['success' => true, 'room_id' => $room_id]);
    } else {
        throw new Exception('Erro ao criar sala');
    }
    $stmt->close();
}

function handleUpdateRoom($conn, $user_id) {
    // Verificar se é admin
    $stmt = $conn->prepare("SELECT id FROM sf_admins WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$admin) {
        http_response_code(403);
        echo json_encode(['error' => 'Apenas administradores podem editar salas']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $room_id = $data['room_id'] ?? null;
    
    if (!$room_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da sala é obrigatório']);
        return;
    }
    
    $name = $data['name'] ?? '';
    $description = $data['description'] ?? '';
    $start_date = $data['start_date'] ?? '';
    $end_date = $data['end_date'] ?? '';
    $max_participants = $data['max_participants'] ?? 50;
    $goals = json_encode($data['goals'] ?? []);
    
    $stmt = $conn->prepare("
        UPDATE sf_challenge_rooms 
        SET name = ?, description = ?, start_date = ?, end_date = ?, max_participants = ?, goals = ?
        WHERE id = ? AND admin_id = ?
    ");
    $stmt->bind_param("sssisiis", $name, $description, $start_date, $end_date, $max_participants, $goals, $room_id, $admin['id']);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Erro ao atualizar sala');
    }
    $stmt->close();
}

function handleDeleteRoom($conn, $user_id) {
    // Verificar se é admin
    $stmt = $conn->prepare("SELECT id FROM sf_admins WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$admin) {
        http_response_code(403);
        echo json_encode(['error' => 'Apenas administradores podem deletar salas']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $room_id = $data['room_id'] ?? null;
    
    if (!$room_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da sala é obrigatório']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM sf_challenge_rooms WHERE id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $room_id, $admin['id']);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Erro ao deletar sala');
    }
    $stmt->close();
}
?>
