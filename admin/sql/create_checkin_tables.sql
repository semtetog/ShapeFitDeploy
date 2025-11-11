-- Tabela para configurações de check-in
CREATE TABLE IF NOT EXISTS `sf_checkin_configs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Domingo, 1=Segunda, ..., 6=Sábado',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `fk_checkin_configs_admin` FOREIGN KEY (`admin_id`) REFERENCES `sf_admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para perguntas de check-in
CREATE TABLE IF NOT EXISTS `sf_checkin_questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('text','multiple_choice','scale') NOT NULL DEFAULT 'text',
  `options` json DEFAULT NULL COMMENT 'Para multiple_choice e scale',
  `order_index` int(11) DEFAULT 0,
  `is_required` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `config_id` (`config_id`),
  CONSTRAINT `fk_checkin_questions_config` FOREIGN KEY (`config_id`) REFERENCES `sf_checkin_configs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para distribuição de check-in (grupos e usuários)
CREATE TABLE IF NOT EXISTS `sf_checkin_distribution` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_id` int(11) NOT NULL,
  `target_type` enum('group','user') NOT NULL,
  `target_id` int(11) NOT NULL COMMENT 'ID do grupo ou usuário',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_config_target` (`config_id`,`target_type`,`target_id`),
  KEY `config_id` (`config_id`),
  CONSTRAINT `fk_checkin_distribution_config` FOREIGN KEY (`config_id`) REFERENCES `sf_checkin_configs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para respostas dos usuários
CREATE TABLE IF NOT EXISTS `sf_checkin_responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(11) NOT NULL,
  `response_text` text DEFAULT NULL,
  `response_value` varchar(255) DEFAULT NULL COMMENT 'Para multiple_choice e scale',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `config_id` (`config_id`),
  KEY `user_id` (`user_id`),
  KEY `question_id` (`question_id`),
  KEY `submitted_at` (`submitted_at`),
  CONSTRAINT `fk_checkin_responses_config` FOREIGN KEY (`config_id`) REFERENCES `sf_checkin_configs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_checkin_responses_user` FOREIGN KEY (`user_id`) REFERENCES `sf_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_checkin_responses_question` FOREIGN KEY (`question_id`) REFERENCES `sf_checkin_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para rastrear quais check-ins foram disponibilizados para cada usuário
CREATE TABLE IF NOT EXISTS `sf_checkin_availability` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `week_date` date NOT NULL COMMENT 'Data da semana (sempre domingo)',
  `is_available` tinyint(1) DEFAULT 1,
  `is_completed` tinyint(1) DEFAULT 0,
  `available_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_config_week` (`user_id`,`config_id`,`week_date`),
  KEY `config_id` (`config_id`),
  KEY `user_id` (`user_id`),
  KEY `week_date` (`week_date`),
  CONSTRAINT `fk_checkin_availability_config` FOREIGN KEY (`config_id`) REFERENCES `sf_checkin_configs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_checkin_availability_user` FOREIGN KEY (`user_id`) REFERENCES `sf_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

