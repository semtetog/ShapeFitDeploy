-- ============================================================
-- EXECUTE ESTE COMANDO AGORA
-- ============================================================

-- Adicionar constraint que referencia sf_admins
ALTER TABLE `sf_challenge_groups`
ADD CONSTRAINT `sf_challenge_groups_created_by_fk` 
FOREIGN KEY (`created_by`) REFERENCES `sf_admins`(`id`) ON DELETE CASCADE;

-- ============================================================
-- Se der erro dizendo que já existe uma constraint com outro nome,
-- execute a query abaixo para descobrir o nome:
-- ============================================================

-- DESCOBRIR NOME DA CONSTRAINT EXISTENTE (execute apenas se o comando acima der erro):
SELECT 
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM 
    information_schema.KEY_COLUMN_USAGE 
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'sf_challenge_groups'
    AND COLUMN_NAME = 'created_by'
    AND REFERENCED_TABLE_NAME IS NOT NULL;

