<?php
// Arquivo: process_delete_meal.php - Processar exclusão de refeição

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

// Validações
if (!$meal_id) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'ID da refeição inválido.'];
    header("Location: " . BASE_APP_URL . "/diary.php");
    exit();
}

// Buscar dados da refeição antes de excluir
$stmt_find = $conn->prepare("SELECT date_consumed FROM sf_user_meal_log WHERE id = ? AND user_id = ?");
$stmt_find->bind_param("ii", $meal_id, $user_id);
$stmt_find->execute();
$result_find = $stmt_find->get_result();
$meal_data = $result_find->fetch_assoc();
$stmt_find->close();

if (!$meal_data) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Refeição não encontrada.'];
    header("Location: " . BASE_APP_URL . "/diary.php");
    exit();
}

$date_consumed = $meal_data['date_consumed'];

// Excluir a refeição
$stmt_delete = $conn->prepare("DELETE FROM sf_user_meal_log WHERE id = ? AND user_id = ?");
$stmt_delete->bind_param("ii", $meal_id, $user_id);

if ($stmt_delete->execute()) {
    // Recalcular resumo diário
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
    
    // Atualizar ou excluir resumo diário
    if ($totals['total_kcal'] > 0) {
        // Ainda há refeições no dia, atualizar resumo
        $stmt_daily = $conn->prepare("
            INSERT INTO sf_user_daily_tracking (user_id, date, kcal_consumed, protein_consumed_g, carbs_consumed_g, fat_consumed_g)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                kcal_consumed = VALUES(kcal_consumed),
                protein_consumed_g = VALUES(protein_consumed_g),
                carbs_consumed_g = VALUES(carbs_consumed_g),
                fat_consumed_g = VALUES(fat_consumed_g)
        ");
        $stmt_daily->bind_param("isddd", 
            $user_id, 
            $date_consumed, 
            $totals['total_kcal'], 
            $totals['total_protein'], 
            $totals['total_carbs'], 
            $totals['total_fat']
        );
        $stmt_daily->execute();
        $stmt_daily->close();
    } else {
        // Não há mais refeições no dia, excluir resumo
        $stmt_delete_daily = $conn->prepare("DELETE FROM sf_user_daily_tracking WHERE user_id = ? AND date = ?");
        $stmt_delete_daily->bind_param("is", $user_id, $date_consumed);
        $stmt_delete_daily->execute();
        $stmt_delete_daily->close();
    }
    
    $_SESSION['alert_message'] = ['type' => 'success', 'message' => 'Refeição excluída com sucesso!'];
} else {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Erro ao excluir a refeição.'];
}

$stmt_delete->close();

// Redirecionar de volta para o diário
header("Location: " . BASE_APP_URL . "/diary.php?date=" . $date_consumed);
exit();
?>




