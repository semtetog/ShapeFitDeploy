-- Execute este SQL no phpMyAdmin da Hostinger para adicionar colunas de metas customizadas

ALTER TABLE `sf_user_profiles` 
ADD COLUMN `custom_calories_goal` INT(11) NULL DEFAULT NULL COMMENT 'Meta personalizada de calorias (sobrescreve o cálculo automático)' AFTER `custom_exercise_minutes`,
ADD COLUMN `custom_protein_goal_g` DECIMAL(6,2) NULL DEFAULT NULL COMMENT 'Meta personalizada de proteínas em gramas' AFTER `custom_calories_goal`,
ADD COLUMN `custom_carbs_goal_g` DECIMAL(6,2) NULL DEFAULT NULL COMMENT 'Meta personalizada de carboidratos em gramas' AFTER `custom_protein_goal_g`,
ADD COLUMN `custom_fat_goal_g` DECIMAL(6,2) NULL DEFAULT NULL COMMENT 'Meta personalizada de gorduras em gramas' AFTER `custom_carbs_goal_g`;

-- Verificar se foi criado corretamente
DESCRIBE `sf_user_profiles`;

