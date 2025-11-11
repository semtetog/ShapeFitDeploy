-- Script para adicionar coluna profile_image_filename na tabela sf_admins
-- Execute este SQL no phpMyAdmin

-- IMPORTANTE: Se a coluna já existir, você verá um erro. Isso é normal, ignore e continue.

-- ============================================
-- PASSO 1: Adicionar coluna profile_image_filename
-- ============================================
-- Se der erro de coluna já existir, ignore e vá para o PASSO 2
ALTER TABLE `sf_admins` 
ADD COLUMN `profile_image_filename` VARCHAR(255) NULL 
AFTER `password_hash`;

-- ============================================
-- PASSO 2: Verificar se precisa adicionar coluna full_name
-- ============================================
-- Se sua tabela já tem 'full_name', ignore este passo
-- Se sua tabela só tem 'name' e você quer usar 'full_name', descomente a linha abaixo:
-- ALTER TABLE `sf_admins` ADD COLUMN `full_name` VARCHAR(255) NULL AFTER `name`;

-- ============================================
-- VERIFICAÇÃO (Opcional - apenas para confirmar)
-- ============================================
-- Execute este comando para ver a estrutura da tabela:
-- DESCRIBE `sf_admins`;

