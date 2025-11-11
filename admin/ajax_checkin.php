<?php
// admin/ajax_checkin.php - AJAX endpoint para gerenciamento de check-in

define('IS_AJAX_REQUEST', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth_admin.php';

requireAdminLogin();
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Se não veio JSON, tentar POST/GET
if (!$data) {
    $data = $_POST;
}

$action = $data['action'] ?? $_GET['action'] ?? '';
$admin_id = $_SESSION['admin_id'] ?? null;

if (!$admin_id) {
    echo json_encode(['success' => false, 'message' => 'Admin não autenticado']);
    exit;
}

try {
    switch ($action) {
        case 'save':
            saveCheckin($data, $admin_id);
            break;
        case 'delete':
            deleteCheckin($data, $admin_id);
            break;
        case 'get':
            getCheckin($data, $admin_id);
            break;
        case 'get_stats':
            getCheckinStats($admin_id);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
            exit;
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro SQL em ajax_checkin.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao processar solicitação: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    error_log("Erro em ajax_checkin.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

function saveCheckin($data, $admin_id) {
    global $conn;
    
    $checkin_id = (int)($data['checkin_id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $day_of_week = (int)($data['day_of_week'] ?? 0);
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] == '1' : 1;
    $questions = json_decode($data['questions'] ?? '[]', true);
    $distribution = json_decode($data['distribution'] ?? '{}', true);
    
    if (empty($name)) {
        throw new Exception('Nome do check-in é obrigatório');
    }
    
    if ($day_of_week < 0 || $day_of_week > 6) {
        throw new Exception('Dia da semana inválido');
    }
    
    if (!is_array($questions) || empty($questions)) {
        throw new Exception('É necessário pelo menos uma pergunta');
    }
    
    $conn->begin_transaction();
    
    try {
        if ($checkin_id > 0) {
            // Atualizar check-in existente
            $stmt = $conn->prepare("UPDATE sf_checkin_configs SET name = ?, description = ?, day_of_week = ?, is_active = ?, updated_at = NOW() WHERE id = ? AND admin_id = ?");
            if (!$stmt) {
                throw new Exception('Erro ao preparar query: ' . $conn->error);
            }
            $stmt->bind_param("ssiiii", $name, $description, $day_of_week, $is_active, $checkin_id, $admin_id);
            if (!$stmt->execute()) {
                throw new Exception('Erro ao atualizar check-in: ' . $stmt->error);
            }
            $stmt->close();
        } else {
            // Criar novo check-in
            $stmt = $conn->prepare("INSERT INTO sf_checkin_configs (admin_id, name, description, day_of_week, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            if (!$stmt) {
                throw new Exception('Erro ao preparar query: ' . $conn->error);
            }
            $stmt->bind_param("issii", $admin_id, $name, $description, $day_of_week, $is_active);
            if (!$stmt->execute()) {
                throw new Exception('Erro ao criar check-in: ' . $stmt->error);
            }
            $checkin_id = $conn->insert_id;
            $stmt->close();
        }
        
        // Remover perguntas antigas
        $stmt_delete = $conn->prepare("DELETE FROM sf_checkin_questions WHERE config_id = ?");
        $stmt_delete->bind_param("i", $checkin_id);
        $stmt_delete->execute();
        $stmt_delete->close();
        
        // Adicionar novas perguntas
        $stmt_insert = $conn->prepare("INSERT INTO sf_checkin_questions (config_id, question_text, question_type, options, order_index, is_required, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        foreach ($questions as $idx => $question) {
            $question_text = trim($question['question_text'] ?? '');
            if (empty($question_text)) {
                continue;
            }
            
            $question_type = $question['question_type'] ?? 'text';
            if (!in_array($question_type, ['text', 'multiple_choice', 'scale'])) {
                $question_type = 'text';
            }
            
            $options = null;
            if (!empty($question['options'])) {
                $options = is_string($question['options']) ? $question['options'] : json_encode($question['options']);
            }
            
            $order_index = (int)($question['order_index'] ?? $idx);
            $is_required = isset($question['is_required']) ? (int)$question['is_required'] : 1;
            
            $stmt_insert->bind_param("isssii", $checkin_id, $question_text, $question_type, $options, $order_index, $is_required);
            $stmt_insert->execute();
        }
        $stmt_insert->close();
        
        // Remover distribuições antigas
        $stmt_delete = $conn->prepare("DELETE FROM sf_checkin_distribution WHERE config_id = ?");
        $stmt_delete->bind_param("i", $checkin_id);
        $stmt_delete->execute();
        $stmt_delete->close();
        
        // Adicionar novas distribuições
        $stmt_insert = $conn->prepare("INSERT INTO sf_checkin_distribution (config_id, target_type, target_id, created_at) VALUES (?, ?, ?, NOW())");
        
        // Grupos
        if (!empty($distribution['groups']) && is_array($distribution['groups'])) {
            $target_type = 'group';
            foreach ($distribution['groups'] as $group_id) {
                $group_id = (int)$group_id;
                if ($group_id > 0) {
                    $stmt_insert->bind_param("isi", $checkin_id, $target_type, $group_id);
                    $stmt_insert->execute();
                }
            }
        }
        
        // Usuários
        if (!empty($distribution['users']) && is_array($distribution['users'])) {
            $target_type = 'user';
            foreach ($distribution['users'] as $user_id) {
                $user_id = (int)$user_id;
                if ($user_id > 0) {
                    $stmt_insert->bind_param("isi", $checkin_id, $target_type, $user_id);
                    $stmt_insert->execute();
                }
            }
        }
        
        $stmt_insert->close();
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $checkin_id > 0 ? 'Check-in atualizado com sucesso!' : 'Check-in criado com sucesso!',
            'checkin_id' => $checkin_id
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function deleteCheckin($data, $admin_id) {
    global $conn;
    
    $checkin_id = (int)($data['checkin_id'] ?? 0);
    
    if ($checkin_id <= 0) {
        throw new Exception('ID do check-in inválido');
    }
    
    // Verificar se o check-in pertence ao admin
    $stmt_check = $conn->prepare("SELECT id FROM sf_checkin_configs WHERE id = ? AND admin_id = ?");
    $stmt_check->bind_param("ii", $checkin_id, $admin_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Check-in não encontrado ou sem permissão');
    }
    $stmt_check->close();
    
    // Deletar (cascade vai deletar perguntas, distribuições e respostas)
    $stmt = $conn->prepare("DELETE FROM sf_checkin_configs WHERE id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $checkin_id, $admin_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao excluir check-in: ' . $stmt->error);
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Check-in excluído com sucesso!'
    ]);
}

function getCheckin($data, $admin_id) {
    global $conn;
    
    $checkin_id = (int)($data['checkin_id'] ?? $_GET['checkin_id'] ?? 0);
    
    if ($checkin_id <= 0) {
        throw new Exception('ID do check-in inválido');
    }
    
    // Buscar check-in
    $stmt = $conn->prepare("SELECT * FROM sf_checkin_configs WHERE id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $checkin_id, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Check-in não encontrado');
    }
    
    $checkin = $result->fetch_assoc();
    $stmt->close();
    
    // Buscar perguntas
    $stmt = $conn->prepare("SELECT * FROM sf_checkin_questions WHERE config_id = ? ORDER BY order_index ASC");
    $stmt->bind_param("i", $checkin_id);
    $stmt->execute();
    $questions_result = $stmt->get_result();
    $questions = [];
    while ($row = $questions_result->fetch_assoc()) {
        $row['options'] = !empty($row['options']) ? json_decode($row['options'], true) : null;
        $questions[] = $row;
    }
    $stmt->close();
    
    // Buscar distribuições
    $stmt = $conn->prepare("SELECT target_type, target_id FROM sf_checkin_distribution WHERE config_id = ?");
    $stmt->bind_param("i", $checkin_id);
    $stmt->execute();
    $dist_result = $stmt->get_result();
    $distribution = ['groups' => [], 'users' => []];
    while ($row = $dist_result->fetch_assoc()) {
        $id = (int)$row['target_id'];
        if ($row['target_type'] === 'group') {
            $distribution['groups'][] = $id;
        } else {
            $distribution['users'][] = $id;
        }
    }
    $stmt->close();
    
    $checkin['questions'] = $questions;
    $checkin['distribution'] = $distribution;
    
    echo json_encode([
        'success' => true,
        'checkin' => $checkin
    ]);
}

function getCheckinStats($admin_id) {
    global $conn;
    
    $stats = [];
    
    // Total de check-ins
    $result = $conn->query("SELECT COUNT(*) as count FROM sf_checkin_configs WHERE admin_id = $admin_id");
    $stats['total'] = $result->fetch_assoc()['count'];
    
    // Ativos
    $result = $conn->query("SELECT COUNT(*) as count FROM sf_checkin_configs WHERE admin_id = $admin_id AND is_active = 1");
    $stats['active'] = $result->fetch_assoc()['count'];
    
    // Inativos
    $stats['inactive'] = $stats['total'] - $stats['active'];
    
    // Total de respostas
    $result = $conn->query("SELECT COUNT(DISTINCT user_id, config_id, DATE(submitted_at)) as count FROM sf_checkin_responses");
    $stats['responses'] = $result->fetch_assoc()['count'];
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}

