-- Query para encontrar o nome da constraint diretamente
-- Execute esta query para ver apenas o nome da constraint:

SELECT 
    CONSTRAINT_NAME as 'Nome da Constraint'
FROM 
    information_schema.KEY_COLUMN_USAGE 
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'sf_challenge_groups'
    AND COLUMN_NAME = 'created_by'
    AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Se a query acima retornar um resultado, copie o nome da constraint
-- Se não retornar nada, significa que a constraint não existe ainda (pode pular o PASSO 2)

