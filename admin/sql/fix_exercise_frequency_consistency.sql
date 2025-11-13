-- ===================================================================
-- Script para corrigir inconsistências entre exercise_type e exercise_frequency
-- ===================================================================
-- Este script corrige casos onde usuários têm exercícios mas não têm frequência definida
-- 
-- IMPORTANTE: Este script trabalha com DADOS REAIS dos usuários existentes no banco
-- Ele atualiza registros existentes na tabela sf_user_profiles
--
-- FUTUROS USUÁRIOS: Não precisarão executar este script porque as validações
-- no código (edit_exercises.php) já impedem que esse problema aconteça novamente
-- ===================================================================

-- ===================================================================
-- PASSO 1: VERIFICAR DADOS ANTES DE CORRIGIR (Execute este primeiro para ver o que será alterado)
-- ===================================================================
-- Descomente as linhas abaixo para ver quais usuários serão afetados:

-- SELECT 
--     user_id,
--     exercise_type,
--     exercise_frequency,
--     CASE 
--         WHEN (exercise_type IS NOT NULL AND exercise_type != '' AND exercise_type != '0') 
--              AND (exercise_frequency IS NULL OR exercise_frequency = '' OR exercise_frequency = 'sedentary')
--         THEN 'INCONSISTENTE: Tem exercícios mas frequência está vazia/sedentary - SERÁ CORRIGIDO para 1_2x_week'
--         WHEN (exercise_type IS NULL OR exercise_type = '' OR exercise_type = '0')
--              AND (exercise_frequency IS NULL OR exercise_frequency = '' OR exercise_frequency != 'sedentary')
--         THEN 'INCONSISTENTE: Sem exercícios mas frequência não é sedentary - SERÁ CORRIGIDO para sedentary'
--         ELSE 'OK - Não será alterado'
--     END as status,
--     CASE 
--         WHEN (exercise_type IS NOT NULL AND exercise_type != '' AND exercise_type != '0') 
--              AND (exercise_frequency IS NULL OR exercise_frequency = '' OR exercise_frequency = 'sedentary')
--         THEN '1_2x_week'
--         WHEN (exercise_type IS NULL OR exercise_type = '' OR exercise_type = '0')
--              AND (exercise_frequency IS NULL OR exercise_frequency = '' OR exercise_frequency != 'sedentary')
--         THEN 'sedentary'
--         ELSE exercise_frequency
--     END as nova_frequencia
-- FROM sf_user_profiles
-- WHERE (
--     -- Casos que serão corrigidos
--     ((exercise_type IS NOT NULL AND exercise_type != '' AND exercise_type != '0') 
--      AND (exercise_frequency IS NULL OR exercise_frequency = '' OR exercise_frequency = 'sedentary'))
--     OR
--     ((exercise_type IS NULL OR exercise_type = '' OR exercise_type = '0')
--      AND (exercise_frequency IS NULL OR exercise_frequency = '' OR exercise_frequency NOT IN ('sedentary', '1_2x_week', '3_4x_week', '5_6x_week', '6_7x_week', '7plus_week')))
-- )
-- ORDER BY user_id;

-- ===================================================================
-- PASSO 2: CONTAR QUANTOS REGISTROS SERÃO AFETADOS
-- ===================================================================
-- Descomente para ver estatísticas antes de executar:

-- SELECT 
--     COUNT(*) as total_usuarios,
--     SUM(CASE 
--         WHEN (exercise_type IS NOT NULL AND exercise_type != '' AND exercise_type != '0') 
--              AND (exercise_frequency IS NULL OR exercise_frequency = '' OR exercise_frequency = 'sedentary')
--         THEN 1 ELSE 0 
--     END) as usuarios_com_exercicios_sem_frequencia,
--     SUM(CASE 
--         WHEN (exercise_type IS NULL OR exercise_type = '' OR exercise_type = '0')
--              AND (exercise_frequency IS NULL OR exercise_frequency = '' OR exercise_frequency != 'sedentary')
--         THEN 1 ELSE 0 
--     END) as usuarios_sem_exercicios_com_frequencia_errada,
--     SUM(CASE 
--         WHEN (exercise_type IS NOT NULL AND exercise_type != '' AND exercise_type != '0') 
--              AND exercise_frequency IN ('1_2x_week', '3_4x_week', '5_6x_week', '6_7x_week', '7plus_week')
--         THEN 1 ELSE 0 
--     END) as usuarios_ok_com_exercicios,
--     SUM(CASE 
--         WHEN (exercise_type IS NULL OR exercise_type = '' OR exercise_type = '0') 
--              AND exercise_frequency = 'sedentary'
--         THEN 1 ELSE 0 
--     END) as usuarios_ok_sem_exercicios
-- FROM sf_user_profiles;

