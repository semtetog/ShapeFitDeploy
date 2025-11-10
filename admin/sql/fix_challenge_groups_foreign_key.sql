-- Corrigir constraint de foreign key na tabela sf_challenge_groups
-- A constraint atual referencia sf_users(id), mas deve referenciar sf_admins(id)

-- Primeiro, remover a constraint existente
ALTER TABLE `sf_challenge_groups` 
DROP FOREIGN KEY `sf_challenge_groups_ibfk_1`;

-- Adicionar nova constraint que referencia sf_admins
ALTER TABLE `sf_challenge_groups`
ADD CONSTRAINT `sf_challenge_groups_ibfk_1` 
FOREIGN KEY (`created_by`) REFERENCES `sf_admins`(`id`) ON DELETE CASCADE;

