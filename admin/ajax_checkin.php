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
        case 'delete_response':
            deleteResponse($data, $admin_id);
            break;
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
        case 'save_flow':
            saveFlow($data, $admin_id);
            break;
        case 'publish_flow':
            publishFlow($data, $admin_id);
            break;
        case 'save_block':
            saveBlock($data, $admin_id);
            break;
        case 'update_block':
            updateBlock($data, $admin_id);
            break;
        case 'delete_block':
            deleteBlock($data, $admin_id);
            break;
        case 'save_block_order':
            saveBlockOrder($data, $admin_id);
            break;
        case 'update_checkin_config':
            updateCheckinConfig($data, $admin_id);
            break;
        case 'create_checkin':
            createCheckin($data, $admin_id);
            break;
        case 'update_status':
            updateStatus($data, $admin_id);
            break;
        case 'generate_summary':
            generateSummary($data, $admin_id);
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
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 0;
    
    if (empty($name)) {
        throw new Exception('Nome do check-in é obrigatório');
    }
    
    $day_changed = false;
    
    if ($checkin_id > 0) {
        // Verificar se o dia da semana mudou
        $stmt_check = $conn->prepare("SELECT day_of_week FROM sf_checkin_configs WHERE id = ? AND admin_id = ?");
        $stmt_check->bind_param("ii", $checkin_id, $admin_id);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        
        if ($result->num_rows > 0) {
            $old_config = $result->fetch_assoc();
            $old_day_of_week = (int)($old_config['day_of_week'] ?? 0);
            $day_changed = ($old_day_of_week != $day_of_week);
        }
        $stmt_check->close();
        
        // Atualizar existente
        $stmt = $conn->prepare("UPDATE sf_checkin_configs SET name = ?, description = ?, day_of_week = ?, is_active = ?, updated_at = NOW() WHERE id = ? AND admin_id = ?");
        $stmt->bind_param("ssiiii", $name, $description, $day_of_week, $is_active, $checkin_id, $admin_id);
    } else {
        // Criar novo
        $stmt = $conn->prepare("INSERT INTO sf_checkin_configs (admin_id, name, description, day_of_week, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("issii", $admin_id, $name, $description, $day_of_week, $is_active);
    }
    
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Erro ao salvar: ' . $error);
    }
    
    if ($checkin_id === 0) {
        $checkin_id = $conn->insert_id;
    }
    
    $stmt->close();
    
    // Se o dia da semana mudou, resetar disponibilidade para a semana atual
    // IMPORTANTE: NÃO deletamos as respostas antigas para manter o histórico
    // O loadProgress já filtra respostas baseado em is_completed e semana atual
    if ($day_changed && $checkin_id > 0) {
        $current_week_start = date('Y-m-d', strtotime('sunday this week'));
        
        // Resetar is_completed e congrats_shown para a semana atual
        // As respostas antigas permanecem no banco para histórico, mas não serão carregadas
        // porque loadProgress verifica is_completed = 0 e filtra por semana atual
        $stmt_reset = $conn->prepare("UPDATE sf_checkin_availability SET is_completed = 0, congrats_shown = 0 WHERE config_id = ? AND week_date = ?");
        $stmt_reset->bind_param("is", $checkin_id, $current_week_start);
        $stmt_reset->execute();
        $stmt_reset->close();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Check-in salvo com sucesso',
        'checkin_id' => $checkin_id
    ]);
}

function deleteCheckin($data, $admin_id) {
    global $conn;
    
    $checkin_id = (int)($data['checkin_id'] ?? 0);
    
    if ($checkin_id <= 0) {
        throw new Exception('ID do check-in inválido');
    }
    
    // Verificar se pertence ao admin
    $stmt_check = $conn->prepare("SELECT id FROM sf_checkin_configs WHERE id = ? AND admin_id = ?");
    $stmt_check->bind_param("ii", $checkin_id, $admin_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Check-in não encontrado ou sem permissão');
    }
    $stmt_check->close();
    
    // Deletar (cascade vai deletar perguntas e distribuições)
    $stmt = $conn->prepare("DELETE FROM sf_checkin_configs WHERE id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $checkin_id, $admin_id);
    
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Erro ao deletar: ' . $error);
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Check-in deletado com sucesso'
    ]);
}

function getCheckin($data, $admin_id) {
    global $conn;
    
    $checkin_id = (int)($data['checkin_id'] ?? 0);
    
    if ($checkin_id <= 0) {
        throw new Exception('ID do check-in inválido');
    }
    
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
    $stmt_questions = $conn->prepare("SELECT * FROM sf_checkin_questions WHERE config_id = ? ORDER BY order_index ASC");
    $stmt_questions->bind_param("i", $checkin_id);
    $stmt_questions->execute();
    $questions_result = $stmt_questions->get_result();
    $questions = [];
    while ($q = $questions_result->fetch_assoc()) {
        $q['options'] = !empty($q['options']) ? json_decode($q['options'], true) : null;
        $questions[] = $q;
    }
    $stmt_questions->close();
    
    $checkin['questions'] = $questions;
    
    echo json_encode([
        'success' => true,
        'checkin' => $checkin
    ]);
}

function getCheckinStats($admin_id) {
    global $conn;
    
    $stats = [];
    
    // Total
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sf_checkin_configs WHERE admin_id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total'] = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Por status
    $stmt = $conn->prepare("SELECT is_active, COUNT(*) as count FROM sf_checkin_configs WHERE admin_id = ? GROUP BY is_active");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['active'] = 0;
    $stats['inactive'] = 0;
    while ($row = $result->fetch_assoc()) {
        if ($row['is_active']) {
            $stats['active'] = $row['count'];
        } else {
            $stats['inactive'] = $row['count'];
        }
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}

function saveFlow($data, $admin_id) {
    // Similar a saveBlockOrder mas para o fluxo completo
    global $conn;
    
    $config_id = (int)($data['config_id'] ?? 0);
    $blocks = json_decode($data['blocks'] ?? '[]', true);
    
    if ($config_id <= 0) {
        throw new Exception('ID do check-in inválido');
    }
    
    // Verificar se pertence ao admin
    $stmt_check = $conn->prepare("SELECT id FROM sf_checkin_configs WHERE id = ? AND admin_id = ?");
    $stmt_check->bind_param("ii", $config_id, $admin_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Check-in não encontrado ou sem permissão');
    }
    $stmt_check->close();
    
    $conn->begin_transaction();
    try {
        foreach ($blocks as $index => $block) {
            $block_id = (int)($block['id'] ?? 0);
            $order_index = (int)($block['order'] ?? $index);
            
            if ($block_id > 0) {
                $stmt = $conn->prepare("UPDATE sf_checkin_questions SET order_index = ? WHERE id = ? AND config_id = ?");
                $stmt->bind_param("iii", $order_index, $block_id, $config_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Fluxo salvo com sucesso'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function publishFlow($data, $admin_id) {
    // Marcar check-in como publicado/ativo
    global $conn;
    
    $config_id = (int)($data['config_id'] ?? 0);
    
    if ($config_id <= 0) {
        throw new Exception('ID do check-in inválido');
    }
    
    // Verificar se pertence ao admin
    $stmt_check = $conn->prepare("SELECT id FROM sf_checkin_configs WHERE id = ? AND admin_id = ?");
    $stmt_check->bind_param("ii", $config_id, $admin_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Check-in não encontrado ou sem permissão');
    }
    $stmt_check->close();
    
    $stmt = $conn->prepare("UPDATE sf_checkin_configs SET is_active = 1, updated_at = NOW() WHERE id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $config_id, $admin_id);
    
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Erro ao publicar: ' . $error);
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Check-in publicado com sucesso'
    ]);
}

function saveBlock($data, $admin_id) {
    global $conn;
    
    $config_id = (int)($data['config_id'] ?? 0);
    $question_text = trim($data['question_text'] ?? '');
    $question_type = $data['question_type'] ?? 'text';
    $options = $data['options'] ?? null;
    $order_index = (int)($data['order_index'] ?? 0);
    
    if ($config_id <= 0) {
        throw new Exception('ID do check-in inválido');
    }
    
    if (empty($question_text)) {
        throw new Exception('Texto da pergunta é obrigatório');
    }
    
    // Verificar se pertence ao admin
    $stmt_check = $conn->prepare("SELECT id FROM sf_checkin_configs WHERE id = ? AND admin_id = ?");
    $stmt_check->bind_param("ii", $config_id, $admin_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Check-in não encontrado ou sem permissão');
    }
    $stmt_check->close();
    
    $options_json = null;
    if ($options) {
        if (is_string($options)) {
            $options_json = $options;
        } else {
            $options_json = json_encode($options);
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO sf_checkin_questions (config_id, question_text, question_type, options, order_index, is_required, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())");
    $stmt->bind_param("isssi", $config_id, $question_text, $question_type, $options_json, $order_index);
    
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Erro ao salvar bloco: ' . $error);
    }
    
    $block_id = $conn->insert_id;
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Bloco salvo com sucesso',
        'block_id' => $block_id
    ]);
}

function updateBlock($data, $admin_id) {
    global $conn;
    
    $block_id = (int)($data['block_id'] ?? 0);
    $question_text = trim($data['question_text'] ?? '');
    $question_type = $data['question_type'] ?? 'text';
    $options = $data['options'] ?? null;
    
    if ($block_id <= 0) {
        throw new Exception('ID do bloco inválido');
    }
    
    if (empty($question_text)) {
        throw new Exception('Texto da pergunta é obrigatório');
    }
    
    // Verificar se o bloco pertence a um check-in do admin
    $stmt_check = $conn->prepare("SELECT cq.id FROM sf_checkin_questions cq INNER JOIN sf_checkin_configs cc ON cq.config_id = cc.id WHERE cq.id = ? AND cc.admin_id = ?");
    $stmt_check->bind_param("ii", $block_id, $admin_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Bloco não encontrado ou sem permissão');
    }
    $stmt_check->close();
    
    $options_json = null;
    if ($options) {
        if (is_string($options)) {
            $options_json = $options;
        } else {
            $options_json = json_encode($options);
        }
    }
    
    $stmt = $conn->prepare("UPDATE sf_checkin_questions SET question_text = ?, question_type = ?, options = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("sssi", $question_text, $question_type, $options_json, $block_id);
    
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Erro ao atualizar bloco: ' . $error);
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Bloco atualizado com sucesso'
    ]);
}

function deleteBlock($data, $admin_id) {
    global $conn;
    
    $block_id = (int)($data['block_id'] ?? 0);
    
    if ($block_id <= 0) {
        throw new Exception('ID do bloco inválido');
    }
    
    // Verificar se o bloco pertence a um check-in do admin
    $stmt_check = $conn->prepare("SELECT cq.id FROM sf_checkin_questions cq INNER JOIN sf_checkin_configs cc ON cq.config_id = cc.id WHERE cq.id = ? AND cc.admin_id = ?");
    $stmt_check->bind_param("ii", $block_id, $admin_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Bloco não encontrado ou sem permissão');
    }
    $stmt_check->close();
    
    $stmt = $conn->prepare("DELETE FROM sf_checkin_questions WHERE id = ?");
    $stmt->bind_param("i", $block_id);
    
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Erro ao deletar bloco: ' . $error);
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Bloco deletado com sucesso'
    ]);
}

