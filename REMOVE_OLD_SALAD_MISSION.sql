-- Remover a missão antiga "Comeu salada hoje?" de todos os usuários
-- Deixar apenas a versão nova "Comeu pelo menos 150g de salada em 2 refeições hoje?"

DELETE FROM sf_user_routine_items
WHERE LOWER(title) = 'comeu salada hoje?';

-- Verificar se foi removido
SELECT 
    u.id as user_id,
    u.name,
    uri.title
FROM sf_user_routine_items uri
JOIN sf_users u ON uri.user_id = u.id
WHERE LOWER(uri.title) LIKE '%salada%'
ORDER BY u.id;
