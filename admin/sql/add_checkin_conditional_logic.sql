-- Adicionar coluna para lógica condicional nas perguntas do check-in
-- Permite que perguntas sejam mostradas apenas se certas condições forem atendidas

SET @dbname = DATABASE();
SET @tablename = 'sf_checkin_questions';
SET @columnname = 'conditional_logic';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` JSON DEFAULT NULL COMMENT \'Lógica condicional para mostrar pergunta (ex: {"depends_on_question_id": 123, "show_if_value": "Sim"})\' AFTER `is_required`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

