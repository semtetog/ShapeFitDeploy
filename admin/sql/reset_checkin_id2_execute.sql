-- Script para resetar COMPLETAMENTE o check-in ID 2
-- Remove respostas, reseta status, remove pontos ganhos e reseta congrats_shown
-- Execute este script quantas vezes precisar para retestar o check-in

-- IMPORTANTE: Substitua [USER_ID_AQUI] pelo seu ID de usuário antes de executar
SET @user_id = [USER_ID_AQUI];
SET @config_id = 2;

DELETE FROM sf_checkin_responses 
WHERE config_id = @config_id AND user_id = @user_id;

UPDATE sf_checkin_availability 
SET is_completed = 0, 
    completed_at = NULL,
    congrats_shown = 0
WHERE config_id = @config_id AND user_id = @user_id;

UPDATE sf_users 
SET points = GREATEST(0, points - 10)
WHERE id = @user_id;

