-- Script para corrigir TODOS os pontos quebrados no banco de dados
-- Arredonda todos os pontos para números inteiros
-- Execute este script UMA VEZ para corrigir todos os usuários

-- CORRIGIR TODOS OS PONTOS QUEBRADOS
UPDATE sf_users
SET points = ROUND(points)
WHERE points != ROUND(points);

