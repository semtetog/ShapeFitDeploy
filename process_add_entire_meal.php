<?php
// public_html/shapefit/process_add_entire_meal.php - VERSÃO FINAL COM PONTOS E TIMESTAMP CORRETO
require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Erro de validação de segurança.'];
        header("Location: " . BASE_APP_URL . "/main_app.php");
        exit();
    }

    $log_date_str = trim($_POST['log_date'] ?? date('Y-m-d'));
    $log_meal_type = trim($_POST['log_meal_type'] ?? '');
    $meal_items_json = $_POST['meal_items_json'] ?? '[]';
    $meal_items = json_decode($meal_items_json, true);

    $errors = [];
    if (empty($log_meal_type)) $errors[] = "Tipo de refeição não selecionado.";
    if (empty($meal_items)) $errors[] = "Nenhum alimento foi adicionado à refeição.";
    
    $date_obj = DateTime::createFromFormat('Y-m-d', $log_date_str);
    if (!$date_obj || $date_obj->format('Y-m-d') !== $log_date_str) {
        $errors[] = "Data de consumo inválida.";
    } else {
        $log_date = $date_obj->format('Y-m-d');
    }

    if (!empty($errors)) {
        $_SESSION['alert_message'] = ['type' => 'danger', 'message' => "Erro ao registrar refeição: " . implode(" ", $errors)];
        header("Location: " . BASE_APP_URL . "/add_food_to_diary.php?date={$log_date_str}&meal_type={$log_meal_type}");
        exit();
    }

    $conn->begin_transaction();
    try {
        $total_kcal_meal = 0; $total_protein_meal = 0; $total_carbs_meal = 0; $total_fat_meal = 0;

        // Adiciona 5 pontos por simplesmente registrar uma refeição (uma vez por tipo de refeição por dia)
        $points_for_logging = 0;
        $action_key_log = "MEAL_LOGGED_{$log_meal_type}";
        $php_timestamp = date('Y-m-d H:i:s'); // Pega a hora correta do PHP

        // CORREÇÃO AQUI: Adiciona a coluna `timestamp` e o placeholder `?`
        $stmt_log_check = $conn->prepare("INSERT IGNORE INTO sf_user_points_log (user_id, points_awarded, action_key, date_awarded, timestamp) VALUES (?, 5, ?, ?, ?)");
        
        // CORREÇÃO AQUI: Adiciona a variável $php_timestamp ao bind
        $stmt_log_check->bind_param("isss", $user_id, $action_key_log, $log_date, $php_timestamp);
        $stmt_log_check->execute();
        if ($stmt_log_check->affected_rows > 0) {
            addPointsToUser($conn, $user_id, 5, "Registrou refeição: {$log_meal_type}");
            $points_for_logging = 5;
        }
        $stmt_log_check->close();
        
        // Insere cada item da refeição no log
        $stmt_log_item = $conn->prepare("INSERT INTO sf_user_meal_log (user_id, date_consumed, meal_type, custom_meal_name, servings_consumed, kcal_consumed, protein_consumed_g, carbs_consumed_g, fat_consumed_g) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt_log_item) throw new Exception("Prepare sf_user_meal_log failed: " . $conn->error);

        foreach ($meal_items as $item) {
            $food_name_to_log = $item['name'];
            if(!empty($item['brand'])) $food_name_to_log .= " (" . $item['brand'] . ")";
            $servings_for_log = 1;
            $stmt_log_item->bind_param("isssdidds", $user_id, $log_date, $log_meal_type, $food_name_to_log, $servings_for_log, $item['kcal'], $item['protein'], $item['carbs'], $item['fat']);
            if (!$stmt_log_item->execute()) throw new Exception("Execute sf_user_meal_log item failed: " . $stmt_log_item->error);
            
            $total_kcal_meal += $item['kcal']; $total_protein_meal += $item['protein']; $total_carbs_meal += $item['carbs']; $total_fat_meal += $item['fat'];
        }
        $stmt_log_item->close();

        // Atualiza o registro diário total do usuário
        getDailyTrackingRecord($conn, $user_id, $log_date);
        $stmt_update_daily = $conn->prepare("UPDATE sf_user_daily_tracking SET kcal_consumed = kcal_consumed + ?, protein_consumed_g = protein_consumed_g + ?, carbs_consumed_g = carbs_consumed_g + ?, fat_consumed_g = fat_consumed_g + ? WHERE user_id = ? AND date = ?");
        if (!$stmt_update_daily) throw new Exception("Prepare sf_user_daily_tracking update failed: " . $conn->error);
        $stmt_update_daily->bind_param("idddis", $total_kcal_meal, $total_protein_meal, $total_carbs_meal, $total_fat_meal, $user_id, $log_date);
        if (!$stmt_update_daily->execute()) throw new Exception("Execute sf_user_daily_tracking update failed: " . $stmt_update_daily->error);
        $stmt_update_daily->close();

        // Após atualizar os totais, chamamos a função para verificar as metas
        $points_from_goals = checkAndAwardMacroGoals($conn, $user_id, $log_date);

        // Armazena os pontos ganhos na sessão para mostrar um popup na próxima página
        $total_points_earned_this_action = $points_for_logging + $points_from_goals;
        if ($total_points_earned_this_action > 0) {
            $_SESSION['points_earned_popup'] = $total_points_earned_this_action;
        }
        
        $conn->commit();
        unset($_SESSION['csrf_token']);
        $_SESSION['alert_message'] = ['type' => 'success', 'message' => 'Refeição registrada com sucesso!'];
        header("Location: " . BASE_APP_URL . "/diary.php?date=" . $log_date);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Erro ao registrar refeição completa para user_id {$user_id}: " . $e->getMessage());
        $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Ops! Ocorreu um erro ao registrar sua refeição. Detalhe: ' . $e->getMessage()];
        header("Location: " . BASE_APP_URL . "/add_food_to_diary.php?date={$log_date_str}&meal_type={$log_meal_type}");
        exit();
    }
} else {
    header("Location: " . BASE_APP_URL . "/main_app.php");
    exit();
}
?>