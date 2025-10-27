<?php
// MASTER_FIX_SCRIPT.php - Script MESTRE que corrige TUDO de uma vez

echo "🚀 INICIANDO CORREÇÃO COMPLETA DO SISTEMA DE UNIDADES 🚀\n\n";

// Passo 1: Adicionar coluna food_type
echo "=== PASSO 1: ADICIONANDO COLUNA FOOD_TYPE ===\n";
require_once 'add_food_type_column.php';

echo "\n" . str_repeat("=", 50) . "\n\n";

// Passo 2: Classificar todos os alimentos
echo "=== PASSO 2: CLASSIFICANDO TODOS OS ALIMENTOS ===\n";
require_once 'perfect_food_classifier.php';

echo "\n" . str_repeat("=", 50) . "\n\n";

// Passo 3: Aplicar conversões corretas
echo "=== PASSO 3: APLICANDO CONVERSÕES CORRETAS ===\n";
require_once 'apply_perfect_conversions.php';

echo "\n" . str_repeat("=", 50) . "\n\n";

echo "🎉 CORREÇÃO COMPLETA FINALIZADA! 🎉\n";
echo "Agora todos os alimentos líquidos (Coca-Cola, Ades, etc.) devem aparecer com LITRO!\n";
echo "Teste no add_food_to_diary.php para verificar!\n";
?>
