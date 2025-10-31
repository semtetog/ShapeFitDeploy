-- Verificar TODAS as missões do usuário 36
SELECT 
    id,
    title,
    icon_class,
    description,
    is_exercise,
    exercise_type,
    created_at
FROM sf_user_routine_items
WHERE user_id = 36
ORDER BY id;

-- Verificar especificamente a missão da salada
SELECT 
    id,
    title,
    icon_class,
    description,
    is_exercise,
    exercise_type
FROM sf_user_routine_items
WHERE user_id = 36 
  AND LOWER(title) LIKE '%salada%';

