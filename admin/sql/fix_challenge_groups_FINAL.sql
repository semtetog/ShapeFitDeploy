-- ============================================================
-- SCRIPT FINAL PARA CORRIGIR FOREIGN KEY DE sf_challenge_groups
-- Baseado na análise do bancoref.sql
-- ============================================================

-- IMPORTANTE: Execute os comandos abaixo UM POR VEZ no phpMyAdmin

-- PASSO 1: Verificar se existe constraint e qual é o nome
-- Execute esta query para descobrir o nome da constraint:
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

-- Se a query acima retornar um resultado, anote o CONSTRAINT_NAME
-- Se não retornar nada, a constraint não existe (pule para o PASSO 3)


-- PASSO 2: Remover constraint existente (execute apenas se o PASSO 1 retornou um nome)
-- Substitua 'NOME_DA_CONSTRAINT' pelo nome encontrado no PASSO 1
-- Nomes comuns podem ser: sf_challenge_groups_ibfk_1, fk_challenge_groups_created_by, etc.

-- Tente este primeiro (nome mais comum):
ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY `sf_challenge_groups_ibfk_1`;

-- Se der erro dizendo que não existe, tente estes outros nomes comuns:
-- ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY `fk_challenge_groups_created_by`;
-- ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY `idx_challenge_groups_created_by`;


-- PASSO 3: Adicionar nova constraint que referencia sf_admins (SEMPRE execute este)
ALTER TABLE `sf_challenge_groups`
ADD CONSTRAINT `sf_challenge_groups_created_by_fk` 
FOREIGN KEY (`created_by`) REFERENCES `sf_admins`(`id`) ON DELETE CASCADE;


-- ============================================================
-- VERIFICAÇÃO: Execute esta query para confirmar que a constraint foi criada:
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
    AND REFERENCED_TABLE_NAME = 'sf_admins';

-- Deve retornar: sf_challenge_groups_created_by_fk | sf_admins | id
-- ============================================================

