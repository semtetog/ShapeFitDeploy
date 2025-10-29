-- ====================================================
-- ATUALIZAÇÃO DO BANCO DE DADOS PARA ABA DE PROGRESSO
-- Data: 20/10/2025
-- VERSÃO SEGURA - VERIFICA SE JÁ EXISTE ANTES DE ADICIONAR
-- ====================================================

-- Este script é seguro para executar múltiplas vezes
-- Ele verifica se as colunas/tabelas já existem antes de criar

-- ====================================================
-- 1. ADICIONAR NOVAS COLUNAS (SE NÃO EXISTIREM)
-- ====================================================

-- Verificar e adicionar steps_daily
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_user_daily_tracking' 
    AND COLUMN_NAME = 'steps_daily'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE sf_user_daily_tracking ADD COLUMN steps_daily INT DEFAULT 0 COMMENT "Passos dados no dia"',
    'SELECT "Coluna steps_daily já existe" AS Info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar workout_hours
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_user_daily_tracking' 
    AND COLUMN_NAME = 'workout_hours'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE sf_user_daily_tracking ADD COLUMN workout_hours DECIMAL(4,2) DEFAULT 0.00 COMMENT "Horas de treino (exercícios)"',
    'SELECT "Coluna workout_hours já existe" AS Info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar cardio_hours
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_user_daily_tracking' 
    AND COLUMN_NAME = 'cardio_hours'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE sf_user_daily_tracking ADD COLUMN cardio_hours DECIMAL(4,2) DEFAULT 0.00 COMMENT "Horas de cardio"',
    'SELECT "Coluna cardio_hours já existe" AS Info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar sleep_hours
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_user_daily_tracking' 
    AND COLUMN_NAME = 'sleep_hours'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE sf_user_daily_tracking ADD COLUMN sleep_hours DECIMAL(4,2) DEFAULT 0.00 COMMENT "Horas dormidas"',
    'SELECT "Coluna sleep_hours já existe" AS Info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ====================================================
-- 2. ADICIONAR COLUNAS ÀS ROTINAS
-- ====================================================

-- Verificar e adicionar is_exercise
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_routine_items' 
    AND COLUMN_NAME = 'is_exercise'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE sf_routine_items ADD COLUMN is_exercise TINYINT(1) DEFAULT 0 COMMENT "Se é um exercício (1=sim, 0=não)"',
    'SELECT "Coluna is_exercise já existe" AS Info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar exercise_type
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_routine_items' 
    AND COLUMN_NAME = 'exercise_type'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE sf_routine_items ADD COLUMN exercise_type ENUM("workout", "cardio", "other") DEFAULT NULL COMMENT "Tipo de exercício"',
    'SELECT "Coluna exercise_type já existe" AS Info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ====================================================
-- 3. ADICIONAR COLUNA AO LOG DE ROTINAS
-- ====================================================

-- Verificar e adicionar exercise_duration_minutes
SET @column_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_user_routine_log' 
    AND COLUMN_NAME = 'exercise_duration_minutes'
);

SET @sql = IF(@column_exists = 0, 
    'ALTER TABLE sf_user_routine_log ADD COLUMN exercise_duration_minutes INT DEFAULT NULL COMMENT "Duração do exercício em minutos"',
    'SELECT "Coluna exercise_duration_minutes já existe" AS Info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ====================================================
-- 4. CRIAR TABELA DE METAS (SE NÃO EXISTIR)
-- ====================================================

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
  
  -- Dados do Usuário
  `user_gender` ENUM('male', 'female') DEFAULT NULL COMMENT 'Gênero para cálculo de distância',
  `step_length_cm` DECIMAL(5,2) DEFAULT NULL COMMENT 'Comprimento médio do passo (cm)',
  
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_goal_type` (`user_id`, `goal_type`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Metas personalizadas dos usuários';

-- ====================================================
-- 5. INSERIR METAS PADRÃO (SE NÃO EXISTIREM)
-- ====================================================

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
);

-- ====================================================
-- 6. ATUALIZAR ROTINAS PADRÃO
-- ====================================================

UPDATE `sf_routine_items` 
SET `is_exercise` = 0 
WHERE `id` IN (2, 4, 5) AND `is_exercise` IS NULL;

-- ====================================================
-- 7. ÍNDICES (SE NÃO EXISTIREM)
-- ====================================================

-- Verificar e criar índice idx_user_date
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_user_daily_tracking' 
    AND INDEX_NAME = 'idx_user_date'
);

SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE sf_user_daily_tracking ADD INDEX idx_user_date (user_id, date)',
    'SELECT "Índice idx_user_date já existe" AS Info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e criar índice idx_date
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_user_daily_tracking' 
    AND INDEX_NAME = 'idx_date'
);

SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE sf_user_daily_tracking ADD INDEX idx_date (date)',
    'SELECT "Índice idx_date já existe" AS Info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e criar índice idx_user_date_exercise
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'sf_user_routine_log' 
    AND INDEX_NAME = 'idx_user_date_exercise'
);

SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE sf_user_routine_log ADD INDEX idx_user_date_exercise (user_id, date, exercise_duration_minutes)',
    'SELECT "Índice idx_user_date_exercise já existe" AS Info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ====================================================
-- 8. TRIGGERS (COM VERIFICAÇÃO)
-- ====================================================

-- Remover triggers se existirem (para recriar)
DROP TRIGGER IF EXISTS `after_routine_complete_add_workout_time`;
DROP TRIGGER IF EXISTS `after_routine_uncomplete_subtract_workout_time`;

-- Criar trigger para SOMAR tempo ao completar
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

-- Criar trigger para SUBTRAIR tempo ao desfazer
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
-- 9. VERIFICAÇÃO FINAL
-- ====================================================

SELECT 'Script executado com sucesso!' AS Status;

-- Verificar colunas adicionadas
SELECT 
    'sf_user_daily_tracking' AS Tabela,
    COLUMN_NAME AS Coluna,
    DATA_TYPE AS Tipo
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'sf_user_daily_tracking' 
  AND COLUMN_NAME IN ('steps_daily', 'workout_hours', 'cardio_hours', 'sleep_hours');

-- Verificar se tabela de metas existe
SELECT 
    COUNT(*) AS 'Tabela sf_user_goals existe?'
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'sf_user_goals';

-- Verificar triggers
SELECT 
    TRIGGER_NAME AS 'Triggers criados'
FROM INFORMATION_SCHEMA.TRIGGERS 
WHERE EVENT_OBJECT_TABLE = 'sf_user_routine_log'
  AND TRIGGER_SCHEMA = DATABASE();

-- ====================================================
-- FIM DA ATUALIZAÇÃO SEGURA
-- ====================================================

-- INSTRUÇÕES:
-- 1. Este script pode ser executado múltiplas vezes sem erro
-- 2. Ele verifica se cada item já existe antes de criar
-- 3. Os triggers são recriados para garantir versão atualizada
-- 4. Execute e veja as mensagens de confirmação no final






