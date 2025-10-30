-- Atualizar ícone da missão de sono para cama (fa-bed)

-- 1. Atualizar missão padrão (template) em sf_routine_items
UPDATE sf_routine_items 
SET icon_class = 'fa-bed' 
WHERE title LIKE '%sono%' OR title LIKE '%Sono%';

-- 2. Atualizar missões personalizadas de todos os usuários em sf_user_routine_items
UPDATE sf_user_routine_items 
SET icon_class = 'fa-bed' 
WHERE title LIKE '%sono%' OR title LIKE '%Sono%';

-- Verificar resultado
SELECT 'Missões de sono atualizadas com ícone fa-bed' AS status;

