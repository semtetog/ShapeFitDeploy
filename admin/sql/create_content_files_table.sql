-- SQL para criar tabela de múltiplos arquivos por conteúdo
-- Execute este SQL no phpMyAdmin
-- IMPORTANTE: Se a tabela já existir, você verá um erro. Isso é normal, ignore e continue.

-- Criar tabela para armazenar múltiplos arquivos por conteúdo
CREATE TABLE IF NOT EXISTS `sf_content_files` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `content_id` INT(11) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_size` INT(11) DEFAULT NULL,
    `mime_type` VARCHAR(100) DEFAULT NULL,
    `thumbnail_url` VARCHAR(500) DEFAULT NULL,
    `video_title` VARCHAR(255) DEFAULT NULL,
    `display_order` INT(11) DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `content_id` (`content_id`),
    KEY `display_order` (`content_id`, `display_order`),
    CONSTRAINT `fk_content_files_content` FOREIGN KEY (`content_id`) REFERENCES `sf_member_content` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