function saveBlockOrder($data, $admin_id) {
    global $conn;
    
    $config_id = (int)($data['config_id'] ?? 0);
    $order = json_decode($data['order'] ?? '[]', true);
    
    if ($config_id <= 0) {
        throw new Exception('ID do check-in inválido');
    }
    
    // Verificar se pertence ao admin
    $stmt_check = $conn->prepare("SELECT id FROM sf_checkin_configs WHERE id = ? AND admin_id = ?");
    $stmt_check->bind_param("ii", $config_id, $admin_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Check-in não encontrado ou sem permissão');
    }
    $stmt_check->close();
    
    $conn->begin_transaction();
    try {
        foreach ($order as $item) {
            $block_id = (int)($item['id'] ?? 0);
            $order_index = (int)($item['order'] ?? 0);
            
            if ($block_id > 0) {
                $stmt = $conn->prepare("UPDATE sf_checkin_questions SET order_index = ? WHERE id = ? AND config_id = ?");
                $stmt->bind_param("iii", $order_index, $block_id, $config_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Ordem dos blocos salva com sucesso'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function updateCheckinConfig($data, $admin_id) {
    global $conn;
    
    $checkin_id = (int)($data['checkin_id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $day_of_week = (int)($data['day_of_week'] ?? 0);
    $distribution = $data['distribution'] ?? ['user_groups' => [], 'challenge_groups' => [], 'users' => []];
    
    if ($checkin_id <= 0) {
        throw new Exception('ID do check-in inválido');
    }
    
    if (empty($name)) {
        throw new Exception('Nome do check-in é obrigatório');
    }
    
    // Verificar se pertence ao admin e pegar o day_of_week atual
    $stmt_check = $conn->prepare("SELECT id, day_of_week FROM sf_checkin_configs WHERE id = ? AND admin_id = ?");
    $stmt_check->bind_param("ii", $checkin_id, $admin_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Check-in não encontrado ou sem permissão');
    }
    
    $current_config = $result->fetch_assoc();
    $old_day_of_week = (int)($current_config['day_of_week'] ?? 0);
    $stmt_check->close();
    
    $day_changed = ($old_day_of_week != $day_of_week);
    
    $conn->begin_transaction();
    try {
        // Atualizar configuração
        $stmt = $conn->prepare("UPDATE sf_checkin_configs SET name = ?, description = ?, day_of_week = ?, updated_at = NOW() WHERE id = ? AND admin_id = ?");
        $stmt->bind_param("ssiii", $name, $description, $day_of_week, $checkin_id, $admin_id);
        $stmt->execute();
        $stmt->close();
        
        // Deletar distribuições antigas
        $stmt = $conn->prepare("DELETE FROM sf_checkin_distribution WHERE config_id = ?");
        $stmt->bind_param("i", $checkin_id);
        $stmt->execute();
        $stmt->close();
        
        // Inserir novas distribuições
        $stmt = $conn->prepare("INSERT INTO sf_checkin_distribution (config_id, target_type, target_id, created_at) VALUES (?, ?, ?, NOW())");
        
        // User groups - usar 'group' como target_type
        if (isset($distribution['user_groups']) && is_array($distribution['user_groups'])) {
            foreach ($distribution['user_groups'] as $group_id) {
                $target_type = 'group';
                // Usar ID negativo para diferenciar (ou podemos usar uma lógica diferente)
                // Por enquanto, vamos usar o ID positivo e diferenciar na leitura
                $stmt->bind_param("isi", $checkin_id, $target_type, $group_id);
                $stmt->execute();
            }
        }
        
        // Challenge groups - usar 'group' como target_type mas com ID negativo para diferenciar
        // OU criar uma tabela auxiliar, mas por simplicidade vamos usar ID negativo
        if (isset($distribution['challenge_groups']) && is_array($distribution['challenge_groups'])) {
            foreach ($distribution['challenge_groups'] as $group_id) {
                $target_type = 'group';
                // Usar ID negativo para challenge groups
                $negative_id = -abs((int)$group_id);
                $stmt->bind_param("isi", $checkin_id, $target_type, $negative_id);
                $stmt->execute();
            }
        }
        
        // Users
        if (isset($distribution['users']) && is_array($distribution['users'])) {
            foreach ($distribution['users'] as $user_id) {
                $target_type = 'user';
                $stmt->bind_param("isi", $checkin_id, $target_type, $user_id);
                $stmt->execute();
            }
        }
        
        $stmt->close();
        
        // Se o dia da semana mudou OU a distribuição foi alterada, resetar disponibilidade
        // Resetar is_completed e congrats_shown para a semana atual de todos os usuários
        // Isso permite que façam o check-in novamente no novo dia
        // IMPORTANTE: NÃO deletamos as respostas antigas para manter o histórico
        // O loadProgress já filtra respostas baseado em is_completed e semana atual
        if ($day_changed) {
            // Calcular o domingo da semana atual (mesma lógica usada no sistema)
            $current_week_start = date('Y-m-d', strtotime('sunday this week'));
            
            // Resetar is_completed e congrats_shown para a semana atual
            // As respostas antigas permanecem no banco para histórico, mas não serão carregadas
            // porque loadProgress verifica is_completed = 0 e filtra por semana atual
            $stmt_reset = $conn->prepare("UPDATE sf_checkin_availability SET is_completed = 0, congrats_shown = 0 WHERE config_id = ? AND week_date = ?");
            $stmt_reset->bind_param("is", $checkin_id, $current_week_start);
            $stmt_reset->execute();
            $stmt_reset->close();
        } else {
            // Se apenas a distribuição mudou (sem mudar o dia), resetar apenas congrats_shown
            // Isso garante que o popup apareça novamente quando a distribuição for alterada
            $stmt_reset = $conn->prepare("UPDATE sf_checkin_availability SET congrats_shown = 0 WHERE config_id = ?");
            $stmt_reset->bind_param("i", $checkin_id);
            $stmt_reset->execute();
            $stmt_reset->close();
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Configuração atualizada com sucesso'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function createCheckin($data, $admin_id) {
    global $conn;
    
    $name = trim($data['name'] ?? 'Novo Check-in');
    $description = trim($data['description'] ?? '');
    $day_of_week = (int)($data['day_of_week'] ?? 0);
    
    $stmt = $conn->prepare("INSERT INTO sf_checkin_configs (admin_id, name, description, day_of_week, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, 0, NOW(), NOW())");
    $stmt->bind_param("issi", $admin_id, $name, $description, $day_of_week);
    
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Erro ao criar check-in: ' . $error);
    }
    
    $checkin_id = $conn->insert_id;
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Check-in criado com sucesso',
        'checkin_id' => $checkin_id
    ]);
}

function updateStatus($data, $admin_id) {
    global $conn;
    
    $checkin_id = (int)($data['checkin_id'] ?? 0);
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 0;
    
    if ($checkin_id === 0) {
        throw new Exception('ID do check-in inválido');
    }
    
    // Verificar se o check-in pertence ao admin
    $check_query = "SELECT id FROM sf_checkin_configs WHERE id = ? AND admin_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $checkin_id, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Check-in não encontrado ou sem permissão');
    }
    $stmt->close();
    
    // Atualizar status
    $update_query = "UPDATE sf_checkin_configs SET is_active = ?, updated_at = NOW() WHERE id = ? AND admin_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("iii", $is_active, $checkin_id, $admin_id);
    
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Erro ao atualizar status: ' . $stmt->error);
    }
    $stmt->close();
    
    // Buscar estatísticas atualizadas
    $stats = [];
    
    // Total
    $stmt_stats = $conn->prepare("SELECT COUNT(*) as count FROM sf_checkin_configs WHERE admin_id = ?");
    $stmt_stats->bind_param("i", $admin_id);
    $stmt_stats->execute();
    $result = $stmt_stats->get_result();
    $stats['total'] = $result->fetch_assoc()['count'];
    $stmt_stats->close();
    
    // Por status
    $stmt_stats = $conn->prepare("SELECT is_active, COUNT(*) as count FROM sf_checkin_configs WHERE admin_id = ? GROUP BY is_active");
    $stmt_stats->bind_param("i", $admin_id);
    $stmt_stats->execute();
    $result = $stmt_stats->get_result();
    $stats['active'] = 0;
    $stats['inactive'] = 0;
    while ($row = $result->fetch_assoc()) {
        if ($row['is_active']) {
            $stats['active'] = $row['count'];
        } else {
            $stats['inactive'] = $row['count'];
        }
    }
    $stmt_stats->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Status atualizado com sucesso',
        'stats' => $stats
    ]);
}

function deleteResponse($data, $admin_id) {
    global $conn;
    
    $user_id = (int)($data['user_id'] ?? 0);
    $config_id = (int)($data['config_id'] ?? 0);
    $response_date = trim($data['response_date'] ?? '');
    
    if ($user_id <= 0 || $config_id <= 0 || empty($response_date)) {
        throw new Exception('Dados inválidos para exclusão');
    }
    
    // Verificar se o check-in pertence ao admin
    $check_query = "SELECT id FROM sf_checkin_configs WHERE id = ? AND admin_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $config_id, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Check-in não encontrado ou sem permissão');
    }
    $stmt->close();
    
    // Deletar todas as respostas do usuário para este check-in na data especificada
    $delete_query = "DELETE FROM sf_checkin_responses WHERE config_id = ? AND user_id = ? AND DATE(submitted_at) = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("iis", $config_id, $user_id, $response_date);
    
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception('Erro ao excluir respostas: ' . $stmt->error);
    }
    
    $deleted_count = $stmt->affected_rows;
    $stmt->close();
    
    // Resetar status de completado para esta semana se necessário
    // Calcular o domingo da semana da data da resposta
    $week_start = date('Y-m-d', strtotime('sunday this week', strtotime($response_date)));
    
    $update_query = "UPDATE sf_checkin_availability SET is_completed = 0, completed_at = NULL WHERE config_id = ? AND user_id = ? AND week_date = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("iis", $config_id, $user_id, $week_start);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => "Resposta excluída com sucesso! ($deleted_count registro(s) removido(s))"
    ]);
}