-- ===================================================================
-- PASSO 3: EXECUTAR AS CORREÇÕES (Descomente para executar)
-- ===================================================================

-- CORREÇÃO 1: Usuários com exercícios mas frequência vazia/NULL/sedentary
-- Define frequência padrão como '1_2x_week' (1 a 2 vezes por semana)
-- ATENÇÃO: Isso é uma suposição. Se você quiser, pode ajustar para outro valor padrão
UPDATE sf_user_profiles
SET exercise_frequency = '1_2x_week'
WHERE (exercise_type IS NOT NULL AND exercise_type != '' AND exercise_type != '0')
  AND (exercise_frequency IS NULL OR exercise_frequency = '' OR exercise_frequency = 'sedentary');

-- CORREÇÃO 2: Usuários sem exercícios mas com frequência diferente de 'sedentary'
-- Define frequência como 'sedentary' para manter consistência
UPDATE sf_user_profiles
SET exercise_frequency = 'sedentary'
WHERE (exercise_type IS NULL OR exercise_type = '' OR exercise_type = '0')
  AND (exercise_frequency IS NULL OR exercise_frequency = '' OR exercise_frequency NOT IN ('sedentary', '1_2x_week', '3_4x_week', '5_6x_week', '6_7x_week', '7plus_week'));

-- ===================================================================
-- PASSO 4: VERIFICAR RESULTADOS APÓS A CORREÇÃO (Execute após executar as correções)
-- ===================================================================
-- Descomente para verificar se ainda há inconsistências:

-- SELECT 
--     COUNT(*) as total_usuarios,
--     SUM(CASE 
--         WHEN (exercise_type IS NOT NULL AND exercise_type != '' AND exercise_type != '0') 
--              AND exercise_frequency IN ('1_2x_week', '3_4x_week', '5_6x_week', '6_7x_week', '7plus_week')
--         THEN 1 ELSE 0 
--     END) as usuarios_com_exercicios_e_frequencia,
--     SUM(CASE 
--         WHEN (exercise_type IS NULL OR exercise_type = '' OR exercise_type = '0') 
--              AND exercise_frequency = 'sedentary'
--         THEN 1 ELSE 0 
--     END) as usuarios_sem_exercicios_sedentarios,
--     SUM(CASE 
--         WHEN (exercise_type IS NOT NULL AND exercise_type != '' AND exercise_type != '0') 
--              AND (exercise_frequency IS NULL OR exercise_frequency = '' OR exercise_frequency = 'sedentary')
--         THEN 1 ELSE 0 
--     END) as usuarios_inconsistentes_restantes
-- FROM sf_user_profiles;
-- 
-- -- Se usuarios_inconsistentes_restantes for 0, significa que todas as inconsistências foram corrigidas!

-- ===================================================================
-- NOTAS IMPORTANTES:
-- ===================================================================
-- 1. Este script NÃO cria novos usuários ou dados fictícios
-- 2. Ele APENAS atualiza dados existentes para corrigir inconsistências
-- 3. FUTUROS USUÁRIOS não precisarão executar este script porque:
--    - As validações no código (edit_exercises.php) já impedem o problema
--    - O JavaScript valida antes de enviar o formulário
--    - O backend valida novamente antes de salvar
-- 4. Se você quiser usar um valor padrão diferente de '1_2x_week' para usuários
--    com exercícios sem frequência, altere a linha 65 antes de executar
-- ===================================================================
