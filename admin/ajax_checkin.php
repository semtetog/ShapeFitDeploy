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
    $prompt = "Você é um assistente de saúde que analisa check-ins semanais de pacientes. Analise a seguinte conversa e crie um resumo detalhado com:\n\n";
    $prompt .= "1. Análise geral do estado do paciente\n";
    $prompt .= "2. Pontos positivos identificados\n";
    $prompt .= "3. Pontos de atenção ou preocupação\n";
    $prompt .= "4. Recomendações ou observações importantes\n\n";
    $prompt .= "Conversa:\n" . $conversation . "\n\n";
    $prompt .= "Crie um resumo profissional e detalhado em português brasileiro:";
    
    // Limitar tamanho do prompt
    if (strlen($prompt) > 3000) {
        $conversation_short = substr($conversation, 0, 2000) . '...';
        $prompt = "Você é um assistente de saúde que analisa check-ins semanais de pacientes. Analise a seguinte conversa e crie um resumo detalhado com:\n\n";
        $prompt .= "1. Análise geral do estado do paciente\n";
        $prompt .= "2. Pontos positivos identificados\n";
        $prompt .= "3. Pontos de atenção ou preocupação\n";
        $prompt .= "4. Recomendações ou observações importantes\n\n";
        $prompt .= "Conversa:\n" . $conversation_short . "\n\n";
        $prompt .= "Crie um resumo profissional e detalhado em português brasileiro:";
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
    // Extrair informações da conversa
    $lines = explode("\n", $conversation);
    $questions = [];
    $responses = [];
    
    $current_question = '';
    foreach ($lines as $line) {
        if (strpos($line, 'Pergunta:') !== false) {
            $current_question = trim(str_replace('Pergunta:', '', $line));
        } elseif (strpos($line, 'Resposta:') !== false) {
            $response = trim(str_replace('Resposta:', '', $line));
            if (!empty($response) && !empty($current_question)) {
                $questions[] = $current_question;
                $responses[] = $response;
            }
        }
    }
    
    $html = '<h4>Resumo do Check-in</h4>';
    $html .= '<p><strong>Paciente:</strong> ' . htmlspecialchars($user_name) . '</p>';
    
    // Análise geral
    $html .= '<h4>Análise Geral</h4>';
    $total_responses = count($responses);
    $html .= '<p>O paciente respondeu a ' . $total_responses . ' perguntas do check-in semanal. ';
    
    // Analisar conteúdo das respostas
    $all_text = strtolower(implode(' ', $responses));
    $positive_indicators = ['bom', 'bem', 'ótimo', 'gostando', 'ajudando', 'focando', 'melhorando', 'ok', 'tudo certo'];
    $challenge_indicators = ['dificil', 'difícil', 'problema', 'preocupado', 'falta', 'não consegui', 'complicado'];
    
    $positive_score = 0;
    $challenge_score = 0;
    
    foreach ($positive_indicators as $indicator) {
        $positive_score += substr_count($all_text, $indicator);
    }
    
    foreach ($challenge_indicators as $indicator) {
        $challenge_score += substr_count($all_text, $indicator);
    }
    
    if ($positive_score > $challenge_score * 2) {
        $html .= 'O paciente demonstra engajamento positivo e progresso consistente no acompanhamento. ';
    } elseif ($challenge_score > 0) {
        $html .= 'Identificados alguns desafios que requerem atenção e suporte adicional. ';
    } else {
        $html .= 'O paciente está mantendo o acompanhamento regular. ';
    }
    $html .= '</p>';
    
    // Pontos positivos
    if ($positive_score > 0) {
        $html .= '<h4>Pontos Positivos</h4>';
        $html .= '<ul>';
        if (stripos($all_text, 'gostando') !== false || stripos($all_text, 'ajudando') !== false) {
            $html .= '<li>Demonstra satisfação com o processo e reconhece o valor do acompanhamento</li>';
        }
        if (stripos($all_text, 'focando') !== false || stripos($all_text, 'forçando') !== false) {
            $html .= '<li>Mostra determinação e esforço para manter o foco nos objetivos</li>';
        }
        if (stripos($all_text, 'bom') !== false || stripos($all_text, 'bem') !== false) {
            $html .= '<li>Relata estado geral positivo durante a semana</li>';
        }
        $html .= '</ul>';
    }
    
    // Pontos de atenção
    if ($challenge_score > 0) {
        $html .= '<h4>Pontos de Atenção</h4>';
        $html .= '<ul>';
        if (stripos($all_text, 'dificil') !== false || stripos($all_text, 'difícil') !== false) {
            $html .= '<li>Menciona dificuldades em manter a rotina, pode precisar de suporte adicional</li>';
        }
        if (stripos($all_text, 'falta') !== false || stripos($all_text, 'não consegui') !== false) {
            $html .= '<li>Algumas atividades ou compromissos não foram cumpridos conforme planejado</li>';
        }
        $html .= '</ul>';
    }
    
    // Observações importantes
    $html .= '<h4>Observações Importantes</h4>';
    $html .= '<ul>';
    
    // Detectar temas específicos
    if (stripos($all_text, 'dieta') !== false || stripos($all_text, 'comida') !== false || stripos($all_text, 'comi') !== false) {
        $html .= '<li>Menciona questões relacionadas à alimentação e dieta</li>';
    }
    if (stripos($all_text, 'exercicio') !== false || stripos($all_text, 'exercício') !== false || stripos($all_text, 'treino') !== false) {
        $html .= '<li>Relata atividades físicas e exercícios realizados</li>';
    }
    if (stripos($all_text, 'aniversário') !== false || stripos($all_text, 'festa') !== false || stripos($all_text, 'evento') !== false) {
        $html .= '<li>Menciona eventos sociais que podem ter impactado a rotina</li>';
    }
    
    $html .= '</ul>';
    
    return $html;
}
?>