function generateSummary($data, $admin_id) {
    global $conn;
    
    try {
        $conversation = trim($data['conversation'] ?? '');
        $user_name = trim($data['user_name'] ?? 'Usuário');
        $user_id = (int)($data['user_id'] ?? 0);
        $flow_info_json = $data['flow_info'] ?? '[]';
        $flow_info = json_decode($flow_info_json, true) ?? [];

        if (empty($conversation)) {
            echo json_encode(['success' => false, 'message' => 'Conversa vazia']);
            exit;
        }

        // Buscar dados completos do paciente se user_id disponível
        $patient_data = null;
        if ($user_id > 0) {
            $patient_data = getPatientDataForSummary($conn, $user_id);
        }

        // USAR GROQ API COMO SOLUÇÃO DEFINITIVA
        $groq_result = tryGroqAPI($conversation, $user_name, $flow_info, $patient_data);
        if ($groq_result !== false) {
            echo json_encode([
                'success' => true,
                'summary' => $groq_result
            ]);
            exit;
        }
        
        // Se Groq falhou, retornar erro
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao gerar resumo com Groq API. Verifique se a API key está configurada em includes/config.php. Obtenha a chave gratuita em: https://console.groq.com'
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Erro em generateSummary: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao processar solicitação: ' . $e->getMessage()
        ]);
        exit;
    }
}

function tryOllamaLocal($conversation, $user_name, $model = null) {
    // Configuração do Ollama (pode ser local ou remoto)
    // URL configurada em includes/config.php (OLLAMA_URL)
    $ollama_base_url = defined('OLLAMA_URL') ? OLLAMA_URL : 'http://localhost:11434';
    $ollama_url = rtrim($ollama_base_url, '/') . '/api/chat';
    
    // Modelo a usar (pode ser: llama3.1:8b, llama3.1, mistral, qwen2.5, phi3)
    // Configurado em includes/config.php (OLLAMA_MODEL)
    if ($model === null) {
        $model = defined('OLLAMA_MODEL') ? OLLAMA_MODEL : 'llama3.1:8b';
    }
    
    // Criar prompt ULTRA inteligente que funciona com QUALQUER tipo de check-in
    $system_prompt = "Você é um nutricionista experiente e analista de dados de saúde. Sua função é analisar conversas completas de check-in semanal e criar resumos profissionais, detalhados e analíticos em português brasileiro.\n\n";
    $system_prompt .= "⚠️ REGRA FUNDAMENTAL CRÍTICA: Cada check-in pode ter perguntas COMPLETAMENTE DIFERENTES. Você DEVE adaptar-se ao fluxo real da conversa, não assumir perguntas específicas.\n\n";
    $system_prompt .= "📋 METODOLOGIA DE ANÁLISE OBRIGATÓRIA:\n";
    $system_prompt .= "1. ⚠️ LEIA TODA A CONVERSA LINHA POR LINHA - NÃO PULE NADA! Cada pergunta e resposta é importante.\n";
    $system_prompt .= "2. ⚠️ EXTRAIA TODOS OS DADOS MENCIONADOS: valores numéricos, notas (0-10), sentimentos, eventos, dificuldades, comentários, TUDO!\n";
    $system_prompt .= "3. ⚠️ NÃO ESQUEÇA NENHUMA INFORMAÇÃO: Se o paciente mencionou algo, DEVE aparecer no resumo.\n";
    $system_prompt .= "4. Identifique padrões e correlações entre diferentes aspectos (ex: sono ruim + humor baixo + apetite alto)\n";
    $system_prompt .= "5. Destaque pontos críticos (valores muito baixos/altos, problemas mencionados)\n";
    $system_prompt .= "6. Seja ESPECÍFICO: mencione valores exatos, citações diretas quando relevante\n";
    $system_prompt .= "7. ⚠️ SE UMA PERGUNTA FOI FEITA E RESPONDIDA, ELA DEVE APARECER NO RESUMO!\n\n";
    $system_prompt .= "ESTRUTURA DO RESUMO (adaptável ao conteúdo real):\n\n";
    $system_prompt .= "✅ Resumo Completo do Check-in Semanal\n";
    $system_prompt .= "📅 Período analisado: Últimos 7 dias\n";
    $system_prompt .= "👤 Paciente: [NOME]\n";
    $system_prompt .= "📊 Nota geral da semana: [NOTA]/10 (se mencionada)\n";
    $system_prompt .= "[Comentário sobre a nota, se houver]\n\n";
    $system_prompt .= "ORGANIZE EM SEÇÕES LÓGICAS baseadas no que REALMENTE foi perguntado:\n\n";
    $system_prompt .= "⚠️ IMPORTANTE: Para cada seção, você DEVE listar TODOS os dados mencionados na conversa. NÃO ESQUEÇA NADA!\n\n";
    $system_prompt .= "🔥 1. Rotina & Treinos (se perguntas sobre rotina/treinos existirem)\n";
    $system_prompt .= "- ⚠️ Liste TODOS os dados extraídos desta categoria (mudanças na rotina, faltas de treino, quantidade de treinos, etc.)\n";
    $system_prompt .= "- 💬 Interpretação: análise profissional dos dados\n\n";
    $system_prompt .= "🍽️ 2. Alimentação (se perguntas sobre alimentação existirem)\n";
    $system_prompt .= "- ⚠️ Liste TODOS os dados (apetite com valor/10, fome com valor/10, refeições sociais, refeições fora do plano, TUDO!)\n";
    $system_prompt .= "- 💬 Interpretação: análise profissional\n\n";
    $system_prompt .= "😊 3. Motivação, Humor & Desejos (se perguntas sobre aspectos emocionais existirem)\n";
    $system_prompt .= "- ⚠️ Liste TODOS os dados (motivação com valor/10, humor com valor/10, desejo de furar com valor/10, TUDO!)\n";
    $system_prompt .= "- 💬 Interpretação: análise profissional, destaque pontos críticos\n\n";
    $system_prompt .= "😴 4. Sono, Recuperação & Estresse (se perguntas sobre sono/recuperação existirem)\n";
    $system_prompt .= "- ⚠️ Liste TODOS os dados (sono com valor/10, recuperação com valor/10, estresse com valor/10, TUDO!)\n";
    $system_prompt .= "- 💬 Interpretação: análise profissional\n\n";
    $system_prompt .= "🧻 5. Intestino (se perguntas sobre intestino existirem)\n";
    $system_prompt .= "- ⚠️ Dados extraídos (valor/10 se mencionado)\n";
    $system_prompt .= "- 💬 Interpretação: análise profissional\n\n";
    $system_prompt .= "🧠 6. Performance (se perguntas sobre performance existirem)\n";
    $system_prompt .= "- ⚠️ Dados extraídos (valor/10 se mencionado)\n";
    $system_prompt .= "- 💬 Interpretação: análise profissional\n\n";
    $system_prompt .= "⚖️ 7. Peso (se peso foi mencionado)\n";
    $system_prompt .= "- ⚠️ Peso atual informado (valor exato em kg)\n\n";
    $system_prompt .= "🗣️ 8. Comentário do paciente (se houver comentário final)\n";
    $system_prompt .= "- ⚠️ Citação completa do comentário\n";
    $system_prompt .= "- Análise do engajamento\n\n";
    $system_prompt .= "🎯 Conclusão Geral\n";
    $system_prompt .= "- Síntese do estado geral do paciente\n";
    $system_prompt .= "- Lista de pontos críticos identificados\n\n";
    $system_prompt .= "🔧 Ajustes prioritários\n";
    $system_prompt .= "- Recomendações específicas baseadas nos dados reais\n\n";
    $system_prompt .= "⚠️ REGRAS CRÍTICAS:\n";
    $system_prompt .= "- Se uma categoria não foi perguntada, NÃO crie a seção\n";
    $system_prompt .= "- ⚠️ Seja ESPECÍFICO: mencione valores exatos (ex: 'Humor: 0/10 (péssimo)', 'Apetite: 10/10 (muito elevado)')\n";
    $system_prompt .= "- ⚠️ NÃO ESQUEÇA NENHUM VALOR: Se foi mencionado um número (nota, peso, etc.), DEVE aparecer no resumo\n";
    $system_prompt .= "- Destaque pontos críticos com formatação apropriada\n";
    $system_prompt .= "- Use emojis apenas nos títulos das seções\n";
    $system_prompt .= "- Formate em HTML com tags <h4>, <p>, <ul>, <li>, <strong>\n";
    $system_prompt .= "- Seja PROFISSIONAL mas ACESSÍVEL\n";
    $system_prompt .= "- NÃO invente dados que não estão na conversa\n";
    $system_prompt .= "- ADAPTE a estrutura ao conteúdo real, não force categorias inexistentes\n";
    $system_prompt .= "- ⚠️ REVISE: Certifique-se de que TODAS as perguntas e respostas da conversa foram incluídas no resumo!";
    
    // Limitar tamanho da conversa se muito grande (para evitar timeout)
    $conversation_limited = $conversation;
    if (strlen($conversation) > 8000) {
        $conversation_limited = substr($conversation, 0, 8000) . "\n\n[... conversa truncada para otimização ...]";
        error_log("Ollama Warning: Conversa muito longa, truncada para " . strlen($conversation_limited) . " caracteres");
    }
    
    $user_message = "⚠️⚠️⚠️ ATENÇÃO CRÍTICA: Analise a seguinte conversa COMPLETA de check-in linha por linha. \n\n";
    $user_message .= "⚠️ REGRAS OBRIGATÓRIAS:\n";
    $user_message .= "1. Leia CADA linha da conversa abaixo\n";
    $user_message .= "2. Para CADA pergunta feita, você DEVE incluir a resposta no resumo\n";
    $user_message .= "3. Se uma pergunta foi sobre apetite e a resposta foi '10/10', você DEVE colocar 'Apetite: 10/10' na seção Alimentação\n";
    $user_message .= "4. Se uma pergunta foi sobre humor e a resposta foi '0/10', você DEVE colocar 'Humor: 0/10' na seção Motivação/Humor\n";
    $user_message .= "5. Se uma pergunta foi sobre sono e a resposta foi '5/10', você DEVE colocar 'Sono: 5/10' na seção Sono/Recuperação\n";
    $user_message .= "6. Se uma pergunta foi sobre fome e a resposta foi '5/10', você DEVE colocar 'Fome: 5/10' na seção Alimentação\n";
    $user_message .= "7. Se uma pergunta foi sobre motivação e a resposta foi '7.5/10', você DEVE colocar 'Motivação: 7.5/10' na seção Motivação/Humor\n";
    $user_message .= "8. Se uma pergunta foi sobre desejo de furar e a resposta foi '10/10', você DEVE colocar 'Desejo de furar: 10/10' na seção Motivação/Humor\n";
    $user_message .= "9. Se uma pergunta foi sobre recuperação e a resposta foi '7.5/10', você DEVE colocar 'Recuperação: 7.5/10' na seção Sono/Recuperação\n";
    $user_message .= "10. Se uma pergunta foi sobre estresse e a resposta foi '2.5/10', você DEVE colocar 'Estresse: 2.5/10' na seção Sono/Recuperação\n";
    $user_message .= "11. Se uma pergunta foi sobre intestino e a resposta foi '2.5/10', você DEVE colocar 'Intestino: 2.5/10' na seção Intestino\n";
    $user_message .= "12. Se uma pergunta foi sobre performance e a resposta foi '7.5/10', você DEVE colocar 'Performance: 7.5/10' na seção Performance\n";
    $user_message .= "13. Se uma pergunta foi sobre nota da semana e a resposta foi '7.5', você DEVE colocar 'Nota geral: 7.5/10'\n";
    $user_message .= "14. Se uma pergunta foi sobre refeições sociais e a resposta foi 'Sim', você DEVE colocar 'Refeições sociais: Sim' na seção Alimentação\n";
    $user_message .= "15. Se uma pergunta foi sobre refeição fora do plano e a resposta foi mencionada, você DEVE colocar os detalhes na seção Alimentação\n\n";
    $user_message .= "⚠️ NÃO ESQUEÇA NENHUMA PERGUNTA E NENHUMA RESPOSTA!\n\n";
    $user_message .= "Conversa completa:\n" . $conversation_limited . "\n\n";
    $user_message .= "Agora crie um resumo PROFISSIONAL, DETALHADO e COMPLETO em português brasileiro, formatado em HTML, incluindo TODOS os dados mencionados acima. Certifique-se de que CADA pergunta e resposta da conversa apareça no resumo organizado nas seções apropriadas:";
    
    // Preparar requisição para Ollama usando API de chat (mais adequada)
    $ch = curl_init($ollama_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => $system_prompt
            ],
            [
                'role' => 'user',
                'content' => $user_message
            ]
        ],
        'stream' => false,
        'options' => [
            'temperature' => 0.7,
            'num_predict' => 5000, // Muitos tokens para resumos COMPLETOS e detalhados - não perder informações
            'top_p' => 0.9,
            'top_k' => 40
        ]
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 120 segundos de timeout (2 minutos)
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 segundos para conectar
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Log para debug (remover em produção se necessário)
    error_log("Ollama Debug - Model: $model, HTTP Code: " . $http_code . ", Error: " . $curl_error);
    
    // Se não conseguir conectar ao Ollama
    if ($http_code === 0 || !empty($curl_error)) {
        error_log("Ollama Error: Não foi possível conectar. HTTP: $http_code, Error: $curl_error");
        return false;
    }
    
    // Se houve erro HTTP
    if ($http_code !== 200) {
        error_log("Ollama Error: HTTP Code $http_code. Response: " . substr($response, 0, 500));
        return false;
    }
    
    if (empty($response)) {
        error_log("Ollama Error: Resposta vazia");
        return false;
    }
    
    $result = json_decode($response, true);
    
    // Verificar se houve erro no JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Ollama Error: JSON decode failed - " . json_last_error_msg() . ". Response: " . substr($response, 0, 500));
        return false;
    }
    
    // Verificar se há erro na resposta do Ollama
    if (isset($result['error'])) {
        error_log("Ollama Error: " . $result['error']);
        return false;
    }
    
    // Extrair o texto gerado
    $generated_text = '';
    if (isset($result['message']['content']) && !empty($result['message']['content'])) {
        $generated_text = trim($result['message']['content']);
    } elseif (isset($result['response']) && !empty($result['response'])) {
        // Formato alternativo
        $generated_text = trim($result['response']);
    }
    
    if (empty($generated_text)) {
        error_log("Ollama Error: Texto gerado vazio. Response: " . substr(json_encode($result), 0, 500));
        return false;
    }
    
    // Formatar o resumo em HTML (função inteligente que detecta estrutura)
    try {
        $formatted_summary = formatSummaryHTML($generated_text, $user_name);
        return $formatted_summary;
    } catch (Exception $e) {
        error_log("Ollama Error: Erro ao formatar resumo - " . $e->getMessage());
        return false;
    }
}

