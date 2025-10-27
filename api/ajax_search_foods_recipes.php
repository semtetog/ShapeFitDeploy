<?php
// Arquivo: api/ajax_search_foods_recipes.php - Busca unificada de receitas e alimentos

// Desabilitar exibição de erros para não quebrar o JSON
error_reporting(0);
ini_set('display_errors', 0);

// Headers para CORS
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header('Content-Type: application/json; charset=utf-8');

// Função para retornar erro JSON
function returnError($message) {
    echo json_encode(['success' => false, 'message' => $message, 'data' => []]);
    exit;
}

// Incluir arquivos necessários
require_once __DIR__ . '/../includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

// Iniciar sessão se não estiver iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Garantir que o usuário esteja logado
if (!isset($_SESSION['user_id'])) {
    returnError('Não autenticado.');
}

$term = isset($_GET['term']) ? trim($_GET['term']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : 'all'; // all, recipes, foods

if (mb_strlen($term, 'UTF-8') < 2) {
    returnError('Termo de busca muito curto.');
}

$results = [];

try {
    $local_term = '%' . $term . '%';
    $start_term = $term . '%';

    // Buscar receitas se solicitado
    if ($type === 'all' || $type === 'recipes') {
        $sql_recipes = "SELECT 
            id, 
            name, 
            image_filename, 
            kcal_per_serving, 
            protein_g_per_serving, 
            carbs_g_per_serving, 
            fat_g_per_serving
        FROM sf_recipes 
        WHERE name LIKE ? 
        ORDER BY 
            CASE WHEN name LIKE ? THEN 1 ELSE 2 END,
            name ASC
        LIMIT 10";
        
        $stmt_recipes = $conn->prepare($sql_recipes);
        $stmt_recipes->bind_param("ss", $local_term, $start_term);
        $stmt_recipes->execute();
        $result_recipes = $stmt_recipes->get_result();
        
        while ($recipe = $result_recipes->fetch_assoc()) {
            $recipe['type'] = 'recipe';
            $results[] = $recipe;
        }
        $stmt_recipes->close();
    }

    // Buscar alimentos se solicitado
    if ($type === 'all' || $type === 'foods') {
        $sql_foods = "SELECT 
            id, 
            name_pt as name,
            'placeholder_food.jpg' as image_filename,
            energy_kcal_100g as kcal_per_serving,
            protein_g_100g as protein_g_per_serving,
            carbohydrate_g_100g as carbs_g_per_serving,
            fat_g_100g as fat_g_per_serving,
            'sf_food_items' as source_table
        FROM sf_food_items 
        WHERE name_pt LIKE ? 
        ORDER BY 
            CASE WHEN name_pt LIKE ? THEN 1 ELSE 2 END,
            name_pt ASC
        LIMIT 10";
        
        $stmt_foods = $conn->prepare($sql_foods);
        if (!$stmt_foods) {
            returnError('Erro na consulta de alimentos.');
        }
        
        $stmt_foods->bind_param("ss", $local_term, $start_term);
        $stmt_foods->execute();
        $result_foods = $stmt_foods->get_result();
        
        while ($food = $result_foods->fetch_assoc()) {
            $food['type'] = 'food';
            $results[] = $food;
        }
        
        $stmt_foods->close();
    }

    // Ordenar resultados: primeiro os que começam com o termo, depois os que contêm
    usort($results, function($a, $b) use ($term) {
        $a_starts = stripos($a['name'], $term) === 0;
        $b_starts = stripos($b['name'], $term) === 0;
        
        if ($a_starts && !$b_starts) return -1;
        if (!$a_starts && $b_starts) return 1;
        
        // Depois ordenar por comprimento do nome
        return strlen($a['name']) - strlen($b['name']);
    });

    // Limitar a 15 resultados no total
    $results = array_slice($results, 0, 15);

    echo json_encode([
        'success' => true,
        'data' => $results
    ]);

} catch (Exception $e) {
    error_log("Erro na busca de receitas/alimentos: " . $e->getMessage());
    returnError('Erro interno do servidor.');
}
?>