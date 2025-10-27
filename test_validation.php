<?php
// test_validation.php - Testar validação de unidades

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/units_manager.php';

$units_manager = new UnitsManager($conn);

// Testar validações
echo "Testando validações:\n";
echo "Grama (1g): " . ($units_manager->validateUnitConversion('Grama', 1) ? 'VÁLIDO' : 'INVÁLIDO') . "\n";
echo "Unidade (1g): " . ($units_manager->validateUnitConversion('Unidade', 1) ? 'VÁLIDO' : 'INVÁLIDO') . "\n";
echo "Unidade (150g): " . ($units_manager->validateUnitConversion('Unidade', 150) ? 'VÁLIDO' : 'INVÁLIDO') . "\n";
echo "Mililitro (1g): " . ($units_manager->validateUnitConversion('Mililitro', 1) ? 'VÁLIDO' : 'INVÁLIDO') . "\n";
echo "Quilograma (1000g): " . ($units_manager->validateUnitConversion('Quilograma', 1000) ? 'VÁLIDO' : 'INVÁLIDO') . "\n";
?>
