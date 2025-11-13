-- Script para apagar TODOS os check-ins de um usuário específico (para testes)
-- Substitua USER_ID_AQUI pelo ID do usuário que você quer limpar

-- Exemplo: DELETE FROM sf_checkin_responses WHERE user_id = 130;

-- 1. Apagar TODAS as respostas do usuário
DELETE FROM sf_checkin_responses 
WHERE user_id = USER_ID_AQUI;

-- 2. Resetar TODOS os status de completado do usuário
UPDATE sf_checkin_availability 
SET is_completed = 0, 
    completed_at = NULL
WHERE user_id = USER_ID_AQUI;

-- 3. Apagar dados do fluxo (se houver relação com user_id)
-- Nota: As tabelas de fluxo usam session_id, então pode ser necessário
-- encontrar as sessions do usuário primeiro. Se não houver relação direta,
-- você pode precisar limpar manualmente ou por data.

