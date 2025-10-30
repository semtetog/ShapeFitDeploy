<?php
// public_html/process_log_meal.php (VERSÃO FINAL E CORRIGIDA)

// Definir fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo');

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

// 1. Validação de Segurança
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Erro de validação de segurança.'];
    header("Location: " . BASE_APP_URL . "/main_app.php");
    exit();
}

// 2. Coleta e Validação de Dados
$user_id = $_SESSION['user_id'];
$recipe_id = filter_input(INPUT_POST, 'recipe_id', FILTER_VALIDATE_INT);
$servings_consumed = filter_input(INPUT_POST, 'servings_consumed', FILTER_VALIDATE_FLOAT);
$meal_type = trim($_POST['meal_type'] ?? '');
$date_consumed = trim($_POST['date_consumed'] ?? '');
$meal_time = trim($_POST['meal_time'] ?? '');
$custom_meal_name = trim($_POST['custom_meal_name'] ?? '');
$kcal_per_serving = (float)($_POST['kcal_per_serving'] ?? 0);
$protein_per_serving = (float)($_POST['protein_per_serving'] ?? 0);
$carbs_per_serving = (float)($_POST['carbs_per_serving'] ?? 0);
$fat_per_serving = (float)($_POST['fat_per_serving'] ?? 0);
$is_food = isset($_POST['is_food']) ? (bool)$_POST['is_food'] : false;
$food_name = trim($_POST['food_name'] ?? '');

// Validar dados
if (!$servings_consumed || empty($meal_type) || empty($date_consumed) || empty($custom_meal_name) || $servings_consumed <= 0) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Dados inválidos. Nome da refeição é obrigatório.'];
    header("Location: " . BASE_APP_URL . "/diary.php?date=" . $date_consumed);
    exit();
}

// Se é alimento, deve ter nome
if ($is_food && empty($food_name)) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Nome do alimento é obrigatório.'];
    header("Location: " . BASE_APP_URL . "/diary.php?date=" . $date_consumed);
    exit();
}

// Se é receita, deve ter ID
if (!$is_food && !$recipe_id) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'ID da receita é obrigatório.'];
    header("Location: " . BASE_APP_URL . "/diary.php?date=" . $date_consumed);
    exit();
}

// 3. Cálculo dos Macros Totais
$total_kcal = $kcal_per_serving * $servings_consumed;
$total_protein = $protein_per_serving * $servings_consumed;
$total_carbs = $carbs_per_serving * $servings_consumed;
$total_fat = $fat_per_serving * $servings_consumed;

// 4. Transação no Banco de Dados
$conn->begin_transaction();

try {
    // Operação A: Inserir o registro individual na tabela de log
    // Combinar data e hora para o timestamp
if (!empty($meal_time)) {
    $datetime_consumed = $date_consumed . ' ' . $meal_time . ':00';
    $logged_time = date('Y-m-d H:i:s', strtotime($datetime_consumed));
} else {
    $logged_time = date('Y-m-d H:i:s');
}
    
    if ($is_food) {
        // É um alimento - usar custom_meal_name
        $sql_log = "INSERT INTO sf_user_meal_log (user_id, custom_meal_name, meal_type, date_consumed, servings_consumed, kcal_consumed, protein_consumed_g, carbs_consumed_g, fat_consumed_g, logged_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_log = $conn->prepare($sql_log);
        // i (user_id), s (custom_meal_name), s (meal_type), s (date_consumed), d (servings), d (kcal), d (protein), d (carbs), d (fat), s (logged_time)
        $stmt_log->bind_param("isssddddds", $user_id, $custom_meal_name, $meal_type, $date_consumed, $servings_consumed, $total_kcal, $total_protein, $total_carbs, $total_fat, $logged_time);
    } else {
        // É uma receita - usar recipe_id
        $sql_log = "INSERT INTO sf_user_meal_log (user_id, recipe_id, meal_type, date_consumed, servings_consumed, kcal_consumed, protein_consumed_g, carbs_consumed_g, fat_consumed_g, logged_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_log = $conn->prepare($sql_log);
        // i (user_id), i (recipe_id), s (meal_type), s (date_consumed), d (servings), d (kcal), d (protein), d (carbs), d (fat), s (logged_time)
        $stmt_log->bind_param("iissddddds", $user_id, $recipe_id, $meal_type, $date_consumed, $servings_consumed, $total_kcal, $total_protein, $total_carbs, $total_fat, $logged_time);
    }
    
    $stmt_log->execute();
    $stmt_log->close();

    // Operação B: Atualizar ou criar o resumo diário
    $sql_track = "
        INSERT INTO sf_user_daily_tracking (user_id, date, kcal_consumed, protein_consumed_g, carbs_consumed_g, fat_consumed_g)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        kcal_consumed = kcal_consumed + VALUES(kcal_consumed),
        protein_consumed_g = protein_consumed_g + VALUES(protein_consumed_g),
        carbs_consumed_g = carbs_consumed_g + VALUES(carbs_consumed_g),
        fat_consumed_g = fat_consumed_g + VALUES(fat_consumed_g)
    ";
    $stmt_track = $conn->prepare($sql_track);
    $stmt_track->bind_param("isdddd", $user_id, $date_consumed, $total_kcal, $total_protein, $total_carbs, $total_fat);
    $stmt_track->execute();
    $stmt_track->close();

    $conn->commit();
    $_SESSION['alert_message'] = ['type' => 'success', 'message' => 'Refeição registrada com sucesso!'];

} catch (Exception $e) {
    $conn->rollback();
    error_log("Erro ao registrar refeição para user_id {$user_id}: " . $e->getMessage());
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Ocorreu um erro de banco de dados ao registrar a refeição.'];
}

header("Location: " . BASE_APP_URL . "/diary.php?date=" . $date_consumed);
exit();
?>