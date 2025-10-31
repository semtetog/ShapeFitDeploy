-- Adicionar constraint UNIQUE para prevenir duplicatas no futuro
-- Baseado em user_id + título (case-insensitive)

-- 1. Remover duplicatas existentes primeiro (caso existam)
CREATE TEMPORARY TABLE temp_unique_missions AS
SELECT MIN(id) as id_to_keep
FROM sf_user_routine_items
GROUP BY user_id, LOWER(TRIM(title));

DELETE uri FROM sf_user_routine_items uri
LEFT JOIN temp_unique_missions tmp ON uri.id = tmp.id_to_keep
WHERE tmp.id_to_keep IS NULL;

DROP TEMPORARY TABLE temp_unique_missions;

-- 2. Adicionar índice UNIQUE composto
ALTER TABLE sf_user_routine_items
ADD UNIQUE KEY unique_user_title (user_id, title(255));
