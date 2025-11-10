<?php
// admin/ajax_challenge_groups.php - API AJAX para gerenciar grupos de desafio

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Ler dados JSON do body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$action = $data['action'] ?? '';

try {
    switch ($action) {
        case 'save':
            saveChallenge($data);
            break;
        case 'delete':
            deleteChallenge($data);
            break;
        case 'get':
            getChallenge($data);
            break;
        case 'toggle_status':
            toggleChallengeStatus($data);
            break;
        case 'get_stats':
            getStats($data);
            break;
            
        case 'get_progress':
            getChallengeProgress($data);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
            exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

function getChallengeProgress($data) {
    global $conn;
    
    $admin_id = $_SESSION['admin_id'] ?? 1;
    $challenge_id = (int)($data['challenge_id'] ?? 0);
    
    if ($challenge_id <= 0) {
        throw new Exception('ID do desafio inválido');
    }
    
    // Verificar se o desafio pertence ao admin
    $stmt_check = $conn->prepare("SELECT id, name, goals, start_date, end_date FROM sf_challenge_groups WHERE id = ? AND created_by = ?");
    $stmt_check->bind_param("ii", $challenge_id, $admin_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Desafio não encontrado ou sem permissão');
    }
    
    $challenge_info = $result_check->fetch_assoc();
    $goals = json_decode($challenge_info['goals'] ?? '[]', true);
    $stmt_check->close();
    
    // Data atual
    $current_date = date('Y-m-d');
    
    // Buscar participantes com progresso
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.name,
            u.email,
            up.profile_image_filename,
            COALESCE(SUM(cgdp.points_earned), 0) as total_points,
            COUNT(DISTINCT cgdp.date) as active_days,
            cgdp_today.calories_consumed as today_calories,
            cgdp_today.water_ml as today_water,
            cgdp_today.exercise_minutes as today_exercise,
            cgdp_today.sleep_hours as today_sleep,
            cgdp_today.points_earned as today_points,
            cgdp_today.points_breakdown as today_points_breakdown
        FROM sf_challenge_group_members cgm
        INNER JOIN sf_users u ON cgm.user_id = u.id
        LEFT JOIN sf_user_profiles up ON u.id = up.user_id
        LEFT JOIN sf_challenge_group_daily_progress cgdp ON cgdp.user_id = u.id AND cgdp.challenge_group_id = ?
        LEFT JOIN sf_challenge_group_daily_progress cgdp_today ON cgdp_today.user_id = u.id 
            AND cgdp_today.challenge_group_id = ? 
            AND cgdp_today.date = ?
        WHERE cgm.group_id = ?
        GROUP BY u.id, u.name, u.email, up.profile_image_filename, 
                 cgdp_today.calories_consumed, cgdp_today.water_ml, 
                 cgdp_today.exercise_minutes, cgdp_today.sleep_hours,
                 cgdp_today.points_earned, cgdp_today.points_breakdown
        ORDER BY total_points DESC, u.name ASC
    ");
    $stmt->bind_param("iisi", $challenge_id, $challenge_id, $current_date, $challenge_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $participants = [];
    $rank = 1;
    while ($row = $result->fetch_assoc()) {
        $points_breakdown = json_decode($row['today_points_breakdown'] ?? '{}', true);
        $participants[] = [
            'rank' => $rank++,
            'user_id' => (int)$row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'profile_image' => $row['profile_image_filename'],
            'total_points' => (int)$row['total_points'],
            'active_days' => (int)$row['active_days'],
            'today' => [
                'calories' => (float)($row['today_calories'] ?? 0),
                'water' => (float)($row['today_water'] ?? 0),
                'exercise' => (int)($row['today_exercise'] ?? 0),
                'sleep' => (float)($row['today_sleep'] ?? 0),
                'points' => (int)($row['today_points'] ?? 0),
                'points_breakdown' => $points_breakdown
            ]
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'challenge' => [
            'id' => $challenge_id,
            'name' => $challenge_info['name'],
            'goals' => $goals,
            'start_date' => $challenge_info['start_date'],
            'end_date' => $challenge_info['end_date']
        ],
        'participants' => $participants,
        'current_date' => $current_date
    ]);
}

function saveChallenge($data) {
    global $conn;
    
    // Obter admin_id da sessão
    $admin_id = $_SESSION['admin_id'] ?? null;
    
    if (!$admin_id) {
        throw new Exception('Admin não autenticado');
    }
    
    // Verificar se o admin existe na tabela sf_admins
    $stmt_check = $conn->prepare("SELECT id FROM sf_admins WHERE id = ?");
    $stmt_check->bind_param("i", $admin_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Admin não encontrado no sistema');
    }
    $stmt_check->close();
    
    $challenge_id = $data['challenge_id'] ?? null;
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $start_date = $data['start_date'] ?? '';
    $end_date = $data['end_date'] ?? '';
    $status = $data['status'] ?? 'scheduled';
    $goals = $data['goals'] ?? [];
    $participants = $data['participants'] ?? [];
    
    // Validações
    if (empty($name)) {
        throw new Exception('Nome do desafio é obrigatório');
    }
    
    if (empty($start_date) || empty($end_date)) {
        throw new Exception('Datas de início e fim são obrigatórias');
    }
    
    if (new DateTime($start_date) > new DateTime($end_date)) {
        throw new Exception('Data de início deve ser anterior à data de fim');
    }
    
    // Preparar metas JSON
    $goals_json = json_encode($goals);
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        if ($challenge_id) {
            // Atualizar desafio existente
            $stmt = $conn->prepare("
                UPDATE sf_challenge_groups 
                SET name = ?, description = ?, start_date = ?, end_date = ?, status = ?, goals = ?, updated_at = NOW()
                WHERE id = ? AND created_by = ?
            ");
            $stmt->bind_param("ssssssii", $name, $description, $start_date, $end_date, $status, $goals_json, $challenge_id, $admin_id);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                throw new Exception('Desafio não encontrado ou sem permissão para editar');
            }
            $stmt->close();
        } else {
            // Criar novo desafio
            $stmt = $conn->prepare("
                INSERT INTO sf_challenge_groups (name, description, start_date, end_date, status, goals, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->bind_param("ssssssi", $name, $description, $start_date, $end_date, $status, $goals_json, $admin_id);
            $stmt->execute();
            $challenge_id = $conn->insert_id;
            $stmt->close();
        }
        
        // Remover participantes existentes
        $stmt = $conn->prepare("DELETE FROM sf_challenge_group_members WHERE group_id = ?");
        $stmt->bind_param("i", $challenge_id);
        $stmt->execute();
        $stmt->close();
        
        // Adicionar novos participantes
        if (!empty($participants)) {
            $stmt = $conn->prepare("
                INSERT INTO sf_challenge_group_members (group_id, user_id, joined_at, status)
                VALUES (?, ?, NOW(), 'active')
            ");
            foreach ($participants as $user_id) {
                $user_id = (int)$user_id;
                if ($user_id > 0) {
                    $stmt->bind_param("ii", $challenge_id, $user_id);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }
        
        // Confirmar transação
        $conn->commit();
        
        $was_edit = isset($data['challenge_id']) && !empty($data['challenge_id']);
        
        echo json_encode([
            'success' => true,
            'message' => $was_edit ? 'Desafio atualizado com sucesso!' : 'Desafio criado com sucesso!',
            'challenge_id' => $challenge_id
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function deleteChallenge($data) {
    global $conn;
    
    $admin_id = $_SESSION['admin_id'] ?? 1;
    $challenge_id = (int)($data['challenge_id'] ?? 0);
    
    if ($challenge_id <= 0) {
        throw new Exception('ID do desafio inválido');
    }
    
    // Verificar se o desafio pertence ao admin
    $stmt = $conn->prepare("SELECT id FROM sf_challenge_groups WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ii", $challenge_id, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Desafio não encontrado ou sem permissão');
    }
    $stmt->close();
    
    // Deletar (cascade vai deletar membros e progresso)
    $stmt = $conn->prepare("DELETE FROM sf_challenge_groups WHERE id = ?");
    $stmt->bind_param("i", $challenge_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Desafio excluído com sucesso!'
    ]);
}

function getChallenge($data) {
    global $conn;
    
    $admin_id = $_SESSION['admin_id'] ?? 1;
    $challenge_id = (int)($data['challenge_id'] ?? 0);
    
    if ($challenge_id <= 0) {
        throw new Exception('ID do desafio inválido');
    }
    
    // Buscar desafio
    $stmt = $conn->prepare("
        SELECT cg.*, 
               GROUP_CONCAT(DISTINCT cgm.user_id) as member_ids
        FROM sf_challenge_groups cg
        LEFT JOIN sf_challenge_group_members cgm ON cg.id = cgm.group_id
        WHERE cg.id = ? AND cg.created_by = ?
        GROUP BY cg.id
    ");
    $stmt->bind_param("ii", $challenge_id, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Desafio não encontrado');
    }
    
    $challenge = $result->fetch_assoc();
    $stmt->close();
    
    // Decodificar goals JSON
    $challenge['goals'] = json_decode($challenge['goals'] ?? '[]', true);
    $challenge['member_ids'] = $challenge['member_ids'] ? explode(',', $challenge['member_ids']) : [];
    
    echo json_encode([
        'success' => true,
        'challenge' => $challenge
    ]);
}

function toggleChallengeStatus($data) {
    global $conn;
    
    $admin_id = $_SESSION['admin_id'] ?? 1;
    $challenge_id = (int)($data['challenge_id'] ?? 0);
    $new_status = $data['status'] ?? 'inactive';
    
    // Validar status
    $allowed_statuses = ['active', 'inactive', 'completed', 'scheduled'];
    if (!in_array($new_status, $allowed_statuses)) {
        throw new Exception('Status inválido');
    }
    
    if ($challenge_id <= 0) {
        throw new Exception('ID do desafio inválido');
    }
    
    // Verificar se o desafio pertence ao admin
    $stmt = $conn->prepare("SELECT id FROM sf_challenge_groups WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ii", $challenge_id, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Desafio não encontrado ou sem permissão');
    }
    $stmt->close();
    
    // Atualizar status
    $stmt = $conn->prepare("UPDATE sf_challenge_groups SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $new_status, $challenge_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Status do desafio atualizado com sucesso!',
        'status' => $new_status
    ]);
}

function getStats($data) {
    global $conn;
    
    $admin_id = $_SESSION['admin_id'] ?? 1;
    
    // Total de grupos
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sf_challenge_groups WHERE created_by = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total'] = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Por status
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) as count 
        FROM sf_challenge_groups 
        WHERE created_by = ?
        GROUP BY status
    ");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stats_by_status = ['active' => 0, 'inactive' => 0, 'completed' => 0, 'scheduled' => 0];
    while ($row = $result->fetch_assoc()) {
        $stats_by_status[$row['status']] = $row['count'];
    }
    $stmt->close();
    
    $stats['active'] = $stats_by_status['active'];
    $stats['completed'] = $stats_by_status['completed'];
    $stats['scheduled'] = $stats_by_status['scheduled'];
    $stats['inactive'] = $stats_by_status['inactive'];
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}

