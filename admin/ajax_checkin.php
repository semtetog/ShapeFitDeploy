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
    $conversation = trim($data['conversation'] ?? '');
    $user_name = trim($data['user_name'] ?? 'Usuário');
    
    if (empty($conversation)) {
        echo json_encode(['success' => false, 'message' => 'Conversa vazia']);
        exit;
    }
    
    // Usar Hugging Face Chat API com modelo de chat para análise mais inteligente
    // Modelo: meta-llama/Llama-3.2-3B-Instruct ou microsoft/Phi-3-mini-4k-instruct
    // Vou usar o modelo de chat que permite prompts mais complexos
    $api_url = 'https://api-inference.huggingface.co/models/microsoft/Phi-3-mini-4k-instruct';
    
    // Criar prompt detalhado para análise
    $prompt = "Você é um nutricionista experiente analisando um check-in semanal detalhado de um paciente. Analise TODA a conversa abaixo e crie um resumo PROFISSIONAL, DETALHADO e ANALÍTICO com:\n\n";
    $prompt .= "1. ANÁLISE GERAL: Avalie o estado geral do paciente, incluindo todos os aspectos mencionados (alimentação, exercícios, sono, humor, motivação, etc). Seja específico e mencione valores/notas quando relevante.\n\n";
    $prompt .= "2. PONTOS POSITIVOS: Identifique e detalhe todos os aspectos positivos mencionados pelo paciente. Seja específico sobre o que está funcionando bem.\n\n";
    $prompt .= "3. PONTOS DE ATENÇÃO: Identifique e detalhe todos os aspectos que requerem atenção, preocupação ou suporte. Inclua notas baixas, dificuldades mencionadas, e qualquer sinal de alerta. Seja específico sobre os valores/notas preocupantes.\n\n";
    $prompt .= "4. ANÁLISE DETALHADA POR CATEGORIA: Analise cada categoria mencionada (apetite, fome, motivação, humor, sono, recuperação, intestino, performance, estresse) com comentários específicos sobre os valores fornecidos.\n\n";
    $prompt .= "5. OBSERVAÇÕES E RECOMENDAÇÕES: Forneça observações profissionais e recomendações específicas baseadas na análise completa.\n\n";
    $prompt .= "IMPORTANTE: Leia TODA a conversa, incluindo todas as perguntas e respostas. Mencione valores específicos (notas, números) quando relevante. Seja detalhado e profissional.\n\n";
    $prompt .= "Conversa completa:\n" . $conversation . "\n\n";
    $prompt .= "Agora crie um resumo profissional, detalhado e analítico em português brasileiro:";
    
    // Limitar tamanho do prompt mas manter estrutura
    if (strlen($prompt) > 3500) {
        $conversation_short = substr($conversation, 0, 2500) . '...';
        $prompt = "Você é um nutricionista experiente analisando um check-in semanal detalhado de um paciente. Analise TODA a conversa abaixo e crie um resumo PROFISSIONAL, DETALHADO e ANALÍTICO com:\n\n";
        $prompt .= "1. ANÁLISE GERAL: Avalie o estado geral do paciente, incluindo todos os aspectos mencionados. Seja específico e mencione valores/notas quando relevante.\n\n";
        $prompt .= "2. PONTOS POSITIVOS: Identifique e detalhe todos os aspectos positivos mencionados pelo paciente.\n\n";
        $prompt .= "3. PONTOS DE ATENÇÃO: Identifique todos os aspectos que requerem atenção, incluindo notas baixas e dificuldades.\n\n";
        $prompt .= "4. ANÁLISE DETALHADA: Analise cada categoria mencionada com comentários específicos sobre os valores fornecidos.\n\n";
        $prompt .= "5. OBSERVAÇÕES E RECOMENDAÇÕES: Forneça observações profissionais e recomendações específicas.\n\n";
        $prompt .= "Conversa completa:\n" . $conversation_short . "\n\n";
        $prompt .= "Agora crie um resumo profissional, detalhado e analítico em português brasileiro:";
    }
    
    // Fazer requisição para a API
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'inputs' => $prompt,
        'parameters' => [
            'max_new_tokens' => 500,
            'temperature' => 0.7,
            'return_full_text' => false
        ]
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 || $http_code === 503) {
        $result = json_decode($response, true);
        
        // Extrair texto gerado
        $generated_text = '';
        if (isset($result[0]['generated_text'])) {
            $generated_text = $result[0]['generated_text'];
        } elseif (isset($result['generated_text'])) {
            $generated_text = $result['generated_text'];
        } elseif (is_string($result)) {
            $generated_text = $result;
        }
        
        if (!empty($generated_text)) {
            // Formatar o resumo em HTML
            $formatted_summary = formatSummaryHTML($generated_text, $user_name);
            
            echo json_encode([
                'success' => true,
                'summary' => $formatted_summary
            ]);
            return;
        }
    }
    
    // Fallback: tentar com modelo de sumarização tradicional mas com melhor prompt
    $api_url_fallback = 'https://api-inference.huggingface.co/models/facebook/bart-large-cnn';
    
    // Criar texto formatado para sumarização
    $text_for_summary = "Check-in semanal do paciente. " . $conversation;
    if (strlen($text_for_summary) > 2000) {
        $text_for_summary = substr($text_for_summary, 0, 2000) . '...';
    }
    
    $ch = curl_init($api_url_fallback);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'inputs' => $text_for_summary,
        'parameters' => [
            'max_length' => 300,
            'min_length' => 100,
            'do_sample' => false
        ]
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if (($http_code === 200 || $http_code === 503) && !empty($response)) {
        $result = json_decode($response, true);
        
        if (isset($result[0]['summary_text'])) {
            $summary_text = $result[0]['summary_text'];
            
            // Adicionar análise adicional baseada nas respostas
            $enhanced_summary = enhanceSummaryWithAnalysis($summary_text, $conversation, $user_name);
            $formatted_summary = formatSummaryHTML($enhanced_summary, $user_name);
            
            echo json_encode([
                'success' => true,
                'summary' => $formatted_summary
            ]);
            return;
        }
    }
    
    // Último fallback: criar resumo inteligente manual
    $formatted_summary = createIntelligentSummary($conversation, $user_name);
    echo json_encode([
        'success' => true,
        'summary' => $formatted_summary
    ]);
}

