-- ============================================================
-- DEBUG: Verificar estado atual da tabela
-- ============================================================

-- 1. Verificar o tipo de dado da coluna created_by
SHOW CREATE TABLE sf_challenge_groups;

-- 2. Verificar se sf_admins.id existe e qual é o tipo
SHOW CREATE TABLE sf_admins;

-- 3. Verificar se há dados em sf_challenge_groups com created_by que não existe em sf_admins
SELECT cg.id, cg.created_by, cg.name, a.id as admin_exists
FROM sf_challenge_groups cg
LEFT JOIN sf_admins a ON cg.created_by = a.id
ORDER BY cg.id;

-- 4. Verificar quais admins existem
SELECT id, username, full_name FROM sf_admins;

-- 5. Verificar todas as constraints que existem na tabela sf_challenge_groups
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM 
    information_schema.KEY_COLUMN_USAGE 
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'sf_challenge_groups';

-- ============================================================
-- Com essas informações, vamos descobrir o problema
-- ============================================================

