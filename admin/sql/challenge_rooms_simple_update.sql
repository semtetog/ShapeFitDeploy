-- SQL simples para adicionar funcionalidades ao sistema existente
-- Baseado na estrutura do bancao4.sql

-- Adicionar colunas para metas personalizadas (se não existirem)
ALTER TABLE `sf_challenge_rooms` 
ADD COLUMN IF NOT EXISTS `goals` JSON DEFAULT NULL COMMENT 'Metas personalizadas do desafio' AFTER `challenge_type`,
ADD COLUMN IF NOT EXISTS `rewards` JSON DEFAULT NULL COMMENT 'Recompensas para vencedores' AFTER `goals`;

-- Adicionar colunas para pontos totais (se não existirem)
ALTER TABLE `sf_challenge_room_members` 
ADD COLUMN IF NOT EXISTS `total_points` INT DEFAULT 0 COMMENT 'Total de pontos acumulados' AFTER `status`,
ADD COLUMN IF NOT EXISTS `last_activity_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Última atividade' AFTER `total_points`;

-- Criar tabela de progresso diário detalhado (se não existir)
CREATE TABLE IF NOT EXISTS `sf_challenge_daily_progress` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `challenge_room_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `steps_count` int DEFAULT 0,
  `exercise_minutes` int DEFAULT 0,
  `water_cups` int DEFAULT 0,
  `calories_consumed` decimal(10,2) DEFAULT 0,
  `points_earned` int DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_room_user_date` (`challenge_room_id`, `user_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar tabela de conquistas (se não existir)
CREATE TABLE IF NOT EXISTS `sf_challenge_achievements` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `challenge_room_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `achievement_type` enum('first_place','second_place','third_place','most_active','most_consistent','most_improved') NOT NULL,
  `points_awarded` int DEFAULT 0,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
