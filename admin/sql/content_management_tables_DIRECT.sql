-- SQL DIRETO para criar/corrigir tabelas de conteúdo
-- Execute este SQL no phpMyAdmin
-- IMPORTANTE: Se alguma coluna já existir, você verá um erro. Isso é normal, ignore e continue.

-- 1. Criar tabela completa (se não existir)
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

-- 2. Se a tabela já existe, execute os comandos abaixo UM POR VEZ
-- Se algum der erro de coluna já existir, ignore e continue com o próximo

-- Adicionar file_path (ignore erro se já existir)
ALTER TABLE `sf_member_content` ADD COLUMN `file_path` VARCHAR(500) DEFAULT NULL AFTER `content_type`;

-- Adicionar file_name (ignore erro se já existir)
ALTER TABLE `sf_member_content` ADD COLUMN `file_name` VARCHAR(255) DEFAULT NULL AFTER `file_path`;

-- Adicionar file_size (ignore erro se já existir)
ALTER TABLE `sf_member_content` ADD COLUMN `file_size` INT(11) DEFAULT NULL AFTER `file_name`;

-- Adicionar mime_type (ignore erro se já existir)
ALTER TABLE `sf_member_content` ADD COLUMN `mime_type` VARCHAR(100) DEFAULT NULL AFTER `file_size`;

-- Adicionar status (ignore erro se já existir)
ALTER TABLE `sf_member_content` ADD COLUMN `status` ENUM('active', 'inactive', 'draft') DEFAULT 'active' AFTER `target_id`;

-- Adicionar índice em status (ignore erro se já existir)
ALTER TABLE `sf_member_content` ADD INDEX `status` (`status`);

-- 3. Criar tabela de relacionamento com categorias
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

