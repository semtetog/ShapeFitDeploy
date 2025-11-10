-- ============================================================
-- CORREÇÃO DIRETA - Execute este comando
-- ============================================================

-- Se já existir uma constraint, vamos tentar adicionar mesmo assim
-- Se der erro, o MySQL vai nos dizer qual constraint já existe
-- Nesse caso, você precisará descobrir o nome e removê-la primeiro

-- Tente adicionar a nova constraint diretamente:
ALTER TABLE `sf_challenge_groups`
ADD CONSTRAINT `sf_challenge_groups_created_by_fk` 
FOREIGN KEY (`created_by`) REFERENCES `sf_admins`(`id`) ON DELETE CASCADE;

-- ============================================================
-- Se der erro dizendo que já existe uma constraint, execute esta query
-- para descobrir o nome real da constraint:
-- ============================================================

-- DESCUBRIR NOME DA CONSTRAINT:
SELECT 
    CONSTRAINT_NAME as 'Nome da Constraint',
    REFERENCED_TABLE_NAME as 'Tabela Referenciada',
    REFERENCED_COLUMN_NAME as 'Coluna Referenciada'
FROM 
    information_schema.KEY_COLUMN_USAGE 
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'sf_challenge_groups'
    AND COLUMN_NAME = 'created_by'
    AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Depois que descobrir o nome, remova com:
-- ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY `NOME_ENCONTRADO`;
-- E execute o ADD CONSTRAINT novamente

