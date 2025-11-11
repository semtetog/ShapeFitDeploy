-- Tabela para conteúdo da área de membros (estrutura unificada)
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

-- Tabela para relacionar conteúdo com categorias
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

-- Tabela para visualizações de conteúdo
CREATE TABLE IF NOT EXISTS `sf_content_views` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `content_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `viewed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `content_id` (`content_id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `fk_content_views_content` FOREIGN KEY (`content_id`) REFERENCES `sf_member_content` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_content_views_user` FOREIGN KEY (`user_id`) REFERENCES `sf_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificar se as colunas existem e adicionar se não existirem
SET @dbname = DATABASE();
SET @tablename = 'sf_member_content';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @dbname
        AND TABLE_NAME = @tablename
        AND COLUMN_NAME = 'file_name') > 0,
    'SELECT 1',
    'ALTER TABLE sf_member_content ADD COLUMN file_name VARCHAR(255) DEFAULT NULL AFTER file_path'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @dbname
        AND TABLE_NAME = @tablename
        AND COLUMN_NAME = 'file_size') > 0,
    'SELECT 1',
    'ALTER TABLE sf_member_content ADD COLUMN file_size INT(11) DEFAULT NULL AFTER file_name'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @dbname
        AND TABLE_NAME = @tablename
        AND COLUMN_NAME = 'mime_type') > 0,
    'SELECT 1',
    'ALTER TABLE sf_member_content ADD COLUMN mime_type VARCHAR(100) DEFAULT NULL AFTER file_size'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;


