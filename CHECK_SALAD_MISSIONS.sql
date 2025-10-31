-- Verificar todas as missões de salada no banco
SELECT 
    u.id as user_id,
    u.name,
    uri.id as mission_id,
    uri.title,
    uri.icon_class,
    uri.created_at
FROM sf_user_routine_items uri
JOIN sf_users u ON uri.user_id = u.id
WHERE LOWER(uri.title) LIKE '%salada%'
ORDER BY u.id, uri.created_at;
