-- ============================================================================
-- Script para ajustar a estrutura da tabela sf_challenge_notifications
-- do refnova.sql para corresponder ao que foi implementado no código
-- ============================================================================

-- PROBLEMA IDENTIFICADO:
-- No refnova.sql, a tabela sf_challenge_notifications tem:
--   - group_id (ao invés de challenge_group_id)
--   - title (não usado no código)
--   - type enum('info','warning','success','achievement') (ao invés de notification_type)
--
-- No código (includes/functions.php), esperamos:
--   - challenge_group_id
--   - notification_type enum('rank_change','overtake','milestone','daily_reminder')
--   - message (já existe)
--   - is_read (já existe)
--   - created_at (já existe)

-- PASSO 1: Verificar se a tabela tem a estrutura antiga
-- Execute este SELECT primeiro para verificar:
-- SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sf_challenge_notifications';

-- PASSO 2: Adicionar coluna challenge_group_id se não existir
ALTER TABLE `sf_challenge_notifications`
ADD COLUMN `challenge_group_id` int(11) NULL AFTER `id`;

-- PASSO 3: Copiar dados de group_id para challenge_group_id
UPDATE `sf_challenge_notifications`
SET `challenge_group_id` = `group_id`
WHERE `challenge_group_id` IS NULL AND `group_id` IS NOT NULL;

-- PASSO 4: Adicionar coluna notification_type se não existir
ALTER TABLE `sf_challenge_notifications`
ADD COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NULL AFTER `challenge_group_id`;

-- PASSO 5: Tornar as colunas NOT NULL após migração
ALTER TABLE `sf_challenge_notifications`
MODIFY COLUMN `challenge_group_id` int(11) NOT NULL,
MODIFY COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NOT NULL DEFAULT 'rank_change';

-- PASSO 6: Remover constraint antiga se existir
ALTER TABLE `sf_challenge_notifications`
DROP FOREIGN KEY IF EXISTS `sf_challenge_notifications_ibfk_1`;

-- PASSO 7: Adicionar nova constraint
ALTER TABLE `sf_challenge_notifications`
ADD CONSTRAINT `fk_challenge_notifications_group` 
FOREIGN KEY (`challenge_group_id`) REFERENCES `sf_challenge_groups` (`id`) ON DELETE CASCADE;

-- PASSO 8: Atualizar índices
ALTER TABLE `sf_challenge_notifications`
DROP INDEX IF EXISTS `idx_challenge_notifications_group`,
ADD INDEX `idx_challenge_notifications_group` (`challenge_group_id`),
ADD INDEX `idx_challenge_notifications_type` (`notification_type`);

-- PASSO 9: Remover colunas antigas (DESCOMENTE APENAS APÓS VERIFICAR QUE TUDO FUNCIONOU)
-- ALTER TABLE `sf_challenge_notifications`
-- DROP COLUMN `group_id`,
-- DROP COLUMN `title`,
-- DROP COLUMN `type`;

-- ============================================================================
-- VERIFICAÇÃO FINAL
-- ============================================================================
-- Execute este SELECT para verificar a estrutura final:
-- SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE 
-- FROM INFORMATION_SCHEMA.COLUMNS
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sf_challenge_notifications'
-- ORDER BY ORDINAL_POSITION;
