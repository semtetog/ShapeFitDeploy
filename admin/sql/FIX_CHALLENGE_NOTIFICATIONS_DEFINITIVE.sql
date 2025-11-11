-- ============================================================================
-- SCRIPT DEFINITIVO - Execute este script completo
-- ============================================================================

-- PASSO 1: Migrar dados (se necessário)
UPDATE `sf_challenge_notifications` 
SET `challenge_group_id` = `group_id` 
WHERE `challenge_group_id` IS NULL AND `group_id` IS NOT NULL;

-- PASSO 2: Definir valor padrão
UPDATE `sf_challenge_notifications` 
SET `notification_type` = 'rank_change' 
WHERE `notification_type` IS NULL;

-- PASSO 3: Garantir tipos corretos (sempre execute)
ALTER TABLE `sf_challenge_notifications`
MODIFY COLUMN `challenge_group_id` int(11) NOT NULL,
MODIFY COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NOT NULL DEFAULT 'rank_change';

-- FIM - Os dados e tipos estão corretos agora
-- As constraints e índices devem ser gerenciadas separadamente se necessário


