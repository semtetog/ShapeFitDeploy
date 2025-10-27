<?php
// admin/ajax_create_category.php (VERSÃO CORRIGIDA COM GERAÇÃO DE SLUG)

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
$conn = require __DIR__ . '/../includes/db.php';
requireAdminLogin();

// Função para criar um slug URL-friendly
function generateSlug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    if (empty($text)) { return 'n-a'; }
    return $text;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit();
}

$category_name = trim($_POST['category_name'] ?? '');
$slug = generateSlug($category_name);

if (empty($category_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'O nome da categoria não pode ser vazio.']);
    exit();
}

try {
    $stmt_check = $conn->prepare("SELECT id FROM sf_categories WHERE name = ? OR slug = ?");
    $stmt_check->bind_param("ss", $category_name, $slug);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Esta categoria já existe.']);
        exit();
    }
    $stmt_check->close();

    $stmt_insert = $conn->prepare("INSERT INTO sf_categories (name, slug) VALUES (?, ?)");
    $stmt_insert->bind_param("ss", $category_name, $slug);
    
    if ($stmt_insert->execute()) {
        $new_id = $conn->insert_id;
        echo json_encode(['success' => true, 'id' => $new_id, 'name' => $category_name]);
    } else {
        throw new Exception("Falha ao executar a inserção.");
    }
    $stmt_insert->close();

} catch (Exception $e) {
    http_response_code(500);
    error_log("Erro em ajax_create_category: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocorreu um erro no servidor ao criar a categoria.']);
}

$conn->close();
?>