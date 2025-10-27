-- Script para criar apenas as tabelas que NÃO existem no banco de dados
-- A tabela sf_diet_plans já existe, então vamos criar apenas as tabelas relacionadas

-- Tabela para refeições do plano alimentar
CREATE TABLE IF NOT EXISTS `sf_diet_plan_meals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `diet_plan_id` int(11) NOT NULL,
  `meal_name` varchar(100) NOT NULL,
  `meal_time` time DEFAULT NULL,
  `calories` int(11) NOT NULL,
  `protein_g` decimal(8,2) NOT NULL,
  `carbs_g` decimal(8,2) NOT NULL,
  `fat_g` decimal(8,2) NOT NULL,
  `description` text DEFAULT NULL,
  `order_index` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `diet_plan_id` (`diet_plan_id`),
  CONSTRAINT `fk_diet_plan_meals_plan` FOREIGN KEY (`diet_plan_id`) REFERENCES `sf_diet_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para alimentos específicos do plano
CREATE TABLE IF NOT EXISTS `sf_diet_plan_foods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `meal_id` int(11) NOT NULL,
  `food_name` varchar(255) NOT NULL,
  `quantity` decimal(8,2) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `calories` int(11) NOT NULL,
  `protein_g` decimal(8,2) NOT NULL,
  `carbs_g` decimal(8,2) NOT NULL,
  `fat_g` decimal(8,2) NOT NULL,
  `order_index` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `meal_id` (`meal_id`),
  CONSTRAINT `fk_diet_plan_foods_meal` FOREIGN KEY (`meal_id`) REFERENCES `sf_diet_plan_meals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para grupos de usuários
CREATE TABLE IF NOT EXISTS `sf_user_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `group_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#ff6b00',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `fk_user_groups_admin` FOREIGN KEY (`admin_id`) REFERENCES `sf_admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para membros dos grupos
CREATE TABLE IF NOT EXISTS `sf_user_group_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_group_user` (`group_id`, `user_id`),
  KEY `group_id` (`group_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_group_members_group` FOREIGN KEY (`group_id`) REFERENCES `sf_user_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_group_members_user` FOREIGN KEY (`user_id`) REFERENCES `sf_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para conteúdos da área de membros
CREATE TABLE IF NOT EXISTS `sf_member_content` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content_type` enum('video','article','recipe','supplement','workout','other') NOT NULL,
  `content_url` varchar(500) DEFAULT NULL,
  `content_text` longtext DEFAULT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `is_premium` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `content_type` (`content_type`),
  CONSTRAINT `fk_member_content_admin` FOREIGN KEY (`admin_id`) REFERENCES `sf_admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para distribuição de conteúdo por usuário/grupo
CREATE TABLE IF NOT EXISTS `sf_content_distribution` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_id` int(11) NOT NULL,
  `target_type` enum('user','group') NOT NULL,
  `target_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_by` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `content_id` (`content_id`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `fk_content_distribution_content` FOREIGN KEY (`content_id`) REFERENCES `sf_member_content` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_content_distribution_admin` FOREIGN KEY (`assigned_by`) REFERENCES `sf_admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para desafios/grupos de desafio
CREATE TABLE IF NOT EXISTS `sf_challenges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `challenge_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `challenge_type` enum('steps','exercise','hydration','nutrition','weight','custom') NOT NULL,
  `target_value` decimal(10,2) DEFAULT NULL,
  `target_unit` varchar(50) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `fk_challenges_admin` FOREIGN KEY (`admin_id`) REFERENCES `sf_admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para participantes dos desafios
CREATE TABLE IF NOT EXISTS `sf_challenge_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `challenge_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `current_progress` decimal(10,2) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_challenge_user` (`challenge_id`, `user_id`),
  KEY `challenge_id` (`challenge_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_challenge_participants_challenge` FOREIGN KEY (`challenge_id`) REFERENCES `sf_challenges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_challenge_participants_user` FOREIGN KEY (`user_id`) REFERENCES `sf_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar índices para performance
CREATE INDEX IF NOT EXISTS `idx_diet_plan_meals_plan_id` ON `sf_diet_plan_meals` (`diet_plan_id`);
CREATE INDEX IF NOT EXISTS `idx_diet_plan_foods_meal_id` ON `sf_diet_plan_foods` (`meal_id`);
CREATE INDEX IF NOT EXISTS `idx_user_groups_admin_id` ON `sf_user_groups` (`admin_id`);
CREATE INDEX IF NOT EXISTS `idx_member_content_admin_id` ON `sf_member_content` (`admin_id`);
CREATE INDEX IF NOT EXISTS `idx_member_content_type` ON `sf_member_content` (`content_type`);
CREATE INDEX IF NOT EXISTS `idx_content_distribution_content_id` ON `sf_content_distribution` (`content_id`);
CREATE INDEX IF NOT EXISTS `idx_challenges_admin_id` ON `sf_challenges` (`admin_id`);
CREATE INDEX IF NOT EXISTS `idx_challenge_participants_challenge_id` ON `sf_challenge_participants` (`challenge_id`);




