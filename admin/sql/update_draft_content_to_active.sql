-- Script para adicionar coluna status e atualizar conteúdos
-- Execute este SQL no phpMyAdmin
-- IMPORTANTE: Se a coluna já existir, você verá um erro. Isso é normal, ignore e continue.

-- ============================================
-- PASSO 1: Adicionar coluna status (se não existir)
-- ============================================
-- Se der erro de coluna já existir, ignore e vá para o PASSO 2
ALTER TABLE `sf_member_content` 
ADD COLUMN `status` ENUM('active', 'inactive', 'draft') DEFAULT 'active' 
AFTER `target_id`;

-- Adicionar índice na coluna status (se não existir)
-- Se der erro de índice já existir, ignore
ALTER TABLE `sf_member_content` 
ADD INDEX `status` (`status`);

-- ============================================
-- PASSO 2: Atualizar todos os conteúdos existentes para 'active'
-- ============================================
-- Primeiro, definir todos os registros existentes como 'active' (caso estejam NULL)
UPDATE `sf_member_content` 
SET `status` = 'active' 
WHERE `status` IS NULL OR `status` = 'draft';

-- ============================================
-- PASSO 3: Verificar quantos conteúdos estão ativos
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

