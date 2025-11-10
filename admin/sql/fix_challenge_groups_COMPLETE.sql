-- ============================================================
-- CORREÇÃO COMPLETA - Execute os comandos nesta ordem
-- ============================================================

-- PROBLEMA IDENTIFICADO:
-- A coluna created_by é int(10) UNSIGNED
-- Mas sf_admins.id é int(11) (SIGNED)
-- MySQL não permite foreign key entre tipos incompatíveis

-- PASSO 1: Alterar o tipo da coluna created_by para corresponder a sf_admins.id
ALTER TABLE `sf_challenge_groups` 
MODIFY COLUMN `created_by` int(11) NOT NULL;

-- PASSO 2: Verificar se há dados inconsistentes (created_by que não existe em sf_admins)
-- Execute esta query para ver se há problemas:
SELECT cg.id, cg.created_by, cg.name
FROM sf_challenge_groups cg
LEFT JOIN sf_admins a ON cg.created_by = a.id
WHERE a.id IS NULL;

-- Se a query acima retornar resultados, você precisa corrigir esses dados
-- Exemplo: UPDATE sf_challenge_groups SET created_by = 1 WHERE created_by = X;


-- PASSO 3: Adicionar a constraint de foreign key
ALTER TABLE `sf_challenge_groups`
ADD CONSTRAINT `sf_challenge_groups_created_by_fk` 
FOREIGN KEY (`created_by`) REFERENCES `sf_admins`(`id`) ON DELETE CASCADE;

-- ============================================================
-- PRONTO! Agora a constraint deve funcionar
-- ============================================================

