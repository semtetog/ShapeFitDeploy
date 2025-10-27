<?php
// public_html/shapefit/ajax_toggle_favorite.php
require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';

header('Content-Type: application/json'); // Sempre retorna JSON
$response = ['success' => false, 'is_favorited' => false, 'message' => ''];
$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $response['message'] = 'Erro de validação de segurança.';
        echo json_encode($response);
        exit();
    }

    $recipe_id = filter_input(INPUT_POST, 'recipe_id', FILTER_VALIDATE_INT);

    if (!$recipe_id) {
        $response['message'] = 'ID da receita inválido.';
        echo json_encode($response);
        exit();
    }

    // Verificar se já é favorito
    $stmt_check = $conn->prepare("SELECT recipe_id FROM sf_user_favorite_recipes WHERE user_id = ? AND recipe_id = ?");
    $stmt_check->bind_param("ii", $user_id, $recipe_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $is_currently_favorited = $result_check->num_rows > 0;
    $stmt_check->close();

    if ($is_currently_favorited) {
        // Desfavoritar
        $stmt_delete = $conn->prepare("DELETE FROM sf_user_favorite_recipes WHERE user_id = ? AND recipe_id = ?");
        $stmt_delete->bind_param("ii", $user_id, $recipe_id);
        if ($stmt_delete->execute()) {
            $response['success'] = true;
            $response['is_favorited'] = false;
            $response['message'] = 'Receita removida dos favoritos!';
        } else {
            $response['message'] = 'Erro ao remover dos favoritos.';
            error_log("Error desfavoritando recipe_id {$recipe_id} for user_id {$user_id}: " . $stmt_delete->error);
        }
        $stmt_delete->close();
    } else {
        // Favoritar
        $stmt_insert = $conn->prepare("INSERT INTO sf_user_favorite_recipes (user_id, recipe_id) VALUES (?, ?)");
        $stmt_insert->bind_param("ii", $user_id, $recipe_id);
        if ($stmt_insert->execute()) {
            $response['success'] = true;
            $response['is_favorited'] = true;
            $response['message'] = 'Receita adicionada aos favoritos!';
        } else {
            $response['message'] = 'Erro ao adicionar aos favoritos.';
            error_log("Error favoritando recipe_id {$recipe_id} for user_id {$user_id}: " . $stmt_insert->error);
        }
        $stmt_insert->close();
    }
} else {
    $response['message'] = 'Método inválido.';
}

echo json_encode($response);
exit();
?>