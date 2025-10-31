-- Script para adicionar a missão "Comeu salada hoje?" para usuários que não a possuem
-- Este script garante que todos os usuários tenham as 3 missões principais editáveis (refeições, intestino, salada)

INSERT INTO sf_user_routine_items (user_id, title, icon_class, description, is_exercise, exercise_type)
SELECT 
    u.id,
    'Comeu salada hoje?',
    'fa-leaf',
    NULL,
    0,
    ''
FROM sf_users u
WHERE u.onboarding_complete = 1
  AND NOT EXISTS (
      SELECT 1 
      FROM sf_user_routine_items uri 
      WHERE uri.user_id = u.id 
      AND LOWER(uri.title) LIKE '%salada%'
  );