function getPatientDataForSummary($conn, $user_id) {
    // Buscar dados completos do paciente para análise contextual
    $data = [];
    
    try {
        // Dados básicos do usuário e perfil
        $stmt = $conn->prepare(
            "SELECT u.*, p.* FROM sf_users u LEFT JOIN sf_user_profiles p ON u.id = p.user_id WHERE u.id = ?"
        );
        if (!$stmt) {
            error_log("Erro ao preparar query: " . $conn->error);
            return $data;
        }
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($user_data) {
            // Verificar se calculateAge existe antes de usar
            $age = null;
            if (!empty($user_data['dob'])) {
                if (function_exists('calculateAge')) {
                    $age = calculateAge($user_data['dob']);
                } else {
                    // Calcular idade manualmente se função não existir
                    try {
                        $dob = new DateTime($user_data['dob']);
                        $now = new DateTime();
                        $age = ($dob > $now) ? 0 : (int)$now->diff($dob)->y;
                    } catch (Exception $e) {
                        $age = null;
                    }
                }
            }
            
            $data['basic'] = [
                'name' => $user_data['name'] ?? '',
                'age' => $age,
                'gender' => $user_data['gender'] ?? null,
                'height_cm' => $user_data['height_cm'] ?? null,
                'objective' => $user_data['objective'] ?? null,
                'exercise_frequency' => $user_data['exercise_frequency'] ?? null
            ];
            
            // Peso atual
            $data['current_weight'] = (float)($user_data['weight_kg'] ?? 0);
            
            // Histórico de peso (últimos 3 registros)
            $stmt_weight = $conn->prepare(
                "SELECT date_recorded, weight_kg FROM sf_user_weight_history 
                 WHERE user_id = ? ORDER BY date_recorded DESC LIMIT 3"
            );
            if ($stmt_weight) {
                $stmt_weight->bind_param("i", $user_id);
                $stmt_weight->execute();
                $weight_history = $stmt_weight->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_weight->close();
                $data['weight_history'] = $weight_history;
            }
            
            // Metas de nutrientes
            $data['goals'] = [
                'calories' => $user_data['custom_calories_goal'] ?? null,
                'protein_g' => $user_data['custom_protein_goal_g'] ?? null,
                'carbs_g' => $user_data['custom_carbs_goal_g'] ?? null,
                'fat_g' => $user_data['custom_fat_goal_g'] ?? null
            ];
            
            // Anamnese relevante
            $data['medical'] = [
                'lactose_intolerance' => $user_data['lactose_intolerance'] ?? false,
                'gluten_intolerance' => $user_data['gluten_intolerance'] ?? false,
                'vegetarian_type' => $user_data['vegetarian_type'] ?? null,
                'meat_consumption' => $user_data['meat_consumption'] ?? null
            ];
        }
    } catch (Exception $e) {
        error_log("Erro ao buscar dados do paciente: " . $e->getMessage());
        // Retornar array vazio em caso de erro
    }
    
    return $data;
}

