-- ============================================================================
-- SCRIPT FINAL - Execute APENAS o que ainda não foi feito
-- Se a coluna challenge_group_id já existe, PULE o PASSO 1
-- ============================================================================

-- PASSO 1: Adicionar challenge_group_id (PULE SE JÁ EXISTIR)
-- ALTER TABLE `sf_challenge_notifications` ADD COLUMN `challenge_group_id` int(11) NULL AFTER `id`;

-- PASSO 2: Migrar dados de group_id (SE group_id EXISTIR)
UPDATE `sf_challenge_notifications` 
SET `challenge_group_id` = `group_id` 
WHERE `challenge_group_id` IS NULL AND `group_id` IS NOT NULL;

-- PASSO 3: Adicionar notification_type (PULE SE JÁ EXISTIR)
-- ALTER TABLE `sf_challenge_notifications` ADD COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NULL AFTER `challenge_group_id`;

-- PASSO 4: Definir valor padrão (SEMPRE EXECUTE)
UPDATE `sf_challenge_notifications` 
SET `notification_type` = 'rank_change' 
WHERE `notification_type` IS NULL;

-- PASSO 5: Tornar NOT NULL (SEMPRE EXECUTE)
ALTER TABLE `sf_challenge_notifications`
MODIFY COLUMN `challenge_group_id` int(11) NOT NULL,
MODIFY COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NOT NULL DEFAULT 'rank_change';

-- PASSO 6: Remover constraint antiga (PULE SE DER ERRO)
ALTER TABLE `sf_challenge_notifications` DROP FOREIGN KEY `sf_challenge_notifications_ibfk_1`;

-- PASSO 7: Adicionar nova constraint (PULE SE DER ERRO "Duplicate key")
ALTER TABLE `sf_challenge_notifications` 
ADD CONSTRAINT `fk_challenge_notifications_group` 
FOREIGN KEY (`challenge_group_id`) REFERENCES `sf_challenge_groups` (`id`) ON DELETE CASCADE;

-- PASSO 8: Remover índice antigo (PULE SE DER ERRO)
ALTER TABLE `sf_challenge_notifications` DROP INDEX `idx_challenge_notifications_group`;

-- PASSO 9: Adicionar índices (PULE SE DER ERRO "Duplicate key")
ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_challenge_notifications_group` (`challenge_group_id`);
ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_challenge_notifications_type` (`notification_type`);
ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_user_read` (`user_id`, `is_read`);
ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_challenge_user_type_created` (`challenge_group_id`, `user_id`, `notification_type`, `created_at`);

