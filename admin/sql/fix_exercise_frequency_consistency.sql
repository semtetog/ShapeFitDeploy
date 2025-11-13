-- Script para corrigir inconsistências entre exercise_type e exercise_frequency
-- Este script corrige casos onde usuários têm exercícios mas não têm frequência definida

-- 1. Identificar casos problemáticos (para verificação)
-- SELECT 
--     user_id,
--     exercise_type,
--     exercise_frequency,
--     CASE 
--         WHEN (exercise_type IS NOT NULL AND exercise_type != '' AND exercise_type != '0') 
--              AND (exercise_frequency IS NULL OR exercise_frequency = '' OR exercise_frequency = 'sedentary')
--         THEN 'INCONSISTENTE: Tem exercícios mas frequência está vazia/sedentary'
--         ELSE 'OK'
--     END as status
-- FROM sf_user_profiles
-- WHERE exercise_type IS NOT NULL AND exercise_type != '' AND exercise_type != '0';

-- 2. Corrigir casos onde há exercícios mas frequência está vazia, NULL ou 'sedentary'
-- Define frequência padrão como '1_2x_week' para usuários com exercícios mas sem frequência válida
UPDATE sf_user_profiles
SET exercise_frequency = '1_2x_week'
WHERE (exercise_type IS NOT NULL AND exercise_type != '' AND exercise_type != '0')
  AND (exercise_frequency IS NULL OR exercise_frequency = '' OR exercise_frequency = 'sedentary');

-- 3. Garantir que usuários sem exercícios tenham frequência como 'sedentary'
UPDATE sf_user_profiles
SET exercise_frequency = 'sedentary'
WHERE (exercise_type IS NULL OR exercise_type = '' OR exercise_type = '0')
  AND (exercise_frequency IS NULL OR exercise_frequency = '' OR exercise_frequency NOT IN ('sedentary', '1_2x_week', '3_4x_week', '5_6x_week', '6_7x_week', '7plus_week'));

-- 4. Verificar resultados (executar após a correção)
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
--     END) as usuarios_inconsistentes
-- FROM sf_user_profiles;

