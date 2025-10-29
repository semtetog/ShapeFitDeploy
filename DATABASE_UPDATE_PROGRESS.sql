-- ====================================================
-- ATUALIZAÇÃO DO BANCO DE DADOS PARA ABA DE PROGRESSO
-- Data: 20/10/2025
-- ====================================================

-- 1. ADICIONAR NOVAS COLUNAS À TABELA sf_user_daily_tracking
-- ============================================================

ALTER TABLE `sf_user_daily_tracking` 
ADD COLUMN `steps_daily` INT DEFAULT 0 COMMENT 'Passos dados no dia',
ADD COLUMN `workout_hours` DECIMAL(4,2) DEFAULT 0.00 COMMENT 'Horas de treino (exercícios)',
ADD COLUMN `cardio_hours` DECIMAL(4,2) DEFAULT 0.00 COMMENT 'Horas de cardio',
ADD COLUMN `sleep_hours` DECIMAL(4,2) DEFAULT 0.00 COMMENT 'Horas dormidas';

-- 2. CRIAR TABELA PARA METAS DO USUÁRIO
-- ======================================

CREATE TABLE IF NOT EXISTS `sf_user_goals` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(10) UNSIGNED NOT NULL,
  `goal_type` ENUM('nutrition', 'activity', 'sleep') NOT NULL DEFAULT 'nutrition',
  
  -- Metas Nutricionais
  `target_kcal` INT DEFAULT NULL COMMENT 'Meta de calorias diárias',
  `target_protein_g` DECIMAL(7,2) DEFAULT NULL COMMENT 'Meta de proteínas (g)',
  `target_carbs_g` DECIMAL(7,2) DEFAULT NULL COMMENT 'Meta de carboidratos (g)',
  `target_fat_g` DECIMAL(7,2) DEFAULT NULL COMMENT 'Meta de gorduras (g)',
  `target_water_cups` INT DEFAULT NULL COMMENT 'Meta de água (copos)',
  
  -- Metas de Atividade
  `target_steps_daily` INT DEFAULT NULL COMMENT 'Meta de passos diários',
  `target_steps_weekly` INT DEFAULT NULL COMMENT 'Meta de passos semanais',
  `target_workout_hours_weekly` DECIMAL(4,2) DEFAULT NULL COMMENT 'Meta de horas de treino semanal',
  `target_workout_hours_monthly` DECIMAL(4,2) DEFAULT NULL COMMENT 'Meta de horas de treino mensal',
  `target_cardio_hours_weekly` DECIMAL(4,2) DEFAULT NULL COMMENT 'Meta de horas de cardio semanal',
  `target_cardio_hours_monthly` DECIMAL(4,2) DEFAULT NULL COMMENT 'Meta de horas de cardio mensal',
  
  -- Metas de Sono
  `target_sleep_hours` DECIMAL(4,2) DEFAULT NULL COMMENT 'Meta de horas de sono diárias',
  
  -- Dados do Usuário para cálculos
  `user_gender` ENUM('male', 'female') DEFAULT NULL COMMENT 'Gênero para cálculo de distância',
  `step_length_cm` DECIMAL(5,2) DEFAULT NULL COMMENT 'Comprimento médio do passo (cm)',
  
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_goal_type` (`user_id`, `goal_type`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_user_goals_user` FOREIGN KEY (`user_id`) REFERENCES `sf_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Metas personalizadas dos usuários';

-- 3. INSERIR METAS PADRÃO PARA USUÁRIOS EXISTENTES
-- =================================================
-- Você pode ajustar estes valores conforme necessário

INSERT INTO `sf_user_goals` (
  `user_id`, 
  `goal_type`, 
  `target_kcal`, 
  `target_protein_g`, 
  `target_carbs_g`, 
  `target_fat_g`, 
  `target_water_cups`,
  `target_steps_daily`,
  `target_steps_weekly`,
  `target_workout_hours_weekly`,
  `target_workout_hours_monthly`,
  `target_cardio_hours_weekly`,
  `target_cardio_hours_monthly`,
  `target_sleep_hours`,
  `user_gender`,
  `step_length_cm`
)
SELECT 
  u.id as user_id,
  'nutrition' as goal_type,
  2000 as target_kcal,  -- Meta padrão de calorias
  120.0 as target_protein_g,  -- Meta padrão de proteínas
  200.0 as target_carbs_g,  -- Meta padrão de carboidratos
  60.0 as target_fat_g,  -- Meta padrão de gorduras
  8 as target_water_cups,  -- Meta padrão de água (8 copos)
  10000 as target_steps_daily,  -- Meta padrão de passos diários (10k)
  70000 as target_steps_weekly,  -- Meta padrão de passos semanais (70k)
  3.0 as target_workout_hours_weekly,  -- 3 horas de treino por semana
  12.0 as target_workout_hours_monthly,  -- 12 horas de treino por mês
  2.5 as target_cardio_hours_weekly,  -- 2.5 horas de cardio por semana
  10.0 as target_cardio_hours_monthly,  -- 10 horas de cardio por mês
  8.0 as target_sleep_hours,  -- 8 horas de sono
  CASE 
    WHEN up.gender = 'male' THEN 'male'
    WHEN up.gender = 'female' THEN 'female'
    ELSE NULL
  END as user_gender,
  CASE 
    WHEN up.gender = 'male' THEN 76.0  -- Média de 76cm para homens
    WHEN up.gender = 'female' THEN 66.0  -- Média de 66cm para mulheres
    ELSE 71.0  -- Média geral
  END as step_length_cm
FROM `sf_users` u
LEFT JOIN `sf_user_profiles` up ON u.id = up.user_id
WHERE NOT EXISTS (
  SELECT 1 FROM sf_user_goals ug WHERE ug.user_id = u.id AND ug.goal_type = 'nutrition'
)
ON DUPLICATE KEY UPDATE user_id = user_id;

-- 4. ÍNDICES PARA MELHOR PERFORMANCE
-- ===================================

ALTER TABLE `sf_user_daily_tracking`
ADD INDEX `idx_user_date` (`user_id`, `date`),
ADD INDEX `idx_date` (`date`);

-- ====================================================
-- FIM DA ATUALIZAÇÃO
-- ====================================================

-- INSTRUÇÕES:
-- 1. Faça backup do banco de dados antes de executar este script
-- 2. Execute no phpMyAdmin da Hostinger
-- 3. Verifique se não há erros
-- 4. Ajuste as metas padrão conforme necessário para seu público






