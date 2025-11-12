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
    
    if ($checkin_id > 0) {
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
    $result = $conn->query("SELECT COUNT(*) as count FROM sf_checkin_configs WHERE admin_id = $admin_id");
    $stats['total'] = $result->fetch_assoc()['count'];
    
    // Por status
    $result = $conn->query("SELECT is_active, COUNT(*) as count FROM sf_checkin_configs WHERE admin_id = $admin_id GROUP BY is_active");
    $stats['active'] = 0;
    $stats['inactive'] = 0;
    while ($row = $result->fetch_assoc()) {
        if ($row['is_active']) {
            $stats['active'] = $row['count'];
        } else {
            $stats['inactive'] = $row['count'];
        }
    }
    
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
    
    echo json_encode([
        'success' => true,
        'message' => 'Status atualizado com sucesso'
    ]);
}
?>
