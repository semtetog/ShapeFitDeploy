-- Script para adicionar coluna status e atualizar conteúdos
-- Execute este SQL no phpMyAdmin
-- IMPORTANTE: Se a coluna já existir, você verá um erro. Isso é normal, ignore e continue.

-- ============================================
-- PASSO 1: Adicionar coluna status (se não existir)
-- ============================================
-- Se der erro de coluna já existir, ignore e vá para o PASSO 2
-- A coluna será adicionada no final da tabela (sem AFTER, pois target_id não existe)
ALTER TABLE `sf_member_content` 
ADD COLUMN `status` ENUM('active', 'inactive', 'draft') DEFAULT 'active';

-- Adicionar índice na coluna status (se não existir)
-- Se der erro de índice já existir, ignore
ALTER TABLE `sf_member_content` 
ADD INDEX `status` (`status`);

-- ============================================
-- PASSO 2: Migrar dados de is_active para status
-- ============================================
-- Converter is_active (tinyint) para status (ENUM)
-- Se is_active = 1, então status = 'active'
-- Se is_active = 0, então status = 'inactive'
UPDATE `sf_member_content` 
SET `status` = CASE 
    WHEN `is_active` = 1 THEN 'active'
    WHEN `is_active` = 0 THEN 'inactive'
    ELSE 'active'
END
WHERE `status` IS NULL OR `status` = 'draft';

-- ============================================
-- PASSO 3: Atualizar todos os conteúdos com status NULL ou 'draft' para 'active'
-- ============================================
UPDATE `sf_member_content` 
SET `status` = 'active' 
WHERE `status` IS NULL OR `status` = 'draft';

-- ============================================
-- PASSO 4: Verificar quantos conteúdos estão ativos
-- ============================================
SELECT 
    COUNT(*) as total_active,
    'Conteúdos ativos' as message
FROM `sf_member_content` 
WHERE `status` = 'active';

-- Ver todos os status
SELECT 
    `status`,
    COUNT(*) as quantidade
FROM `sf_member_content`
GROUP BY `status`;

-- Ver comparação entre is_active e status
SELECT 
    `is_active`,
    `status`,
    COUNT(*) as quantidade
FROM `sf_member_content`
GROUP BY `is_active`, `status`;

