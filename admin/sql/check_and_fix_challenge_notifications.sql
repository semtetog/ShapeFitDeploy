-- Script para verificar e corrigir a estrutura da tabela sf_challenge_notifications
-- Compara com a estrutura esperada no código

-- Verificar estrutura atual
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'sf_challenge_notifications'
ORDER BY ORDINAL_POSITION;

-- Se a tabela tem group_id ao invés de challenge_group_id, fazer migração:
-- 1. Adicionar coluna challenge_group_id
ALTER TABLE `sf_challenge_notifications`
ADD COLUMN `challenge_group_id` int(11) NULL AFTER `id`;

-- 2. Copiar dados
UPDATE `sf_challenge_notifications`
SET `challenge_group_id` = `group_id`
WHERE `challenge_group_id` IS NULL AND `group_id` IS NOT NULL;

-- 3. Adicionar coluna notification_type se não existir
ALTER TABLE `sf_challenge_notifications`
ADD COLUMN IF NOT EXISTS `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NULL AFTER `challenge_group_id`;

-- 4. Tornar NOT NULL
ALTER TABLE `sf_challenge_notifications`
MODIFY COLUMN `challenge_group_id` int(11) NOT NULL,
MODIFY COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NOT NULL;

-- 5. Remover colunas antigas (descomente após verificar que migração funcionou)
-- ALTER TABLE `sf_challenge_notifications`
-- DROP COLUMN `group_id`,
-- DROP COLUMN `title`,
-- DROP COLUMN `type`;

-- 6. Atualizar foreign keys
ALTER TABLE `sf_challenge_notifications`
DROP FOREIGN KEY IF EXISTS `sf_challenge_notifications_ibfk_1`;

ALTER TABLE `sf_challenge_notifications`
ADD CONSTRAINT `fk_challenge_notifications_group` 
FOREIGN KEY (`challenge_group_id`) REFERENCES `sf_challenge_groups` (`id`) ON DELETE CASCADE;

-- 7. Atualizar índices
ALTER TABLE `sf_challenge_notifications`
ADD INDEX IF NOT EXISTS `idx_challenge_notifications_group` (`challenge_group_id`),
ADD INDEX IF NOT EXISTS `idx_challenge_notifications_type` (`notification_type`);


