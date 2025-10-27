-- ====================================================
-- ATUALIZAÇÃO DO BANCO DE DADOS PARA ABA DE PROGRESSO
-- Data: 20/10/2025
-- VERSÃO CORRIGIDA COM INTEGRAÇÃO DE ROTINAS
-- ====================================================

-- 1. ADICIONAR NOVAS COLUNAS À TABELA sf_user_daily_tracking
-- ============================================================

ALTER TABLE `sf_user_daily_tracking` 
ADD COLUMN `steps_daily` INT DEFAULT 0 COMMENT 'Passos dados no dia',
ADD COLUMN `workout_hours` DECIMAL(4,2) DEFAULT 0.00 COMMENT 'Horas de treino (exercícios)',
ADD COLUMN `cardio_hours` DECIMAL(4,2) DEFAULT 0.00 COMMENT 'Horas de cardio',
ADD COLUMN `sleep_hours` DECIMAL(4,2) DEFAULT 0.00 COMMENT 'Horas dormidas';

-- 2. ADICIONAR COLUNAS ÀS ROTINAS PARA IDENTIFICAR EXERCÍCIOS
-- ============================================================

ALTER TABLE `sf_routine_items`
ADD COLUMN `is_exercise` TINYINT(1) DEFAULT 0 COMMENT 'Se é um exercício (1=sim, 0=não)',
ADD COLUMN `exercise_type` ENUM('workout', 'cardio', 'other') DEFAULT NULL COMMENT 'Tipo de exercício';

-- 3. ADICIONAR COLUNA PARA REGISTRAR TEMPO DE TREINO NO LOG
-- ===========================================================

ALTER TABLE `sf_user_routine_log`
ADD COLUMN `exercise_duration_minutes` INT DEFAULT NULL COMMENT 'Duração do exercício em minutos';

-- 4. CRIAR TABELA PARA METAS DO USUÁRIO
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

-- 5. INSERIR METAS PADRÃO PARA USUÁRIOS EXISTENTES (ERRO CORRIGIDO)
-- ==================================================================

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
  2000 as target_kcal,
  120.0 as target_protein_g,
  200.0 as target_carbs_g,
  60.0 as target_fat_g,
  8 as target_water_cups,
  10000 as target_steps_daily,
  70000 as target_steps_weekly,
  3.0 as target_workout_hours_weekly,
  12.0 as target_workout_hours_monthly,
  2.5 as target_cardio_hours_weekly,
  10.0 as target_cardio_hours_monthly,
  8.0 as target_sleep_hours,
  CASE 
    WHEN up.gender = 'male' THEN 'male'
    WHEN up.gender = 'female' THEN 'female'
    ELSE NULL
  END as user_gender,
  CASE 
    WHEN up.gender = 'male' THEN 76.0
    WHEN up.gender = 'female' THEN 66.0
    ELSE 71.0
  END as step_length_cm
FROM `sf_users` u
LEFT JOIN `sf_user_profiles` up ON u.id = up.user_id
WHERE NOT EXISTS (
  SELECT 1 FROM sf_user_goals ug WHERE ug.user_id = u.id AND ug.goal_type = 'nutrition'
)
ON DUPLICATE KEY UPDATE 
  `target_kcal` = VALUES(`target_kcal`);  -- Corrigido: especificando a tabela

-- 6. ATUALIZAR ROTINAS EXISTENTES COMO EXERCÍCIOS (BASEADO NO ONBOARDING)
-- =========================================================================
-- As rotinas de exercício virão do onboarding do usuário
-- Por enquanto, vamos deixar as rotinas padrão como NÃO exercício

UPDATE `sf_routine_items` 
SET `is_exercise` = 0 
WHERE `id` IN (2, 4, 5);  -- Salada, intestino, registrar refeições

-- 7. ÍNDICES PARA MELHOR PERFORMANCE
-- ===================================

ALTER TABLE `sf_user_daily_tracking`
ADD INDEX `idx_user_date` (`user_id`, `date`),
ADD INDEX `idx_date` (`date`);