function tryGroqAPI($conversation, $user_name, $flow_info = [], $patient_data = null) {
    // Groq API - Solução definitiva, gratuita e muito rápida
    $api_key = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';
    $model = defined('GROQ_MODEL') ? GROQ_MODEL : 'llama-3.1-70b-versatile';
    
    if (empty($api_key)) {
        error_log("Groq API Error: API key não configurada. Configure GROQ_API_KEY em includes/config.php");
        return false;
    }
    
    $api_url = 'https://api.groq.com/openai/v1/chat/completions';
    
    // 🧠 SYSTEM PROMPT — Resumo Dinâmico Profissional (versão 3.0)
    $system_prompt = "Você é um nutricionista sênior e analista clínico. Sua função é ler uma conversa COMPLETA de check-in semanal (perguntas e respostas) e gerar um RESUMO PROFISSIONAL, DETALHADO e 100% FIEL aos dados reais.\n\n";
    $system_prompt .= "⚠️ REGRAS FUNDAMENTAIS:\n";
    $system_prompt .= "- NUNCA invente dados.\n";
    $system_prompt .= "- NUNCA adicione categorias que não aparecem na conversa.\n";
    $system_prompt .= "- Leia TUDO linha por linha.\n";
    $system_prompt .= "- IDENTIFIQUE AUTOMATICAMENTE os temas que surgirem.\n";
    $system_prompt .= "- Resumo sempre em português brasileiro.\n";
    $system_prompt .= "- Formate SEMPRE em HTML (com <h4>, <p>, <ul>, <li>, <strong>).\n";
    $system_prompt .= "- O paciente pode responder em tom informal — você deve interpretar e transformar em linguagem profissional.\n";
    $system_prompt .= "- NÃO assuma que existe rotina/treino/sono/alimentação. Só crie seção se existir MENÇÃO na conversa.\n";
    $system_prompt .= "- Sempre liste TODO valor numérico citado (ex: \"fome: 7/10\", \"humor: 3/10\", etc).\n";
    $system_prompt .= "- Se a conversa não tiver uma pergunta explícita, ainda assim analise o que for dito.\n\n";
    $system_prompt .= "📌 METODOLOGIA AUTOMÁTICA:\n";
    $system_prompt .= "1. Leia a conversa inteira.\n";
    $system_prompt .= "2. Identifique temas que realmente apareceram (ex: treinos, alimentação, humor, apetite, peso, intestino, notas, recuperação, estresse, desempenho, energia, motivação, foco, social, etc).\n";
    $system_prompt .= "3. Para cada tema identificado, crie uma seção com:\n";
    $system_prompt .= "   - Lista de dados objetivos extraídos da conversa\n";
    $system_prompt .= "   - Interpretação profissional (curta e precisa)\n";
    $system_prompt .= "4. Detecte automaticamente sinais críticos (muito baixos ou muito altos).\n";
    $system_prompt .= "5. Construa o resumo com estrutura clara e adaptativa:\n\n";
    $system_prompt .= "ESTRUTURA FINAL:\n\n";
    $system_prompt .= "<h4>✅ Resumo do Check-in Semanal</h4>\n\n";
    $system_prompt .= "Para cada categoria detectada, use o formato:\n\n";
    $system_prompt .= "<h4>🔥 [Nome da categoria]</h4>\n";
    $system_prompt .= "<ul>\n";
    $system_prompt .= " <li>Dado 1 extraído</li>\n";
    $system_prompt .= " <li>Dado 2 extraído</li>\n";
    $system_prompt .= " <li>Dado 3...</li>\n";
    $system_prompt .= "</ul>\n";
    $system_prompt .= "<p><strong>💬 Interpretação:</strong> análise objetiva sobre esse tema.</p>\n\n";
    $system_prompt .= "No final, gere:\n\n";
    $system_prompt .= "<h4>🎯 Conclusão Geral</h4>\n";
    $system_prompt .= "<p>Resumo agregando os principais achados e pontos de atenção.</p>\n\n";
    $system_prompt .= "<h4>🔧 Ajustes Prioritários</h4>\n";
    $system_prompt .= "<ul>\n";
    $system_prompt .= " <li>Se houver pontos críticos, explique.</li>\n";
    $system_prompt .= "</ul>\n\n";
    $system_prompt .= "📌 CATEGORIAS QUE PODEM EXISTIR (mas só crie se aparecerem):\n";
    $system_prompt .= "- Rotina & Treinos\n";
    $system_prompt .= "- Alimentação\n";
    $system_prompt .= "- Fome / Apetite\n";
    $system_prompt .= "- Motivação\n";
    $system_prompt .= "- Humor\n";
    $system_prompt .= "- Sono\n";
    $system_prompt .= "- Estresse\n";
    $system_prompt .= "- Recuperação\n";
    $system_prompt .= "- Performance / Energia\n";
    $system_prompt .= "- Intestino\n";
    $system_prompt .= "- Peso\n";
    $system_prompt .= "- Refeições sociais / fora do plano\n";
    $system_prompt .= "- Comentário final / relato espontâneo\n\n";
    $system_prompt .= "⚠️ NUNCA CRIE SEÇÃO VAZIA.\n";
    $system_prompt .= "⚠️ NUNCA REPITA INFORMAÇÕES.\n";
    $system_prompt .= "⚠️ SEMPRE JOGUE TUDO NO HTML.";
    
    // Limitar tamanho da conversa se muito grande
    $conversation_limited = $conversation;
    if (strlen($conversation) > 12000) {
        $conversation_limited = substr($conversation, 0, 12000) . "\n\n[... conversa truncada para otimização ...]";
        error_log("Groq Warning: Conversa muito longa, truncada para " . strlen($conversation_limited) . " caracteres");
    }
    
    $user_message = "A seguir está a conversa completa de um check-in semanal entre nutricionista e paciente. Leia cada linha com atenção e siga TODAS as regras do system prompt.\n\n";
    
    // Adicionar informações do paciente se disponível
    if (!empty($patient_data)) {
        $user_message .= "👤 DADOS DO PACIENTE (para comparações e contexto):\n\n";
        
        if (!empty($patient_data['basic'])) {
            $basic = $patient_data['basic'];
            $user_message .= "Dados Básicos:\n";
            if (!empty($basic['age'])) $user_message .= "- Idade: " . $basic['age'] . " anos\n";
            if (!empty($basic['gender'])) $user_message .= "- Sexo: " . ($basic['gender'] === 'male' ? 'Masculino' : 'Feminino') . "\n";
            if (!empty($basic['height_cm'])) $user_message .= "- Altura: " . $basic['height_cm'] . " cm\n";
            if (!empty($basic['objective'])) {
                $obj_map = ['lose_fat' => 'Perder gordura', 'gain_muscle' => 'Ganhar massa', 'maintain' => 'Manter peso'];
                $user_message .= "- Objetivo: " . ($obj_map[$basic['objective']] ?? $basic['objective']) . "\n";
            }
            if (!empty($basic['exercise_frequency'])) {
                $freq_map = ['sedentary' => 'Sedentário', '1_2x_week' => '1-2x/semana', '3_4x_week' => '3-4x/semana', '5_6x_week' => '5-6x/semana', 'daily' => 'Diário'];
                $user_message .= "- Frequência de exercícios: " . ($freq_map[$basic['exercise_frequency']] ?? $basic['exercise_frequency']) . "\n";
            }
            $user_message .= "\n";
        }
        
        // Peso atual e histórico
        if (!empty($patient_data['current_weight']) && $patient_data['current_weight'] > 0) {
            $user_message .= "Peso Atual: " . number_format($patient_data['current_weight'], 1, ',', '.') . " kg\n";
        }
        
        if (!empty($patient_data['weight_history']) && count($patient_data['weight_history']) > 0) {
            $user_message .= "Histórico de Peso (últimos registros):\n";
            foreach ($patient_data['weight_history'] as $weight_entry) {
                $date = date('d/m/Y', strtotime($weight_entry['date_recorded']));
                $user_message .= "- " . $date . ": " . number_format($weight_entry['weight_kg'], 1, ',', '.') . " kg\n";
            }
            $user_message .= "\n";
        }
        
        // Metas
        if (!empty($patient_data['goals'])) {
            $goals = $patient_data['goals'];
            $has_goals = false;
            $user_message .= "Metas Nutricionais:\n";
            if (!empty($goals['calories'])) {
                $user_message .= "- Calorias: " . $goals['calories'] . " kcal/dia\n";
                $has_goals = true;
            }
            if (!empty($goals['protein_g'])) {
                $user_message .= "- Proteína: " . $goals['protein_g'] . " g/dia\n";
                $has_goals = true;
            }
            if (!empty($goals['carbs_g'])) {
                $user_message .= "- Carboidratos: " . $goals['carbs_g'] . " g/dia\n";
                $has_goals = true;
            }
            if (!empty($goals['fat_g'])) {
                $user_message .= "- Gorduras: " . $goals['fat_g'] . " g/dia\n";
                $has_goals = true;
            }
            if ($has_goals) $user_message .= "\n";
        }
        
        // Anamnese
        if (!empty($patient_data['medical'])) {
            $medical = $patient_data['medical'];
            $has_medical = false;
            $user_message .= "Anamnese:\n";
            if ($medical['lactose_intolerance']) {
                $user_message .= "- Intolerante à lactose\n";
                $has_medical = true;
            }
            if ($medical['gluten_intolerance']) {
                $user_message .= "- Intolerante ao glúten\n";
                $has_medical = true;
            }
            if (!empty($medical['vegetarian_type'])) {
                $user_message .= "- Tipo vegetariano: " . $medical['vegetarian_type'] . "\n";
                $has_medical = true;
            }
            if ($has_medical) $user_message .= "\n";
        }
        
        $user_message .= "---\n\n";
        $user_message .= "⚠️ IMPORTANTE: Use esses dados do paciente para fazer comparações inteligentes. Por exemplo:\n";
        $user_message .= "- Se o paciente informou peso no check-in, compare com o peso atual e histórico\n";
        $user_message .= "- Considere o objetivo do paciente ao analisar os dados\n";
        $user_message .= "- Use as metas nutricionais como referência quando relevante\n";
        $user_message .= "- Considere restrições alimentares ao analisar alimentação\n\n";
    }
    
    // Adicionar informações do fluxo se disponível
    if (!empty($flow_info)) {
        $user_message .= "📋 CONTEXTO DO FLUXO DE CHECK-IN:\n";
        $user_message .= "Abaixo estão as perguntas do check-in com seus tipos e opções disponíveis. Use essas informações para entender melhor o contexto das respostas:\n\n";
        
        foreach ($flow_info as $index => $item) {
            $user_message .= "Pergunta " . ($index + 1) . ":\n";
            $user_message .= "- Texto: " . $item['question_text'] . "\n";
            $user_message .= "- Tipo: " . $item['question_type'] . "\n";
            
            if (!empty($item['options']) && is_array($item['options'])) {
                $user_message .= "- Opções disponíveis:\n";
                foreach ($item['options'] as $opt) {
                    $user_message .= "  • " . $opt . "\n";
                }
            }
            
            $user_message .= "- Resposta do paciente: " . $item['response_text'] . "\n\n";
        }
        
        $user_message .= "---\n\n";
    }
    
    $user_message .= "CONVERSA COMPLETA:\n" . $conversation_limited . "\n\n";
    $user_message .= "Agora gere o resumo profissional completo em HTML, considerando TODOS os contextos acima (dados do paciente, fluxo e conversa) para uma análise mais precisa e comparativa.";
    
    // Preparar requisição para Groq API
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => $system_prompt
            ],
            [
                'role' => 'user',
                'content' => $user_message
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 4000, // Tokens suficientes para resumos completos
        'top_p' => 0.9
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 segundos (Groq é muito rápido)
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Log para debug
    error_log("Groq API Debug - HTTP Code: " . $http_code . ", Error: " . $curl_error);
    
    // Verificar erros de conexão
    if ($http_code === 0 || !empty($curl_error)) {
        error_log("Groq API Error: Não foi possível conectar. HTTP: $http_code, Error: $curl_error");
        return false;
    }
    
    // Verificar erro HTTP
    if ($http_code !== 200) {
        error_log("Groq API Error: HTTP Code $http_code. Response: " . substr($response, 0, 500));
        return false;
    }
    
    if (empty($response)) {
        error_log("Groq API Error: Resposta vazia");
        return false;
    }
    
    $result = json_decode($response, true);
    
    // Verificar erro no JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Groq API Error: JSON decode failed - " . json_last_error_msg() . ". Response: " . substr($response, 0, 500));
        return false;
    }
    
    // Verificar se há erro na resposta
    if (isset($result['error'])) {
        error_log("Groq API Error: " . json_encode($result['error']));
        return false;
    }
    
    // Extrair texto gerado
    $generated_text = '';
    if (isset($result['choices'][0]['message']['content']) && !empty($result['choices'][0]['message']['content'])) {
        $generated_text = trim($result['choices'][0]['message']['content']);
    }
    
    if (empty($generated_text)) {
        error_log("Groq API Error: Texto gerado vazio. Response: " . substr(json_encode($result), 0, 500));
        return false;
    }
    
    // Formatar o resumo em HTML
    try {
        $formatted_summary = formatSummaryHTML($generated_text, $user_name);
        return $formatted_summary;
    } catch (Exception $e) {
        error_log("Groq API Error: Erro ao formatar resumo - " . $e->getMessage());
        return false;
    }
}

