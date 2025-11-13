-- Script COMPLETO para apagar check-ins feitos hoje (para testes)
-- Este script remove TODAS as respostas e reseta TODOS os status de completado

-- 1. Apagar TODAS as respostas de check-in submetidas hoje
DELETE FROM sf_checkin_responses 
WHERE DATE(submitted_at) = CURDATE()
   OR DATE(submitted_at) = DATE(NOW())
   OR DATE(submitted_at) = DATE(CURRENT_DATE());

-- 2. Resetar TODOS os status de completado da semana atual
-- (O check-in é marcado como completo para a semana, não apenas para o dia)
-- Calcula o domingo da semana atual (início da semana)
SET @week_start = DATE(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY));

UPDATE sf_checkin_availability 
SET is_completed = 0, 
    completed_at = NULL
WHERE week_date = @week_start
   OR DATE(completed_at) = CURDATE()
   OR DATE(completed_at) = DATE(NOW())
   OR DATE(completed_at) = DATE(CURRENT_DATE());

-- 3. Apagar dados do fluxo de check-in de hoje
DELETE FROM sf_checkin_flow_answers 
WHERE DATE(created_at) = CURDATE()
   OR DATE(created_at) = DATE(NOW())
   OR DATE(created_at) = DATE(CURRENT_DATE());

DELETE FROM sf_checkin_flow_events 
WHERE DATE(created_at) = CURDATE()
   OR DATE(created_at) = DATE(NOW())
   OR DATE(created_at) = DATE(CURRENT_DATE());

