-- Script para corrigir o usuário ID 89
-- Problemas identificados:
-- 1. Campo 'name' vazio na tabela sf_users
-- 2. Perfil com dados inválidos (dob '0000-00-00', gender vazio, height/weight zerados)

-- Corrigir o nome do usuário (extraído do email)
UPDATE `sf_users` 
SET `name` = 'Camila Faleiros' 
WHERE `id` = 89;

-- Corrigir o perfil do usuário
-- Definir valores padrão mínimos para permitir o funcionamento
UPDATE `sf_user_profiles`
SET 
    `dob` = '1990-01-01',  -- Data padrão válida
    `gender` = 'female',   -- Assumindo feminino baseado no nome
    `height_cm` = 165,      -- Altura padrão razoável
    `weight_kg` = 60.00,   -- Peso padrão razoável
    `objective` = 'maintain_weight'  -- Objetivo padrão
WHERE `user_id` = 89;

-- Verificar se o perfil existe, se não existir, criar
INSERT INTO `sf_user_profiles` 
    (`user_id`, `dob`, `gender`, `height_cm`, `weight_kg`, `objective`, `has_dietary_restrictions`, `updated_at`)
SELECT 89, '1990-01-01', 'female', 165, 60.00, 'maintain_weight', 0, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `sf_user_profiles` WHERE `user_id` = 89
);

