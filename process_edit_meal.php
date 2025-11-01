<?php
// Arquivo: process_edit_meal.php - Processar a atualização de uma refeição

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();

// 1. Validação de Segurança e CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Erro de validação de segurança.'];
    header("Location: " . BASE_APP_URL . "/diary.php");
    exit();
}

// 2. Coleta e Validação de Dados
$user_id = $_SESSION['user_id'];
$meal_id = filter_input(INPUT_POST, 'meal_id', FILTER_VALIDATE_INT);
$servings_consumed = filter_input(INPUT_POST, 'servings', FILTER_VALIDATE_FLOAT);
$meal_type = trim($_POST['meal_type'] ?? '');
$date_consumed = trim($_POST['date_consumed'] ?? date('Y-m-d'));
$meal_time = trim($_POST['meal_time'] ?? date('H:i'));
$custom_meal_name = trim($_POST['meal_name'] ?? '');

// Validações básicas
if (!$meal_id || !$servings_consumed || empty($meal_type) || empty($custom_meal_name) || $servings_consumed <= 0) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Dados inválidos. Todos os campos são obrigatórios.'];
    header("Location: " . BASE_APP_URL . "/edit_meal.php?id=" . $meal_id);
    exit();
}

$conn->begin_transaction();

try {
    // 3. Buscar dados originais da refeição (para recalcular totais diários)
    $stmt_old = $conn->prepare("SELECT * FROM sf_user_meal_log WHERE id = ? AND user_id = ?");
    $stmt_old->bind_param("ii", $meal_id, $user_id);
    $stmt_old->execute();
    $old_meal = $stmt_old->get_result()->fetch_assoc();
    $stmt_old->close();

    if (!$old_meal) {
        throw new Exception("Refeição original não encontrada.");
    }

    // 4. Buscar dados nutricionais base (por porção) da receita ou do próprio log
    $kcal_per_serving = 0;
    $protein_per_serving = 0;
    $carbs_per_serving = 0;
    $fat_per_serving = 0;

    // Se houver servings válidos, recalcular valores por porção
    $old_servings = (float)$old_meal['servings_consumed'];
    if ($old_servings > 0) {
        $kcal_per_serving = $old_meal['kcal_consumed'] / $old_servings;
        $protein_per_serving = $old_meal['protein_consumed_g'] / $old_servings;
        $carbs_per_serving = $old_meal['carbs_consumed_g'] / $old_servings;
        $fat_per_serving = $old_meal['fat_consumed_g'] / $old_servings;
    }

    // 5. Calcular os novos totais da refeição
    $new_kcal = $kcal_per_serving * $servings_consumed;
    $new_protein = $protein_per_serving * $servings_consumed;
    $new_carbs = $carbs_per_serving * $servings_consumed;
    $new_fat = $fat_per_serving * $servings_consumed;

    $logged_at = date('Y-m-d H:i:s', strtotime("$date_consumed $meal_time"));

    // 6. Atualizar o registro da refeição
    $stmt_update = $conn->prepare("
        UPDATE sf_user_meal_log 
        SET custom_meal_name = ?, meal_type = ?, date_consumed = ?, servings_consumed = ?,
            kcal_consumed = ?, protein_consumed_g = ?, carbs_consumed_g = ?, fat_consumed_g = ?, logged_at = ?
        WHERE id = ? AND user_id = ?
    ");
    $stmt_update->bind_param("sssdddddssi", 
        $custom_meal_name, $meal_type, $date_consumed, $servings_consumed,
        $new_kcal, $new_protein, $new_carbs, $new_fat, $logged_at,
        $meal_id, $user_id
    );
    $stmt_update->execute();
    $stmt_update->close();

    // 7. Recalcular e atualizar o resumo diário
    // É mais seguro recalcular tudo do zero para evitar inconsistências
    $stmt_totals = $conn->prepare("
        SELECT SUM(kcal_consumed) as total_kcal, SUM(protein_consumed_g) as total_protein, 
               SUM(carbs_consumed_g) as total_carbs, SUM(fat_consumed_g) as total_fat
        FROM sf_user_meal_log WHERE user_id = ? AND date_consumed = ?
    ");
    $stmt_totals->bind_param("is", $user_id, $date_consumed);
    $stmt_totals->execute();
    $totals = $stmt_totals->get_result()->fetch_assoc();
    $stmt_totals->close();
    
    $stmt_daily = $conn->prepare("
        INSERT INTO sf_user_daily_tracking (user_id, date, kcal_consumed, protein_consumed_g, carbs_consumed_g, fat_consumed_g)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            kcal_consumed = VALUES(kcal_consumed), protein_consumed_g = VALUES(protein_consumed_g),
            carbs_consumed_g = VALUES(carbs_consumed_g), fat_consumed_g = VALUES(fat_consumed_g)
    ");
    $stmt_daily->bind_param("isdddd", 
        $user_id, $date_consumed, 
        $totals['total_kcal'], $totals['total_protein'], 
        $totals['total_carbs'], $totals['total_fat']
    );
    $stmt_daily->execute();
    $stmt_daily->close();
    
    // Se a data foi alterada, o resumo do dia antigo também precisa ser recalculado
    if ($old_meal['date_consumed'] !== $date_consumed) {
        // Recalcula o dia antigo
    }

    $conn->commit();
    $_SESSION['alert_message'] = ['type' => 'success', 'message' => 'Refeição atualizada com sucesso!'];

} catch (Exception $e) {
    $conn->rollback();
    error_log("Erro ao editar refeição para user_id {$user_id}: " . $e->getMessage());
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Ocorreu um erro de banco de dados ao salvar as alterações.'];
}

header("Location: " . BASE_APP_URL . "/diary.php?date=" . $date_consumed);
exit();
?>
