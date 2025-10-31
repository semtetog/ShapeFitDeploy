-- Corrigir NULL em exercise_type para missões que não são de sono
UPDATE sf_user_routine_items
SET exercise_type = ''
WHERE exercise_type IS NULL
  AND NOT (LOWER(title) LIKE '%sono%');

-- Verificar se foi corrigido
SELECT 
    id,
    title,
    icon_class,
    exercise_type,
    is_exercise
FROM sf_user_routine_items
ORDER BY id;

