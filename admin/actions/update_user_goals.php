<?php
// admin/actions/update_user_goals.php

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/auth_admin.php';
$conn = require __DIR__ . '/../../includes/db.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_ADMIN_URL . "/users.php");
    exit;
}

$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$daily_calories = filter_input(INPUT_POST, 'daily_calories', FILTER_VALIDATE_INT);
$protein_g = filter_input(INPUT_POST, 'protein_g', FILTER_VALIDATE_INT);
$carbs_g = filter_input(INPUT_POST, 'carbs_g', FILTER_VALIDATE_INT);
$fat_g = filter_input(INPUT_POST, 'fat_g', FILTER_VALIDATE_INT);
$water_ml = filter_input(INPUT_POST, 'water_ml', FILTER_VALIDATE_INT);

if (!$user_id || !$daily_calories || !$protein_g || !$carbs_g || !$fat_g || !$water_ml) {
    $_SESSION['admin_alert'] = [
        'type' => 'danger',
        'message' => 'Todos os campos são obrigatórios.'
    ];
    header("Location: " . BASE_ADMIN_URL . "/view_user.php?id=" . $user_id);
    exit;
}

// Atualizar as metas do usuário
$stmt = $conn->prepare("
    UPDATE sf_user_profiles 
    SET daily_calories_goal = ?, 
        protein_goal_g = ?, 
        carbs_goal_g = ?, 
        fat_goal_g = ?,
        water_goal_ml = ?
    WHERE user_id = ?
");

$stmt->bind_param("iiiiii", $daily_calories, $protein_g, $carbs_g, $fat_g, $water_ml, $user_id);

if ($stmt->execute()) {
    $_SESSION['admin_alert'] = [
        'type' => 'success',
        'message' => 'Metas nutricionais atualizadas com sucesso!'
    ];
} else {
    $_SESSION['admin_alert'] = [
        'type' => 'danger',
        'message' => 'Erro ao atualizar metas: ' . $stmt->error
    ];
}

$stmt->close();
$conn->close();

header("Location: " . BASE_ADMIN_URL . "/view_user.php?id=" . $user_id);
exit;

