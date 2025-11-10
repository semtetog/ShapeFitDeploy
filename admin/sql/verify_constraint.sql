-- ============================================================
-- VERIFICAR SE A CONSTRAINT FOI CRIADA COM SUCESSO
-- ============================================================

-- Execute esta query para verificar se a constraint existe:
SELECT 
    CONSTRAINT_NAME as 'Nome da Constraint',
    REFERENCED_TABLE_NAME as 'Tabela Referenciada',
    REFERENCED_COLUMN_NAME as 'Coluna Referenciada',
    TABLE_NAME as 'Tabela Local',
    COLUMN_NAME as 'Coluna Local'
FROM 
    information_schema.KEY_COLUMN_USAGE 
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'sf_challenge_groups'
    AND COLUMN_NAME = 'created_by'
    AND REFERENCED_TABLE_NAME = 'sf_admins';

-- Se retornar um resultado mostrando:
-- sf_challenge_groups_created_by_fk | sf_admins | id
-- Então a constraint foi criada com sucesso! ✅

-- ============================================================
-- TESTE FINAL: Tentar criar um desafio no sistema
-- Se funcionar, está tudo certo!
-- ============================================================

