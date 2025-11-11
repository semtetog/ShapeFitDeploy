-- Adicionar coluna points_breakdown para armazenar detalhamento de pontos
ALTER TABLE `sf_challenge_group_daily_progress`
ADD COLUMN `points_breakdown` TEXT NULL AFTER `points_earned`;

-- Adicionar índice para melhorar performance
ALTER TABLE `sf_challenge_group_daily_progress`
ADD INDEX `idx_challenge_progress_user_group` (`user_id`, `challenge_group_id`);


