-- ============================================================================
-- SCRIPT DE VERIFICAÇÃO - Execute este para ver o estado atual
-- ============================================================================

-- Ver colunas da tabela
SHOW COLUMNS FROM `sf_challenge_notifications`;

-- Ver constraints
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'sf_challenge_notifications'
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Ver índices
SHOW INDEX FROM `sf_challenge_notifications`;


