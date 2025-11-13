-- ============================================================
-- SCRIPT FINAL PARA CORRIGIR TODOS OS PONTOS QUEBRADOS
-- Execute este script UMA VEZ para corrigir todos os usuários
-- ============================================================

-- CORRIGIR TODOS OS PONTOS QUEBRADOS (força arredondamento)
UPDATE sf_users
SET points = CAST(ROUND(points) AS DECIMAL(10,0))
WHERE ABS(points - ROUND(points)) > 0.001;

-- Verificar se funcionou (deve retornar 0 linhas)
SELECT COUNT(*) as usuarios_ainda_com_pontos_quebrados
FROM sf_users
WHERE ABS(points - ROUND(points)) > 0.001;

