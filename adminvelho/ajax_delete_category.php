<?php
// admin/ajax_delete_category.php

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
$conn = require __DIR__ . '/../includes/db.php';
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit();
}

$category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);

if (!$category_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID da categoria inválido.']);
    exit();
}

$conn->begin_transaction();

try {
    // 1. Remove todas as associações desta categoria com as receitas
    $stmt_delete_assoc = $conn->prepare("DELETE FROM sf_recipe_has_categories WHERE category_id = ?");
    $stmt_delete_assoc->bind_param("i", $category_id);
    $stmt_delete_assoc->execute();
    $stmt_delete_assoc->close();

    // 2. Remove a categoria em si
    $stmt_delete_cat = $conn->prepare("DELETE FROM sf_categories WHERE id = ?");
    $stmt_delete_cat->bind_param("i", $category_id);
    $stmt_delete_cat->execute();
    
    if ($stmt_delete_cat->affected_rows > 0) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Categoria excluída com sucesso.']);
    } else {
        throw new Exception("Categoria não encontrada para exclusão.");
    }
    $stmt_delete_cat->close();

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    error_log("Erro em ajax_delete_category: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro no servidor ao excluir a categoria.']);
}

$conn->close();
?>