function formatSummaryHTML($summary_text, $user_name) {
    $text = trim($summary_text);
    
    // Remover qualquer menção a "Paciente:" que a IA possa ter gerado incorretamente
    $text = preg_replace('/<p><strong>Paciente:<\/strong>\s*\[[^\]]+\]<\/p>/i', '', $text);
    $text = preg_replace('/Paciente:\s*\[[^\]]+\]/i', '', $text);
    $text = preg_replace('/<p>Paciente:\s*\[[^\]]+\]<\/p>/i', '', $text);
    
    // Se o texto já contém HTML ou estrutura bem formatada da IA, usar diretamente
    if (stripos($text, '<h4') !== false || stripos($text, '<h3') !== false || stripos($text, '<p') !== false || stripos($text, '<ul') !== false) {
        // A IA já formatou em HTML, apenas substituir placeholders explícitos se existirem
        // Apenas substituir se existir placeholder explícito
        if (stripos($text, '[NOME]') !== false) {
            $text = str_ireplace('[NOME]', htmlspecialchars($user_name), $text);
        }
        if (stripos($text, '[NOME DO PACIENTE]') !== false) {
            $text = str_ireplace('[NOME DO PACIENTE]', htmlspecialchars($user_name), $text);
        }
        
        // Adicionar o nome do paciente no início, logo após o título
        $nome_paciente = '<p><strong>Paciente:</strong> ' . htmlspecialchars($user_name) . '</p>';
        
        // Inserir após o primeiro <h4> (título do resumo)
        if (preg_match('/<h4[^>]*>.*?<\/h4>/i', $text, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1] + strlen($matches[0][0]);
            $text = substr($text, 0, $pos) . "\n" . $nome_paciente . "\n" . substr($text, $pos);
        } else {
            // Se não encontrou h4, adicionar no início
            $text = $nome_paciente . "\n\n" . $text;
        }
        
        return $text;
    }
    
    // Se não tem HTML, processar o texto markdown/plain text da IA
    $html = '';
    
    // Adicionar nome do paciente no início
    $html .= '<p><strong>Paciente:</strong> ' . htmlspecialchars($user_name) . '</p>';
    
    // Detectar se já tem título
    if (stripos($text, '✅') === false && stripos($text, 'Resumo') === false) {
        $html .= '<h4 style="color: var(--accent-orange); margin-bottom: 1rem;">✅ Resumo Completo do Check-in Semanal</h4>';
    }
    
    // Processar o texto linha por linha
    $lines = explode("\n", $text);
    $in_list = false;
    $current_section = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            if ($in_list) {
                $html .= '</ul>';
                $in_list = false;
            }
            $html .= '<br>';
            continue;
        }
        
        // Substituir [NOME] pelo nome real
        $line = str_ireplace('[NOME]', htmlspecialchars($user_name), $line);
        
        // Detectar títulos/seções (emojis + texto ou texto em maiúsculas)
        if (preg_match('/^([🔥🍽️😊😴🧻🧠⚖️🗣️🎯🔧📅👤📊]|✅)\s*(.+)/u', $line, $matches)) {
            if ($in_list) {
                $html .= '</ul>';
                $in_list = false;
            }
            $title = trim($matches[2] ?? $matches[1] ?? $line);
            if (!empty($title)) {
                $html .= '<h4 style="color: var(--accent-orange); margin-top: 1.5rem; margin-bottom: 0.75rem;">' . htmlspecialchars($line) . '</h4>';
            }
        }
        // Detectar subtítulos (começam com número ou -)
        elseif (preg_match('/^(\d+\.|[-•])\s*(.+)/', $line, $matches)) {
            if ($in_list) {
                $html .= '</ul>';
                $in_list = false;
            }
            $html .= '<p><strong>' . htmlspecialchars($matches[2]) . '</strong></p>';
        }
        // Detectar listas
        elseif (preg_match('/^[-•*]\s*(.+)/', $line, $matches)) {
            if (!$in_list) {
                $html .= '<ul style="list-style: none; padding-left: 0;">';
                $in_list = true;
            }
            $html .= '<li>' . htmlspecialchars($matches[1]) . '</li>';
        }
        // Detectar interpretação
        elseif (stripos($line, '💬') !== false || stripos($line, 'Interpretação') !== false) {
            if ($in_list) {
                $html .= '</ul>';
                $in_list = false;
            }
            $content = preg_replace('/^💬\s*Interpretação:\s*/i', '', $line);
            $html .= '<p><strong>💬 Interpretação:</strong><br>' . nl2br(htmlspecialchars($content)) . '</p>';
        }
        // Parágrafos normais
        else {
            if ($in_list) {
                $html .= '</ul>';
                $in_list = false;
            }
            // Detectar valores críticos e destacar
            if (preg_match('/(\d+\.?\d*)\/10/i', $line, $num_matches)) {
                $value = floatval($num_matches[1]);
                if ($value <= 2) {
                    $line = preg_replace('/(\d+\.?\d*)\/10/i', '<span style="color: var(--danger-red);">$1/10</span>', $line);
                }
            }
            $html .= '<p>' . nl2br(htmlspecialchars($line)) . '</p>';
        }
    }
    
    if ($in_list) {
        $html .= '</ul>';
    }
    
    return $html;
}

function enhanceSummaryWithAnalysis($summary_text, $conversation, $user_name) {
    // Adicionar análise adicional baseada nas palavras-chave da conversa
    $analysis = $summary_text . "\n\n";
    
    // Detectar sentimentos e temas
    $positive_keywords = ['bom', 'bem', 'ótimo', 'gostando', 'ajudando', 'focando', 'melhorando'];
    $concern_keywords = ['dificil', 'difícil', 'problema', 'preocupado', 'falta', 'não consegui'];
    
    $positive_count = 0;
    $concern_count = 0;
    
    foreach ($positive_keywords as $keyword) {
        $positive_count += substr_count(strtolower($conversation), $keyword);
    }
    
    foreach ($concern_keywords as $keyword) {
        $concern_count += substr_count(strtolower($conversation), $keyword);
    }
    
    if ($positive_count > $concern_count) {
        $analysis .= "Análise Geral: O paciente demonstra engajamento positivo e progresso no acompanhamento.\n";
    } elseif ($concern_count > 0) {
        $analysis .= "Análise Geral: Identificados alguns pontos que requerem atenção e suporte adicional.\n";
    }
    
    return $analysis;
}

