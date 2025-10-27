<?php
// Arquivo: process_edit_meal.php - Processar edição de refeição

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_APP_URL . "/login.php");
    exit();
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Erro de validação de segurança.'];
    header("Location: " . BASE_APP_URL . "/diary.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$meal_id = filter_input(INPUT_POST, 'meal_id', FILTER_VALIDATE_INT);
$meal_name = trim($_POST['meal_name'] ?? '');
$meal_type = trim($_POST['meal_type'] ?? '');
$date_consumed = trim($_POST['date_consumed'] ?? '');
$time_consumed = trim($_POST['time_consumed'] ?? '');
$servings = filter_input(INPUT_POST, 'servings', FILTER_VALIDATE_FLOAT);

// Validações
if (!$meal_id || !$meal_name || !$meal_type || !$date_consumed || !$servings || $servings <= 0) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Dados inválidos.'];
    header("Location: " . BASE_APP_URL . "/diary.php");
    exit();
}

// Validar se a refeição pertence ao usuário
$stmt_check = $conn->prepare("SELECT id, servings_consumed, kcal_consumed, protein_consumed_g, carbs_consumed_g, fat_consumed_g FROM sf_user_meal_log WHERE id = ? AND user_id = ?");
$stmt_check->bind_param("ii", $meal_id, $user_id);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$current_meal = $result_check->fetch_assoc();
$stmt_check->close();

if (!$current_meal) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Refeição não encontrada.'];
    header("Location: " . BASE_APP_URL . "/diary.php");
    exit();
}

// Calcular novos valores nutricionais baseados na mudança de porções
$servings_ratio = $servings / $current_meal['servings_consumed'];
$new_kcal = $current_meal['kcal_consumed'] * $servings_ratio;
$new_protein = $current_meal['protein_consumed_g'] * $servings_ratio;
$new_carbs = $current_meal['carbs_consumed_g'] * $servings_ratio;
$new_fat = $current_meal['fat_consumed_g'] * $servings_ratio;

// Combinar data e hora para o timestamp
$datetime_consumed = $date_consumed . ' ' . $time_consumed . ':00';
$timestamp_consumed = date('Y-m-d H:i:s', strtotime($datetime_consumed));

// Atualizar a refeição
$stmt_update = $conn->prepare("
    UPDATE sf_user_meal_log 
    SET 
        custom_meal_name = ?,
        meal_type = ?,
        date_consumed = ?,
        servings_consumed = ?,
        kcal_consumed = ?,
        protein_consumed_g = ?,
        carbs_consumed_g = ?,
        fat_consumed_g = ?,
        logged_at = ?
    WHERE id = ? AND user_id = ?
");

$stmt_update->bind_param("sssddddssii", 
    $meal_name, 
    $meal_type, 
    $date_consumed, 
    $servings, 
    $new_kcal, 
    $new_protein, 
    $new_carbs, 
    $new_fat, 
    $timestamp_consumed, 
    $meal_id, 
    $user_id
);

if ($stmt_update->execute()) {
    // Atualizar resumo diário
    $stmt_daily = $conn->prepare("
        INSERT INTO sf_user_daily_tracking (user_id, date, kcal_consumed, protein_consumed_g, carbs_consumed_g, fat_consumed_g)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            kcal_consumed = VALUES(kcal_consumed),
            protein_consumed_g = VALUES(protein_consumed_g),
            carbs_consumed_g = VALUES(carbs_consumed_g),
            fat_consumed_g = VALUES(fat_consumed_g)
    ");
    
    // Recalcular totais do dia
    $stmt_totals = $conn->prepare("
        SELECT 
            SUM(kcal_consumed) as total_kcal,
            SUM(protein_consumed_g) as total_protein,
            SUM(carbs_consumed_g) as total_carbs,
            SUM(fat_consumed_g) as total_fat
        FROM sf_user_meal_log 
        WHERE user_id = ? AND date_consumed = ?
    ");
    $stmt_totals->bind_param("is", $user_id, $date_consumed);
    $stmt_totals->execute();
    $totals = $stmt_totals->get_result()->fetch_assoc();
    $stmt_totals->close();
    
    $stmt_daily->bind_param("isdddd", 
        $user_id, 
        $date_consumed, 
        $totals['total_kcal'], 
        $totals['total_protein'], 
        $totals['total_carbs'], 
        $totals['total_fat']
    );
    $stmt_daily->execute();
    $stmt_daily->close();
    
    $_SESSION['alert_message'] = ['type' => 'success', 'message' => 'Refeição atualizada com sucesso!'];
} else {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Erro ao atualizar a refeição.'];
}

$stmt_update->close();

// Redirecionar de volta para o diário
header("Location: " . BASE_APP_URL . "/diary.php?date=" . $date_consumed);
exit();
?>
