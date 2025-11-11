-- Tabela para metas padrão dos grupos de usuários
CREATE TABLE IF NOT EXISTS `sf_user_group_goals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  
  -- Metas Nutricionais
  `target_kcal` INT DEFAULT NULL COMMENT 'Meta de calorias diárias',
  `target_protein_g` DECIMAL(7,2) DEFAULT NULL COMMENT 'Meta de proteínas (g)',
  `target_carbs_g` DECIMAL(7,2) DEFAULT NULL COMMENT 'Meta de carboidratos (g)',
  `target_fat_g` DECIMAL(7,2) DEFAULT NULL COMMENT 'Meta de gorduras (g)',
  `target_water_ml` INT DEFAULT NULL COMMENT 'Meta de água (ml)',
  `target_water_cups` INT DEFAULT NULL COMMENT 'Meta de água (copos)',
  
  -- Metas de Atividade
  `target_steps_daily` INT DEFAULT NULL COMMENT 'Meta de passos diários',
  `target_exercise_minutes` INT DEFAULT NULL COMMENT 'Meta de exercício (minutos/dia)',
  `target_workout_hours_weekly` DECIMAL(4,2) DEFAULT NULL COMMENT 'Meta de horas de treino semanal',
  
  -- Metas de Sono
  `target_sleep_hours` DECIMAL(4,2) DEFAULT NULL COMMENT 'Meta de horas de sono diárias',
  
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_group_goals` (`group_id`),
  KEY `group_id` (`group_id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `fk_group_goals_group` FOREIGN KEY (`group_id`) REFERENCES `sf_user_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_group_goals_admin` FOREIGN KEY (`admin_id`) REFERENCES `sf_admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Metas padrão dos grupos de usuários';

