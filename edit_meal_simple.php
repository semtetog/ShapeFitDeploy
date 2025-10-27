<?php
// Versão simplificada para debug

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Editar Refeição</title></head><body>";
echo "<h1>Teste - Editar Refeição</h1>";

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo "<p>Erro: Usuário não logado</p>";
    echo "<p><a href='" . BASE_APP_URL . "/login.php'>Fazer Login</a></p>";
    echo "</body></html>";
    exit();
}

$user_id = $_SESSION['user_id'];
$meal_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

echo "<p>User ID: $user_id</p>";
echo "<p>Meal ID: $meal_id</p>";

if (!$meal_id) {
    echo "<p>Erro: ID da refeição inválido</p>";
    echo "</body></html>";
    exit();
}

// Buscar dados da refeição
$stmt = $conn->prepare("
    SELECT 
        log.id,
        log.meal_type,
        log.custom_meal_name,
        log.date_consumed,
        log.servings_consumed,
        log.kcal_consumed,
        log.protein_consumed_g,
        log.carbs_consumed_g,
        log.fat_consumed_g,
        log.logged_at,
        r.name as recipe_name,
        r.kcal_per_serving,
        r.protein_g_per_serving,
        r.carbs_g_per_serving,
        r.fat_g_per_serving
    FROM sf_user_meal_log log
    LEFT JOIN sf_recipes r ON log.recipe_id = r.id
    WHERE log.id = ? AND log.user_id = ?
");

if (!$stmt) {
    echo "<p>Erro na preparação da query: " . $conn->error . "</p>";
    echo "</body></html>";
    exit();
}

$stmt->bind_param("ii", $meal_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$meal = $result->fetch_assoc();
$stmt->close();

if (!$meal) {
    echo "<p>Erro: Refeição não encontrada</p>";
    echo "</body></html>";
    exit();
}

echo "<h2>Dados da Refeição:</h2>";
echo "<pre>";
print_r($meal);
echo "</pre>";

echo "<p><a href='" . BASE_APP_URL . "/diary.php'>Voltar ao Diário</a></p>";
echo "</body></html>";
?>
