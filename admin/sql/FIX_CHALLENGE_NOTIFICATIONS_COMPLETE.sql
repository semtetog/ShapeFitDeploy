-- ============================================================================
-- SCRIPT COMPLETO PARA AJUSTAR sf_challenge_notifications
-- Execute este script uma única vez para corrigir a estrutura da tabela
-- ============================================================================

-- Verificar estrutura atual (apenas para visualização)
SELECT 'ESTRUTURA ATUAL:' as info;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE 
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'sf_challenge_notifications'
ORDER BY ORDINAL_POSITION;

-- ============================================================================
-- PASSO 1: Adicionar coluna challenge_group_id (se não existir)
-- ============================================================================
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_challenge_notifications' 
    AND COLUMN_NAME = 'challenge_group_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `sf_challenge_notifications` ADD COLUMN `challenge_group_id` int(11) NULL AFTER `id`',
    'SELECT "Coluna challenge_group_id já existe" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- PASSO 2: Migrar dados de group_id para challenge_group_id
-- ============================================================================
SET @col_group_id_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_challenge_notifications' 
    AND COLUMN_NAME = 'group_id'
);

SET @sql = IF(@col_group_id_exists > 0,
    'UPDATE `sf_challenge_notifications` SET `challenge_group_id` = `group_id` WHERE `challenge_group_id` IS NULL AND `group_id` IS NOT NULL',
    'SELECT "Coluna group_id não existe, pulando migração" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- PASSO 3: Adicionar coluna notification_type (se não existir)
-- ============================================================================
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_challenge_notifications' 
    AND COLUMN_NAME = 'notification_type'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `sf_challenge_notifications` ADD COLUMN `notification_type` enum(\'rank_change\',\'overtake\',\'milestone\',\'daily_reminder\') NULL AFTER `challenge_group_id`',
    'SELECT "Coluna notification_type já existe" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- PASSO 4: Definir valor padrão para notification_type onde está NULL
-- ============================================================================
UPDATE `sf_challenge_notifications` 
SET `notification_type` = 'rank_change' 
WHERE `notification_type` IS NULL;

-- ============================================================================
-- PASSO 5: Tornar as colunas NOT NULL
-- ============================================================================
ALTER TABLE `sf_challenge_notifications`
MODIFY COLUMN `challenge_group_id` int(11) NOT NULL,
MODIFY COLUMN `notification_type` enum('rank_change','overtake','milestone','daily_reminder') NOT NULL DEFAULT 'rank_change';

-- ============================================================================
-- PASSO 6: Remover constraint antiga (se existir)
-- ============================================================================
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_challenge_notifications' 
    AND CONSTRAINT_NAME = 'sf_challenge_notifications_ibfk_1'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = IF(@fk_exists > 0,
    'ALTER TABLE `sf_challenge_notifications` DROP FOREIGN KEY `sf_challenge_notifications_ibfk_1`',
    'SELECT "Constraint antiga não existe, pulando remoção" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- PASSO 7: Adicionar nova constraint (se não existir)
-- ============================================================================
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_challenge_notifications' 
    AND CONSTRAINT_NAME = 'fk_challenge_notifications_group'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `sf_challenge_notifications` ADD CONSTRAINT `fk_challenge_notifications_group` FOREIGN KEY (`challenge_group_id`) REFERENCES `sf_challenge_groups` (`id`) ON DELETE CASCADE',
    'SELECT "Constraint fk_challenge_notifications_group já existe" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- PASSO 8: Atualizar índices
-- ============================================================================
-- Remover índice antigo se existir
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_challenge_notifications' 
    AND INDEX_NAME = 'idx_challenge_notifications_group'
);

SET @sql = IF(@idx_exists > 0,
    'ALTER TABLE `sf_challenge_notifications` DROP INDEX `idx_challenge_notifications_group`',
    'SELECT "Índice idx_challenge_notifications_group não existe, pulando remoção" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar índices
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_challenge_notifications' 
    AND INDEX_NAME = 'idx_challenge_notifications_group'
);

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_challenge_notifications_group` (`challenge_group_id`)',
    'SELECT "Índice idx_challenge_notifications_group já existe" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_challenge_notifications' 
    AND INDEX_NAME = 'idx_challenge_notifications_type'
);

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_challenge_notifications_type` (`notification_type`)',
    'SELECT "Índice idx_challenge_notifications_type já existe" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar outros índices necessários
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_challenge_notifications' 
    AND INDEX_NAME = 'idx_user_read'
);

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_user_read` (`user_id`, `is_read`)',
    'SELECT "Índice idx_user_read já existe" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_challenge_notifications' 
    AND INDEX_NAME = 'idx_challenge_user_type_created'
);

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `sf_challenge_notifications` ADD INDEX `idx_challenge_user_type_created` (`challenge_group_id`, `user_id`, `notification_type`, `created_at`)',
    'SELECT "Índice idx_challenge_user_type_created já existe" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- PASSO 9: Remover colunas antigas (DESCOMENTE APENAS SE QUISER REMOVER)
-- ============================================================================
-- IMPORTANTE: Descomente as linhas abaixo APENAS após verificar que tudo funcionou corretamente
-- e que não há mais dados importantes nas colunas antigas

/*
SET @col_group_id_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_challenge_notifications' 
    AND COLUMN_NAME = 'group_id'
);

SET @sql = IF(@col_group_id_exists > 0,
    'ALTER TABLE `sf_challenge_notifications` DROP COLUMN `group_id`',
    'SELECT "Coluna group_id não existe, pulando remoção" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_title_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_challenge_notifications' 
    AND COLUMN_NAME = 'title'
);

SET @sql = IF(@col_title_exists > 0,
    'ALTER TABLE `sf_challenge_notifications` DROP COLUMN `title`',
    'SELECT "Coluna title não existe, pulando remoção" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_type_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_challenge_notifications' 
    AND COLUMN_NAME = 'type'
);

SET @sql = IF(@col_type_exists > 0,
    'ALTER TABLE `sf_challenge_notifications` DROP COLUMN `type`',
    'SELECT "Coluna type não existe, pulando remoção" as info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
*/

-- ============================================================================
-- VERIFICAÇÃO FINAL
-- ============================================================================
SELECT 'ESTRUTURA FINAL:' as info;
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'sf_challenge_notifications'
ORDER BY ORDINAL_POSITION;

SELECT 'CONSTRAINTS:' as info;
SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE
FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'sf_challenge_notifications';

SELECT 'ÍNDICES:' as info;
SELECT INDEX_NAME, COLUMN_NAME
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'sf_challenge_notifications'
ORDER BY INDEX_NAME, SEQ_IN_INDEX;

SELECT '✅ MIGRAÇÃO CONCLUÍDA!' as resultado;

