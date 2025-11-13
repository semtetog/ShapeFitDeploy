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
