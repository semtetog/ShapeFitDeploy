-- ============================================================
-- CORREÇÃO SIMPLES - Execute os comandos ABAIXO UM POR VEZ
-- ============================================================

-- PASSO 1: Descobrir o nome da constraint atual
-- Opção A: Execute este comando para ver o nome da constraint diretamente:
SELECT 
    CONSTRAINT_NAME as 'Nome da Constraint'
FROM 
    information_schema.KEY_COLUMN_USAGE 
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'sf_challenge_groups'
    AND COLUMN_NAME = 'created_by'
    AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Opção B: Ou execute este comando e procure por "CONSTRAINT" no resultado:
-- SHOW CREATE TABLE sf_challenge_groups;

-- Você verá algo como: sf_challenge_groups_ibfk_1
-- Copie esse nome para usar no PASSO 2
-- Se não aparecer nenhum resultado, pode pular o PASSO 2 e ir direto para o PASSO 3


-- PASSO 2: Remover a constraint antiga
-- Substitua 'sf_challenge_groups_ibfk_1' pelo nome que você encontrou no PASSO 1
ALTER TABLE `sf_challenge_groups` DROP FOREIGN KEY `sf_challenge_groups_ibfk_1`;


-- PASSO 3: Adicionar a nova constraint (sempre execute este)
ALTER TABLE `sf_challenge_groups`
ADD CONSTRAINT `sf_challenge_groups_created_by_fk` 
FOREIGN KEY (`created_by`) REFERENCES `sf_admins`(`id`) ON DELETE CASCADE;


-- ============================================================
-- PRONTO! Agora a constraint referencia sf_admins corretamente
-- ============================================================

