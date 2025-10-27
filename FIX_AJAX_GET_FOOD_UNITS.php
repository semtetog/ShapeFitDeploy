<?php
// FIX_AJAX_GET_FOOD_UNITS.php - Corrigir API para não retornar unidades hardcoded

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "🔧 CORRIGINDO API AJAX_GET_FOOD_UNITS 🔧\n\n";

// Ler o arquivo atual
$current_file = 'api/ajax_get_food_units.php';
$content = file_get_contents($current_file);

echo "Arquivo atual lido: {$current_file}\n";

// Verificar se o arquivo existe
if (!$content) {
    echo "❌ Erro: Não foi possível ler o arquivo {$current_file}\n";
    exit;
}

// Substituir a lógica para retornar array vazio quando não há unidades específicas
$new_content = '<?php
// api/ajax_get_food_units.php - Buscar unidades de um alimento específico

header(\'Content-Type: application/json; charset=utf-8\');
require_once __DIR__ . \'/../includes/config.php\';
require_once APP_ROOT_PATH . \'/includes/db.php\';

// Verificar autenticação
if (!isset($_SESSION[\'user_id\'])) {
    http_response_code(401);
    echo json_encode([\'success\' => false, \'message\' => \'Não autenticado.\']);
    exit;
}

$food_id_string = $_GET[\'food_id\'] ?? \'\';

if (empty($food_id_string)) {
    echo json_encode([\'success\' => true, \'data\' => []]);
    exit;
}

// Extrair ID do alimento
$food_db_id = null;
$id_parts = explode(\'_\', $food_id_string, 2);
if (count($id_parts) === 2) {
    $prefix = $id_parts[0];
    $identifier = $id_parts[1];

    if ($prefix === \'taco\' && is_numeric($identifier)) {
        $stmt_find = $conn->prepare("SELECT id FROM sf_food_items WHERE taco_id = ? LIMIT 1");
    } elseif ($prefix === \'off\' && is_numeric($identifier)) {
        $stmt_find = $conn->prepare("SELECT id FROM sf_food_items WHERE barcode = ? LIMIT 1");
    } else {
        $stmt_find = false;
    }
    
    if ($stmt_find) {
        $stmt_find->bind_param("s", $identifier);
        $stmt_find->execute();
        $stmt_find->bind_result($found_id);
        if ($stmt_find->fetch()) {
            $food_db_id = $found_id;
        }
        $stmt_find->close();
    }
}

$units = [];

if ($food_db_id) {
    // Buscar unidades ESPECÍFICAS do alimento (não unidades genéricas)
    $units_sql = "SELECT fu.*, u.abbreviation, u.name as unit_name 
                  FROM sf_food_units fu 
                  JOIN sf_units u ON fu.unit_id = u.id 
                  WHERE fu.food_id = ? 
                  ORDER BY fu.is_default DESC, u.abbreviation";
    $stmt = $conn->prepare($units_sql);
    $stmt->bind_param("i", $food_db_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $units[] = [
            \'abbreviation\' => $row[\'abbreviation\'],
            \'name\' => $row[\'unit_name\'],
            \'factor\' => $row[\'factor\'],
            \'unit\' => $row[\'unit\'],
            \'is_default\' => (bool)$row[\'is_default\']
        ];
    }
    $stmt->close();
}

// IMPORTANTE: Retornar array vazio se não há unidades específicas para o alimento
echo json_encode([\'success\' => true, \'data\' => $units], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
?>';

// Salvar o arquivo corrigido
if (file_put_contents($current_file, $new_content)) {
    echo "✅ Arquivo {$current_file} corrigido com sucesso!\n";
    echo "✅ Agora a API retorna array vazio quando não há unidades específicas para o alimento\n";
    echo "✅ Isso fará com que o JavaScript mostre a mensagem de 'não classificado'\n";
} else {
    echo "❌ Erro ao salvar o arquivo {$current_file}\n";
}

echo "\n=== CORREÇÃO CONCLUÍDA ===\n";
echo "Agora teste novamente no add_food_to_diary.php!\n";
echo "Deve aparecer a mensagem de 'não classificado' para alimentos sem unidades.\n";
?>
