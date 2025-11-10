<?php
define('IS_AJAX_REQUEST', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth_admin.php';

requireAdminLogin();
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$action = $data['action'] ?? '';
$admin_id = $_SESSION['admin_id'] ?? null;

if (!$admin_id) {
    echo json_encode(['success' => false, 'message' => 'Admin não autenticado']);
    exit;
}

// Verificar estrutura da tabela
$test_query = "SHOW COLUMNS FROM sf_user_groups LIKE 'name'";
$test_result = $conn->query($test_query);
$has_name_column = $test_result->num_rows > 0;
$test_result->free();

$test_query2 = "SHOW COLUMNS FROM sf_user_groups LIKE 'status'";
$test_result2 = $conn->query($test_query2);
$has_status_column = $test_result2->num_rows > 0;
$test_result2->free();

$name_column = $has_name_column ? 'name' : 'group_name';
$status_column = $has_status_column ? 'status' : 'is_active';

try {
    switch ($action) {
        case 'save':
            saveUserGroup($data, $admin_id, $name_column, $status_column, $has_status_column);
            break;
        case 'delete':
            deleteUserGroup($data, $admin_id);
            break;
        case 'get':
            getUserGroup($data, $admin_id, $name_column, $status_column, $has_status_column);
            break;
        case 'toggle_status':
            toggleUserGroupStatus($data, $admin_id, $status_column, $has_status_column);
            break;
        case 'get_stats':
            getUserGroupStats($admin_id, $status_column, $has_status_column);
            break;
        case 'get_members':
            getGroupMembers($data, $admin_id);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
            exit;
    }
} catch (mysqli_sql_exception $e) {
    error_log("Erro SQL em ajax_user_groups.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao processar solicitação: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    error_log("Erro em ajax_user_groups.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

function saveUserGroup($data, $admin_id, $name_column, $status_column, $has_status_column) {
    global $conn;
    
    $group_id = (int)($data['group_id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $status = $data['status'] ?? 'active';
    $members = $data['members'] ?? [];
    
    if (empty($name)) {
        throw new Exception('Nome do grupo é obrigatório');
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }
    
    // Converter status para formato correto se necessário
    $status_value = $status;
    if (!$has_status_column) {
        $status_value = $status === 'active' ? 1 : 0;
    }
    
    $conn->begin_transaction();
    
    try {
        if ($group_id > 0) {
            // Atualizar grupo existente
            if ($has_status_column) {
                $stmt = $conn->prepare("UPDATE sf_user_groups SET $name_column = ?, description = ?, $status_column = ?, updated_at = NOW() WHERE id = ? AND admin_id = ?");
                if (!$stmt) {
                    throw new Exception('Erro ao preparar query: ' . $conn->error);
                }
                $stmt->bind_param("sssii", $name, $description, $status, $group_id, $admin_id);
            } else {
                $stmt = $conn->prepare("UPDATE sf_user_groups SET $name_column = ?, description = ?, $status_column = ?, updated_at = NOW() WHERE id = ? AND admin_id = ?");
                if (!$stmt) {
                    throw new Exception('Erro ao preparar query: ' . $conn->error);
                }
                $stmt->bind_param("ssiii", $name, $description, $status_value, $group_id, $admin_id);
            }
            if (!$stmt->execute()) {
                throw new Exception('Erro ao atualizar grupo: ' . $stmt->error);
            }
            $stmt->close();
            $was_edit = true;
        } else {
            // Criar novo grupo
            if ($has_status_column) {
                $stmt = $conn->prepare("INSERT INTO sf_user_groups (admin_id, $name_column, description, $status_column, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                if (!$stmt) {
                    throw new Exception('Erro ao preparar query: ' . $conn->error);
                }
                $stmt->bind_param("isss", $admin_id, $name, $description, $status);
            } else {
                $stmt = $conn->prepare("INSERT INTO sf_user_groups (admin_id, $name_column, description, $status_column, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                if (!$stmt) {
                    throw new Exception('Erro ao preparar query: ' . $conn->error);
                }
                $stmt->bind_param("issi", $admin_id, $name, $description, $status_value);
            }
            if (!$stmt->execute()) {
                throw new Exception('Erro ao criar grupo: ' . $stmt->error);
            }
            $group_id = $conn->insert_id;
            $stmt->close();
            $was_edit = false;
        }
        
        // Remover todos os membros existentes
        $stmt_delete = $conn->prepare("DELETE FROM sf_user_group_members WHERE group_id = ?");
        $stmt_delete->bind_param("i", $group_id);
        $stmt_delete->execute();
        $stmt_delete->close();
        
        // Adicionar novos membros
        if (!empty($members) && is_array($members)) {
            $stmt_insert = $conn->prepare("INSERT INTO sf_user_group_members (group_id, user_id, joined_at) VALUES (?, ?, NOW())");
            foreach ($members as $user_id) {
                $user_id = (int)$user_id;
                if ($user_id > 0) {
                    $stmt_insert->bind_param("ii", $group_id, $user_id);
                    $stmt_insert->execute();
                }
            }
            $stmt_insert->close();
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $was_edit ? 'Grupo atualizado com sucesso!' : 'Grupo criado com sucesso!',
            'group_id' => $group_id
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function deleteUserGroup($data, $admin_id) {
    global $conn;
    
    $group_id = (int)($data['group_id'] ?? 0);
    
    if ($group_id <= 0) {
        throw new Exception('ID do grupo inválido');
    }
    
    // Verificar se o grupo pertence ao admin
    $stmt_check = $conn->prepare("SELECT id FROM sf_user_groups WHERE id = ? AND admin_id = ?");
    $stmt_check->bind_param("ii", $group_id, $admin_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Grupo não encontrado ou sem permissão');
    }
    $stmt_check->close();
    
    // Deletar grupo (cascade vai deletar os membros)
    $stmt = $conn->prepare("DELETE FROM sf_user_groups WHERE id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $group_id, $admin_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao excluir grupo: ' . $stmt->error);
    }
    
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Grupo excluído com sucesso!']);
}

function getUserGroup($data, $admin_id, $name_column, $status_column, $has_status_column) {
    global $conn;
    
    $group_id = (int)($data['group_id'] ?? 0);
    
    if ($group_id <= 0) {
        throw new Exception('ID do grupo inválido');
    }
    
    // Buscar grupo
    $stmt = $conn->prepare("SELECT id, $name_column as name, description, $status_column as status FROM sf_user_groups WHERE id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $group_id, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Grupo não encontrado ou sem permissão');
    }
    
    $group = $result->fetch_assoc();
    $stmt->close();
    
    // Normalizar status
    if (!$has_status_column) {
        $group['status'] = $group['status'] == 1 ? 'active' : 'inactive';
    }
    
    // Buscar membros do grupo
    $stmt_members = $conn->prepare("SELECT user_id FROM sf_user_group_members WHERE group_id = ?");
    $stmt_members->bind_param("i", $group_id);
    $stmt_members->execute();
    $result_members = $stmt_members->get_result();
    
    $members = [];
    while ($row = $result_members->fetch_assoc()) {
        $members[] = (int)$row['user_id'];
    }
    $stmt_members->close();
    
    $group['members'] = $members;
    
    echo json_encode(['success' => true, 'group' => $group]);
}

function toggleUserGroupStatus($data, $admin_id, $status_column, $has_status_column) {
    global $conn;
    
    $group_id = (int)($data['group_id'] ?? 0);
    $new_status = $data['status'] ?? 'inactive';
    
    if ($group_id <= 0) {
        throw new Exception('ID do grupo inválido');
    }
    
    if (!in_array($new_status, ['active', 'inactive'])) {
        throw new Exception('Status inválido');
    }
    
    // Verificar se o grupo pertence ao admin
    $stmt_check = $conn->prepare("SELECT id FROM sf_user_groups WHERE id = ? AND admin_id = ?");
    $stmt_check->bind_param("ii", $group_id, $admin_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Grupo não encontrado ou sem permissão');
    }
    $stmt_check->close();
    
    // Converter status para formato correto se necessário
    $status_value = $new_status;
    if (!$has_status_column) {
        $status_value = $new_status === 'active' ? 1 : 0;
    }
    
    // Atualizar status
    if ($has_status_column) {
        $stmt = $conn->prepare("UPDATE sf_user_groups SET $status_column = ?, updated_at = NOW() WHERE id = ? AND admin_id = ?");
        $stmt->bind_param("sii", $new_status, $group_id, $admin_id);
    } else {
        $stmt = $conn->prepare("UPDATE sf_user_groups SET $status_column = ?, updated_at = NOW() WHERE id = ? AND admin_id = ?");
        $stmt->bind_param("iii", $status_value, $group_id, $admin_id);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao atualizar status: ' . $stmt->error);
    }
    
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso!']);
}

function getUserGroupStats($admin_id, $status_column, $has_status_column) {
    global $conn;
    
    if ($has_status_column) {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN $status_column = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN $status_column = 'inactive' THEN 1 ELSE 0 END) as inactive
            FROM sf_user_groups
            WHERE admin_id = ?
        ");
        $stmt->bind_param("i", $admin_id);
    } else {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN $status_column = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN $status_column = 0 THEN 1 ELSE 0 END) as inactive
            FROM sf_user_groups
            WHERE admin_id = ?
        ");
        $stmt->bind_param("i", $admin_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => (int)($stats['total'] ?? 0),
            'active' => (int)($stats['active'] ?? 0),
            'inactive' => (int)($stats['inactive'] ?? 0)
        ]
    ]);
}

function getGroupMembers($data, $admin_id) {
    global $conn;
    
    $group_id = (int)($data['group_id'] ?? 0);
    
    if ($group_id <= 0) {
        throw new Exception('ID do grupo inválido');
    }
    
    // Verificar se o grupo pertence ao admin
    $stmt_check = $conn->prepare("SELECT id FROM sf_user_groups WHERE id = ? AND admin_id = ?");
    $stmt_check->bind_param("ii", $group_id, $admin_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Grupo não encontrado ou sem permissão');
    }
    $stmt_check->close();
    
    // Buscar membros
    $stmt = $conn->prepare("
        SELECT u.id, u.name, u.email, up.profile_image_filename as profile_image
        FROM sf_users u
        INNER JOIN sf_user_group_members ugm ON u.id = ugm.user_id
        LEFT JOIN sf_user_profiles up ON u.id = up.user_id
        WHERE ugm.group_id = ?
        ORDER BY u.name
    ");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    $stmt->close();
    
    echo json_encode(['success' => true, 'members' => $members]);
}

$conn->close();
?>

