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
            handleGetProgress($conn, $user_id);
            break;
        case 'POST':
            handleUpdateProgress($conn, $user_id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleGetProgress($conn, $user_id) {
    $room_id = $_GET['room_id'] ?? null;
    $date = $_GET['date'] ?? date('Y-m-d');
    
    if (!$room_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da sala é obrigatório']);
        return;
    }
    
    // Verificar se o usuário é membro da sala
    $stmt = $conn->prepare("
        SELECT id FROM sf_challenge_room_members 
        WHERE challenge_room_id = ? AND user_id = ? AND status = 'active'
    ");
    $stmt->bind_param("ii", $room_id, $user_id);
    $stmt->execute();
    $membership = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$membership) {
        http_response_code(403);
        echo json_encode(['error' => 'Você não é membro desta sala']);
        return;
    }
    
    // Buscar progresso do dia
    $stmt = $conn->prepare("
        SELECT 
            steps_count,
            exercise_minutes,
            water_cups,
            calories_consumed,
            points_earned
        FROM sf_challenge_daily_progress
        WHERE challenge_room_id = ? AND user_id = ? AND date = ?
    ");
    $stmt->bind_param("iis", $room_id, $user_id, $date);
    $stmt->execute();
    $progress = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$progress) {
        // Criar registro vazio se não existir
        $stmt = $conn->prepare("
            INSERT INTO sf_challenge_daily_progress 
            (challenge_room_id, user_id, date, steps_count, exercise_minutes, water_cups, calories_consumed, points_earned)
            VALUES (?, ?, ?, 0, 0, 0, 0, 0)
        ");
        $stmt->bind_param("iis", $room_id, $user_id, $date);
        $stmt->execute();
        $stmt->close();
        
        $progress = [
            'steps_count' => 0,
            'exercise_minutes' => 0,
            'water_cups' => 0,
            'calories_consumed' => 0,
            'points_earned' => 0
        ];
    }
    
    echo json_encode(['progress' => $progress]);
}

function handleUpdateProgress($conn, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $room_id = $data['room_id'] ?? null;
    $date = $data['date'] ?? date('Y-m-d');
    $steps_count = $data['steps_count'] ?? 0;
    $exercise_minutes = $data['exercise_minutes'] ?? 0;
    $water_cups = $data['water_cups'] ?? 0;
    $calories_consumed = $data['calories_consumed'] ?? 0;
    
    if (!$room_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da sala é obrigatório']);
        return;
    }
    
    // Verificar se o usuário é membro da sala
    $stmt = $conn->prepare("
        SELECT id FROM sf_challenge_room_members 
        WHERE challenge_room_id = ? AND user_id = ? AND status = 'active'
    ");
    $stmt->bind_param("ii", $room_id, $user_id);
    $stmt->execute();
    $membership = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$membership) {
        http_response_code(403);
        echo json_encode(['error' => 'Você não é membro desta sala']);
        return;
    }
    
    // Calcular pontos baseado nas metas da sala
    $stmt = $conn->prepare("SELECT goals FROM sf_challenge_rooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $goals = json_decode($room['goals'], true);
    $points_earned = calculatePoints($steps_count, $exercise_minutes, $water_cups, $calories_consumed, $goals);
    
    // Atualizar ou inserir progresso
    $stmt = $conn->prepare("
        INSERT INTO sf_challenge_daily_progress 
        (challenge_room_id, user_id, date, steps_count, exercise_minutes, water_cups, calories_consumed, points_earned)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        steps_count = VALUES(steps_count),
        exercise_minutes = VALUES(exercise_minutes),
        water_cups = VALUES(water_cups),
        calories_consumed = VALUES(calories_consumed),
        points_earned = VALUES(points_earned)
    ");
    $stmt->bind_param("iisiiidi", $room_id, $user_id, $date, $steps_count, $exercise_minutes, $water_cups, $calories_consumed, $points_earned);
    $stmt->execute();
    $stmt->close();
    
    // Atualizar total de pontos do membro
    $stmt = $conn->prepare("
        UPDATE sf_challenge_room_members 
        SET total_points = (
            SELECT COALESCE(SUM(points_earned), 0) 
            FROM sf_challenge_daily_progress 
            WHERE challenge_room_id = ? AND user_id = ?
        )
        WHERE challenge_room_id = ? AND user_id = ?
    ");
    $stmt->bind_param("iiii", $room_id, $user_id, $room_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'points_earned' => $points_earned,
        'message' => 'Progresso atualizado com sucesso'
    ]);
}

function calculatePoints($steps, $exercise, $water, $calories, $goals) {
    $points = 0;
    
    // Pontos por passos
    if (isset($goals['steps']) && $goals['steps'] > 0) {
        $steps_percentage = min(($steps / $goals['steps']) * 100, 100);
        $points += round($steps_percentage * 0.1); // 10 pontos por 100% da meta
    }
    
    // Pontos por exercício
    if (isset($goals['exercise']) && $goals['exercise'] > 0) {
        $exercise_percentage = min(($exercise / $goals['exercise']) * 100, 100);
        $points += round($exercise_percentage * 0.15); // 15 pontos por 100% da meta
    }
    
    // Pontos por água
    if (isset($goals['water']) && $goals['water'] > 0) {
        $water_percentage = min(($water / $goals['water']) * 100, 100);
        $points += round($water_percentage * 0.1); // 10 pontos por 100% da meta
    }
    
    // Pontos por calorias
    if (isset($goals['calories']) && $goals['calories'] > 0) {
        $calories_percentage = min(($calories / $goals['calories']) * 100, 100);
        $points += round($calories_percentage * 0.05); // 5 pontos por 100% da meta
    }
    
    return $points;
}
?>
