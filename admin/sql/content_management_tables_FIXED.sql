-- SQL CORRIGIDO para criar/corrigir tabelas de conteúdo
-- Execute este SQL no phpMyAdmin
-- IMPORTANTE: Execute os comandos UM POR VEZ. Se algum der erro de coluna já existir, ignore e continue.

-- ============================================
-- PASSO 1: Criar tabela completa (se não existir)
-- ============================================
CREATE TABLE IF NOT EXISTS `sf_member_content` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `admin_id` INT(11) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `content_type` ENUM('chef', 'supplements', 'videos', 'articles', 'pdf') NOT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `file_name` VARCHAR(255) DEFAULT NULL,
    `file_size` INT(11) DEFAULT NULL,
    `mime_type` VARCHAR(100) DEFAULT NULL,
    `content_text` LONGTEXT DEFAULT NULL,
    `thumbnail_url` VARCHAR(500) DEFAULT NULL,
    `target_type` ENUM('all', 'user', 'group') DEFAULT 'all',
    `target_id` INT(11) DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'draft') DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `admin_id` (`admin_id`),
    KEY `content_type` (`content_type`),
    KEY `status` (`status`),
    KEY `target_type` (`target_type`, `target_id`),
    CONSTRAINT `fk_member_content_admin` FOREIGN KEY (`admin_id`) REFERENCES `sf_admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PASSO 2: Se a tabela já existe, adicionar colunas que faltam
-- Execute cada comando separadamente. Se der erro de coluna já existir, ignore.
-- ============================================

-- 2.1. Adicionar file_path (se não existir)
ALTER TABLE `sf_member_content` ADD COLUMN `file_path` VARCHAR(500) DEFAULT NULL;

-- 2.2. Adicionar file_name (se não existir)
ALTER TABLE `sf_member_content` ADD COLUMN `file_name` VARCHAR(255) DEFAULT NULL;

-- 2.3. Adicionar file_size (se não existir)
ALTER TABLE `sf_member_content` ADD COLUMN `file_size` INT(11) DEFAULT NULL;

-- 2.4. Adicionar mime_type (se não existir)
ALTER TABLE `sf_member_content` ADD COLUMN `mime_type` VARCHAR(100) DEFAULT NULL;

-- 2.5. Adicionar content_text (se não existir)
ALTER TABLE `sf_member_content` ADD COLUMN `content_text` LONGTEXT DEFAULT NULL;

-- 2.6. Adicionar thumbnail_url (se não existir)
ALTER TABLE `sf_member_content` ADD COLUMN `thumbnail_url` VARCHAR(500) DEFAULT NULL;

-- 2.7. Adicionar target_type (se não existir)
ALTER TABLE `sf_member_content` ADD COLUMN `target_type` ENUM('all', 'user', 'group') DEFAULT 'all';

-- 2.8. Adicionar target_id (se não existir)
ALTER TABLE `sf_member_content` ADD COLUMN `target_id` INT(11) DEFAULT NULL;

-- 2.9. Adicionar status (se não existir) - SEM AFTER, vai para o final
ALTER TABLE `sf_member_content` ADD COLUMN `status` ENUM('active', 'inactive', 'draft') DEFAULT 'active';

-- 2.10. Adicionar índice em status (se não existir)
ALTER TABLE `sf_member_content` ADD INDEX `status` (`status`);

-- 2.11. Adicionar índice em target_type e target_id (se não existir)
ALTER TABLE `sf_member_content` ADD INDEX `target_type` (`target_type`, `target_id`);

-- ============================================
-- PASSO 3: Criar tabela de relacionamento com categorias
-- ============================================
CREATE TABLE IF NOT EXISTS `sf_content_category_relations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `content_id` INT(11) NOT NULL,
    `category_id` INT(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_content_category` (`content_id`, `category_id`),
    KEY `content_id` (`content_id`),
    KEY `category_id` (`category_id`),
    CONSTRAINT `fk_content_category_content` FOREIGN KEY (`content_id`) REFERENCES `sf_member_content` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_content_category_category` FOREIGN KEY (`category_id`) REFERENCES `sf_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


