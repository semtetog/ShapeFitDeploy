<?php
// api/calculate_nutrition.php - API para calcular valores nutricionais com unidades

require_once '../includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/units_manager.php';

header('Content-Type: application/json');

try {
    $units_manager = new UnitsManager($conn);
    
    $food_id = $_POST['food_id'] ?? null;
    $quantity = floatval($_POST['quantity'] ?? 0);
    $unit_id = intval($_POST['unit_id'] ?? 0);
    $is_recipe = $_POST['is_recipe'] ?? '0';
    
    if (!$food_id || !$quantity || !$unit_id) {
        throw new Exception('Parâmetros obrigatórios não fornecidos');
    }
    
    // Buscar dados nutricionais do alimento/receita
    if ($is_recipe === '1') {
        $sql = "SELECT kcal_per_serving, protein_g_per_serving, carbs_g_per_serving, fat_g_per_serving FROM sf_recipes WHERE id = ?";
    } else {
        $sql = "SELECT energy_kcal_100g, protein_g_100g, carbohydrate_g_100g, fat_g_100g FROM sf_food_items WHERE id = ?";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $food_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$food = $result->fetch_assoc()) {
        throw new Exception('Alimento/receita não encontrado');
    }
    
    // Converter quantidade para unidade base
    $quantity_in_base_unit = $units_manager->convertToBaseUnit($quantity, $unit_id, $food_id);
    
    // Preparar dados nutricionais por 100g
    if ($is_recipe === '1') {
        $nutrition_per_100g = [
            'kcal' => $food['kcal_per_serving'],
            'protein' => $food['protein_g_per_serving'],
            'carbs' => $food['carbs_g_per_serving'],
            'fat' => $food['fat_g_per_serving']
        ];
        // Para receitas, assumimos que 1 porção = 100g
        $factor = $quantity_in_base_unit / 100;
    } else {
        $nutrition_per_100g = [
            'kcal' => $food['energy_kcal_100g'],
            'protein' => $food['protein_g_100g'],
            'carbs' => $food['carbohydrate_g_100g'],
            'fat' => $food['fat_g_100g']
        ];
        $factor = $quantity_in_base_unit / 100;
    }
    
    // Calcular valores nutricionais
    $calculated_nutrition = [
        'kcal' => round($nutrition_per_100g['kcal'] * $factor, 1),
        'protein' => round($nutrition_per_100g['protein'] * $factor, 1),
        'carbs' => round($nutrition_per_100g['carbs'] * $factor, 1),
        'fat' => round($nutrition_per_100g['fat'] * $factor, 1)
    ];
    
    // Buscar informações da unidade
    $sql = "SELECT name, abbreviation FROM sf_measurement_units WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $unit_info = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'nutrition' => $calculated_nutrition,
            'quantity_in_base_unit' => round($quantity_in_base_unit, 2),
            'unit_info' => $unit_info,
            'factor' => round($factor, 4)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
