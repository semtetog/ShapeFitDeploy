<?php
/**
 * Teste da API sem autenticação
 * Acesse: https://appshapefit.com/test_api_sem_auth.php?term=whey
 */

// Define o cabeçalho correto para JSON com UTF-8
header('Content-Type: application/json; charset=utf-8');

// ✅ CREDENCIAIS DO BANCO (do arquivo db.php)
$host = '127.0.0.1:3306';
$dbname = 'u785537399_shapefit';
$username = 'u785537399_shapefit';
$password = 'Gameroficial2*';

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

if (mb_strlen($term, 'UTF-8') < 2) {
    echo json_encode(['success' => false, 'message' => 'Termo de busca muito curto.', 'data' => []]);
    exit;
}

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Erro de conexão: " . $conn->connect_error);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$results = [];

// --- BUSCA NO BANCO DE DADOS LOCAL ---
try {
    $local_term = '%' . $term . '%';
    $start_term = $term . '%';

    $sql_local = "SELECT taco_id, name_pt, brand, energy_kcal_100g, protein_g_100g, carbohydrate_g_100g, fat_g_100g FROM sf_food_items WHERE name_pt LIKE ? ORDER BY CASE WHEN name_pt LIKE ? THEN 1 ELSE 2 END, LENGTH(name_pt), name_pt LIMIT 15";
    
    $stmt_local = $conn->prepare($sql_local);
    
    if ($stmt_local) {
        $stmt_local->bind_param("ss", $local_term, $start_term);
        $stmt_local->execute();
        $result_set = $stmt_local->get_result();

        while ($row = $result_set->fetch_assoc()) {
            // Formata o nome com a marca se disponível
            $display_name = $row['name_pt'];
            if (!empty($row['brand']) && $row['brand'] !== 'TACO') {
                $display_name = $row['name_pt'] . ' - ' . $row['brand'];
            }
            
            $results[] = [
                'id' => 'taco_' . $row['taco_id'],
                'name' => $display_name,
                'brand' => $row['brand'] ?: 'TACO',
                'image_url' => null,
                'kcal_100g' => $row['energy_kcal_100g'],
                'protein_100g' => $row['protein_g_100g'],
                'carbs_100g' => $row['carbohydrate_g_100g'],
                'fat_100g' => $row['fat_g_100g'],
                'api_serving_size_info' => '100g'
            ];
        }
        $stmt_local->close();
    }
} catch (Exception $e) {
    error_log("Exceção na busca local de alimentos: " . $e->getMessage());
}

// Garante que a saída seja sempre um JSON válido
echo json_encode(['success' => true, 'data' => $results], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

$conn->close();
?>
