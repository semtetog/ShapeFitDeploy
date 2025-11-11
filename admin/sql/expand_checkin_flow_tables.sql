-- Expandir tabelas do check-in para suportar sistema completo de fluxos estilo Typebot

-- Adicionar colunas na tabela de configurações (apenas se não existirem)
-- Verificar e adicionar flow_data
SET @dbname = DATABASE();
SET @tablename = 'sf_checkin_configs';
SET @columnname = 'flow_data';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1', -- Coluna já existe, não fazer nada
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` JSON NULL AFTER `description`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Verificar e adicionar status
SET @columnname = 'status';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` ENUM(\'draft\', \'published\', \'archived\') NOT NULL DEFAULT \'draft\' AFTER `is_active`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Verificar e adicionar version
SET @columnname = 'version';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` INT(11) NULL DEFAULT NULL AFTER `status`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Tabela para versões publicadas (snapshots imutáveis)
CREATE TABLE IF NOT EXISTS `sf_checkin_flow_versions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_id` int(11) NOT NULL,
  `version` int(11) NOT NULL,
  `snapshot` JSON NOT NULL COMMENT 'Snapshot completo do fluxo no momento da publicação',
  `published_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `published_by` int(11) NOT NULL COMMENT 'admin_id',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_config_version` (`config_id`, `version`),
  KEY `config_id` (`config_id`),
  CONSTRAINT `fk_flow_versions_config` FOREIGN KEY (`config_id`) REFERENCES `sf_checkin_configs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para blocos do fluxo (expandir além de perguntas)
CREATE TABLE IF NOT EXISTS `sf_checkin_flow_blocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_id` int(11) NOT NULL,
  `block_key` varchar(100) NOT NULL COMMENT 'UUID ou identificador único estável',
  `block_type` enum('bot_message','question','action') NOT NULL DEFAULT 'question',
  `subtype` varchar(50) DEFAULT NULL COMMENT 'text,textarea,number,email,phone,date,time,multiple_choice,checkbox,rating,slider,yesno,chips,file',
  `title` varchar(255) DEFAULT NULL,
  `prompt` text NOT NULL COMMENT 'Texto da mensagem ou pergunta',
  `rich_content` JSON DEFAULT NULL COMMENT 'Conteúdo rico (imagens, vídeos, markdown)',
  `variable_name` varchar(100) DEFAULT NULL COMMENT 'Nome da variável para salvar resposta',
  `validate_schema` JSON DEFAULT NULL COMMENT 'Schema de validação (Zod-like)',
  `ui_schema` JSON DEFAULT NULL COMMENT 'Configurações de UI (alinhamento, colunas, ícones)',
  `position_x` decimal(10,2) DEFAULT 0,
  `position_y` decimal(10,2) DEFAULT 0,
  `delay_ms` int(11) DEFAULT 0 COMMENT 'Delay antes de avançar (para bot_message)',
  `auto_continue` tinyint(1) DEFAULT 0 COMMENT 'Avançar automaticamente após delay',
  `order_index` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_config_block_key` (`config_id`, `block_key`),
  KEY `config_id` (`config_id`),
  KEY `block_key` (`block_key`),
  CONSTRAINT `fk_flow_blocks_config` FOREIGN KEY (`config_id`) REFERENCES `sf_checkin_configs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para opções de múltipla escolha/checkbox
CREATE TABLE IF NOT EXISTS `sf_checkin_flow_block_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `block_id` int(11) NOT NULL,
  `label` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `icon` varchar(50) DEFAULT NULL COMMENT 'Emoji ou URL de ícone',
  `color` varchar(20) DEFAULT NULL,
  `next_block_key` varchar(100) DEFAULT NULL COMMENT 'Branch direto opcional',
  `meta` JSON DEFAULT NULL,
  `order_index` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `block_id` (`block_id`),
  CONSTRAINT `fk_block_options_block` FOREIGN KEY (`block_id`) REFERENCES `sf_checkin_flow_blocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para conexões/edges entre blocos
CREATE TABLE IF NOT EXISTS `sf_checkin_flow_edges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_id` int(11) NOT NULL,
  `from_block_key` varchar(100) NOT NULL,
  `to_block_key` varchar(100) NOT NULL,
  `condition` JSON DEFAULT NULL COMMENT 'Expressão de condição (ex: {"expr": "answers.Sport === \'Ride\'"})',
  `priority` int(11) DEFAULT 0 COMMENT 'Ordem de avaliação (menor = maior prioridade)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `config_id` (`config_id`),
  KEY `from_block_key` (`from_block_key`),
  KEY `to_block_key` (`to_block_key`),
  CONSTRAINT `fk_flow_edges_config` FOREIGN KEY (`config_id`) REFERENCES `sf_checkin_configs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para variáveis declaradas
CREATE TABLE IF NOT EXISTS `sf_checkin_flow_variables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `var_type` enum('string','number','boolean','date','json') NOT NULL DEFAULT 'string',
  `default_value` text DEFAULT NULL,
  `required` tinyint(1) DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_config_variable` (`config_id`, `name`),
  KEY `config_id` (`config_id`),
  CONSTRAINT `fk_flow_variables_config` FOREIGN KEY (`config_id`) REFERENCES `sf_checkin_configs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expandir tabela de sessões (apenas se as colunas não existirem)
SET @tablename = 'sf_checkin_availability';

-- Verificar e adicionar session_id
SET @columnname = 'session_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` VARCHAR(36) NULL COMMENT \'UUID da sessão ativa\' AFTER `is_completed`, ADD INDEX `', @columnname, '` (`', @columnname, '`)')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Verificar e adicionar last_block_key
SET @columnname = 'last_block_key';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` VARCHAR(100) NULL AFTER `session_id`, ADD INDEX `', @columnname, '` (`', @columnname, '`)')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Verificar e adicionar flow_version_id
SET @columnname = 'flow_version_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` INT(11) NULL AFTER `last_block_key`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Tabela para respostas detalhadas (expandir além de sf_checkin_responses)
CREATE TABLE IF NOT EXISTS `sf_checkin_flow_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` VARCHAR(36) NOT NULL COMMENT 'UUID da sessão',
  `variable_name` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `block_key` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  KEY `variable_name` (`variable_name`),
  KEY `block_key` (`block_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para eventos/telemetria
CREATE TABLE IF NOT EXISTS `sf_checkin_flow_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` VARCHAR(36) NOT NULL,
  `event_type` enum('block_render','user_input','validation_error','transition','complete','abandon') NOT NULL,
  `block_key` varchar(100) DEFAULT NULL,
  `data` JSON DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  KEY `event_type` (`event_type`),
  KEY `block_key` (`block_key`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

