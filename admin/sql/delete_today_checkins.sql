-- Script para apagar check-ins feitos hoje (para testes)
-- Este script remove todas as respostas de check-in submetidas hoje
-- e reseta o status de completado para permitir testar novamente

-- 1. Apagar respostas de check-in submetidas hoje
DELETE FROM sf_checkin_responses 
WHERE DATE(submitted_at) = CURDATE();

-- 2. Resetar status de completado para check-ins de hoje
UPDATE sf_checkin_availability 
SET is_completed = 0, 
    completed_at = NULL
WHERE DATE(completed_at) = CURDATE();

-- 3. (Opcional) Apagar também dados do fluxo de check-in de hoje, se existirem
-- Descomente as linhas abaixo se quiser limpar também os dados do fluxo:
-- DELETE FROM sf_checkin_flow_answers WHERE DATE(created_at) = CURDATE();
-- DELETE FROM sf_checkin_flow_events WHERE DATE(created_at) = CURDATE();

