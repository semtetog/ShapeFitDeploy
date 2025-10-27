<?php
// Arquivo: process_save_custom_food.php
// Salva um novo alimento, seja ele vindo do scanner ou do cadastro manual.

header('Content-Type: application/json'); // Sempre retorna JSON para o JS
require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin(); // Exige que o usuário esteja logado para adicionar alimentos
require_once APP_ROOT_PATH . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    // Para formulários, redirecionamos com erro. Para API, retornamos JSON.
    // Vamos padronizar para redirecionar, já que o formulário manual é a fonte principal.
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Erro de segurança.'];
    header("Location: " . BASE_APP_URL . "/create_custom_food.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// --- Pega os dados do POST, adaptando os nomes dos campos do formulário manual ---
// O nome do campo do formulário manual é 'food_name', então usamos ele como prioridade
$name_pt = trim($_POST['food_name'] ?? $_POST['name_pt'] ?? '');
$brand = trim($_POST['brand_name'] ?? $_POST['brand'] ?? '');

$kcal = filter_input(INPUT_POST, 'kcal_100g', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
$protein = filter_input(INPUT_POST, 'protein_100g', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
$carbs = filter_input(INPUT_POST, 'carbs_100g', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
$fat = filter_input(INPUT_POST, 'fat_100g', FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);

// O código de barras é opcional para cadastro manual
$barcode = trim($_POST['barcode'] ?? ''); 

// --- Validação ---
if (empty($name_pt) || $kcal === null || $protein === null || $carbs === null || $fat === null) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Por favor, preencha todos os campos obrigatórios corretamente.'];
    header("Location: " . BASE_APP_URL . "/create_custom_food.php");
    exit;
}

// --- Lógica de Inserção no Banco de Dados ---

// Marcar o source_table como "user_created" para identificar alimentos criados por usuários
$source_table = "user_created";

// Se um código de barras foi fornecido, usamos ON DUPLICATE KEY UPDATE.
// Se não, é um simples INSERT.
if (!empty($barcode)) {
    // Verificar se já existe um alimento com este código de barras
    $check_sql = "SELECT id, added_by_user_id FROM sf_food_items WHERE barcode = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $barcode);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();
    
    if ($existing && $existing['added_by_user_id'] === null) {
        // Existe mas foi criado pelo sistema (TACO/Sonia), não atualizar
        $_SESSION['alert_message'] = ['type' => 'warning', 'message' => 'Este código de barras já está cadastrado no sistema.'];
        header("Location: " . BASE_APP_URL . "/add_food_to_diary.php");
        exit;
    }
    
    $sql = "INSERT INTO sf_food_items (barcode, name_pt, brand, energy_kcal_100g, protein_g_100g, carbohydrate_g_100g, fat_g_100g, added_by_user_id, source_table)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name_pt = VALUES(name_pt),
                brand = VALUES(brand),
                energy_kcal_100g = VALUES(energy_kcal_100g),
                protein_g_100g = VALUES(protein_g_100g),
                carbohydrate_g_100g = VALUES(carbohydrate_g_100g),
                fat_g_100g = VALUES(fat_g_100g),
                source_table = VALUES(source_table)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssddddis", $barcode, $name_pt, $brand, $kcal, $protein, $carbs, $fat, $user_id, $source_table);
} else {
    // Para alimentos manuais sem código de barras
    $sql = "INSERT INTO sf_food_items (name_pt, brand, energy_kcal_100g, protein_g_100g, carbohydrate_g_100g, fat_g_100g, added_by_user_id, source_table)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssddddis", $name_pt, $brand, $kcal, $protein, $carbs, $fat, $user_id, $source_table);
}

if ($stmt->execute()) {
    $_SESSION['alert_message'] = ['type' => 'success', 'message' => 'Alimento salvo com sucesso!'];
    // Redireciona para a página de adicionar refeição, onde o usuário pode agora buscar pelo novo alimento.
    header("Location: " . BASE_APP_URL . "/add_food_to_diary.php");
    exit;
} else {
    error_log("Erro ao salvar alimento customizado: " . $stmt->error);
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Erro ao salvar o alimento no banco de dados.'];
    header("Location: " . BASE_APP_URL . "/create_custom_food.php");
    exit;
}
$stmt->close();
$conn->close();
?>