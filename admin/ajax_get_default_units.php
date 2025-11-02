<?php
// admin/ajax_get_default_units.php - API para buscar unidades padrão de uma categoria

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/units_manager.php';

requireAdminLogin();

header('Content-Type: application/json');

try {
    $category = $_GET['category'] ?? null;
    
    if (!$category) {
        throw new Exception('Categoria não fornecida');
    }
    
    // Mapeamento de categorias para abreviações de unidades
    $category_units_map = [
        'líquido' => ['ml', 'l', 'cs', 'cc', 'xc'],
        'semi_liquido' => ['g', 'ml', 'cs', 'cc', 'xc'],
        'granular' => ['g', 'kg', 'cs', 'cc'],
        'unidade_inteira' => ['un', 'g', 'kg'],
        'fatias_pedacos' => ['fat', 'g', 'kg'],
        'corte_porcao' => ['g', 'kg', 'un'],
        'colher_cremoso' => ['cs', 'cc', 'g'],
        'condimentos' => ['cc', 'cs', 'g'],
        'oleos_gorduras' => ['cs', 'cc', 'ml', 'l'],
        'preparacoes_compostas' => ['g', 'kg', 'un']
    ];
    
    $abbreviations = $category_units_map[$category] ?? ['g', 'ml', 'un'];
    $placeholders = implode(',', array_fill(0, count($abbreviations), '?'));
    
    // Buscar as unidades no banco com seus fatores de conversão reais
    $sql = "
        SELECT 
            id,
            name,
            abbreviation,
            conversion_factor,
            CASE 
                WHEN abbreviation = 'g' THEN 1
                ELSE 0
            END AS is_default
        FROM sf_measurement_units 
        WHERE abbreviation IN ($placeholders) AND is_active = TRUE
        ORDER BY FIELD(abbreviation, '" . implode("', '", $abbreviations) . "')
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . $conn->error);
    }
    
    $stmt->bind_param(str_repeat('s', count($abbreviations)), ...$abbreviations);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $units = [];
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $units
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>