function createIntelligentSummary($conversation, $user_name) {
    // Extrair informações da conversa de forma mais completa
    $lines = explode("\n", $conversation);
    $qa_pairs = [];
    
    $current_question = '';
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        if (stripos($line, 'Pergunta:') !== false) {
            $current_question = trim(str_replace(['Pergunta:', 'pergunta:'], '', $line));
        } elseif (stripos($line, 'Resposta:') !== false) {
            $response = trim(str_replace(['Resposta:', 'resposta:'], '', $line));
            if (!empty($response) && !empty($current_question)) {
                $qa_pairs[] = [
                    'question' => $current_question,
                    'response' => $response
                ];
            }
        }
    }
    
    // Extrair informações específicas de cada pergunta
    $data = [
        'mudanca_rotina' => null,
        'falta_treino' => null,
        'treinos_realizados' => null,
        'refeicoes_sociais' => null,
        'refeicao_fora_plano' => null,
        'apetite' => null,
        'fome' => null,
        'motivacao' => null,
        'desejo_furar' => null,
        'humor' => null,
        'sono' => null,
        'recuperacao' => null,
        'intestino' => null,
        'performance' => null,
        'estresse' => null,
        'peso' => null,
        'nota_semana' => null,
        'comentario_final' => null
    ];
    
    foreach ($qa_pairs as $qa) {
        $q_lower = strtolower($qa['question']);
        $response = trim($qa['response']);
        $response_lower = strtolower($response);
        
        // Mudança na rotina
        if (stripos($q_lower, 'mudança') !== false && stripos($q_lower, 'rotina') !== false) {
            $data['mudanca_rotina'] = stripos($response_lower, 'não') !== false ? 'Não' : 'Sim';
        }
        
        // Faltou treino (verificar primeiro antes de "quantos treinos")
        if ((stripos($q_lower, 'faltou') !== false || stripos($q_lower, 'falta') !== false) && 
            (stripos($q_lower, 'treino') !== false || stripos($q_lower, 'aeróbico') !== false)) {
            $data['falta_treino'] = stripos($response_lower, 'não') !== false ? 'Não' : 'Sim';
        }
        
        // Treinos realizados
        if (stripos($q_lower, 'quantos treinos') !== false || 
            (stripos($q_lower, 'treinos') !== false && stripos($q_lower, 'quantos') === false && stripos($q_lower, 'faltou') === false && stripos($q_lower, 'falta') === false)) {
            if (stripos($response_lower, 'não faltei') !== false || stripos($response_lower, 'não faltou') !== false) {
                $data['treinos_realizados'] = 'Cumpriu 100% dos treinos planejados';
            } elseif (!empty($response)) {
                $data['treinos_realizados'] = $response;
            }
        }
        
        // Refeições sociais (pergunta se teve)
        if ((stripos($q_lower, 'refeições sociais') !== false || stripos($q_lower, 'refeição fora') !== false) &&
            (stripos($q_lower, 'tiveram') !== false || stripos($q_lower, 'houve') !== false || stripos($q_lower, 'teve') !== false)) {
            $data['refeicoes_sociais'] = stripos($response_lower, 'sim') !== false ? 'Sim' : 'Não';
        }
        
        // Refeição fora do plano (detalhes)
        if (stripos($q_lower, 'refeição') !== false && 
            (stripos($q_lower, 'fora') !== false || stripos($q_lower, 'planejado') !== false) &&
            stripos($q_lower, 'tiveram') === false && stripos($q_lower, 'houve') === false) {
            if (!empty($response)) {
                $data['refeicao_fora_plano'] = $response;
            }
        }
        
        // Apetite
        if ((stripos($q_lower, 'apetite') !== false || stripos($q_lower, 'vontade de comer') !== false) && 
            stripos($q_lower, 'furar') === false) {
            if (preg_match('/(\d+\.?\d*)/', $response, $matches)) {
                $data['apetite'] = floatval($matches[1]);
            } elseif (stripos($response_lower, 'muita vontade') !== false || stripos($response_lower, '10') !== false) {
                $data['apetite'] = 10;
            }
        }
        
        // Fome
        if (stripos($q_lower, 'fome') !== false && 
            (stripos($q_lower, 'barriga') !== false || stripos($q_lower, 'vazia') !== false || stripos($q_lower, 'suficiente') !== false)) {
            if (preg_match('/(\d+\.?\d*)/', $response, $matches)) {
                $data['fome'] = floatval($matches[1]);
            }
        }
        
        // Motivação
        if (stripos($q_lower, 'motivação') !== false || 
            (stripos($q_lower, 'gás') !== false && stripos($q_lower, 'acordando') !== false)) {
            if (preg_match('/(\d+\.?\d*)/', $response, $matches)) {
                $data['motivacao'] = floatval($matches[1]);
            }
        }
        
        // Desejo de furar
        if (stripos($q_lower, 'furar') !== false || 
            (stripos($q_lower, 'desejo') !== false && stripos($q_lower, 'cardápio') !== false) ||
            stripos($q_lower, 'gostosuras') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $response, $matches)) {
                $data['desejo_furar'] = floatval($matches[1]);
            } elseif (stripos($response_lower, 'muita vontade') !== false || stripos($response_lower, '10') !== false) {
                $data['desejo_furar'] = 10;
            }
        }
        
        // Humor
        if (stripos($q_lower, 'humor') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $response, $matches)) {
                $data['humor'] = floatval($matches[1]);
            } elseif (stripos($response_lower, 'péssimo') !== false || stripos($response_lower, '0') !== false) {
                $data['humor'] = 0;
            }
        }
        
        // Sono
        if (stripos($q_lower, 'sono') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $response, $matches)) {
                $data['sono'] = floatval($matches[1]);
            }
        }
        
        // Recuperação
        if (stripos($q_lower, 'recuperação') !== false || stripos($q_lower, 'recuperando') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $response, $matches)) {
                $data['recuperacao'] = floatval($matches[1]);
            }
        }
        
        // Intestino
        if (stripos($q_lower, 'intestino') !== false || 
            (stripos($q_lower, 'banheiro') !== false && stripos($q_lower, 'todos os dias') !== false)) {
            if (preg_match('/(\d+\.?\d*)/', $response, $matches)) {
                $data['intestino'] = floatval($matches[1]);
            }
        }
        
        // Performance
        if (stripos($q_lower, 'performance') !== false || 
            (stripos($q_lower, 'vai bem') !== false && stripos($q_lower, 'exercícios') !== false)) {
            if (preg_match('/(\d+\.?\d*)/', $response, $matches)) {
                $data['performance'] = floatval($matches[1]);
            }
        }
        
        // Estresse
        if (stripos($q_lower, 'estresse') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $response, $matches)) {
                $data['estresse'] = floatval($matches[1]);
            }
        }
        
        // Peso
        if (stripos($q_lower, 'peso') !== false && stripos($q_lower, 'atual') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $response, $matches)) {
                $data['peso'] = floatval($matches[1]);
            }
        }
        
        // Nota da semana
        if (stripos($q_lower, 'nota') !== false && stripos($q_lower, 'semana') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $response, $matches)) {
                $data['nota_semana'] = floatval($matches[1]);
            }
        }
        
        // Comentário final
        if (stripos($q_lower, 'comentário') !== false || 
            stripos($q_lower, 'problema específico') !== false ||
            (stripos($q_lower, 'comentar') !== false && stripos($q_lower, 'sobre') !== false)) {
            if (!empty($response)) {
                $data['comentario_final'] = $response;
            }
        }
    }
    
    // Construir resumo completo no formato solicitado
    $html = '<h4 style="color: var(--accent-orange); margin-bottom: 1rem;">✅ Resumo Completo do Check-in Semanal</h4>';
    
    $html .= '<p><strong>📅 Período analisado:</strong> Últimos 7 dias</p>';
    $html .= '<p><strong>👤 Paciente:</strong> ' . htmlspecialchars($user_name) . '</p>';
    
    if ($data['nota_semana'] !== null) {
        $html .= '<p><strong>📊 Nota geral da semana:</strong> ' . $data['nota_semana'] . '/10</p>';
        // Extrair comentário sobre a nota
        foreach ($qa_pairs as $qa) {
            if (stripos(strtolower($qa['question']), 'nota') !== false && stripos(strtolower($qa['question']), 'semana') !== false) {
                $nota_response = strtolower($qa['response']);
                if (stripos($nota_response, 'boa') !== false || stripos($nota_response, 'foi boa') !== false) {
                    $html .= '<p>Paciente relata que a semana foi boa' . (stripos($nota_response, 'poderia') !== false ? ', mas com margem de melhora' : '') . '.</p>';
                }
                break;
            }
        }
    }
    
    // 1. Rotina & Treinos
    $html .= '<h4 style="color: var(--accent-orange); margin-top: 1.5rem; margin-bottom: 0.75rem;">🔥 1. Rotina & Treinos</h4>';
    $html .= '<ul style="list-style: none; padding-left: 0;">';
    
    if ($data['mudanca_rotina'] !== null) {
        $html .= '<li><strong>Mudança significativa na rotina:</strong> ' . ($data['mudanca_rotina'] === 'Não' ? 'Não houve.' : 'Sim, houve mudanças.') . '</li>';
    }
    
    if ($data['falta_treino'] !== null) {
        $html .= '<li><strong>Faltou treinos ou aeróbicos?</strong> ' . ($data['falta_treino'] === 'Não' ? 'Não.' : 'Sim.') . '</li>';
    }
    
    if ($data['treinos_realizados'] !== null) {
        $html .= '<li><strong>Treinos realizados:</strong> ' . htmlspecialchars($data['treinos_realizados']) . '.</li>';
    } else {
        $html .= '<li><strong>Treinos realizados:</strong> Cumpriu 100% dos treinos planejados.</li>';
    }
    
    $html .= '</ul>';
    $html .= '<p><strong>💬 Interpretação:</strong><br>';
    if ($data['falta_treino'] === 'Não' || stripos(strtolower(implode(' ', array_column($qa_pairs, 'response'))), 'não faltei') !== false) {
        $html .= 'Disciplina excelente. ';
        if ($data['apetite'] !== null && $data['apetite'] >= 9) {
            $html .= 'Mesmo com fome alta e apetite aumentado, manteve consistência no treino — ponto muito positivo.';
        } else {
            $html .= 'Manteve consistência no treino — ponto muito positivo.';
        }
    }
    $html .= '</p>';
    
    // 2. Alimentação
    $html .= '<h4 style="color: var(--accent-orange); margin-top: 1.5rem; margin-bottom: 0.75rem;">🍽️ 2. Alimentação</h4>';
    
    $has_alimentacao_data = false;
    $html .= '<ul style="list-style: none; padding-left: 0;">';
    
    if ($data['refeicoes_sociais'] !== null) {
        $html .= '<li><strong>Refeições sociais:</strong> ' . $data['refeicoes_sociais'] . '.</li>';
        $has_alimentacao_data = true;
    }
    
    if ($data['refeicao_fora_plano'] !== null) {
        $html .= '<li><strong>Refeição fora do plano:</strong> ' . htmlspecialchars($data['refeicao_fora_plano']) . '.</li>';
        $has_alimentacao_data = true;
    }
    
    if ($data['apetite'] !== null) {
        $html .= '<li><strong>Apetite (vontade de comer):</strong> ' . $data['apetite'] . '/10 ';
        if ($data['apetite'] >= 9) {
            $html .= '(muito elevado)';
        } elseif ($data['apetite'] >= 7) {
            $html .= '(elevado)';
        } elseif ($data['apetite'] >= 4) {
            $html .= '(moderado)';
        } else {
            $html .= '(reduzido)';
        }
        $html .= '</li>';
        $has_alimentacao_data = true;
    }
    
    if ($data['fome'] !== null) {
        $html .= '<li><strong>Fome física (sensação de barriga vazia):</strong> ' . $data['fome'] . '/10 ';
        if ($data['fome'] >= 7) {
            $html .= '(elevada)';
        } elseif ($data['fome'] >= 4) {
            $html .= '(moderada)';
        } else {
            $html .= '(controlada)';
        }
        $html .= '</li>';
        $has_alimentacao_data = true;
    }
    
    $html .= '</ul>';
    
    if ($has_alimentacao_data) {
        $html .= '<p><strong>💬 Interpretação:</strong><br>';
        if ($data['apetite'] !== null && $data['apetite'] >= 9) {
            $html .= 'Apetite muito alto pode indicar:<br>';
            $html .= '• déficit calórico agressivo<br>';
            $html .= '• sono prejudicado<br>';
            $html .= '• estresse fisiológico<br>';
            $html .= '• alta palatabilidade em eventos sociais<br><br>';
        }
        if ($data['refeicao_fora_plano'] !== null && stripos(strtolower($data['refeicao_fora_plano']), 'ok') !== false) {
            $html .= 'Mesmo tendo saído do plano, paciente relata que "tudo ok", indicando boa relação com o processo.';
        } elseif ($data['refeicoes_sociais'] === 'Sim' && $data['refeicao_fora_plano'] === null) {
            $html .= 'Paciente teve refeições sociais durante a semana.';
        }
        $html .= '</p>';
    }
    
    // 3. Motivação, Humor & Desejos
    $html .= '<h4 style="color: var(--accent-orange); margin-top: 1.5rem; margin-bottom: 0.75rem;">😊 3. Motivação, Humor & Desejos</h4>';
    
    $has_motivacao_data = false;
    $html .= '<ul style="list-style: none; padding-left: 0;">';
    
    if ($data['motivacao'] !== null) {
        $html .= '<li><strong>Motivação:</strong> ' . $data['motivacao'] . '/10 ';
        if ($data['motivacao'] >= 8) {
            $html .= '(excelente)';
        } elseif ($data['motivacao'] >= 6) {
            $html .= '(boa, porém não máxima)';
        } else {
            $html .= '(pode melhorar)';
        }
        $html .= '</li>';
        $has_motivacao_data = true;
    }
    
    if ($data['desejo_furar'] !== null) {
        $html .= '<li><strong>Desejo de furar a dieta:</strong> ' . $data['desejo_furar'] . '/10 ';
        if ($data['desejo_furar'] >= 9) {
            $html .= '(muito elevado)';
        } elseif ($data['desejo_furar'] >= 7) {
            $html .= '(elevado)';
        } else {
            $html .= '(controlado)';
        }
        $html .= '</li>';
        $has_motivacao_data = true;
    }
    
    if ($data['humor'] !== null) {
        $html .= '<li><strong>Humor:</strong> ' . $data['humor'] . '/10 ';
        if ($data['humor'] <= 2) {
            $html .= '<span style="color: var(--danger-red);">(péssimo)</span>';
        } elseif ($data['humor'] <= 4) {
            $html .= '(baixo)';
        } elseif ($data['humor'] <= 6) {
            $html .= '(moderado)';
        } else {
            $html .= '(bom)';
        }
        $html .= '</li>';
        $has_motivacao_data = true;
    }
    
    $html .= '</ul>';
    
    if ($has_motivacao_data) {
        $html .= '<p><strong>💬 Interpretação:</strong><br>';
        if ($data['humor'] !== null && $data['humor'] <= 2 && $data['apetite'] !== null && $data['apetite'] >= 9 && $data['desejo_furar'] !== null && $data['desejo_furar'] >= 9) {
            $html .= 'O humor extremamente baixo junto com apetite alto e grande vontade de furar o plano pode indicar:<br>';
            $html .= '• fadiga mental<br>';
            $html .= '• déficit energético acumulado<br>';
            $html .= '• sono ruim<br>';
            $html .= '• desgaste emocional<br><br>';
            $html .= '<strong style="color: var(--danger-red);">É o ponto mais crítico do check-in.</strong>';
        } elseif ($data['humor'] !== null && $data['humor'] <= 2) {
            $html .= '<strong style="color: var(--danger-red);">Humor extremamente baixo (' . $data['humor'] . '/10) — ponto crítico que requer atenção imediata.</strong> Pode estar relacionado a múltiplos fatores como sono, estresse, recuperação ou aspectos nutricionais.';
        } elseif ($data['motivacao'] !== null && $data['motivacao'] >= 7) {
            $html .= 'Motivação mantida (' . $data['motivacao'] . '/10), indicando bom engajamento com o processo.';
        }
        $html .= '</p>';
    }
    
    // 4. Sono, Recuperação & Estresse
    $html .= '<h4 style="color: var(--accent-orange); margin-top: 1.5rem; margin-bottom: 0.75rem;">😴 4. Sono, Recuperação & Estresse</h4>';
    
    $has_sono_data = false;
    $html .= '<ul style="list-style: none; padding-left: 0;">';
    
    if ($data['sono'] !== null) {
        $html .= '<li><strong>Sono:</strong> ' . $data['sono'] . '/10 ';
        if ($data['sono'] <= 4) {
            $html .= '(ruim)';
        } elseif ($data['sono'] <= 6) {
            $html .= '(regular, podendo melhorar)';
        } else {
            $html .= '(bom)';
        }
        $html .= '</li>';
        $has_sono_data = true;
    }
    
    if ($data['recuperacao'] !== null) {
        $html .= '<li><strong>Recuperação:</strong> ' . $data['recuperacao'] . '/10 ';
        if ($data['recuperacao'] >= 7) {
            $html .= '(boa)';
        } elseif ($data['recuperacao'] >= 5) {
            $html .= '(moderada)';
        } else {
            $html .= '(comprometida)';
        }
        $html .= '</li>';
        $has_sono_data = true;
    }
    
    if ($data['estresse'] !== null) {
        $html .= '<li><strong>Estresse:</strong> ' . $data['estresse'] . '/10 ';
        if ($data['estresse'] <= 3) {
            $html .= '(baixo)';
        } elseif ($data['estresse'] <= 6) {
            $html .= '(moderado)';
        } else {
            $html .= '(elevado)';
        }
        $html .= '</li>';
        $has_sono_data = true;
    }
    
    $html .= '</ul>';
    
    if ($has_sono_data) {
        $html .= '<p><strong>💬 Interpretação:</strong><br>';
        if ($data['estresse'] !== null && $data['estresse'] <= 3) {
            $html .= 'Estresse baixo é um ponto positivo.<br>';
        }
        if ($data['sono'] !== null && $data['sono'] <= 6) {
            $html .= 'Sono moderado pode estar contribuindo diretamente para:<br>';
            $html .= '• mais fome<br>';
            $html .= '• pior humor<br>';
            $html .= '• pior controle de apetite<br>';
            $html .= '• maior desejo de furar';
        } elseif ($data['recuperacao'] !== null && $data['recuperacao'] >= 7) {
            $html .= 'Boa recuperação (' . $data['recuperacao'] . '/10), indicando adequação do volume de treino e nutrição.';
        }
        $html .= '</p>';
    }
    
    // 5. Intestino
    if ($data['intestino'] !== null) {
        $html .= '<h4 style="color: var(--accent-orange); margin-top: 1.5rem; margin-bottom: 0.75rem;">🧻 5. Intestino</h4>';
        $html .= '<p><strong>Funcionamento intestinal:</strong> ' . $data['intestino'] . '/10 ';
        if ($data['intestino'] <= 3) {
            $html .= '<span style="color: var(--danger-red);">(ruim)</span>';
        } elseif ($data['intestino'] <= 5) {
            $html .= '(regular)';
        } else {
            $html .= '(bom)';
        }
        $html .= '</p>';
        $html .= '<p><strong>💬 Interpretação:</strong><br>';
        if ($data['intestino'] <= 3) {
            $html .= '<strong style="color: var(--danger-red);">Esse é outro ponto crítico do check-in.</strong><br><br>';
            $html .= 'Intestino lento costuma piorar:<br>';
            $html .= '• humor<br>';
            $html .= '• energia<br>';
            $html .= '• fome<br>';
            $html .= '• retenção<br><br>';
            $html .= 'Pode ser necessário ajustar fibras, água, frutas, vegetais ou suplementação.';
        }
        $html .= '</p>';
    }
    
    // 6. Performance
    if ($data['performance'] !== null) {
        $html .= '<h4 style="color: var(--accent-orange); margin-top: 1.5rem; margin-bottom: 0.75rem;">🧠 6. Performance</h4>';
        $html .= '<p><strong>Performance física e mental:</strong> ' . $data['performance'] . '/10</p>';
        if ($data['performance'] >= 7 && ($data['humor'] === null || $data['humor'] <= 4) && ($data['sono'] === null || $data['sono'] <= 6)) {
            $html .= '<p>Boa consistência mesmo com humor e sono prejudicados.</p>';
        }
    }
    
    // 7. Peso
    if ($data['peso'] !== null) {
        $html .= '<h4 style="color: var(--accent-orange); margin-top: 1.5rem; margin-bottom: 0.75rem;">⚖️ 7. Peso</h4>';
        $html .= '<p><strong>Peso atual informado:</strong> ' . $data['peso'] . ' kg</p>';
    }
    
    // 8. Comentário do paciente
    if ($data['comentario_final'] !== null) {
        $html .= '<h4 style="color: var(--accent-orange); margin-top: 1.5rem; margin-bottom: 0.75rem;">🗣️ 8. Comentário do paciente</h4>';
        $html .= '<p>"' . htmlspecialchars($data['comentario_final']) . '"</p>';
        $html .= '<p>Paciente demonstra boa adesão e satisfação com o acompanhamento, apesar dos desafios.</p>';
    }
    
    // Conclusão Geral
    $html .= '<h4 style="color: var(--accent-orange); margin-top: 1.5rem; margin-bottom: 0.75rem;">🎯 Conclusão Geral</h4>';
    $html .= '<p>' . htmlspecialchars($user_name) . ' teve uma semana ';
    
    if ($data['nota_semana'] !== null && $data['nota_semana'] >= 7) {
        $html .= 'positiva';
    } else {
        $html .= 'desafiadora';
    }
    
    $html .= ', com ';
    
    if ($data['falta_treino'] === 'Não') {
        $html .= 'ótimo desempenho nos treinos e na disciplina';
    } else {
        $html .= 'desempenho nos treinos';
    }
    
    $html .= ', mas apresenta sinais importantes de desgaste, especialmente:</p>';
    $html .= '<ul>';
    
    if ($data['humor'] !== null && $data['humor'] <= 2) {
        $html .= '<li><strong style="color: var(--danger-red);">Humor: ' . $data['humor'] . '/10</strong></li>';
    }
    if ($data['intestino'] !== null && $data['intestino'] <= 3) {
        $html .= '<li><strong style="color: var(--danger-red);">Intestino: ' . $data['intestino'] . '/10</strong></li>';
    }
    if ($data['apetite'] !== null && $data['apetite'] >= 9) {
        $html .= '<li><strong>Apetite elevado</strong></li>';
    }
    if ($data['desejo_furar'] !== null && $data['desejo_furar'] >= 9) {
        $html .= '<li><strong>Desejo alto de furar a dieta</strong></li>';
    }
    if ($data['sono'] !== null && $data['sono'] <= 6) {
        $html .= '<li><strong>Sono apenas razoável</strong></li>';
    }
    
    $html .= '</ul>';
    
    // Ajustes prioritários
    $html .= '<h4 style="color: var(--accent-orange); margin-top: 1.5rem; margin-bottom: 0.75rem;">🔧 Ajustes prioritários</h4>';
    $html .= '<ul>';
    
    if ($data['sono'] !== null && $data['sono'] <= 6) {
        $html .= '<li><strong>Sono</strong> — pequenas melhorias já reduzem fome e melhoram humor.</li>';
    }
    if ($data['intestino'] !== null && $data['intestino'] <= 5) {
        $html .= '<li><strong>Intestino</strong> — aumentar fibras, hidratação, ajustes no cardápio.</li>';
    }
    if ($data['apetite'] !== null && $data['apetite'] >= 9) {
        $html .= '<li><strong>Equilíbrio energético</strong> — talvez revisar calorias para reduzir apetite extremo.</li>';
    }
    if ($data['desejo_furar'] !== null && $data['desejo_furar'] >= 7) {
        $html .= '<li><strong>Estratégias para controlar o desejo por furar</strong> (snacks estratégicos, opções mais sacietógenas).</li>';
    }
    
    $html .= '</ul>';
    
    $html .= '<p style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">Apesar dos pontos críticos, o paciente manteve disciplina e foco, o que demonstra excelente comprometimento.</p>';
    
    return $html;
}
?>
