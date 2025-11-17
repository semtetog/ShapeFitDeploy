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
        case 'POST':
            handleCalculatePoints($conn, $user_id);
            break;
        case 'GET':
            handleGetUserPoints($conn, $user_id);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleCalculatePoints($conn, $user_id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $liga_id = $data['liga_id'] ?? null;
    $date = $data['date'] ?? date('Y-m-d');
    
    if (!$liga_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da liga é obrigatório']);
        return;
    }
    
    // Verificar se o usuário é membro da liga
    $stmt = $conn->prepare("
        SELECT id FROM sf_challenge_room_members 
        WHERE challenge_room_id = ? AND user_id = ? AND status = 'active'
    ");
    $stmt->bind_param("ii", $liga_id, $user_id);
    $stmt->execute();
    $membership = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$membership) {
        http_response_code(403);
        echo json_encode(['error' => 'Você não é membro desta liga']);
        return;
    }
    
    // Buscar regras da liga
    $stmt = $conn->prepare("SELECT goals FROM sf_challenge_rooms WHERE id = ?");
    $stmt->bind_param("i", $liga_id);
    $stmt->execute();
    $liga = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $scoring_rules = json_decode($liga['goals'], true);
    $total_points = 0;
    $points_breakdown = [];
    
    // Calcular pontos por módulo
    foreach ($scoring_rules as $module => $rules) {
        $module_points = calculateModulePoints($conn, $user_id, $date, $module, $rules);
        $total_points += $module_points;
        $points_breakdown[$module] = $module_points;
    }
    
    // Salvar progresso diário
    $stmt = $conn->prepare("
        INSERT INTO sf_challenge_daily_progress 
        (challenge_room_id, user_id, date, points_earned)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        points_earned = VALUES(points_earned)
    ");
    $stmt->bind_param("iisi", $liga_id, $user_id, $date, $total_points);
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
    $stmt->bind_param("iiii", $liga_id, $user_id, $liga_id, $user_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'total_points' => $total_points,
        'points_breakdown' => $points_breakdown,
        'message' => 'Pontos calculados com sucesso'
    ]);
}

function handleGetUserPoints($conn, $user_id) {
    $liga_id = $_GET['liga_id'] ?? null;
    $date = $_GET['date'] ?? date('Y-m-d');
    
    if (!$liga_id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID da liga é obrigatório']);
        return;
    }
    
    // Buscar pontos do usuário na liga
    $stmt = $conn->prepare("
        SELECT 
            crm.total_points,
            cdp.points_earned as daily_points,
            cdp.steps_count,
            cdp.exercise_minutes,
            cdp.water_cups,
            cdp.calories_consumed
        FROM sf_challenge_room_members crm
        LEFT JOIN sf_challenge_daily_progress cdp ON crm.challenge_room_id = cdp.challenge_room_id 
            AND crm.user_id = cdp.user_id AND cdp.date = ?
        WHERE crm.challenge_room_id = ? AND crm.user_id = ?
    ");
    $stmt->bind_param("sii", $date, $liga_id, $user_id);
    $stmt->execute();
    $points = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$points) {
        http_response_code(404);
        echo json_encode(['error' => 'Usuário não encontrado nesta liga']);
        return;
    }
    
    echo json_encode(['points' => $points]);
}

function calculateModulePoints($conn, $user_id, $date, $module, $rules) {
    $points = 0;
    
    switch ($module) {
        case 'diario':
            $points += calculateDiarioPoints($conn, $user_id, $date, $rules);
            break;
        case 'hidratacao':
            $points += calculateHidratacaoPoints($conn, $user_id, $date, $rules);
            break;
        case 'missoes':
            $points += calculateMissoesPoints($conn, $user_id, $date, $rules);
            break;
        case 'checkin':
            $points += calculateCheckinPoints($conn, $user_id, $date, $rules);
            break;
    }
    
    return $points;
}

function calculateDiarioPoints($conn, $user_id, $date, $rules) {
    $points = 0;
    
    // Verificar se preencheu o diário
    if (isset($rules['dia_preenchido']) && $rules['dia_preenchido'] > 0) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM sf_user_meal_log 
            WHERE user_id = ? AND DATE(date_consumed) = ?
        ");
        $stmt->bind_param("is", $user_id, $date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result['count'] > 0) {
            $points += $rules['dia_preenchido'];
        }
    }
    
    // Verificar meta de proteína
    if (isset($rules['meta_proteina']) && $rules['meta_proteina'] > 0) {
        // Implementar lógica de verificação de meta de proteína
        // Por enquanto, retorna pontos se preencheu o diário
        if ($points > 0) {
            $points += $rules['meta_proteina'];
        }
    }
    
    // Verificar meta de kcal
    if (isset($rules['meta_kcal']) && $rules['meta_kcal'] > 0) {
        // Implementar lógica de verificação de meta de kcal
        // Por enquanto, retorna pontos se preencheu o diário
        if ($points > 0) {
            $points += $rules['meta_kcal'];
        }
    }
    
    return $points;
}

function calculateHidratacaoPoints($conn, $user_id, $date, $rules) {
    $points = 0;
    
    if (isset($rules['meta_agua']) && $rules['meta_agua'] > 0) {
        // Buscar consumo de água do dia
        $stmt = $conn->prepare("
            SELECT water_consumed_cups 
            FROM sf_user_meal_log 
            WHERE user_id = ? AND DATE(date_consumed) = ?
            ORDER BY date_consumed DESC 
            LIMIT 1
        ");
        $stmt->bind_param("is", $user_id, $date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Verificar se atingiu a meta (assumindo meta de 8 copos)
        if ($result && $result['water_consumed_cups'] >= 8) {
            $points += $rules['meta_agua'];
        }
    }
    
    return $points;
}

function calculateMissoesPoints($conn, $user_id, $date, $rules) {
    $points = 0;
    
    if (isset($rules['por_missao']) && $rules['por_missao'] > 0) {
        // Buscar missões completadas no dia
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM sf_user_routine_log 
            WHERE user_id = ? AND DATE(completed_at) = ? AND status = 'completed'
        ");
        $stmt->bind_param("is", $user_id, $date);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $points += $result['count'] * $rules['por_missao'];
    }
    
    return $points;
}

function calculateCheckinPoints($conn, $user_id, $date, $rules) {
    $points = 0;
    
    if (isset($rules['bonus_peso']) && $rules['bonus_peso'] > 0) {
        // Verificar se fez check-in na semana
        $week_start = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
        
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM sf_user_measurements 
            WHERE user_id = ? AND DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->bind_param("iss", $user_id, $week_start, $week_end);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result['count'] > 0) {
            $points += $rules['bonus_peso'];
        }
    }
    
    return $points;
}
?>
