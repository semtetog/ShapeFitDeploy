-- ============================================================
-- CORREÇÃO RÁPIDA - Execute os comandos nesta ordem
-- ============================================================

-- COMANDO 1: Tentar remover constraint antiga (ignore erro se não existir)
ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY `sf_challenge_groups_ibfk_1`;

-- COMANDO 2: Adicionar nova constraint (SEMPRE execute este)
ALTER TABLE `sf_challenge_groups`
ADD CONSTRAINT `sf_challenge_groups_created_by_fk` 
FOREIGN KEY (`created_by`) REFERENCES `sf_admins`(`id`) ON DELETE CASCADE;

-- ============================================================
-- PRONTO! Agora teste criar um desafio
-- ============================================================

