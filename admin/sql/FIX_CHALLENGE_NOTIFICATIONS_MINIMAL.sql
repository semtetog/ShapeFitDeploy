-- ============================================================================
-- SCRIPT MÍNIMO - Execute apenas este script
-- Apenas garante que os dados e tipos estão corretos
-- ============================================================================

-- 1. Migrar dados de group_id para challenge_group_id (se necessário)
UPDATE `sf_challenge_notifications` 
SET `challenge_group_id` = `group_id` 
WHERE `challenge_group_id` IS NULL AND `group_id` IS NOT NULL;

-- 2. Definir valor padrão para notification_type onde está NULL
UPDATE `sf_challenge_notifications` 
SET `notification_type` = 'rank_change' 
WHERE `notification_type` IS NULL;

-- 3. Garantir que as colunas estão com os tipos corretos
ALTER TABLE `sf_challenge_notifications`
MODIFY COLUMN `challenge_group_id` int(11) NOT NULL,
MODIFY COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NOT NULL DEFAULT 'rank_change';

-- PRONTO! Os dados e tipos estão corretos agora.

