-- Script para adicionar coluna video_title na tabela sf_member_content
-- Execute este SQL no phpMyAdmin

-- IMPORTANTE: Se a coluna já existir, você verá um erro. Isso é normal, ignore e continue.

-- ============================================
-- PASSO 1: Adicionar coluna video_title
-- ============================================
-- Se der erro de coluna já existir, ignore e vá para o PASSO 2
ALTER TABLE `sf_member_content` 
ADD COLUMN `video_title` VARCHAR(255) NULL 
AFTER `content_text`;

-- ============================================
-- VERIFICAÇÃO (Opcional - apenas para confirmar)
-- ============================================
-- Execute este comando para ver a estrutura da tabela:
-- DESCRIBE `sf_member_content`;

