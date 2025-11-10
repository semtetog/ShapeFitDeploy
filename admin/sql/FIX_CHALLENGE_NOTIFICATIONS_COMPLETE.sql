-- ============================================================================
-- SCRIPT COMPLETO - Execute TUDO de uma vez
-- Se algum comando der erro, significa que já foi executado, CONTINUE
-- ============================================================================

-- PASSO 1: Adicionar challenge_group_id (se não existir, pode dar erro)
ALTER TABLE `sf_challenge_notifications` ADD COLUMN `challenge_group_id` int(11) NULL AFTER `id`;

-- PASSO 2: Migrar dados de group_id (se group_id existir)
UPDATE `sf_challenge_notifications` SET `challenge_group_id` = `group_id` WHERE `challenge_group_id` IS NULL AND `group_id` IS NOT NULL;

-- PASSO 3: Adicionar notification_type (se não existir, pode dar erro)
ALTER TABLE `sf_challenge_notifications` ADD COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NULL AFTER `challenge_group_id`;

-- PASSO 4: Definir valor padrão
UPDATE `sf_challenge_notifications` SET `notification_type` = 'rank_change' WHERE `notification_type` IS NULL;

-- PASSO 5: Tornar NOT NULL
ALTER TABLE `sf_challenge_notifications` MODIFY COLUMN `challenge_group_id` int(11) NOT NULL, MODIFY COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NOT NULL DEFAULT 'rank_change';

-- PASSO 6: Remover constraint antiga (pode dar erro se não existir)
ALTER TABLE `sf_challenge_notifications` DROP FOREIGN KEY `sf_challenge_notifications_ibfk_1`;

-- PASSO 7: Adicionar nova constraint (pode dar erro se já existir)
ALTER TABLE `sf_challenge_notifications` ADD CONSTRAINT `fk_challenge_notifications_group` FOREIGN KEY (`challenge_group_id`) REFERENCES `sf_challenge_groups` (`id`) ON DELETE CASCADE;

-- PASSO 8: Remover índice antigo (pode dar erro se não existir)
ALTER TABLE `sf_challenge_notifications` DROP INDEX `idx_challenge_notifications_group`;

-- PASSO 9: Adicionar índices (pode dar erro se já existirem)
ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_challenge_notifications_group` (`challenge_group_id`);
ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_challenge_notifications_type` (`notification_type`);
ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_user_read` (`user_id`, `is_read`);
ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_challenge_user_type_created` (`challenge_group_id`, `user_id`, `notification_type`, `created_at`);
