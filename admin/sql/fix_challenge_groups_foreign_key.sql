-- Corrigir constraint de foreign key na tabela sf_challenge_groups
-- A constraint atual referencia sf_users(id), mas deve referenciar sf_admins(id)
-- 
-- IMPORTANTE: Execute este script no banco de dados para corrigir a constraint
-- O erro ocorre porque $_SESSION['admin_id'] retorna o ID de sf_admins, não de sf_users

-- Passo 1: Verificar qual é o nome exato da constraint
-- Execute no MySQL: 
-- SHOW CREATE TABLE sf_challenge_groups;
-- Procure por CONSTRAINT e copie o nome exato (pode ser diferente de sf_challenge_groups_ibfk_1)

-- Passo 2: Remover a constraint existente (substitua 'NOME_DA_CONSTRAINT' pelo nome real)
-- ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY `NOME_DA_CONSTRAINT`;

-- Passo 3: Adicionar nova constraint que referencia sf_admins
ALTER TABLE `sf_challenge_groups`
ADD CONSTRAINT `sf_challenge_groups_ibfk_1` 
FOREIGN KEY (`created_by`) REFERENCES `sf_admins`(`id`) ON DELETE CASCADE;

-- Se a constraint já existir com esse nome, você pode precisar removê-la primeiro:
-- ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY `sf_challenge_groups_ibfk_1`;
-- Depois execute o ALTER TABLE acima novamente.

