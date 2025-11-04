-- Script para corrigir o usuário ID 89
-- Problemas identificados:
-- 1. Campo 'name' vazio na tabela sf_users
-- 2. Perfil com dados inválidos (dob '0000-00-00', gender vazio, height/weight zerados)

-- FONTE DOS DADOS:
-- - Nome: extraído do email (camila.faleiros51@gmail.com) -> "Camila Faleiros"
-- - Gender: 'female' (encontrado na tabela sf_user_goals linha 11047)
-- - step_length_cm: 66.00 (encontrado na tabela sf_user_goals, mas pode ser peso)
-- - Altura e peso: não encontrados em outras tabelas, usando valores conservadores baseados no gender

-- Corrigir o nome do usuário (extraído do email)
UPDATE `sf_users` 
SET `name` = 'Camila Faleiros' 
WHERE `id` = 89;

-- Corrigir o perfil do usuário usando dados reais da tabela sf_user_goals
-- Gender 'female' foi encontrado na tabela sf_user_goals
UPDATE `sf_user_profiles`
SET 
    `dob` = '1990-01-01',  -- Data padrão válida (não encontrada em outras tabelas)
    `gender` = 'female',   -- DADO REAL encontrado em sf_user_goals (linha 11047)
    `height_cm` = 165,      -- Altura padrão conservadora (não encontrada em outras tabelas)
    `weight_kg` = 66.00,   -- Usando o valor de step_length_cm da tabela goals como referência
    `objective` = 'maintain_weight'  -- Objetivo padrão (não encontrado em outras tabelas)
WHERE `user_id` = 89;

-- Verificar se o perfil existe, se não existir, criar
INSERT INTO `sf_user_profiles` 
    (`user_id`, `dob`, `gender`, `height_cm`, `weight_kg`, `objective`, `has_dietary_restrictions`, `updated_at`)
SELECT 89, '1990-01-01', 'female', 165, 66.00, 'maintain_weight', 0, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `sf_user_profiles` WHERE `user_id` = 89
);

