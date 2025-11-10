-- ============================================================================
-- SCRIPT COMPLETO PARA AJUSTAR sf_challenge_notifications
-- Execute este script uma única vez para corrigir a estrutura da tabela
-- ============================================================================

-- Verificar estrutura atual
SELECT 'ESTRUTURA ATUAL:' as info;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE 
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'sf_challenge_notifications'
ORDER BY ORDINAL_POSITION;

-- ============================================================================
-- PASSO 1: Adicionar coluna challenge_group_id (se não existir)
-- ============================================================================
-- Se der erro "Duplicate column name", significa que já existe, continue
ALTER TABLE `sf_challenge_notifications` 
ADD COLUMN `challenge_group_id` int(11) NULL AFTER `id`;

-- ============================================================================
-- PASSO 2: Migrar dados de group_id para challenge_group_id
-- ============================================================================
-- Se group_id existir, copiar os dados
UPDATE `sf_challenge_notifications` 
SET `challenge_group_id` = `group_id` 
WHERE `challenge_group_id` IS NULL AND `group_id` IS NOT NULL;

-- ============================================================================
-- PASSO 3: Adicionar coluna notification_type (se não existir)
-- ============================================================================
-- Se der erro "Duplicate column name", significa que já existe, continue
ALTER TABLE `sf_challenge_notifications` 
ADD COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NULL AFTER `challenge_group_id`;

-- ============================================================================
-- PASSO 4: Definir valor padrão para notification_type onde está NULL
-- ============================================================================
UPDATE `sf_challenge_notifications` 
SET `notification_type` = 'rank_change' 
WHERE `notification_type` IS NULL;

-- ============================================================================
-- PASSO 5: Tornar as colunas NOT NULL
-- ============================================================================
ALTER TABLE `sf_challenge_notifications`
MODIFY COLUMN `challenge_group_id` int(11) NOT NULL,
MODIFY COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NOT NULL DEFAULT 'rank_change';

-- ============================================================================
-- PASSO 6: Remover constraint antiga (se existir)
-- ============================================================================
-- Se der erro "Can't DROP", significa que não existe, continue
ALTER TABLE `sf_challenge_notifications` 
DROP FOREIGN KEY `sf_challenge_notifications_ibfk_1`;

-- ============================================================================
-- PASSO 7: Adicionar nova constraint
-- ============================================================================
-- Se der erro "Duplicate key name", significa que já existe, continue
ALTER TABLE `sf_challenge_notifications` 
ADD CONSTRAINT `fk_challenge_notifications_group` 
FOREIGN KEY (`challenge_group_id`) REFERENCES `sf_challenge_groups` (`id`) ON DELETE CASCADE;

-- ============================================================================
-- PASSO 8: Atualizar índices
-- ============================================================================
-- Remover índice antigo se existir (pode dar erro se não existir, ignore)
ALTER TABLE `sf_challenge_notifications` 
DROP INDEX `idx_challenge_notifications_group`;

-- Adicionar índices (pode dar erro se já existir, ignore)
ALTER TABLE `sf_challenge_notifications` 
ADD INDEX `idx_challenge_notifications_group` (`challenge_group_id`);

ALTER TABLE `sf_challenge_notifications` 
ADD INDEX `idx_challenge_notifications_type` (`notification_type`);

ALTER TABLE `sf_challenge_notifications` 
ADD INDEX `idx_user_read` (`user_id`, `is_read`);

ALTER TABLE `sf_challenge_notifications` 
ADD INDEX `idx_challenge_user_type_created` (`challenge_group_id`, `user_id`, `notification_type`, `created_at`);

-- ============================================================================
-- VERIFICAÇÃO FINAL
-- ============================================================================
SELECT 'ESTRUTURA FINAL:' as info;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'sf_challenge_notifications'
ORDER BY ORDINAL_POSITION;

SELECT 'CONSTRAINTS:' as info;
SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'sf_challenge_notifications';

SELECT 'ÍNDICES:' as info;
SELECT INDEX_NAME, COLUMN_NAME
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'sf_challenge_notifications'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

-- ============================================================================
-- OPcional: Remover colunas antigas (DESCOMENTE APENAS SE QUISER REMOVER)
-- ============================================================================
-- IMPORTANTE: Descomente as linhas abaixo APENAS após verificar que tudo funcionou
-- e que não há mais dados importantes nas colunas antigas

/*
ALTER TABLE `sf_challenge_notifications` DROP COLUMN `group_id`;
ALTER TABLE `sf_challenge_notifications` DROP COLUMN `title`;
ALTER TABLE `sf_challenge_notifications` DROP COLUMN `type`;
*/

SELECT '✅ MIGRAÇÃO CONCLUÍDA!' as resultado;
