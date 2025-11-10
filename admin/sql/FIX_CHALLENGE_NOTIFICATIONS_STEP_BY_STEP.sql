-- ============================================================================
-- SCRIPT PASSO A PASSO - Execute apenas os passos que ainda não foram feitos
-- ============================================================================

-- ============================================================================
-- VERIFICAÇÃO INICIAL: Veja quais colunas já existem
-- ============================================================================
-- Execute este SELECT primeiro para ver o que já existe:
SHOW COLUMNS FROM `sf_challenge_notifications`;

-- ============================================================================
-- PASSO 1: Se challenge_group_id NÃO existe, execute:
-- ============================================================================
ALTER TABLE `sf_challenge_notifications` 
ADD COLUMN `challenge_group_id` int(11) NULL AFTER `id`;

-- ============================================================================
-- PASSO 2: Se group_id existe, migre os dados:
-- ============================================================================
UPDATE `sf_challenge_notifications` 
SET `challenge_group_id` = `group_id` 
WHERE `challenge_group_id` IS NULL AND `group_id` IS NOT NULL;

-- ============================================================================
-- PASSO 3: Se notification_type NÃO existe, execute:
-- ============================================================================
ALTER TABLE `sf_challenge_notifications` 
ADD COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NULL AFTER `challenge_group_id`;

-- ============================================================================
-- PASSO 4: Definir valores padrão
-- ============================================================================
UPDATE `sf_challenge_notifications` 
SET `notification_type` = 'rank_change' 
WHERE `notification_type` IS NULL;

-- ============================================================================
-- PASSO 5: Tornar NOT NULL (sempre execute)
-- ============================================================================
ALTER TABLE `sf_challenge_notifications`
MODIFY COLUMN `challenge_group_id` int(11) NOT NULL,
MODIFY COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NOT NULL DEFAULT 'rank_change';

-- ============================================================================
-- PASSO 6: Remover constraint antiga (se existir)
-- ============================================================================
ALTER TABLE `sf_challenge_notifications` 
DROP FOREIGN KEY `sf_challenge_notifications_ibfk_1`;

-- ============================================================================
-- PASSO 7: Adicionar nova constraint
-- ============================================================================
ALTER TABLE `sf_challenge_notifications` 
ADD CONSTRAINT `fk_challenge_notifications_group` 
FOREIGN KEY (`challenge_group_id`) REFERENCES `sf_challenge_groups` (`id`) ON DELETE CASCADE;

-- ============================================================================
-- PASSO 8: Atualizar índices
-- ============================================================================
-- Remover índice antigo (se existir)
ALTER TABLE `sf_challenge_notifications` 
DROP INDEX `idx_challenge_notifications_group`;

-- Adicionar índices (execute todos)
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
SHOW COLUMNS FROM `sf_challenge_notifications`;

