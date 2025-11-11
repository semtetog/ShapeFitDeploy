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
        case 'get_goals':
            getGroupGoals($data, $admin_id);
            break;
        case 'save_goals':
            saveGroupGoals($data, $admin_id);
            break;
        case 'apply_goals_to_members':
            applyGoalsToMembers($data, $admin_id);
            break;
        case 'revert_goals_from_members':
            revertGoalsFromMembers($data, $admin_id);
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

function getGroupGoals($data, $admin_id) {
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
    
    // Verificar se a tabela existe
    $table_exists = false;
    $check_table = $conn->query("SHOW TABLES LIKE 'sf_user_group_goals'");
    if ($check_table && $check_table->num_rows > 0) {
        $table_exists = true;
    }
    
    if (!$table_exists) {
        echo json_encode(['success' => true, 'goals' => null]);
        return;
    }
    
    // Buscar metas
    $stmt = $conn->prepare("SELECT * FROM sf_user_group_goals WHERE group_id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $group_id, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $goals = $result->fetch_assoc();
        echo json_encode(['success' => true, 'goals' => $goals]);
    } else {
        echo json_encode(['success' => true, 'goals' => null]);
    }
    $stmt->close();
}

function saveGroupGoals($data, $admin_id) {
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
    
    // Verificar se a tabela existe, se não, retornar erro
    $check_table = $conn->query("SHOW TABLES LIKE 'sf_user_group_goals'");
    if (!$check_table || $check_table->num_rows === 0) {
        throw new Exception('Tabela de metas não existe. Execute o script SQL para criar a tabela.');
    }
    
    // Preparar dados
    $target_kcal = !empty($data['target_kcal']) ? (int)$data['target_kcal'] : null;
    $target_water_ml = !empty($data['target_water_ml']) ? (int)$data['target_water_ml'] : null;
    $target_protein_g = !empty($data['target_protein_g']) ? (float)$data['target_protein_g'] : null;
    $target_carbs_g = !empty($data['target_carbs_g']) ? (float)$data['target_carbs_g'] : null;
    $target_fat_g = !empty($data['target_fat_g']) ? (float)$data['target_fat_g'] : null;
    $target_exercise_minutes = !empty($data['target_exercise_minutes']) ? (int)$data['target_exercise_minutes'] : null;
    $target_sleep_hours = !empty($data['target_sleep_hours']) ? (float)$data['target_sleep_hours'] : null;
    
    // Verificar se já existem metas
    $stmt_check_goals = $conn->prepare("SELECT id FROM sf_user_group_goals WHERE group_id = ?");
    $stmt_check_goals->bind_param("i", $group_id);
    $stmt_check_goals->execute();
    $result_check_goals = $stmt_check_goals->get_result();
    $stmt_check_goals->close();
    
    if ($result_check_goals->num_rows > 0) {
        // Atualizar
        $stmt = $conn->prepare("
            UPDATE sf_user_group_goals 
            SET target_kcal = ?, target_water_ml = ?, target_protein_g = ?, target_carbs_g = ?, 
                target_fat_g = ?, target_exercise_minutes = ?, 
                target_sleep_hours = ?, updated_at = NOW()
            WHERE group_id = ? AND admin_id = ?
        ");
        $stmt->bind_param("iidddiidi", $target_kcal, $target_water_ml, $target_protein_g, $target_carbs_g, 
                         $target_fat_g, $target_exercise_minutes, $target_sleep_hours, 
                         $group_id, $admin_id);
    } else {
        // Inserir
        $stmt = $conn->prepare("
            INSERT INTO sf_user_group_goals 
            (group_id, admin_id, target_kcal, target_water_ml, target_protein_g, target_carbs_g, 
             target_fat_g, target_exercise_minutes, target_sleep_hours, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param("iiiidddii", $group_id, $admin_id, $target_kcal, $target_water_ml, $target_protein_g, 
                         $target_carbs_g, $target_fat_g, $target_exercise_minutes, $target_sleep_hours);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao salvar metas: ' . $stmt->error);
    }
    
    $stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Metas salvas com sucesso!']);
}

function applyGoalsToMembers($data, $admin_id) {
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
    
    // Verificar se a tabela de metas existe
    $check_table = $conn->query("SHOW TABLES LIKE 'sf_user_group_goals'");
    if (!$check_table || $check_table->num_rows === 0) {
        throw new Exception('Tabela de metas não existe. Execute o script SQL para criar a tabela.');
    }
    
    // Buscar metas do grupo
    $stmt_goals = $conn->prepare("SELECT * FROM sf_user_group_goals WHERE group_id = ? AND admin_id = ?");
    $stmt_goals->bind_param("ii", $group_id, $admin_id);
    $stmt_goals->execute();
    $result_goals = $stmt_goals->get_result();
    
    if ($result_goals->num_rows === 0) {
        $stmt_goals->close();
        throw new Exception('Nenhuma meta definida para este grupo. Defina as metas primeiro.');
    }
    
    $goals = $result_goals->fetch_assoc();
    $stmt_goals->close();
    
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
    
    if (empty($members)) {
        throw new Exception('Nenhum membro encontrado neste grupo.');
    }
    
    // Verificar se a tabela sf_user_goals existe
    $check_user_goals = $conn->query("SHOW TABLES LIKE 'sf_user_goals'");
    if (!$check_user_goals || $check_user_goals->num_rows === 0) {
        throw new Exception('Tabela de metas de usuários não existe. Execute o script SQL para criar a tabela.');
    }
    
    // Aplicar metas a cada membro
    $applied_count = 0;
    $conn->begin_transaction();
    
    try {
        foreach ($members as $user_id) {
            // Verificar se já existe meta de nutrição para o usuário
            $stmt_check_user = $conn->prepare("SELECT id FROM sf_user_goals WHERE user_id = ? AND goal_type = 'nutrition'");
            $stmt_check_user->bind_param("i", $user_id);
            $stmt_check_user->execute();
            $result_check_user = $stmt_check_user->get_result();
            $stmt_check_user->close();
            
            if ($result_check_user->num_rows > 0) {
                // Atualizar
                $stmt_update = $conn->prepare("
                    UPDATE sf_user_goals 
                    SET target_kcal = ?, target_protein_g = ?, target_carbs_g = ?, target_fat_g = ?, 
                        target_water_cups = ?, updated_at = NOW()
                    WHERE user_id = ? AND goal_type = 'nutrition'
                ");
                $water_cups = $goals['target_water_ml'] ? (int)($goals['target_water_ml'] / 250) : null;
                $stmt_update->bind_param("idddii", $goals['target_kcal'], $goals['target_protein_g'], 
                                        $goals['target_carbs_g'], $goals['target_fat_g'], $water_cups, $user_id);
            } else {
                // Inserir
                $stmt_insert = $conn->prepare("
                    INSERT INTO sf_user_goals 
                    (user_id, goal_type, target_kcal, target_protein_g, target_carbs_g, target_fat_g, 
                     target_water_cups, created_at, updated_at)
                    VALUES (?, 'nutrition', ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $water_cups = $goals['target_water_ml'] ? (int)($goals['target_water_ml'] / 250) : null;
                $stmt_insert->bind_param("iidddi", $user_id, $goals['target_kcal'], $goals['target_protein_g'], 
                                        $goals['target_carbs_g'], $goals['target_fat_g'], $water_cups);
                
                if ($stmt_insert->execute()) {
                    $applied_count++;
                }
                $stmt_insert->close();
                continue;
            }
            
            if ($stmt_update->execute()) {
                $applied_count++;
            }
            $stmt_update->close();
            
            // Atualizar/inserir metas de atividade
            $stmt_check_activity = $conn->prepare("SELECT id FROM sf_user_goals WHERE user_id = ? AND goal_type = 'activity'");
            $stmt_check_activity->bind_param("i", $user_id);
            $stmt_check_activity->execute();
            $result_check_activity = $stmt_check_activity->get_result();
            $stmt_check_activity->close();
            
            if ($result_check_activity->num_rows > 0) {
                $stmt_update_activity = $conn->prepare("
                    UPDATE sf_user_goals 
                    SET target_steps_daily = ?, updated_at = NOW()
                    WHERE user_id = ? AND goal_type = 'activity'
                ");
                $stmt_update_activity->bind_param("ii", $goals['target_steps_daily'], $user_id);
                $stmt_update_activity->execute();
                $stmt_update_activity->close();
            } else {
                $stmt_insert_activity = $conn->prepare("
                    INSERT INTO sf_user_goals 
                    (user_id, goal_type, target_steps_daily, created_at, updated_at)
                    VALUES (?, 'activity', ?, NOW(), NOW())
                ");
                $stmt_insert_activity->bind_param("ii", $user_id, $goals['target_steps_daily']);
                $stmt_insert_activity->execute();
                $stmt_insert_activity->close();
            }
            
            // Atualizar/inserir metas de sono
            $stmt_check_sleep = $conn->prepare("SELECT id FROM sf_user_goals WHERE user_id = ? AND goal_type = 'sleep'");
            $stmt_check_sleep->bind_param("i", $user_id);
            $stmt_check_sleep->execute();
            $result_check_sleep = $stmt_check_sleep->get_result();
            $stmt_check_sleep->close();
            
            if ($result_check_sleep->num_rows > 0) {
                $stmt_update_sleep = $conn->prepare("
                    UPDATE sf_user_goals 
                    SET target_sleep_hours = ?, updated_at = NOW()
                    WHERE user_id = ? AND goal_type = 'sleep'
                ");
                $stmt_update_sleep->bind_param("di", $goals['target_sleep_hours'], $user_id);
                $stmt_update_sleep->execute();
                $stmt_update_sleep->close();
            } else {
                $stmt_insert_sleep = $conn->prepare("
                    INSERT INTO sf_user_goals 
                    (user_id, goal_type, target_sleep_hours, created_at, updated_at)
                    VALUES (?, 'sleep', ?, NOW(), NOW())
                ");
                $stmt_insert_sleep->bind_param("id", $user_id, $goals['target_sleep_hours']);
                $stmt_insert_sleep->execute();
                $stmt_insert_sleep->close();
            }
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "Metas aplicadas com sucesso a $applied_count membro(s)!"
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function revertGoalsFromMembers($data, $admin_id) {
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
    
    // Buscar membros do grupo
    $stmt_members = $conn->prepare("SELECT user_id FROM sf_user_group_members WHERE group_id = ?");
    $stmt_members->bind_param("i", $group_id);
    $stmt_members->execute();
    $result_members = $stmt_members->get_result();
    
    $user_ids = [];
    while ($row = $result_members->fetch_assoc()) {
        $user_ids[] = (int)$row['user_id'];
    }
    $stmt_members->close();
    
    if (empty($user_ids)) {
        echo json_encode(['success' => true, 'message' => 'Nenhum membro encontrado no grupo.']);
        return;
    }
    
    // Verificar se a tabela de metas de usuário existe
    $check_table = $conn->query("SHOW TABLES LIKE 'sf_user_goals'");
    if (!$check_table || $check_table->num_rows === 0) {
        throw new Exception('Tabela de metas de usuário não existe.');
    }
    
    // Deletar metas aplicadas do grupo para cada membro
    // Remover todas as metas dos usuários do grupo
    $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
    $stmt_delete = $conn->prepare("DELETE FROM sf_user_goals WHERE user_id IN ($placeholders)");
    $stmt_delete->bind_param(str_repeat('i', count($user_ids)), ...$user_ids);
    
    if (!$stmt_delete->execute()) {
        $stmt_delete->close();
        throw new Exception('Erro ao reverter metas: ' . $stmt_delete->error);
    }
    
    $affected_rows = $stmt_delete->affected_rows;
    $stmt_delete->close();
    
    echo json_encode(['success' => true, 'message' => "Metas revertidas para {$affected_rows} membro(s) com sucesso!"]);
}

$conn->close();
?>

