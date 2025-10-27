<?php
// admin/ajax_save_unit_conversions.php - API para salvar conversões de unidades

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/units_manager.php';

requireAdminLogin();

header('Content-Type: application/json');

try {
    $food_id = $_POST['food_id'] ?? null;
    $units_json = $_POST['units'] ?? null;
    
    if (!$food_id || !$units_json) {
        throw new Exception('Parâmetros obrigatórios não fornecidos');
    }
    
    $units = json_decode($units_json, true);
    if (!$units) {
        throw new Exception('Dados de unidades inválidos');
    }
    
    $units_manager = new UnitsManager($conn);
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        // Limpar unidades existentes para este alimento
        $sql = "DELETE FROM sf_food_item_conversions WHERE food_item_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $food_id);
        $stmt->execute();
        
        // Adicionar novas unidades
        foreach ($units as $unit) {
            // Converter para float se necessário
            $conversion_factor = floatval($unit['conversion_factor']);
            
            // Validar conversão
            if (!$units_manager->validateUnitConversion($unit['name'], $conversion_factor)) {
                throw new Exception("Conversão inválida para '{$unit['name']}': {$conversion_factor}g. Verifique se o valor está dentro dos limites realistas.");
            }
            
            // Verificar se a unidade existe na tabela de unidades universais
            $sql = "SELECT id FROM sf_measurement_units WHERE name = ? AND abbreviation = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $unit['name'], $unit['abbreviation']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($existing_unit = $result->fetch_assoc()) {
                $unit_id = $existing_unit['id'];
            } else {
                // Criar nova unidade universal
                $sql = "INSERT INTO sf_measurement_units (name, abbreviation, category, conversion_factor, conversion_unit, is_active) VALUES (?, ?, 'custom', 1, 'g', TRUE)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $unit['name'], $unit['abbreviation']);
                $stmt->execute();
                $unit_id = $conn->insert_id;
            }
            
            // Adicionar conversão específica para o alimento
            $sql = "INSERT INTO sf_food_item_conversions (food_item_id, unit_id, conversion_factor, is_default) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $is_default = $unit['is_default'] ? 1 : 0;
            $stmt->bind_param("iidi", $food_id, $unit_id, $conversion_factor, $is_default);
            $stmt->execute();
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Unidades salvas com sucesso!'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
