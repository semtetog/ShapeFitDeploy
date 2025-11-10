-- ============================================================
-- CORREÇÃO PASSO A PASSO - Execute os comandos nesta ordem
-- ============================================================

-- PASSO 1: Verificar se existe constraint
SELECT CONSTRAINT_NAME 
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'sf_challenge_groups'
AND COLUMN_NAME = 'created_by'
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Se a query acima retornou um resultado (ex: sf_challenge_groups_ibfk_1)
-- Execute o PASSO 2A abaixo
-- Se não retornou nada, pule para o PASSO 3


-- PASSO 2A: Remover constraint existente (execute apenas se o PASSO 1 retornou um nome)
-- Substitua 'sf_challenge_groups_ibfk_1' pelo nome que você encontrou no PASSO 1
ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY `sf_challenge_groups_ibfk_1`;


-- PASSO 3: Adicionar nova constraint (SEMPRE execute este)
ALTER TABLE `sf_challenge_groups`
ADD CONSTRAINT `sf_challenge_groups_created_by_fk` 
FOREIGN KEY (`created_by`) REFERENCES `sf_admins`(`id`) ON DELETE CASCADE;


-- ============================================================
-- PRONTO! Agora teste criar um desafio no sistema
-- ============================================================

