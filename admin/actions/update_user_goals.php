<?php
// admin/actions/update_user_goals.php

// Limpar qualquer output anterior
if (ob_get_level()) ob_end_clean();
ob_start();

// Headers primeiro
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/db.php';
    
    // Verificar admin logado
    session_start();
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Não autorizado');
    }
    
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido');
    }
    
    // Obter conexão
    $conn = require __DIR__ . '/../../includes/db.php';
    
    // Validar dados
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $daily_calories = filter_input(INPUT_POST, 'daily_calories', FILTER_VALIDATE_INT);
    $protein_g = filter_input(INPUT_POST, 'protein_g', FILTER_VALIDATE_INT);
    $carbs_g = filter_input(INPUT_POST, 'carbs_g', FILTER_VALIDATE_INT);
    $fat_g = filter_input(INPUT_POST, 'fat_g', FILTER_VALIDATE_INT);
    $water_ml = filter_input(INPUT_POST, 'water_ml', FILTER_VALIDATE_INT);
    
    if ($user_id === false || $daily_calories === false || $protein_g === false || 
        $carbs_g === false || $fat_g === false || $water_ml === false) {
        throw new Exception('Dados inválidos fornecidos');
    }
    
    // Atualizar no banco (usando colunas custom)
    $stmt = $conn->prepare("
        UPDATE sf_user_profiles 
        SET custom_calories_goal = ?, 
            custom_protein_goal_g = ?, 
            custom_carbs_goal_g = ?, 
            custom_fat_goal_g = ?,
            custom_water_goal_ml = ?
        WHERE user_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . $conn->error);
    }
    
    $stmt->bind_param("iiiiii", $daily_calories, $protein_g, $carbs_g, $fat_g, $water_ml, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao executar: ' . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();
    
    // Sucesso!
    ob_clean();
    echo json_encode([
        'success' => true, 
        'message' => 'Metas atualizadas com sucesso!',
        'data' => [
            'user_id' => $user_id,
            'daily_calories' => $daily_calories,
            'protein_g' => $protein_g,
            'carbs_g' => $carbs_g,
            'fat_g' => $fat_g,
            'water_ml' => $water_ml
        ]
    ]);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

ob_end_flush();
exit;
