<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth_admin.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

if (!isset($_POST['action']) || $_POST['action'] !== 'update_goals') {
    echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    exit;
}

$user_id = intval($_POST['user_id']);
$calories_goal = intval($_POST['calories_goal']);
$protein_goal = floatval($_POST['protein_goal']);
$carbs_goal = floatval($_POST['carbs_goal']);
$fat_goal = floatval($_POST['fat_goal']);
$water_goal = intval($_POST['water_goal']);

// Validar dados
if ($calories_goal < 800 || $calories_goal > 5000) {
    echo json_encode(['success' => false, 'message' => 'Meta de calorias deve estar entre 800 e 5000 kcal']);
    exit;
}

if ($protein_goal < 20 || $protein_goal > 300) {
    echo json_encode(['success' => false, 'message' => 'Meta de proteínas deve estar entre 20 e 300g']);
    exit;
}

if ($carbs_goal < 20 || $carbs_goal > 500) {
    echo json_encode(['success' => false, 'message' => 'Meta de carboidratos deve estar entre 20 e 500g']);
    exit;
}

if ($fat_goal < 10 || $fat_goal > 200) {
    echo json_encode(['success' => false, 'message' => 'Meta de gorduras deve estar entre 10 e 200g']);
    exit;
}

if ($water_goal < 1000 || $water_goal > 5000) {
    echo json_encode(['success' => false, 'message' => 'Meta de hidratação deve estar entre 1000 e 5000ml']);
    exit;
}

try {
    // Atualizar metas do usuário
    $stmt = $conn->prepare("
        UPDATE sf_users 
        SET 
            water_goal_ml = ?,
            daily_calories_goal = ?,
            protein_goal_g = ?,
            carbs_goal_g = ?,
            fat_goal_g = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->bind_param("iidddi", 
        $water_goal, 
        $calories_goal, 
        $protein_goal, 
        $carbs_goal, 
        $fat_goal, 
        $user_id
    );
    
    if ($stmt->execute()) {
        // Log da alteração
        $admin_id = $_SESSION['admin_id'] ?? 0;
        $log_message = "Metas nutricionais atualizadas pelo admin $admin_id: Calorias: $calories_goal, Proteínas: $protein_goal, Carboidratos: $carbs_goal, Gorduras: $fat_goal, Água: $water_goal";
        
        error_log("ADMIN GOALS UPDATE - User $user_id: $log_message");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Metas atualizadas com sucesso!',
            'data' => [
                'calories_goal' => $calories_goal,
                'protein_goal' => $protein_goal,
                'carbs_goal' => $carbs_goal,
                'fat_goal' => $fat_goal,
                'water_goal' => $water_goal
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar metas no banco de dados']);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Erro ao atualizar metas do usuário $user_id: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

$conn->close();
?>




