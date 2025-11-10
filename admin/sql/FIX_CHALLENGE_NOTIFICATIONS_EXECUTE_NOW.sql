-- ============================================================================
-- SCRIPT FINAL - Execute este script completo
-- Este script apenas garante que os dados e tipos estão corretos
-- Não tenta adicionar constraints/índices que já existem
-- ============================================================================

-- 1. Migrar dados de group_id para challenge_group_id (se group_id existir e challenge_group_id estiver NULL)
UPDATE `sf_challenge_notifications` 
SET `challenge_group_id` = `group_id` 
WHERE `challenge_group_id` IS NULL AND `group_id` IS NOT NULL;

-- 2. Definir valor padrão para notification_type onde está NULL
UPDATE `sf_challenge_notifications` 
SET `notification_type` = 'rank_change' 
WHERE `notification_type` IS NULL;

-- 3. Garantir que as colunas estão com os tipos corretos e NOT NULL
ALTER TABLE `sf_challenge_notifications`
MODIFY COLUMN `challenge_group_id` int(11) NOT NULL,
MODIFY COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NOT NULL DEFAULT 'rank_change';

-- ============================================================================
-- FIM DO SCRIPT
-- As constraints e índices já devem estar configurados no banco
-- Se precisar adicionar manualmente, execute os comandos abaixo separadamente
-- ============================================================================

-- COMANDOS OPCIONAIS (execute apenas se necessário):
-- Adicionar constraint (pode dar erro se já existir):
-- ALTER TABLE `sf_challenge_notifications` ADD CONSTRAINT `fk_challenge_notifications_group` FOREIGN KEY (`challenge_group_id`) REFERENCES `sf_challenge_groups` (`id`) ON DELETE CASCADE;

-- Adicionar índices (pode dar erro se já existirem):
-- ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_challenge_notifications_group` (`challenge_group_id`);
-- ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_challenge_notifications_type` (`notification_type`);
-- ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_user_read` (`user_id`, `is_read`);
-- ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_challenge_user_type_created` (`challenge_group_id`, `user_id`, `notification_type`, `created_at`);
