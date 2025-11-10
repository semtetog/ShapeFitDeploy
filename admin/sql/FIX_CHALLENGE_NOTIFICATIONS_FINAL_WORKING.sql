-- ============================================================================
-- SCRIPT FINAL QUE FUNCIONA - Execute este script
-- ============================================================================

-- PASSO 1: Migrar dados de group_id para challenge_group_id (se necessário)
UPDATE `sf_challenge_notifications` 
SET `challenge_group_id` = `group_id` 
WHERE `challenge_group_id` IS NULL AND `group_id` IS NOT NULL;

-- PASSO 2: Definir valor padrão para notification_type
UPDATE `sf_challenge_notifications` 
SET `notification_type` = 'rank_change' 
WHERE `notification_type` IS NULL;

-- PASSO 3: Garantir tipos corretos
ALTER TABLE `sf_challenge_notifications`
MODIFY COLUMN `challenge_group_id` int(11) NOT NULL,
MODIFY COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NOT NULL DEFAULT 'rank_change';

-- PRONTO! O script está completo.
-- As constraints e índices já devem estar configurados no banco.
-- Se o código ainda não funcionar, verifique se a constraint em group_id foi removida.

