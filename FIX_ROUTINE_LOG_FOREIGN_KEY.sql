-- Script para corrigir foreign key constraint em sf_user_routine_log
-- O problema: a FK aponta para sf_routine_items mas agora usamos sf_user_routine_items

-- Passo 1: Remover a FK antiga
ALTER TABLE sf_user_routine_log 
DROP FOREIGN KEY fk_sf_user_routine_log_sf_routine_items;

-- Passo 2: O campo routine_item_id agora pode referenciar qualquer um dos dois IDs
-- (Não podemos ter duas FKs apontando para campos diferentes)
-- Por isso, vamos simplesmente remover a constraint e garantir integridade no código

-- Nota: Isso é necessário porque routine_item_id pode apontar tanto para
-- sf_routine_items.id (missões antigas) quanto sf_user_routine_items.id (missões novas)

SELECT 'Foreign key constraint removida. Agora routine_item_id pode apontar para qualquer das duas tabelas.' AS status;

