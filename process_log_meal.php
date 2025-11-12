<?php
// public_html/process_log_meal.php (VERSÃO FINAL E CORRIGIDA)

// Definir fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo');

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

/**
 * Retorna resposta em JSON e encerra o script.
 *
 * @param array $payload
 * @param int   $status
 */
function respond_json(array $payload, int $status = 200): void
{
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($payload);
    exit();
}

/**
 * Processa um item de refeição (alimento ou receita) e persiste no banco.
 *
 * @param mysqli $conn
 * @param int    $user_id
 * @param array  $item
 *
 * @return array
 *
 * @throws Exception
 */
function processMealItem(mysqli $conn, int $user_id, array $item): array
{
    $recipe_id = isset($item['recipe_id']) && $item['recipe_id'] !== '' ? (int)$item['recipe_id'] : null;
    $servings_consumed = isset($item['servings_consumed']) ? (float)$item['servings_consumed'] : 0;
    $meal_type = trim($item['meal_type'] ?? '');
    $date_consumed = trim($item['date_consumed'] ?? '');
    $meal_time = trim($item['meal_time'] ?? '');
    $custom_meal_name = trim($item['custom_meal_name'] ?? '');
    $kcal_per_serving = (float)($item['kcal_per_serving'] ?? 0);
    $protein_per_serving = (float)($item['protein_per_serving'] ?? 0);
    $carbs_per_serving = (float)($item['carbs_per_serving'] ?? 0);
    $fat_per_serving = (float)($item['fat_per_serving'] ?? 0);
    $is_food = !empty($item['is_food']);
    $food_name = trim($item['food_name'] ?? '');

    if ($is_food && $food_name === '') {
        $food_name = $custom_meal_name;
    }

    if ($servings_consumed <= 0 || $meal_type === '' || $date_consumed === '' || $custom_meal_name === '') {
        throw new Exception('Dados inválidos para registrar a refeição.');
    }

    if ($is_food && $food_name === '') {
        throw new Exception('Nome do alimento é obrigatório.');
    }

    if (!$is_food && !$recipe_id) {
        throw new Exception('ID da receita é obrigatório.');
    }

    $date = DateTime::createFromFormat('Y-m-d', $date_consumed);
    if (!$date || $date->format('Y-m-d') !== $date_consumed) {
        throw new Exception('Data da refeição inválida.');
    }

    if ($meal_time !== '') {
        $timestamp = strtotime($date_consumed . ' ' . $meal_time);
        $logged_time = $timestamp ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s');
    } else {
        $logged_time = date('Y-m-d H:i:s');
    }

    $total_kcal = isset($item['total_kcal']) ? (float)$item['total_kcal'] : ($kcal_per_serving * $servings_consumed);
    $total_protein = isset($item['total_protein']) ? (float)$item['total_protein'] : ($protein_per_serving * $servings_consumed);
    $total_carbs = isset($item['total_carbs']) ? (float)$item['total_carbs'] : ($carbs_per_serving * $servings_consumed);
    $total_fat = isset($item['total_fat']) ? (float)$item['total_fat'] : ($fat_per_serving * $servings_consumed);

    if ($is_food) {
        $sql_log = "INSERT INTO sf_user_meal_log (user_id, custom_meal_name, meal_type, date_consumed, servings_consumed, kcal_consumed, protein_consumed_g, carbs_consumed_g, fat_consumed_g, logged_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_log = $conn->prepare($sql_log);
        if (!$stmt_log) {
            throw new Exception('Erro ao preparar inserção de alimento.');
        }
        $stmt_log->bind_param(
            "isssddddds",
            $user_id,
            $custom_meal_name,
            $meal_type,
            $date_consumed,
            $servings_consumed,
            $total_kcal,
            $total_protein,
            $total_carbs,
            $total_fat,
            $logged_time
        );
    } else {
        $sql_log = "INSERT INTO sf_user_meal_log (user_id, recipe_id, meal_type, date_consumed, servings_consumed, kcal_consumed, protein_consumed_g, carbs_consumed_g, fat_consumed_g, logged_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_log = $conn->prepare($sql_log);
        if (!$stmt_log) {
            throw new Exception('Erro ao preparar inserção de receita.');
        }
        $stmt_log->bind_param(
            "iissddddds",
            $user_id,
            $recipe_id,
            $meal_type,
            $date_consumed,
            $servings_consumed,
            $total_kcal,
            $total_protein,
            $total_carbs,
            $total_fat,
            $logged_time
        );
    }

    $stmt_log->execute();
    $stmt_log->close();

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
    if (!$stmt_track) {
        throw new Exception('Erro ao preparar atualização do consumo diário.');
    }
    $stmt_track->bind_param(
        "isdddd",
        $user_id,
        $date_consumed,
        $total_kcal,
        $total_protein,
        $total_carbs,
        $total_fat
    );
    $stmt_track->execute();
    $stmt_track->close();

    return [
        'success' => true,
        'date_consumed' => $date_consumed
    ];
}
// 1. Validação de Segurança
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Erro de validação de segurança.'];
    header("Location: " . BASE_APP_URL . "/main_app.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$is_batch = isset($_POST['batch']) && $_POST['batch'] === '1';

if ($is_batch) {
    $items_raw = $_POST['items'] ?? '[]';
    $items = json_decode($items_raw, true);

    if (!is_array($items) || empty($items)) {
        respond_json(['success' => false, 'message' => 'Nenhuma refeição foi informada.'], 400);
    }

    $conn->begin_transaction();

    try {
        $redirectDate = null;

        foreach ($items as $batchItem) {
            $result = processMealItem($conn, $user_id, $batchItem);
            if (!$redirectDate && !empty($result['date_consumed'])) {
                $redirectDate = $result['date_consumed'];
            }
        }

        $conn->commit();

        respond_json([
            'success' => true,
            'redirect' => BASE_APP_URL . '/diary.php?date=' . ($redirectDate ?: date('Y-m-d'))
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Erro ao registrar refeições em lote para user_id {$user_id}: " . $e->getMessage());
        respond_json(['success' => false, 'message' => 'Não foi possível salvar as refeições.'], 500);
    }
}

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

if (!$servings_consumed || empty($meal_type) || empty($date_consumed) || empty($custom_meal_name) || $servings_consumed <= 0) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Dados inválidos. Nome da refeição é obrigatório.'];
    header("Location: " . BASE_APP_URL . "/diary.php?date=" . $date_consumed);
    exit();
}

if ($is_food && empty($food_name)) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Nome do alimento é obrigatório.'];
    header("Location: " . BASE_APP_URL . "/diary.php?date=" . $date_consumed);
    exit();
}

if (!$is_food && !$recipe_id) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'ID da receita é obrigatório.'];
    header("Location: " . BASE_APP_URL . "/diary.php?date=" . $date_consumed);
    exit();
}

$total_kcal = $kcal_per_serving * $servings_consumed;
$total_protein = $protein_per_serving * $servings_consumed;
$total_carbs = $carbs_per_serving * $servings_consumed;
$total_fat = $fat_per_serving * $servings_consumed;

$singleItem = [
    'recipe_id' => $recipe_id,
    'servings_consumed' => $servings_consumed,
    'meal_type' => $meal_type,
    'date_consumed' => $date_consumed,
    'meal_time' => $meal_time,
    'custom_meal_name' => $custom_meal_name,
    'kcal_per_serving' => $kcal_per_serving,
    'protein_per_serving' => $protein_per_serving,
    'carbs_per_serving' => $carbs_per_serving,
    'fat_per_serving' => $fat_per_serving,
    'is_food' => $is_food ? 1 : 0,
    'food_name' => $food_name,
    'total_kcal' => $total_kcal,
    'total_protein' => $total_protein,
    'total_carbs' => $total_carbs,
    'total_fat' => $total_fat
];

$conn->begin_transaction();

try {
    processMealItem($conn, $user_id, $singleItem);
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