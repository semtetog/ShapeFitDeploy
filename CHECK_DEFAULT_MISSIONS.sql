-- Verificar todas as missões padrão que devem ser copiadas para novos usuários
SELECT 
    id,
    title,
    icon_class,
    description,
    is_exercise,
    exercise_type,
    is_active,
    default_for_all_users
FROM sf_routine_items
WHERE is_active = 1 
ORDER BY id;

-- Verificar especificamente missões que devem ser copiadas
SELECT 
    id,
    title,
    icon_class,
    description,
    is_exercise,
    exercise_type
FROM sf_routine_items
WHERE is_active = 1 AND default_for_all_users = 1
ORDER BY id;

