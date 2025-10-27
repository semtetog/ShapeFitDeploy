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
            handleGetLigas($conn, $user_id);
            break;
        case 'POST':
            handleCreateLiga($conn, $user_id);
            break;
        case 'PUT':
            handleUpdateLiga($conn, $user_id);
            break;
        case 'DELETE':
            handleDeleteLiga($conn, $user_id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGetLigas($conn, $user_id) {
    // Verificar se é admin
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(403);
        echo json_encode(['error' => 'Apenas administradores podem acessar']);
        return;
    }
    
    $admin_id = $_SESSION['admin_id'] ?? null;
    if (!$admin_id) {
        http_response_code(403);
        echo json_encode(['error' => 'ID do administrador não encontrado']);
        return;
    }
    
    $stmt = $conn->prepare("
        SELECT 
            cr.*,
            COUNT(crm.user_id) as total_participantes
        FROM sf_challenge_rooms cr
        LEFT JOIN sf_challenge_room_members crm ON cr.id = crm.challenge_room_id
        WHERE cr.created_by = ?
        GROUP BY cr.id
        ORDER BY cr.created_at DESC
    ");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $ligas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode(['ligas' => $ligas]);
}

function handleCreateLiga($conn, $user_id) {
    // Verificar se é admin
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(403);
        echo json_encode(['error' => 'Apenas administradores podem criar ligas']);
        return;
    }
    
    $admin_id = $_SESSION['admin_id'] ?? null;
    if (!$admin_id) {
        http_response_code(403);
        echo json_encode(['error' => 'ID do administrador não encontrado']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = $data['name'] ?? '';
    $description = $data['description'] ?? '';
    $start_date = $data['start_date'] ?? '';
    $end_date = $data['end_date'] ?? '';
    $participants = $data['participants'] ?? [];
    $scoring_modules = $data['scoring_modules'] ?? [];
    $rewards = $data['rewards'] ?? [];
    
    // Validar dados
    if (empty($name) || empty($start_date) || empty($end_date)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nome, data de início e fim são obrigatórios']);
        return;
    }
    
    // Criar a liga
    $stmt = $conn->prepare("
        INSERT INTO sf_challenge_rooms (name, description, created_by, start_date, end_date, status, goals, rewards)
        VALUES (?, ?, ?, ?, ?, 'active', ?, ?)
    ");
    
    $goals_json = json_encode($scoring_modules);
    $rewards_json = json_encode($rewards);
    
    $stmt->bind_param("ssisss", $name, $description, $admin_id, $start_date, $end_date, $goals_json, $rewards_json);
    
    if ($stmt->execute()) {
        $liga_id = $conn->insert_id;
        
        // Adicionar participantes
        if (!empty($participants)) {
            foreach ($participants as $participant_id) {
                $stmt_participant = $conn->prepare("
                    INSERT INTO sf_challenge_room_members (challenge_room_id, user_id, status)
                    VALUES (?, ?, 'active')
                ");
                $stmt_participant->bind_param("ii", $liga_id, $participant_id);
                $stmt_participant->execute();
                $stmt_participant->close();
            }
        }
        
        echo json_encode([
            'success' => true, 
            'liga_id' => $liga_id,
            'message' => 'Liga criada com sucesso'
        ]);
    } else {
        throw new Exception('Erro ao criar liga');
    }
    $stmt->close();
}

function handleUpdateLiga($conn, $user_id) {
    // Verificar se é admin
    $stmt = $conn->prepare("SELECT id FROM sf_admins WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$admin) {
        http_response_code(403);
        echo json_encode(['error' => 'Apenas administradores podem editar ligas']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $liga_id = $data['liga_id'] ?? null;
    
    if (!$liga_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da liga é obrigatório']);
        return;
    }
    
    $name = $data['name'] ?? '';
    $description = $data['description'] ?? '';
    $start_date = $data['start_date'] ?? '';
    $end_date = $data['end_date'] ?? '';
    $scoring_modules = $data['scoring_modules'] ?? [];
    $rewards = $data['rewards'] ?? [];
    
    // Atualizar a liga
    $stmt = $conn->prepare("
        UPDATE sf_challenge_rooms 
        SET name = ?, description = ?, start_date = ?, end_date = ?, goals = ?, rewards = ?
        WHERE id = ? AND created_by = ?
    ");
    
    $goals_json = json_encode($scoring_modules);
    $rewards_json = json_encode($rewards);
    
    $stmt->bind_param("sssssii", $name, $description, $start_date, $end_date, $goals_json, $rewards_json, $liga_id, $admin_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Liga atualizada com sucesso']);
    } else {
        throw new Exception('Erro ao atualizar liga');
    }
    $stmt->close();
}

function handleDeleteLiga($conn, $user_id) {
    // Verificar se é admin
    $stmt = $conn->prepare("SELECT id FROM sf_admins WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$admin) {
        http_response_code(403);
        echo json_encode(['error' => 'Apenas administradores podem deletar ligas']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $liga_id = $data['liga_id'] ?? null;
    
    if (!$liga_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da liga é obrigatório']);
        return;
    }
    
    // Deletar a liga
    $stmt = $conn->prepare("DELETE FROM sf_challenge_rooms WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ii", $liga_id, $admin_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Liga deletada com sucesso']);
    } else {
        throw new Exception('Erro ao deletar liga');
    }
    $stmt->close();
}
?>
