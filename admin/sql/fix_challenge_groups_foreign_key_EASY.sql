-- ============================================================
-- CORREÇÃO SIMPLES - Execute os comandos ABAIXO UM POR VEZ
-- ============================================================

-- PASSO 1: Descobrir o nome da constraint atual
-- Execute este comando e copie o nome da constraint que aparece:
SHOW CREATE TABLE sf_challenge_groups;

-- Você verá algo como:
-- CONSTRAINT `sf_challenge_groups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `sf_users` (`id`)
-- Copie o nome: sf_challenge_groups_ibfk_1


-- PASSO 2: Remover a constraint antiga
-- Substitua 'sf_challenge_groups_ibfk_1' pelo nome que você encontrou no PASSO 1
ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY `sf_challenge_groups_ibfk_1`;


-- PASSO 3: Adicionar a nova constraint (sempre execute este)
ALTER TABLE `sf_challenge_groups`
ADD CONSTRAINT `sf_challenge_groups_created_by_fk` 
FOREIGN KEY (`created_by`) REFERENCES `sf_admins`(`id`) ON DELETE CASCADE;


-- ============================================================
-- PRONTO! Agora a constraint referencia sf_admins corretamente
-- ============================================================

