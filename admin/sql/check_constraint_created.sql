-- ============================================================
-- VERIFICAR SE A CONSTRAINT FOI CRIADA COM SUCESSO
-- ============================================================

-- Execute esta query para ver se a constraint existe:
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
    AND REFERENCED_TABLE_NAME = 'sf_admins';

-- ============================================================
-- Se retornar um resultado mostrando:
-- Nome da Constraint | Tabela Referenciada | Coluna Referenciada
-- (algum nome)       | sf_admins          | id
-- 
-- Então a constraint foi criada com sucesso! ✅
-- ============================================================

