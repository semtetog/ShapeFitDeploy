-- Script para apagar TODAS as respostas de um check-in específico
-- Substitua CHECKIN_ID_AQUI pelo ID do check-in (exemplo: 2)

-- 1. Apagar TODAS as respostas deste check-in
DELETE FROM sf_checkin_responses 
WHERE config_id = CHECKIN_ID_AQUI;

-- 2. Resetar TODOS os status de completado deste check-in
UPDATE sf_checkin_availability 
SET is_completed = 0, 
    completed_at = NULL
WHERE config_id = CHECKIN_ID_AQUI;

-- 3. (Opcional) Se quiser apagar também os dados do fluxo, descomente:
-- DELETE FROM sf_checkin_flow_answers WHERE ... (precisa de session_id relacionado)
-- DELETE FROM sf_checkin_flow_events WHERE ... (precisa de session_id relacionado)

