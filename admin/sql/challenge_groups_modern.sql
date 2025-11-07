-- Sistema moderno de Grupos de Desafio
-- Ajustado para a estrutura real do banco de dados na Hostinger
-- Baseado em banconovo.sql

-- NOTA: As tabelas sf_challenge_groups, sf_challenge_goals e sf_challenge_group_members já existem
-- Este SQL apenas adiciona índices e cria a tabela de progresso diário

-- Adicionar índices na tabela sf_challenge_group_members (se não existirem)
CREATE INDEX IF NOT EXISTS `idx_challenge_group_members_status` ON `sf_challenge_group_members`(`status`);
CREATE INDEX IF NOT EXISTS `idx_challenge_group_members_group` ON `sf_challenge_group_members`(`group_id`);
CREATE INDEX IF NOT EXISTS `idx_challenge_group_members_user` ON `sf_challenge_group_members`(`user_id`);

-- Criar tabela de progresso diário específica para grupos de desafio
-- (Diferente de challenge_rooms que usa challenge_room_id)
CREATE TABLE IF NOT EXISTS `sf_challenge_group_daily_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `challenge_group_id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `calories_consumed` decimal(10,2) DEFAULT 0.00,
  `water_ml` decimal(10,2) DEFAULT 0.00,
  `exercise_minutes` int(11) DEFAULT 0,
  `sleep_hours` decimal(4,2) DEFAULT 0.00,
  `steps_count` int(11) DEFAULT 0,
  `points_earned` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_group_user_date` (`challenge_group_id`, `user_id`, `date`),
  KEY `idx_challenge_group_progress_date` (`date`),
  KEY `idx_challenge_group_progress_user_date` (`user_id`, `date`),
  CONSTRAINT `fk_challenge_group_progress_group` FOREIGN KEY (`challenge_group_id`) REFERENCES `sf_challenge_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_challenge_group_progress_user` FOREIGN KEY (`user_id`) REFERENCES `sf_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
