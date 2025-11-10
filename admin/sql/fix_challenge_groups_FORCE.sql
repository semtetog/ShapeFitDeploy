-- ============================================================
-- CORREÇÃO FORÇADA - Execute os comandos nesta ordem
-- ============================================================

-- PASSO 1: Verificar e corrigir tipo de dado (garantir que está correto)
ALTER TABLE `sf_challenge_groups` 
MODIFY COLUMN `created_by` int(11) NOT NULL;

-- PASSO 2: Verificar se todos os valores de created_by existem em sf_admins
-- Se houver registros com created_by que não existe em sf_admins, vamos corrigir
UPDATE sf_challenge_groups 
SET created_by = 1 
WHERE created_by NOT IN (SELECT id FROM sf_admins);

-- PASSO 3: Tentar adicionar a constraint com nome diferente
-- Se der erro de "Duplicate key", tente o PASSO 4
ALTER TABLE `sf_challenge_groups`
ADD CONSTRAINT `fk_challenge_groups_created_by` 
FOREIGN KEY (`created_by`) REFERENCES `sf_admins`(`id`) ON DELETE CASCADE;

-- PASSO 4: Se o PASSO 3 ainda der erro, tente sem especificar nome (MySQL gera automaticamente)
-- ALTER TABLE `sf_challenge_groups`
-- ADD FOREIGN KEY (`created_by`) REFERENCES `sf_admins`(`id`) ON DELETE CASCADE;

-- ============================================================
-- Se ainda der erro, execute o script debug_constraint_issue.sql
-- para ver o que está acontecendo
-- ============================================================

