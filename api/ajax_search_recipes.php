<?php
// Arquivo: api/ajax_search_recipes.php

header("Access-Control-Allow-Origin: https://localhost"); 
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

// Garantir que o usuário esteja logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

if (mb_strlen($term, 'UTF-8') < 2) {
    echo json_encode(['success' => false, 'message' => 'Termo de busca muito curto.', 'data' => []]);
    exit;
}

$results = [];

try {
    $local_term = '%' . $term . '%';
    $start_term = $term . '%';

    $sql = "SELECT id, name, image_filename, kcal_per_serving, protein_g_per_serving, carbs_g_per_serving, fat_g_per_serving 
            FROM sf_recipes 
            WHERE is_public = TRUE AND name LIKE ? 
            ORDER BY CASE WHEN name LIKE ? THEN 1 ELSE 2 END, LENGTH(name), name 
            LIMIT 15";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("ss", $local_term, $start_term);
        $stmt->execute();
        $result_set = $stmt->get_result();

        while ($row = $result_set->fetch_assoc()) {
            $results[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'image_filename' => $row['image_filename'],
                'kcal_per_serving' => (float)$row['kcal_per_serving'],
                'protein_g_per_serving' => (float)$row['protein_g_per_serving'],
                'carbs_g_per_serving' => (float)$row['carbs_g_per_serving'],
                'fat_g_per_serving' => (float)$row['fat_g_per_serving']
            ];
        }
        
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Erro na busca de receitas: " . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'data' => $results
]);
?>




