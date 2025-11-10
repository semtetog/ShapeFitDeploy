-- ============================================================
-- RESOLVER CONFLITO DE NOME - Execute os comandos nesta ordem
-- ============================================================

-- PASSO 1: Verificar se existe índice ou constraint com nome similar
SELECT 
    CONSTRAINT_NAME,
    INDEX_NAME,
    TABLE_NAME,
    COLUMN_NAME
FROM 
    information_schema.STATISTICS 
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'sf_challenge_groups'
    AND INDEX_NAME LIKE '%created_by%';

-- PASSO 2: Verificar todas as constraints da tabela
SELECT 
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    TABLE_NAME
FROM 
    information_schema.TABLE_CONSTRAINTS 
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'sf_challenge_groups';

-- PASSO 3: Tentar adicionar constraint com nome diferente
ALTER TABLE `sf_challenge_groups`
ADD CONSTRAINT `fk_challenge_groups_created_by` 
FOREIGN KEY (`created_by`) REFERENCES `sf_admins`(`id`) ON DELETE CASCADE;

-- ============================================================
-- Se ainda der erro, vamos tentar sem especificar o nome da constraint
-- (o MySQL vai gerar um nome automaticamente)
-- ============================================================

-- PASSO 4: Tentar sem nome (deixa o MySQL gerar automaticamente)
-- ALTER TABLE `sf_challenge_groups`
-- ADD FOREIGN KEY (`created_by`) REFERENCES `sf_admins`(`id`) ON DELETE CASCADE;

