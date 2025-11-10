-- Script SIMPLES para corrigir constraint de foreign key
-- Execute os comandos abaixo UM POR VEZ no phpMyAdmin ou MySQL

-- PASSO 1: Verificar qual constraint existe atualmente
-- Execute esta query para ver o nome da constraint:
SHOW CREATE TABLE sf_challenge_groups;

-- PASSO 2: Copie o nome da constraint que aparece no resultado (ex: sf_challenge_groups_ibfk_1)
-- e execute o comando abaixo substituindo 'NOME_DA_CONSTRAINT' pelo nome real:

-- ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY `NOME_DA_CONSTRAINT`;

-- PASSO 3: Se você não souber o nome, tente remover com um destes nomes comuns:
-- (Execute um por vez até que um funcione, ou ignore se der erro dizendo que não existe)

ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY `sf_challenge_groups_ibfk_1`;

-- Se o comando acima deu erro dizendo que a constraint não existe, tente este:
-- ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY `sf_challenge_groups_created_by_fk`;

-- PASSO 4: Adicionar nova constraint que referencia sf_admins (sempre execute este)
ALTER TABLE `sf_challenge_groups`
ADD CONSTRAINT `sf_challenge_groups_created_by_fk` 
FOREIGN KEY (`created_by`) REFERENCES `sf_admins`(`id`) ON DELETE CASCADE;

