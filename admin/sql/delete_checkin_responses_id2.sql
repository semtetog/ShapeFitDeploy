-- Script para apagar TODAS as respostas do check-in ID 2
-- Execute este script para limpar completamente o check-in de teste

DELETE FROM sf_checkin_responses WHERE config_id = 2;

UPDATE sf_checkin_availability 
SET is_completed = 0, completed_at = NULL
WHERE config_id = 2;

