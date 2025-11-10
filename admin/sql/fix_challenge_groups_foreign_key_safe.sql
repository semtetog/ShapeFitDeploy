-- Script SEGURO para corrigir constraint de foreign key na tabela sf_challenge_groups
-- Este script primeiro verifica e depois corrige a constraint

-- Opção 1: Se você souber o nome exato da constraint (verifique com SHOW CREATE TABLE)
-- Substitua 'NOME_DA_CONSTRAINT_AQUI' pelo nome real da constraint

SET @constraint_name = (
    SELECT CONSTRAINT_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'sf_challenge_groups'
    AND COLUMN_NAME = 'created_by'
    AND REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 1
);

-- Remover constraint existente (se existir)
SET @sql = CONCAT('ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY `', @constraint_name, '`');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar nova constraint que referencia sf_admins
ALTER TABLE `sf_challenge_groups`
ADD CONSTRAINT `sf_challenge_groups_created_by_fk` 
FOREIGN KEY (`created_by`) REFERENCES `sf_admins`(`id`) ON DELETE CASCADE;

