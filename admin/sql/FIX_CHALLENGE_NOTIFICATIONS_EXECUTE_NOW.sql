-- ============================================================================
-- EXECUTE ESTE SCRIPT - Pule apenas os comandos que derem erro
-- ============================================================================

-- 1. Migrar dados (se group_id existir)
UPDATE `sf_challenge_notifications` SET `challenge_group_id` = `group_id` WHERE `challenge_group_id` IS NULL AND `group_id` IS NOT NULL;

-- 2. Adicionar notification_type (se não existir)
ALTER TABLE `sf_challenge_notifications` ADD COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NULL AFTER `challenge_group_id`;

-- 3. Definir valor padrão
UPDATE `sf_challenge_notifications` SET `notification_type` = 'rank_change' WHERE `notification_type` IS NULL;

-- 4. Tornar NOT NULL
ALTER TABLE `sf_challenge_notifications` MODIFY COLUMN `challenge_group_id` int(11) NOT NULL, MODIFY COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NOT NULL DEFAULT 'rank_change';

-- 5. Remover constraint antiga
ALTER TABLE `sf_challenge_notifications` DROP FOREIGN KEY `sf_challenge_notifications_ibfk_1`;

-- 6. Adicionar nova constraint
ALTER TABLE `sf_challenge_notifications` ADD CONSTRAINT `fk_challenge_notifications_group` FOREIGN KEY (`challenge_group_id`) REFERENCES `sf_challenge_groups` (`id`) ON DELETE CASCADE;

-- 7. Remover índice antigo
ALTER TABLE `sf_challenge_notifications` DROP INDEX `idx_challenge_notifications_group`;

-- 8. Adicionar índices
ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_challenge_notifications_group` (`challenge_group_id`);
ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_challenge_notifications_type` (`notification_type`);
ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_user_read` (`user_id`, `is_read`);
ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_challenge_user_type_created` (`challenge_group_id`, `user_id`, `notification_type`, `created_at`);

