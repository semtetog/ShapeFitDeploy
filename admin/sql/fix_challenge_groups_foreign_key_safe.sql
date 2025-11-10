-- Script SEGURO para corrigir constraint de foreign key na tabela sf_challenge_groups
-- Este script primeiro verifica e depois corrige a constraint

-- Passo 1: Verificar e remover constraint existente (se existir)
-- Primeiro, vamos tentar encontrar o nome da constraint
SELECT CONSTRAINT_NAME 
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'sf_challenge_groups'
AND COLUMN_NAME = 'created_by'
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Passo 2: Se a query acima retornou um nome de constraint, remova manualmente:
-- ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY `NOME_DA_CONSTRAINT_ENCONTRADA`;

-- Passo 3: Remover todas as constraints relacionadas a created_by (múltiplas tentativas)
-- Tente estes comandos um por um até que um funcione (ou nenhum dê erro se já não existir):

-- Tentativa 1: Nome padrão comum
SET @sql1 = 'ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY IF EXISTS `sf_challenge_groups_ibfk_1`';
-- Se o MySQL não suportar IF EXISTS, use apenas:
-- ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY `sf_challenge_groups_ibfk_1`;

-- Tentativa 2: Outro nome comum
SET @sql2 = 'ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY IF EXISTS `sf_challenge_groups_created_by_fk`';

-- Passo 4: Adicionar nova constraint que referencia sf_admins
ALTER TABLE `sf_challenge_groups`
ADD CONSTRAINT `sf_challenge_groups_created_by_fk` 
FOREIGN KEY (`created_by`) REFERENCES `sf_admins`(`id`) ON DELETE CASCADE;

