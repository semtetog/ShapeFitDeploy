-- Atualização do sistema de salas de desafio
-- Baseado na estrutura existente do bancao4.sql

-- Adicionar colunas para metas personalizadas na tabela sf_challenge_rooms
ALTER TABLE `sf_challenge_rooms` 
ADD COLUMN `goals` JSON DEFAULT NULL COMMENT 'Metas personalizadas do desafio (passos, exercício, hidratação, etc.)' AFTER `challenge_type`,
ADD COLUMN `rewards` JSON DEFAULT NULL COMMENT 'Recompensas para os vencedores' AFTER `goals`,
ADD COLUMN `admin_id` INT(10) UNSIGNED DEFAULT NULL COMMENT 'ID do nutricionista administrador' AFTER `created_by;

-- Adicionar colunas para pontos totais na tabela sf_challenge_room_members
ALTER TABLE `sf_challenge_room_members` 
ADD COLUMN `total_points` INT DEFAULT 0 COMMENT 'Total de pontos acumulados pelo membro' AFTER `status`,
ADD COLUMN `last_activity_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Última atividade do membro' AFTER `total_points`;

-- Criar tabela para progresso diário detalhado (se não existir)
CREATE TABLE IF NOT EXISTS `sf_challenge_daily_progress` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `challenge_room_id` int(10) UNSIGNED NOT NULL COMMENT 'ID da sala de desafio',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'ID do usuário',
  `date` date NOT NULL COMMENT 'Data do progresso',
  `steps_count` int DEFAULT 0 COMMENT 'Número de passos',
  `exercise_minutes` int DEFAULT 0 COMMENT 'Minutos de exercício',
  `water_cups` int DEFAULT 0 COMMENT 'Copos de água consumidos',
  `calories_consumed` decimal(10,2) DEFAULT 0 COMMENT 'Calorias consumidas',
  `points_earned` int DEFAULT 0 COMMENT 'Pontos ganhos no dia',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_room_user_date` (`challenge_room_id`, `user_id`, `date`),
  KEY `idx_challenge_room_id` (`challenge_room_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Progresso diário detalhado dos membros';

-- Criar tabela para conquistas/medalhas (se não existir)
CREATE TABLE IF NOT EXISTS `sf_challenge_achievements` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `challenge_room_id` int(10) UNSIGNED NOT NULL COMMENT 'ID da sala de desafio',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'ID do usuário',
  `achievement_type` enum('first_place','second_place','third_place','most_active','most_consistent','most_improved') NOT NULL COMMENT 'Tipo de conquista',
  `points_awarded` int DEFAULT 0 COMMENT 'Pontos concedidos pela conquista',
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Data da conquista',
  PRIMARY KEY (`id`),
  KEY `idx_challenge_room_id` (`challenge_room_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_achievement_type` (`achievement_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Conquistas e medalhas dos membros';

-- Adicionar chaves estrangeiras se não existirem
-- (Verificar se as tabelas existem antes de adicionar)

-- Atualizar a tabela sf_challenge_rooms para incluir referência ao admin
-- (Isso será feito apenas se a coluna admin_id for adicionada com sucesso)
