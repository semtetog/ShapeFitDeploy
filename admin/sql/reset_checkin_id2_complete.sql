-- Script para resetar COMPLETAMENTE o check-in ID 2
-- Remove respostas, reseta status, remove pontos ganhos e reseta congrats_shown
-- Execute este script quantas vezes precisar para retestar o check-in

-- IMPORTANTE: Substitua [USER_ID_AQUI] pelo seu ID de usuário antes de executar
SET @user_id = [USER_ID_AQUI]; -- Ex: 1, 2, 17, etc.
SET @config_id = 2;

-- 1. Apagar todas as respostas do check-in
DELETE FROM sf_checkin_responses 
WHERE config_id = @config_id AND user_id = @user_id;

-- 2. Resetar status de completado e disponibilidade
UPDATE sf_checkin_availability 
SET is_completed = 0, 
    completed_at = NULL,
    congrats_shown = 0
WHERE config_id = @config_id AND user_id = @user_id;

-- 3. Remover pontos ganhos com check-in (10 pontos)
-- Primeiro, verificar se há log de pontos para check-in
-- Como não há log específico, vamos remover 10 pontos diretamente
UPDATE sf_users 
SET points = GREATEST(0, points - 10)
WHERE id = @user_id;

-- 4. (Opcional) Se quiser remover também do log de pontos (caso exista registro)
-- DELETE FROM sf_user_points_log 
-- WHERE user_id = @user_id 
--   AND action_key LIKE '%CHECKIN%' 
--   AND action_context_id = @config_id;

-- Confirmação (opcional, para verificar antes/depois)
-- SELECT points FROM sf_users WHERE id = @user_id;
-- SELECT * FROM sf_checkin_availability WHERE config_id = @config_id AND user_id = @user_id;
-- SELECT COUNT(*) as total_respostas FROM sf_checkin_responses WHERE config_id = @config_id AND user_id = @user_id;

