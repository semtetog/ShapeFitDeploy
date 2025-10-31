-- Adicionar missão da salada para o usuário 36
INSERT INTO sf_user_routine_items (user_id, title, icon_class, description, is_exercise, exercise_type)
SELECT 
    36,
    'Comeu salada hoje?',
    'fa-leaf',
    NULL,
    0,
    ''
WHERE NOT EXISTS (
    SELECT 1 
    FROM sf_user_routine_items 
    WHERE user_id = 36 
    AND LOWER(title) LIKE '%salada%'
);

-- Verificar se foi adicionada
SELECT 
    id,
    title,
    icon_class
FROM sf_user_routine_items
WHERE user_id = 36
ORDER BY id;

