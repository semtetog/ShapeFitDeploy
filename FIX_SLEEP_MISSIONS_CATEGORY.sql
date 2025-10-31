-- Script para corrigir missões de sono que foram criadas com categoria 'duration' ao invés de 'sleep'
-- Atualiza todas as missões de sono (identificadas pelo título contendo "sono") para ter exercise_type = 'sleep' e is_exercise = 1

UPDATE sf_user_routine_items
SET exercise_type = 'sleep',
    is_exercise = 1
WHERE LOWER(title) LIKE '%sono%'
  AND exercise_type != 'sleep';

-- Verificar quantas missões foram corrigidas
SELECT COUNT(*) as total_corrigidas
FROM sf_user_routine_items
WHERE LOWER(title) LIKE '%sono%'
  AND exercise_type = 'sleep';

