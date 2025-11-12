<?php
define('IS_AJAX_REQUEST', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminLogin();
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

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
} catch (mysqli_sql_exception $e) {
    error_log("Erro SQL em ajax_challenge_groups.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao processar solicitação: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    error_log("Erro em ajax_challenge_groups.php: " . $e->getMessage());
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
    if (!$stmt_check) {
        throw new Exception('Erro ao preparar query: ' . $conn->error);
    }
    $stmt_check->bind_param("ii", $challenge_id, $admin_id);
    if (!$stmt_check->execute()) {
        $error = $stmt_check->error;
        $stmt_check->close();
        throw new Exception('Erro ao executar query: ' . $error);
    }
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Desafio não encontrado ou sem permissão');
    }
    
    $challenge_info = $result_check->fetch_assoc();
    if (!$challenge_info) {
        $stmt_check->close();
        throw new Exception('Erro ao buscar informações do desafio');
    }
    
    $goals = json_decode($challenge_info['goals'] ?? '[]', true);
    if (!is_array($goals)) {
        $goals = [];
    }
    $stmt_check->close();
    
    // Data atual
    $current_date = date('Y-m-d');
    
    // Buscar participantes com progresso
    $stmt_participants = $conn->prepare("
        SELECT 
            u.id,
            u.name,
            u.email,
            up.profile_image_filename
        FROM sf_challenge_group_members cgm
        INNER JOIN sf_users u ON cgm.user_id = u.id
        LEFT JOIN sf_user_profiles up ON u.id = up.user_id
        WHERE cgm.group_id = ?
        ORDER BY u.name ASC
    ");
    if (!$stmt_participants) {
        throw new Exception('Erro ao preparar query de participantes: ' . $conn->error);
    }
    $stmt_participants->bind_param("i", $challenge_id);
    if (!$stmt_participants->execute()) {
        $error = $stmt_participants->error;
        $stmt_participants->close();
        throw new Exception('Erro ao executar query de participantes: ' . $error);
    }
    $result_participants = $stmt_participants->get_result();
    
    $participants = [];
    
    while ($user = $result_participants->fetch_assoc()) {
        if (!$user || !isset($user['id'])) {
            continue;
        }
        
        $user_id = (int)$user['id'];
        
        // Buscar pontos totais do usuário no desafio
        $total_points = 0;
        $active_days = 0;
        $stmt_total = $conn->prepare("
            SELECT 
                COALESCE(SUM(points_earned), 0) as total_points,
                COUNT(DISTINCT date) as active_days
            FROM sf_challenge_group_daily_progress
            WHERE challenge_group_id = ? AND user_id = ?
        ");
        if ($stmt_total) {
            $stmt_total->bind_param("ii", $challenge_id, $user_id);
            if ($stmt_total->execute()) {
                $result_total = $stmt_total->get_result();
                $total_data = $result_total->fetch_assoc();
                if ($total_data) {
                    $total_points = (int)($total_data['total_points'] ?? 0);
                    $active_days = (int)($total_data['active_days'] ?? 0);
                }
            }
            $stmt_total->close();
        }
        
        // Buscar progresso de hoje
        $today_calories = 0;
        $today_water = 0;
        $today_exercise = 0;
        $today_sleep = 0;
        $today_points = 0;
        $points_breakdown = [];
        
        $stmt_today = $conn->prepare("
            SELECT 
                calories_consumed,
                water_ml,
                exercise_minutes,
                sleep_hours,
                points_earned,
                points_breakdown
            FROM sf_challenge_group_daily_progress
            WHERE challenge_group_id = ? AND user_id = ? AND date = ?
        ");
        if ($stmt_today) {
            $stmt_today->bind_param("iis", $challenge_id, $user_id, $current_date);
            if ($stmt_today->execute()) {
                $result_today = $stmt_today->get_result();
                $today_data = $result_today->fetch_assoc();
                if ($today_data) {
                    $today_calories = (float)($today_data['calories_consumed'] ?? 0);
                    $today_water = (float)($today_data['water_ml'] ?? 0);
                    $today_exercise = (int)($today_data['exercise_minutes'] ?? 0);
                    $today_sleep = (float)($today_data['sleep_hours'] ?? 0);
                    $today_points = (int)($today_data['points_earned'] ?? 0);
                    if (!empty($today_data['points_breakdown'])) {
                        $decoded = json_decode($today_data['points_breakdown'], true);
                        if (is_array($decoded)) {
                            $points_breakdown = $decoded;
                        }
                    }
                }
            }
            $stmt_today->close();
        }
        
        $participants[] = [
            'rank' => 0, // Será calculado depois
            'user_id' => $user_id,
            'name' => $user['name'] ?? '',
            'email' => $user['email'] ?? '',
            'profile_image' => $user['profile_image_filename'] ?? null,
            'total_points' => $total_points,
            'active_days' => $active_days,
            'today' => [
                'calories' => $today_calories,
                'water' => $today_water,
                'exercise' => $today_exercise,
                'sleep' => $today_sleep,
                'points' => $today_points,
                'points_breakdown' => $points_breakdown
            ]
        ];
    }
    $stmt_participants->close();
    
    // Ordenar por pontos totais e atribuir ranking
    usort($participants, function($a, $b) {
        if ($a['total_points'] == $b['total_points']) {
            return strcmp($a['name'], $b['name']);
        }
        return $b['total_points'] - $a['total_points'];
    });
    
    // Atribuir ranking
    foreach ($participants as $index => &$participant) {
        $participant['rank'] = $index + 1;
    }
    unset($participant);
    
    echo json_encode([
        'success' => true,
        'challenge' => [
            'id' => $challenge_id,
            'name' => $challenge_info['name'] ?? '',
            'goals' => $goals,
            'start_date' => $challenge_info['start_date'] ?? '',
            'end_date' => $challenge_info['end_date'] ?? ''
        ],
        'participants' => $participants,
        'current_date' => $current_date
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
    $participants_input = $data['participants'] ?? [];
    
    if (!is_array($participants_input)) {
        $participants_input = [];
    }
    
    // Normalizar e remover duplicados
    $participants = [];
    foreach ($participants_input as $participant_id) {
        $participant_id = (int)$participant_id;
        if ($participant_id > 0 && !in_array($participant_id, $participants, true)) {
            $participants[] = $participant_id;
        }
    }
    
    // Validações
    if (empty($name)) {
        throw new Exception('Nome do desafio é obrigatório');
    }
    
    if (empty($start_date) || empty($end_date)) {
        throw new Exception('Datas de início e fim são obrigatórias');
    }
    
    // Converter datas - aceitar tanto Y-m-d quanto d/m/Y
    $start_date_obj = null;
    $end_date_obj = null;
    
    // Tentar formato Y-m-d primeiro (formato que o frontend envia)
    $start_date_obj = DateTime::createFromFormat('Y-m-d', $start_date);
    $end_date_obj = DateTime::createFromFormat('Y-m-d', $end_date);
    
    // Se não funcionar, tentar formato d/m/Y
    if (!$start_date_obj) {
        $start_date_obj = DateTime::createFromFormat('d/m/Y', $start_date);
    }
    if (!$end_date_obj) {
        $end_date_obj = DateTime::createFromFormat('d/m/Y', $end_date);
    }
    
    // Se ainda não funcionar, tentar outros formatos comuns
    if (!$start_date_obj) {
        $start_date_obj = DateTime::createFromFormat('Y/m/d', $start_date);
    }
    if (!$end_date_obj) {
        $end_date_obj = DateTime::createFromFormat('Y/m/d', $end_date);
    }
    
    // Validar se as datas foram parseadas corretamente
    if (!$start_date_obj || !$end_date_obj) {
        error_log("Erro ao parsear datas - start_date: '$start_date', end_date: '$end_date'");
        throw new Exception('Formato de data inválido. Use dd/mm/aaaa ou YYYY-mm-dd');
    }
    
    // Determinar status adequado com base nas datas (respeitando inativo)
    $status = calculateChallengeStatusForDates($status, $start_date_obj, $end_date_obj);
    
    // Converter para formato Y-m-d para armazenar no banco
    $start_date = $start_date_obj->format('Y-m-d');
    $end_date = $end_date_obj->format('Y-m-d');
    
    // Validar se as datas são válidas (não são datas inválidas como 31/02)
    $errors = DateTime::getLastErrors();
    if ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
        throw new Exception('Datas inválidas. Verifique se as datas estão corretas.');
    }
    
    if ($start_date > $end_date) {
        throw new Exception('Data de início deve ser anterior à data de fim');
    }
    
    // Validar goals
    if (empty($goals) || !is_array($goals)) {
        throw new Exception('Pelo menos uma meta deve ser definida');
    }
    
    // Validar participantes
    if (empty($participants)) {
        throw new Exception('Pelo menos um participante deve ser selecionado');
    }
    
    // Converter goals para JSON
    $goals_json = json_encode($goals);
    
    $conn->begin_transaction();
    
    try {
        $was_edit = false;
        
        if ($challenge_id) {
            // Atualizar desafio existente
            $stmt = $conn->prepare("
                UPDATE sf_challenge_groups 
                SET name = ?, description = ?, start_date = ?, end_date = ?, status = ?, goals = ?, updated_at = NOW()
                WHERE id = ? AND created_by = ?
            ");
            $stmt->bind_param("ssssssii", $name, $description, $start_date, $end_date, $status, $goals_json, $challenge_id, $admin_id);
            $stmt->execute();
            $stmt->close();
            $was_edit = true;
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
            $stmt = $conn->prepare("INSERT INTO sf_challenge_group_members (group_id, user_id, joined_at) VALUES (?, ?, NOW())");
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
        
        // Sincronizar progresso retroativo (não bloqueia resposta se falhar)
        try {
            backfillChallengeProgress($conn, $challenge_id, $participants, $start_date, $end_date, $status);
        } catch (Exception $syncException) {
            error_log("Erro ao sincronizar progresso retroativo do desafio {$challenge_id}: " . $syncException->getMessage());
        }
        
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

function calculateChallengeStatusForDates($statusInput, DateTime $startDateObj, DateTime $endDateObj) {
    $status = is_string($statusInput) ? strtolower(trim($statusInput)) : 'scheduled';
    if ($status === '') {
        $status = 'scheduled';
    }
    
    if ($status === 'inactive') {
        return 'inactive';
    }
    
    $start = clone $startDateObj;
    $start->setTime(0, 0, 0);
    
    $end = clone $endDateObj;
    $end->setTime(0, 0, 0);
    
    $today = new DateTime('today');
    
    if ($end < $today) {
        return 'completed';
    }
    
    if ($start > $today) {
        return 'scheduled';
    }
    
    return 'active';
}

function backfillChallengeProgress($conn, $challenge_id, array $participants, $start_date, $end_date, $status = null) {
    if (!$challenge_id || empty($participants)) {
        return;
    }
    
    if (is_string($status) && strtolower($status) === 'inactive') {
        // Não sincronizar desafios inativos
        return;
    }
    
    $start = DateTime::createFromFormat('Y-m-d', $start_date);
    $end = DateTime::createFromFormat('Y-m-d', $end_date);
    
    if (!$start || !$end) {
        return;
    }
    
    $start->setTime(0, 0, 0);
    $end->setTime(0, 0, 0);
    
    $today = new DateTime('today');
    $today->setTime(0, 0, 0);
    
    if ($end > $today) {
        $end = clone $today;
    }
    
    if ($start > $end) {
        return;
    }
    
    foreach ($participants as $user_id) {
        $user_id = (int)$user_id;
        if ($user_id <= 0) {
            continue;
        }
        
        $current = clone $start;
        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            try {
                syncChallengeGroupProgress($conn, $user_id, $dateStr);
            } catch (Exception $e) {
                error_log("Erro ao sincronizar progresso para o desafio {$challenge_id}, usuário {$user_id}, data {$dateStr}: " . $e->getMessage());
            }
            $current->modify('+1 day');
        }
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
        'message' => 'Desafio deletado com sucesso!'
    ]);
}

function getChallenge($data) {
    global $conn;
    
    $admin_id = $_SESSION['admin_id'] ?? 1;
    $challenge_id = (int)($data['challenge_id'] ?? 0);
    
    if ($challenge_id <= 0) {
        throw new Exception('ID do desafio inválido');
    }
    
    $stmt = $conn->prepare("
        SELECT 
            cg.id,
            cg.name,
            cg.description,
            cg.start_date,
            cg.end_date,
            cg.status,
            cg.goals,
            cg.created_by,
            cg.created_at,
            cg.updated_at
        FROM sf_challenge_groups cg
        WHERE cg.id = ? AND cg.created_by = ?
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
    
    // Buscar participantes
    $stmt = $conn->prepare("
        SELECT user_id 
        FROM sf_challenge_group_members 
        WHERE group_id = ?
    ");
    $stmt->bind_param("i", $challenge_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $participants = [];
    while ($row = $result->fetch_assoc()) {
        $participants[] = (int)$row['user_id'];
    }
    $stmt->close();
    
    $challenge['participants'] = $participants;
    
    echo json_encode([
        'success' => true,
        'challenge' => $challenge
    ]);
}

function toggleChallengeStatus($data) {
    global $conn;
    
    $admin_id = $_SESSION['admin_id'] ?? 1;
    $challenge_id = (int)($data['challenge_id'] ?? 0);
    $new_status = $data['status'] ?? 'active';
    
    if ($challenge_id <= 0) {
        throw new Exception('ID do desafio inválido');
    }
    
    if (!in_array($new_status, ['active', 'inactive'])) {
        throw new Exception('Status inválido');
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
        'message' => 'Status atualizado com sucesso!',
        'status' => $new_status
    ]);
}

function getStats($data) {
    global $conn;
    
    $admin_id = $_SESSION['admin_id'] ?? 1;
    
    $stats = [];
    
    // Total
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
    
    $stats_by_status = [
        'active' => 0,
        'inactive' => 0,
        'completed' => 0,
        'scheduled' => 0
    ];
    
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

$conn->close();
?>
