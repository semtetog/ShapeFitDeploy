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
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
            exit;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
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