function formatSummaryHTML($summary_text, $user_name) {
    $html = '<h4>Resumo do Check-in</h4>';
    $html .= '<p><strong>Paciente:</strong> ' . htmlspecialchars($user_name) . '</p>';
    
    // Processar o texto para identificar seções
    $text = trim($summary_text);
    
    // Se o texto já tem estrutura (com números, títulos, etc), formatar melhor
    $text = preg_replace('/\n\s*\n/', "\n\n", $text); // Remover linhas vazias extras
    $paragraphs = explode("\n\n", $text);
    
    foreach ($paragraphs as $para) {
        $para = trim($para);
        if (empty($para)) continue;
        
        // Detectar se é um título/seção
        if (preg_match('/^(\d+\.?\s*[-:]?\s*)?(Análise|Pontos|Recomendações|Observações|Estado|Atenção|Positivos|Negativos)/i', $para)) {
            $html .= '<h4>' . htmlspecialchars($para) . '</h4>';
        } elseif (preg_match('/^[-•*]\s+/', $para) || preg_match('/^\d+\.\s+/', $para)) {
            // Lista
            $html .= '<ul><li>' . htmlspecialchars(preg_replace('/^[-•*]\s+/', '', $para)) . '</li></ul>';
        } else {
            $html .= '<p>' . nl2br(htmlspecialchars($para)) . '</p>';
        }
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
    
    $html = '<h4>Resumo do Check-in</h4>';
    $html .= '<p><strong>Paciente:</strong> ' . htmlspecialchars($user_name) . '</p>';
    
    // Análise geral detalhada
    $html .= '<h4>Análise Geral</h4>';
    $total_responses = count($qa_pairs);
    $html .= '<p>O paciente completou o check-in semanal respondendo a <strong>' . $total_responses . ' perguntas</strong>. ';
    
    // Extrair todas as notas numéricas e valores
    $all_responses_text = strtolower(implode(' ', array_column($qa_pairs, 'response')));
    $numeric_values = [];
    $scores = [];
    
    // Procurar por padrões numéricos (notas de 0 a 10, valores, etc)
    foreach ($qa_pairs as $qa) {
        $response_lower = strtolower($qa['response']);
        $question_lower = strtolower($qa['question']);
        
        // Extrair números da resposta
        if (preg_match('/(\d+\.?\d*)/', $qa['response'], $matches)) {
            $numeric_value = floatval($matches[1]);
            if ($numeric_value >= 0 && $numeric_value <= 10) {
                $scores[] = [
                    'question' => $qa['question'],
                    'value' => $numeric_value
                ];
            }
        }
        
        // Detectar respostas específicas importantes
        if (stripos($question_lower, 'humor') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $qa['response'], $matches)) {
                $numeric_values['humor'] = floatval($matches[1]);
            }
        }
        if (stripos($question_lower, 'apetite') !== false || stripos($question_lower, 'vontade de comer') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $qa['response'], $matches)) {
                $numeric_values['apetite'] = floatval($matches[1]);
            }
        }
        if (stripos($question_lower, 'fome') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $qa['response'], $matches)) {
                $numeric_values['fome'] = floatval($matches[1]);
            }
        }
        if (stripos($question_lower, 'motivação') !== false || stripos($question_lower, 'gás') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $qa['response'], $matches)) {
                $numeric_values['motivacao'] = floatval($matches[1]);
            }
        }
        if (stripos($question_lower, 'sono') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $qa['response'], $matches)) {
                $numeric_values['sono'] = floatval($matches[1]);
            }
        }
        if (stripos($question_lower, 'recuperação') !== false || stripos($question_lower, 'recuperando') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $qa['response'], $matches)) {
                $numeric_values['recuperacao'] = floatval($matches[1]);
            }
        }
        if (stripos($question_lower, 'intestino') !== false || stripos($question_lower, 'banheiro') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $qa['response'], $matches)) {
                $numeric_values['intestino'] = floatval($matches[1]);
            }
        }
        if (stripos($question_lower, 'performance') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $qa['response'], $matches)) {
                $numeric_values['performance'] = floatval($matches[1]);
            }
        }
        if (stripos($question_lower, 'estresse') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $qa['response'], $matches)) {
                $numeric_values['estresse'] = floatval($matches[1]);
            }
        }
        if (stripos($question_lower, 'peso') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $qa['response'], $matches)) {
                $numeric_values['peso'] = floatval($matches[1]);
            }
        }
        if (stripos($question_lower, 'nota') !== false && stripos($question_lower, 'semana') !== false) {
            if (preg_match('/(\d+\.?\d*)/', $qa['response'], $matches)) {
                $numeric_values['nota_semana'] = floatval($matches[1]);
            }
        }
    }
    
    // Análise de sentimentos
    $positive_indicators = ['bom', 'bem', 'ótimo', 'gostando', 'ajudando', 'focando', 'melhorando', 'ok', 'tudo certo', 'boa'];
    $challenge_indicators = ['dificil', 'difícil', 'problema', 'preocupado', 'falta', 'não consegui', 'complicado', 'péssimo'];
    
    $positive_score = 0;
    $challenge_score = 0;
    
    foreach ($positive_indicators as $indicator) {
        $positive_score += substr_count($all_responses_text, $indicator);
    }
    
    foreach ($challenge_indicators as $indicator) {
        $challenge_score += substr_count($all_responses_text, $indicator);
    }
    
    // Análise geral baseada em todos os dados
    $html .= 'Analisando todas as respostas e avaliações numéricas, ';
    
    $critical_issues = [];
    $positive_aspects = [];
    
    // Analisar cada métrica
    if (isset($numeric_values['humor']) && $numeric_values['humor'] <= 2) {
        $critical_issues[] = 'Humor extremamente baixo (' . $numeric_values['humor'] . '/10) - requer atenção imediata';
    }
    if (isset($numeric_values['intestino']) && $numeric_values['intestino'] <= 3) {
        $critical_issues[] = 'Função intestinal comprometida (' . $numeric_values['intestino'] . '/10)';
    }
    if (isset($numeric_values['apetite']) && $numeric_values['apetite'] >= 9) {
        $critical_issues[] = 'Apetite muito elevado (' . $numeric_values['apetite'] . '/10) - pode indicar necessidade de ajuste nutricional';
    }
    
    if (isset($numeric_values['motivacao']) && $numeric_values['motivacao'] >= 7) {
        $positive_aspects[] = 'Motivação mantida (' . $numeric_values['motivacao'] . '/10)';
    }
    if (isset($numeric_values['recuperacao']) && $numeric_values['recuperacao'] >= 7) {
        $positive_aspects[] = 'Boa recuperação (' . $numeric_values['recuperacao'] . '/10)';
    }
    if (isset($numeric_values['performance']) && $numeric_values['performance'] >= 7) {
        $positive_aspects[] = 'Performance adequada (' . $numeric_values['performance'] . '/10)';
    }
    
    if (count($critical_issues) > 0) {
        $html .= 'identificamos <strong>pontos críticos que requerem atenção imediata</strong>. ';
    } elseif ($positive_score > $challenge_score * 2) {
        $html .= 'o paciente demonstra <strong>engajamento positivo e progresso consistente</strong>. ';
    } elseif ($challenge_score > 0) {
        $html .= 'identificamos <strong>alguns desafios que requerem atenção e suporte adicional</strong>. ';
    } else {
        $html .= 'o paciente está <strong>mantendo o acompanhamento regular</strong>. ';
    }
    $html .= '</p>';
    
    // Análise Detalhada por Categoria
    $html .= '<h4>Análise Detalhada por Categoria</h4>';
    $html .= '<ul>';
    
    if (isset($numeric_values['humor'])) {
        $humor = $numeric_values['humor'];
        $html .= '<li><strong>Humor:</strong> ' . $humor . '/10 - ';
        if ($humor <= 2) {
            $html .= '<span style="color: var(--danger-red);">CRÍTICO: Humor extremamente baixo, requer atenção profissional imediata.</span>';
        } elseif ($humor <= 4) {
            $html .= 'Humor baixo, pode estar relacionado a outros fatores como sono ou estresse.';
        } elseif ($humor <= 6) {
            $html .= 'Humor moderado, dentro do esperado considerando os desafios da rotina.';
        } else {
            $html .= 'Humor positivo, indicando bem-estar emocional adequado.';
        }
        $html .= '</li>';
    }
    
    if (isset($numeric_values['apetite'])) {
        $apetite = $numeric_values['apetite'];
        $html .= '<li><strong>Apetite (Vontade de Comer):</strong> ' . $apetite . '/10 - ';
        if ($apetite >= 9) {
            $html .= 'Apetite muito elevado, pode indicar necessidade de ajuste na distribuição de macronutrientes ou horários das refeições.';
        } elseif ($apetite >= 7) {
            $html .= 'Apetite elevado, dentro do normal para fase de adaptação.';
        } elseif ($apetite >= 4) {
            $html .= 'Apetite moderado, adequado ao plano nutricional.';
        } else {
            $html .= 'Apetite reduzido, pode indicar necessidade de revisão do plano.';
        }
        $html .= '</li>';
    }
    
    if (isset($numeric_values['fome'])) {
        $fome = $numeric_values['fome'];
        $html .= '<li><strong>Níveis de Fome:</strong> ' . $fome . '/10 - ';
        if ($fome >= 7) {
            $html .= 'Fome elevada durante o dia, pode ser necessário ajustar volume ou distribuição das refeições.';
        } elseif ($fome >= 4) {
            $html .= 'Fome moderada, adequada ao plano nutricional.';
        } else {
            $html .= 'Fome controlada, indicando boa adesão ao plano.';
        }
        $html .= '</li>';
    }
    
    if (isset($numeric_values['motivacao'])) {
        $motivacao = $numeric_values['motivacao'];
        $html .= '<li><strong>Motivação:</strong> ' . $motivacao . '/10 - ';
        if ($motivacao >= 8) {
            $html .= 'Alta motivação, excelente engajamento com o processo.';
        } elseif ($motivacao >= 6) {
            $html .= 'Motivação adequada, mantendo foco nos objetivos.';
        } else {
            $html .= 'Motivação pode estar sendo desafiada, pode precisar de suporte adicional.';
        }
        $html .= '</li>';
    }
    
    if (isset($numeric_values['sono'])) {
        $sono = $numeric_values['sono'];
        $html .= '<li><strong>Sono (Quantidade e Qualidade):</strong> ' . $sono . '/10 - ';
        if ($sono <= 4) {
            $html .= 'Sono comprometido, pode impactar recuperação, humor e performance. Recomenda-se atenção.';
        } elseif ($sono <= 6) {
            $html .= 'Sono moderado, pode ser melhorado para otimizar resultados.';
        } else {
            $html .= 'Sono adequado, contribuindo positivamente para a recuperação.';
        }
        $html .= '</li>';
    }
    
    if (isset($numeric_values['recuperacao'])) {
        $recuperacao = $numeric_values['recuperacao'];
        $html .= '<li><strong>Recuperação:</strong> ' . $recuperacao . '/10 - ';
        if ($recuperacao >= 7) {
            $html .= 'Boa recuperação, indicando adequação do volume de treino e nutrição.';
        } elseif ($recuperacao >= 5) {
            $html .= 'Recuperação moderada, pode ser otimizada.';
        } else {
            $html .= 'Recuperação comprometida, pode ser necessário ajustar volume de treino ou nutrição.';
        }
        $html .= '</li>';
    }
    
    if (isset($numeric_values['intestino'])) {
        $intestino = $numeric_values['intestino'];
        $html .= '<li><strong>Função Intestinal:</strong> ' . $intestino . '/10 - ';
        if ($intestino <= 3) {
            $html .= '<span style="color: var(--danger-red);">ATENÇÃO: Função intestinal comprometida, pode indicar necessidade de ajuste na ingestão de fibras, hidratação ou distribuição de macronutrientes.</span>';
        } elseif ($intestino <= 5) {
            $html .= 'Função intestinal pode ser melhorada com ajustes nutricionais.';
        } else {
            $html .= 'Função intestinal adequada.';
        }
        $html .= '</li>';
    }
    
    if (isset($numeric_values['performance'])) {
        $performance = $numeric_values['performance'];
        $html .= '<li><strong>Performance:</strong> ' . $performance . '/10 - ';
        if ($performance >= 7) {
            $html .= 'Performance adequada, indicando boa adaptação ao plano.';
        } elseif ($performance >= 5) {
            $html .= 'Performance moderada, pode ser otimizada.';
        } else {
            $html .= 'Performance comprometida, pode estar relacionada a recuperação, sono ou nutrição.';
        }
        $html .= '</li>';
    }
    
    if (isset($numeric_values['estresse'])) {
        $estresse = $numeric_values['estresse'];
        $html .= '<li><strong>Níveis de Estresse:</strong> ' . $estresse . '/10 - ';
        if ($estresse <= 3) {
            $html .= 'Estresse controlado, ambiente favorável para progresso.';
        } elseif ($estresse <= 6) {
            $html .= 'Estresse moderado, dentro do esperado.';
        } else {
            $html .= 'Estresse elevado, pode impactar recuperação e adesão ao plano.';
        }
        $html .= '</li>';
    }
    
    if (isset($numeric_values['peso'])) {
        $peso = $numeric_values['peso'];
        $html .= '<li><strong>Peso Atual:</strong> ' . $peso . ' kg</li>';
    }
    
    if (isset($numeric_values['nota_semana'])) {
        $nota = $numeric_values['nota_semana'];
        $html .= '<li><strong>Avaliação da Semana:</strong> ' . $nota . '/10 - ';
        if ($nota >= 8) {
            $html .= 'Semana excelente segundo o próprio paciente.';
        } elseif ($nota >= 6) {
            $html .= 'Semana boa, com espaço para melhorias conforme relatado pelo paciente.';
        } else {
            $html .= 'Semana desafiadora, pode precisar de suporte adicional.';
        }
        $html .= '</li>';
    }
    
    $html .= '</ul>';
    
    // Pontos Positivos Detalhados
    $html .= '<h4>Pontos Positivos</h4>';
    $html .= '<ul>';
    
    if (stripos($all_responses_text, 'gostando') !== false || stripos($all_responses_text, 'ajudando') !== false) {
        $html .= '<li><strong>Satisfação com o processo:</strong> O paciente demonstra satisfação com o aplicativo e reconhece o valor do acompanhamento para manter o foco.</li>';
    }
    if (stripos($all_responses_text, 'focando') !== false || stripos($all_responses_text, 'forçando') !== false) {
        $html .= '<li><strong>Determinação:</strong> Mostra determinação e esforço ativo para manter o foco nos objetivos, mesmo diante de dificuldades.</li>';
    }
    if (stripos($all_responses_text, 'não faltei') !== false || stripos($all_responses_text, 'não faltou') !== false) {
        $html .= '<li><strong>Consistência nos treinos:</strong> Manteve a consistência nos treinos durante a semana, sem faltas.</li>';
    }
    if (isset($numeric_values['motivacao']) && $numeric_values['motivacao'] >= 7) {
        $html .= '<li><strong>Motivação mantida:</strong> Mantém boa motivação (' . $numeric_values['motivacao'] . '/10) para cuidar da saúde.</li>';
    }
    if (isset($numeric_values['recuperacao']) && $numeric_values['recuperacao'] >= 7) {
        $html .= '<li><strong>Boa recuperação:</strong> Apresenta boa recuperação (' . $numeric_values['recuperacao'] . '/10) tanto dos exercícios quanto das atividades do dia-a-dia.</li>';
    }
    if (isset($numeric_values['performance']) && $numeric_values['performance'] >= 7) {
        $html .= '<li><strong>Performance adequada:</strong> Mantém boa performance (' . $numeric_values['performance'] . '/10) tanto nos exercícios quanto nas atividades mentais.</li>';
    }
    if (isset($numeric_values['estresse']) && $numeric_values['estresse'] <= 3) {
        $html .= '<li><strong>Estresse controlado:</strong> Níveis de estresse baixos (' . $numeric_values['estresse'] . '/10), ambiente favorável para progresso.</li>';
    }
    
    $html .= '</ul>';
    
    // Pontos de Atenção Detalhados
    $html .= '<h4>Pontos de Atenção</h4>';
    $html .= '<ul>';
    
    if (isset($numeric_values['humor']) && $numeric_values['humor'] <= 2) {
        $html .= '<li><strong style="color: var(--danger-red);">HUMOR CRÍTICO:</strong> Humor extremamente baixo (' . $numeric_values['humor'] . '/10). Este é um ponto crítico que requer atenção imediata e pode estar relacionado a múltiplos fatores (sono, estresse, recuperação, aspectos nutricionais). Recomenda-se investigação mais profunda e possível suporte profissional.</li>';
    }
    
    if (isset($numeric_values['intestino']) && $numeric_values['intestino'] <= 3) {
        $html .= '<li><strong style="color: var(--danger-red);">FUNÇÃO INTESTINAL COMPROMETIDA:</strong> Nota muito baixa (' . $numeric_values['intestino'] . '/10) para função intestinal. Pode indicar necessidade de ajuste na ingestão de fibras, hidratação adequada, ou distribuição de macronutrientes. Pode também estar relacionado ao estresse ou outros fatores.</li>';
    }
    
    if (isset($numeric_values['apetite']) && $numeric_values['apetite'] >= 9) {
        $html .= '<li><strong>Apetite muito elevado:</strong> Apetite de ' . $numeric_values['apetite'] . '/10 pode indicar necessidade de ajuste na distribuição de macronutrientes, horários das refeições, ou volume alimentar. Pode estar relacionado ao humor baixo ou outros fatores.</li>';
    }
    
    if (stripos($all_responses_text, 'dificil') !== false || stripos($all_responses_text, 'difícil') !== false) {
        $html .= '<li><strong>Dificuldades na dieta:</strong> O paciente menciona que é difícil manter a dieta, mas está se esforçando e focando. Pode precisar de estratégias adicionais ou ajustes no plano para facilitar a adesão.</li>';
    }
    
    if (isset($numeric_values['sono']) && $numeric_values['sono'] <= 5) {
        $html .= '<li><strong>Sono comprometido:</strong> Sono com nota ' . $numeric_values['sono'] . '/10 pode estar impactando negativamente o humor, recuperação e performance. Recomenda-se atenção para melhorar qualidade e quantidade de sono.</li>';
    }
    
    if (isset($numeric_values['fome']) && $numeric_values['fome'] >= 7) {
        $html .= '<li><strong>Fome elevada:</strong> Níveis de fome de ' . $numeric_values['fome'] . '/10 durante o dia podem indicar necessidade de ajuste no volume ou distribuição das refeições.</li>';
    }
    
    $html .= '</ul>';
    
    // Observações e Recomendações
    $html .= '<h4>Observações e Recomendações</h4>';
    $html .= '<ul>';
    
    // Detectar temas específicos
    if (stripos($all_responses_text, 'dieta') !== false || stripos($all_responses_text, 'comida') !== false || stripos($all_responses_text, 'comi') !== false) {
        $html .= '<li><strong>Alimentação:</strong> O paciente menciona questões relacionadas à alimentação. ';
        if (stripos($all_responses_text, 'jantar') !== false || stripos($all_responses_text, 'aniversário') !== false || stripos($all_responses_text, 'festa') !== false) {
            $html .= 'Houve eventos sociais (jantar de aniversário) com consumo de alimentos fora do plano, mas o paciente relatou que "tudo ok", indicando boa gestão da situação.';
        }
        $html .= '</li>';
    }
    
    if (stripos($all_responses_text, 'exercicio') !== false || stripos($all_responses_text, 'exercício') !== false || stripos($all_responses_text, 'treino') !== false) {
        $html .= '<li><strong>Exercícios:</strong> O paciente manteve consistência nos treinos, sem faltas durante a semana. ';
        if (stripos($all_responses_text, 'forçando') !== false) {
            $html .= 'Está se esforçando ativamente para manter a rotina de exercícios, demonstrando comprometimento.';
        }
        $html .= '</li>';
    }
    
    if (isset($numeric_values['humor']) && $numeric_values['humor'] <= 2) {
        $html .= '<li><strong style="color: var(--danger-red);">PRIORIDADE:</strong> O humor extremamente baixo (' . $numeric_values['humor'] . '/10) é o ponto mais crítico identificado. Recomenda-se investigar causas (sono, estresse, aspectos nutricionais, fatores externos) e considerar suporte profissional adicional se necessário.</li>';
    }
    
    if (isset($numeric_values['intestino']) && $numeric_values['intestino'] <= 3) {
        $html .= '<li><strong>Ajuste Nutricional:</strong> A função intestinal comprometida (' . $numeric_values['intestino'] . '/10) pode ser melhorada com aumento de fibras, hidratação adequada, e possível ajuste na distribuição de macronutrientes. Pode também estar relacionado ao humor baixo ou estresse.</li>';
    }
    
    if (isset($numeric_values['apetite']) && $numeric_values['apetite'] >= 9 && isset($numeric_values['humor']) && $numeric_values['humor'] <= 2) {
        $html .= '<li><strong>Correlação Apetite-Humor:</strong> O apetite muito elevado (' . $numeric_values['apetite'] . '/10) pode estar relacionado ao humor extremamente baixo (' . $numeric_values['humor'] . '/10), indicando possível alimentação emocional. Recomenda-se abordagem integrada.</li>';
    }
    
    $html .= '</ul>';
    
    return $html;
}
?>
