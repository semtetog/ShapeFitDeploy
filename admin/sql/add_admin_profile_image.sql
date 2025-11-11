-- Script para adicionar coluna profile_image_filename na tabela sf_admins
-- Execute este SQL no phpMyAdmin

-- Verificar se a coluna já existe
SELECT COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'sf_admins' 
  AND COLUMN_NAME = 'profile_image_filename';

-- Se não existir, adicionar a coluna
ALTER TABLE `sf_admins` 
ADD COLUMN `profile_image_filename` VARCHAR(255) NULL 
AFTER `password_hash`;

-- Verificar se a coluna full_name existe (pode ser name em alguns casos)
SELECT COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'sf_admins' 
  AND COLUMN_NAME IN ('full_name', 'name');

-- Se não tiver full_name mas tiver name, criar uma coluna full_name ou usar name
-- (Isso depende da estrutura atual da tabela)

