-- Script para corrigir TODOS os pontos quebrados no banco de dados
-- Arredonda todos os pontos para números inteiros
-- Execute este script UMA VEZ para corrigir todos os usuários

-- 1. Verificar quantos usuários têm pontos quebrados
SELECT 
    COUNT(*) as total_usuarios_com_pontos_quebrados,
    SUM(CASE WHEN points != ROUND(points) THEN 1 ELSE 0 END) as usuarios_afetados
FROM sf_users
WHERE points != ROUND(points);

-- 2. Mostrar exemplos de pontos quebrados (antes da correção)
SELECT 
    id,
    name,
    points as pontos_atuais,
    ROUND(points) as pontos_corrigidos,
    (points - ROUND(points)) as diferenca
FROM sf_users
WHERE points != ROUND(points)
ORDER BY ABS(points - ROUND(points)) DESC
LIMIT 20;

-- 3. CORRIGIR TODOS OS PONTOS QUEBRADOS
-- Arredonda todos os pontos para o inteiro mais próximo
UPDATE sf_users
SET points = ROUND(points)
WHERE points != ROUND(points);

-- 4. Verificar se a correção funcionou
SELECT 
    COUNT(*) as usuarios_ainda_com_pontos_quebrados
FROM sf_users
WHERE points != ROUND(points);

-- 5. Mostrar estatísticas finais
SELECT 
    COUNT(*) as total_usuarios,
    SUM(points) as total_pontos_sistema,
    AVG(points) as media_pontos,
    MIN(points) as minimo_pontos,
    MAX(points) as maximo_pontos
FROM sf_users;

