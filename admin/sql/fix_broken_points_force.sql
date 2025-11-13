-- Script FORÇADO para corrigir TODOS os pontos quebrados
-- Este script força o arredondamento mesmo se houver problemas de precisão

-- 1. Atualizar todos os pontos para inteiros (forçado)
UPDATE sf_users
SET points = CAST(ROUND(points) AS DECIMAL(10,0))
WHERE ABS(points - ROUND(points)) > 0.001;

-- 2. Garantir que a coluna não aceite decimais (opcional, mas recomendado)
-- ALTER TABLE sf_users MODIFY points DECIMAL(10,0) NOT NULL DEFAULT 0;

-- 3. Verificar resultado
SELECT 
    id,
    name,
    points,
    CASE 
        WHEN points = ROUND(points) THEN 'OK'
        ELSE 'AINDA QUEBRADO'
    END as status
FROM sf_users
WHERE ABS(points - ROUND(points)) > 0.001
LIMIT 20;

