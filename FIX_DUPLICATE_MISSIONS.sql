-- Script para corrigir missões duplicadas em sf_user_routine_items
-- Remove duplicatas baseado em user_id e título (case-insensitive)

-- 1. Criar tabela temporária com IDs únicos (manter a primeira ocorrência)
CREATE TEMPORARY TABLE temp_unique_missions AS
SELECT 
    MIN(id) as id_to_keep,
    user_id,
    LOWER(TRIM(title)) as title_lower
FROM sf_user_routine_items
GROUP BY user_id, LOWER(TRIM(title));

-- 2. Deletar missões duplicadas (todas exceto a primeira)
DELETE uri FROM sf_user_routine_items uri
LEFT JOIN temp_unique_missions tmp 
    ON uri.id = tmp.id_to_keep
WHERE tmp.id_to_keep IS NULL;

-- 3. Remover tabela temporária
DROP TEMPORARY TABLE temp_unique_missions;

-- Verificar se ainda há duplicatas
SELECT 
    user_id,
    LOWER(TRIM(title)) as title_lower,
    COUNT(*) as count
FROM sf_user_routine_items
GROUP BY user_id, LOWER(TRIM(title))
HAVING COUNT(*) > 1;

-- Se retornar 0 linhas, não há mais duplicatas
