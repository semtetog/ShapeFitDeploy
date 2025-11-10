-- ============================================================================
-- SCRIPT FINAL - Execute este script completo
-- Ignore erros de "Duplicate" ou "doesn't exist" - isso é normal
-- ============================================================================

-- 1. Migrar dados de group_id para challenge_group_id (se group_id existir e houver dados NULL)
UPDATE `sf_challenge_notifications` 
SET `challenge_group_id` = `group_id` 
WHERE `challenge_group_id` IS NULL AND `group_id` IS NOT NULL;

-- 2. Definir valor padrão para notification_type onde está NULL
UPDATE `sf_challenge_notifications` 
SET `notification_type` = 'rank_change' 
WHERE `notification_type` IS NULL;

-- 3. Garantir que as colunas estão NOT NULL e com os tipos corretos
ALTER TABLE `sf_challenge_notifications`
MODIFY COLUMN `challenge_group_id` int(11) NOT NULL,
MODIFY COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NOT NULL DEFAULT 'rank_change';

-- 4. Adicionar nova constraint (pode dar erro se já existir, ignore)
ALTER TABLE `sf_challenge_notifications` 
ADD CONSTRAINT `fk_challenge_notifications_group` 
FOREIGN KEY (`challenge_group_id`) REFERENCES `sf_challenge_groups` (`id`) ON DELETE CASCADE;

-- 5. Adicionar índices necessários (pode dar erro se já existirem, ignore)
ALTER TABLE `sf_challenge_notifications` 
ADD INDEX `idx_challenge_notifications_group` (`challenge_group_id`);

ALTER TABLE `sf_challenge_notifications` 
ADD INDEX `idx_challenge_notifications_type` (`notification_type`);

ALTER TABLE `sf_challenge_notifications` 
ADD INDEX `idx_user_read` (`user_id`, `is_read`);

ALTER TABLE `sf_challenge_notifications` 
ADD INDEX `idx_challenge_user_type_created` (`challenge_group_id`, `user_id`, `notification_type`, `created_at`);