ALTER TABLE `sf_user_routine_log`
ADD INDEX `idx_user_date_exercise` (`user_id`, `date`, `exercise_duration_minutes`);

-- 8. CRIAR TRIGGER PARA SOMAR AUTOMATICAMENTE O TEMPO DE TREINO
-- ===============================================================
-- Quando uma rotina de exercício é completada com duração, soma automaticamente
-- no sf_user_daily_tracking

DELIMITER $$

CREATE TRIGGER `after_routine_complete_add_workout_time`
AFTER INSERT ON `sf_user_routine_log`
FOR EACH ROW
BEGIN
    DECLARE routine_is_exercise TINYINT;
    DECLARE routine_exercise_type VARCHAR(10);
    
    -- Verifica se é um exercício
    SELECT is_exercise, exercise_type 
    INTO routine_is_exercise, routine_exercise_type
    FROM sf_routine_items 
    WHERE id = NEW.routine_item_id;
    
    -- Se for exercício E tiver duração E foi completado
    IF routine_is_exercise = 1 AND NEW.exercise_duration_minutes IS NOT NULL AND NEW.is_completed = 1 THEN
        -- Converte minutos para horas
        SET @duration_hours = NEW.exercise_duration_minutes / 60;
        
        -- Atualiza ou insere no tracking diário
        IF routine_exercise_type = 'cardio' THEN
            INSERT INTO sf_user_daily_tracking (user_id, date, cardio_hours)
            VALUES (NEW.user_id, NEW.date, @duration_hours)
            ON DUPLICATE KEY UPDATE 
                cardio_hours = cardio_hours + @duration_hours;
        ELSE
            -- workout ou other
            INSERT INTO sf_user_daily_tracking (user_id, date, workout_hours)
            VALUES (NEW.user_id, NEW.date, @duration_hours)
            ON DUPLICATE KEY UPDATE 
                workout_hours = workout_hours + @duration_hours;
        END IF;
    END IF;
END$$

DELIMITER ;

-- 9. CRIAR TRIGGER PARA SUBTRAIR TEMPO QUANDO DESFAZER ROTINA
-- ============================================================

DELIMITER $$

CREATE TRIGGER `after_routine_uncomplete_subtract_workout_time`
AFTER DELETE ON `sf_user_routine_log`
FOR EACH ROW
BEGIN
    DECLARE routine_is_exercise TINYINT;
    DECLARE routine_exercise_type VARCHAR(10);
    
    -- Verifica se era um exercício
    SELECT is_exercise, exercise_type 
    INTO routine_is_exercise, routine_exercise_type
    FROM sf_routine_items 
    WHERE id = OLD.routine_item_id;
    
    -- Se foi exercício E tinha duração
    IF routine_is_exercise = 1 AND OLD.exercise_duration_minutes IS NOT NULL THEN
        -- Converte minutos para horas
        SET @duration_hours = OLD.exercise_duration_minutes / 60;
        
        -- Subtrai do tracking diário
        IF routine_exercise_type = 'cardio' THEN
            UPDATE sf_user_daily_tracking
            SET cardio_hours = GREATEST(0, cardio_hours - @duration_hours)
            WHERE user_id = OLD.user_id AND date = OLD.date;
        ELSE
            UPDATE sf_user_daily_tracking
            SET workout_hours = GREATEST(0, workout_hours - @duration_hours)
            WHERE user_id = OLD.user_id AND date = OLD.date;
        END IF;
    END IF;
END$$

DELIMITER ;

-- ====================================================
-- FIM DA ATUALIZAÇÃO
-- ====================================================

-- INSTRUÇÕES:
-- 1. Faça backup do banco de dados antes de executar este script
-- 2. Execute no phpMyAdmin da Hostinger
-- 3. Verifique se não há erros
-- 4. Ajuste as metas padrão conforme necessário para seu público
-- 5. Configure quais rotinas são exercícios (ver próximo arquivo)





