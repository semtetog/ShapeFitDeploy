-- Script para ajustar a estrutura da tabela sf_challenge_notifications
-- para corresponder ao que foi implementado no código

-- Verificar se a tabela existe e tem a estrutura antiga
-- Se tiver group_id, title, type -> precisa ajustar

-- PASSO 1: Adicionar colunas novas se não existirem
ALTER TABLE `sf_challenge_notifications`
ADD COLUMN IF NOT EXISTS `challenge_group_id` int(11) NULL AFTER `id`,
ADD COLUMN IF NOT EXISTS `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NULL AFTER `challenge_group_id`;

-- PASSO 2: Copiar dados de group_id para challenge_group_id (se group_id existir)
UPDATE `sf_challenge_notifications`
SET `challenge_group_id` = `group_id`
WHERE `challenge_group_id` IS NULL AND `group_id` IS NOT NULL;

-- PASSO 3: Remover colunas antigas se existirem (após verificar que dados foram migrados)
-- ALTER TABLE `sf_challenge_notifications`
-- DROP COLUMN IF EXISTS `group_id`,
-- DROP COLUMN IF EXISTS `title`,
-- DROP COLUMN IF EXISTS `type`;

-- PASSO 4: Tornar challenge_group_id NOT NULL após migração
ALTER TABLE `sf_challenge_notifications`
MODIFY COLUMN `challenge_group_id` int(11) NOT NULL,
MODIFY COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NOT NULL;

-- PASSO 5: Atualizar foreign keys
-- Remover constraint antiga se existir
ALTER TABLE `sf_challenge_notifications`
DROP FOREIGN KEY IF EXISTS `sf_challenge_notifications_ibfk_1`;

-- Adicionar nova constraint
ALTER TABLE `sf_challenge_notifications`
ADD CONSTRAINT `fk_challenge_notifications_group` 
FOREIGN KEY (`challenge_group_id`) REFERENCES `sf_challenge_groups` (`id`) ON DELETE CASCADE;

-- PASSO 6: Atualizar índices
ALTER TABLE `sf_challenge_notifications`
DROP INDEX IF EXISTS `idx_challenge_notifications_group`,
ADD INDEX `idx_challenge_notifications_group` (`challenge_group_id`),
ADD INDEX `idx_challenge_notifications_type` (`notification_type`);

