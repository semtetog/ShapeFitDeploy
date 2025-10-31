-- Verificar todas as missões do usuário 36
SELECT 
    id,
    title,
    icon_class,
    description,
    is_exercise,
    exercise_type,
    user_id,
    created_at
FROM sf_user_routine_items
WHERE user_id = 36
ORDER BY id;

-- Contar quantas missões cada tipo
SELECT 
    CASE 
        WHEN LOWER(title) LIKE '%refeições%' THEN 'Refeições'
        WHEN LOWER(title) LIKE '%intestino%' THEN 'Intestino'
        WHEN LOWER(title) LIKE '%salada%' THEN 'Salada'
        WHEN LOWER(title) LIKE '%sono%' OR exercise_type = 'sleep' THEN 'Sono'
        ELSE 'Outra'
    END as tipo,
    COUNT(*) as total,
    GROUP_CONCAT(title SEPARATOR ' | ') as titulos
FROM sf_user_routine_items
WHERE user_id = 36
GROUP BY tipo;

