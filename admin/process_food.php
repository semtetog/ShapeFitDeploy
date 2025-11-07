<?php
// admin/process_food.php - Processador de ações para alimentos

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

$response = ['success' => false, 'message' => ''];

try {
    switch ($action) {
        case 'add':
            processAddFood();
            $response['success'] = true;
            $response['message'] = 'Alimento adicionado com sucesso!';
            break;
            
        case 'edit':
            processEditFood();
            $response['success'] = true;
            $response['message'] = 'Alimento atualizado com sucesso!';
            break;
            
        case 'delete':
            processDeleteFood();
            $response['success'] = true;
            $response['message'] = 'Alimento excluído com sucesso!';
            break;
            
        case 'approve':
            processApproveFood();
            $response['success'] = true;
            $response['message'] = 'Alimento aprovado e disponibilizado globalmente!';
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    if (!$isAjax) {
        $_SESSION['admin_alert'] = [
            'type' => 'danger',
            'message' => 'Erro: ' . $e->getMessage()
        ];
    }
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

header('Location: foods_management_new.php');
exit;

function processAddFood() {
    global $conn;
    
    $name_pt = trim($_POST['name_pt'] ?? '');
    $energy_kcal_100g = (float)($_POST['energy_kcal_100g'] ?? 0);
    $protein_g_100g = (float)($_POST['protein_g_100g'] ?? 0);
    $carbohydrate_g_100g = (float)($_POST['carbohydrate_g_100g'] ?? 0);
    $fat_g_100g = (float)($_POST['fat_g_100g'] ?? 0);
    $source_table = trim($_POST['source_table'] ?? 'Manual');
    
    // Validações
    if (empty($name_pt)) {
        throw new Exception('Nome do alimento é obrigatório');
    }
    
    if ($energy_kcal_100g < 0 || $protein_g_100g < 0 || $carbohydrate_g_100g < 0 || $fat_g_100g < 0) {
        throw new Exception('Valores nutricionais não podem ser negativos');
    }
    
    // Verificar se já existe
    $stmt_check = $conn->prepare("SELECT id FROM sf_food_items WHERE name_pt = ?");
    $stmt_check->bind_param("s", $name_pt);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        throw new Exception('Alimento com este nome já existe');
    }
    $stmt_check->close();
    
    // Inserir
    $stmt = $conn->prepare("
        INSERT INTO sf_food_items (
            name_pt, 
            energy_kcal_100g, 
            protein_g_100g, 
            carbohydrate_g_100g, 
            fat_g_100g,
            source_table
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("sdddds", $name_pt, $energy_kcal_100g, $protein_g_100g, $carbohydrate_g_100g, $fat_g_100g, $source_table);
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao adicionar alimento: ' . $stmt->error);
    }
    
    $stmt->close();
}

function processEditFood() {
    global $conn;
    
    $id = (int)($_POST['id'] ?? 0);
    $name_pt = trim($_POST['name_pt'] ?? '');
    $energy_kcal_100g = (float)($_POST['energy_kcal_100g'] ?? 0);
    $protein_g_100g = (float)($_POST['protein_g_100g'] ?? 0);
    $carbohydrate_g_100g = (float)($_POST['carbohydrate_g_100g'] ?? 0);
    $fat_g_100g = (float)($_POST['fat_g_100g'] ?? 0);
    $source_table = trim($_POST['source_table'] ?? '');
    
    // Validações
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    if (empty($name_pt)) {
        throw new Exception('Nome do alimento é obrigatório');
    }
    
    if ($energy_kcal_100g < 0 || $protein_g_100g < 0 || $carbohydrate_g_100g < 0 || $fat_g_100g < 0) {
        throw new Exception('Valores nutricionais não podem ser negativos');
    }
    
    // Verificar se existe
    $stmt_check = $conn->prepare("SELECT id FROM sf_food_items WHERE id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows === 0) {
        throw new Exception('Alimento não encontrado');
    }
    $stmt_check->close();
    
    // Verificar se nome já existe em outro registro
    $stmt_check_name = $conn->prepare("SELECT id FROM sf_food_items WHERE name_pt = ? AND id != ?");
    $stmt_check_name->bind_param("si", $name_pt, $id);
    $stmt_check_name->execute();
    $result_check = $stmt_check_name->get_result();
    if ($result_check->num_rows > 0) {
        $stmt_check_name->close();
        throw new Exception('Alimento com este nome já existe');
    }
    $stmt_check_name->close();
    
    // Atualizar
    $stmt = $conn->prepare("
        UPDATE sf_food_items 
        SET name_pt = ?, 
            energy_kcal_100g = ?, 
            protein_g_100g = ?, 
            carbohydrate_g_100g = ?, 
            fat_g_100g = ?,
            source_table = ?
        WHERE id = ?
    ");
    
    $stmt->bind_param("sddddsi", $name_pt, $energy_kcal_100g, $protein_g_100g, $carbohydrate_g_100g, $fat_g_100g, $source_table, $id);
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao atualizar alimento: ' . $stmt->error);
    }
    
    $stmt->close();
}

function processDeleteFood() {
    global $conn;
    
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    // Verificar se existe
    $stmt_check = $conn->prepare("SELECT name_pt FROM sf_food_items WHERE id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Alimento não encontrado');
    }
    $food_name = $result->fetch_assoc()['name_pt'];
    $stmt_check->close();
    
    // Verificar se está sendo usado em refeições
    $stmt_usage = $conn->prepare("SELECT COUNT(*) as count FROM sf_user_meal_log WHERE recipe_id IS NULL AND custom_meal_name = ?");
    $stmt_usage->bind_param("s", $food_name);
    $stmt_usage->execute();
    $usage_count = $stmt_usage->get_result()->fetch_assoc()['count'];
    $stmt_usage->close();
    
    if ($usage_count > 0) {
        throw new Exception("Não é possível excluir este alimento pois está sendo usado em {$usage_count} refeição(ões)");
    }
    
    // Excluir
    $stmt = $conn->prepare("DELETE FROM sf_food_items WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao excluir alimento: ' . $stmt->error);
    }
    
    $stmt->close();
}

function processApproveFood() {
    global $conn;
    
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        throw new Exception('ID inválido');
    }
    
    // Verificar se existe e se foi criado por usuário
    $stmt_check = $conn->prepare("SELECT id, added_by_user_id FROM sf_food_items WHERE id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    if ($result->num_rows === 0) {
        $stmt_check->close();
        throw new Exception('Alimento não encontrado');
    }
    $food = $result->fetch_assoc();
    $stmt_check->close();
    
    if ($food['added_by_user_id'] === null) {
        throw new Exception('Este alimento já é global');
    }
    
    // Aprovar: remover added_by_user_id (tornar global)
    $stmt = $conn->prepare("UPDATE sf_food_items SET added_by_user_id = NULL WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao aprovar alimento: ' . $stmt->error);
    }
    
    $stmt->close();
}
?>
