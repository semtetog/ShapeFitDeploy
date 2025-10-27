<?php
// update_food_categories.php - Atualiza as categorias de alimentos

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

echo "=== ATUALIZANDO CATEGORIAS DE ALIMENTOS ===\n\n";

try {
    // Atualizar a coluna food_type para incluir todas as categorias
    $sql = "ALTER TABLE sf_food_items MODIFY COLUMN food_type ENUM(
        'líquido',
        'semi_liquido', 
        'granular',
        'unidade_inteira',
        'fatias_pedacos',
        'corte_porcao',
        'colher_cremoso',
        'condimentos',
        'oleos_gorduras',
        'preparacoes_compostas'
    ) DEFAULT 'granular'";
    
    if ($conn->query($sql)) {
        echo "✅ Categorias atualizadas com sucesso!\n";
    } else {
        echo "❌ Erro ao atualizar categorias: " . $conn->error . "\n";
        exit(1);
    }
    
    echo "\n=== CATEGORIAS DISPONÍVEIS ===\n";
    echo "1. líquido - Água, suco, leite, refrigerante, café\n";
    echo "2. semi_liquido - Iogurte, purê, mingau, molhos, mel\n";
    echo "3. granular - Arroz, feijão, açúcar, farinha, cereais\n";
    echo "4. unidade_inteira - Maçã, banana, pão, ovo, biscoito\n";
    echo "5. fatias_pedacos - Queijo, presunto, bolo, pizza\n";
    echo "6. corte_porcao - Carnes cruas, peixes, frango\n";
    echo "7. colher_cremoso - Doce de leite, pasta amendoim, sorvete\n";
    echo "8. condimentos - Sal, açúcar, canela, cacau em pó\n";
    echo "9. oleos_gorduras - Óleo, azeite, manteiga\n";
    echo "10. preparacoes_compostas - Lasanha, estrogonofe, feijoada\n";
    
    echo "\n✅ Estrutura atualizada!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
?>
