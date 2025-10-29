<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de segurança inválido']);
    exit;
}

$user_id = $_SESSION['user_id'];
$photo_type = $_POST['photo_type'] ?? ''; // 'first' ou 'last'
$checkin_date = $_POST['checkin_date'] ?? date('Y-m-d');

if (!in_array($photo_type, ['first', 'last'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tipo de foto inválido']);
    exit;
}

try {
    // Verificar se há arquivo enviado
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Nenhum arquivo foi enviado ou ocorreu um erro no upload');
    }

    $file = $_FILES['photo'];
    
    // Validar tipo de arquivo
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception('Tipo de arquivo não permitido. Use JPG, PNG ou WebP');
    }
    
    // Validar tamanho (máximo 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Arquivo muito grande. Máximo 5MB');
    }
    
    // Criar diretório se não existir
    $upload_dir = APP_ROOT_PATH . '/assets/images/weekly_checkin/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Gerar nome único para o arquivo
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $user_id . '_' . $photo_type . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Mover arquivo
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Erro ao salvar arquivo');
    }
    
    // Salvar no banco de dados
    $stmt = $conn->prepare("
        INSERT INTO sf_weekly_checkin_photos (user_id, photo_type, checkin_date, filename, created_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            filename = VALUES(filename),
            created_at = VALUES(created_at)
    ");
    
    $stmt->bind_param("isss", $user_id, $photo_type, $checkin_date, $filename);
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao salvar dados no banco');
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Foto enviada com sucesso',
        'filename' => $filename,
        'url' => BASE_ASSET_URL . '/assets/images/weekly_checkin/' . $filename
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>





