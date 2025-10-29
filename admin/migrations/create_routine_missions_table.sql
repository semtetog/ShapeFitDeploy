-- Tabela para armazenar as missões de rotina configuráveis pelo nutricionista
-- Esta tabela permite que o nutricionista crie, edite e remova missões personalizadas

CREATE TABLE IF NOT EXISTS `sf_routine_missions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Nome da missão',
  `description` text DEFAULT NULL COMMENT 'Descrição opcional da missão',
  `icon_name` varchar(50) DEFAULT 'clock' COMMENT 'Nome do ícone SVG a ser usado',
  `mission_type` enum('binary','duration') DEFAULT 'binary' COMMENT 'Tipo: binária (sim/não) ou com duração',
  `default_duration_minutes` int(11) DEFAULT NULL COMMENT 'Duração padrão em minutos (para missões com duração)',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Se a missão está ativa para uso',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Missões de rotina configuráveis';

-- Inserir algumas missões padrão
INSERT INTO `sf_routine_missions` (`name`, `description`, `icon_name`, `mission_type`, `default_duration_minutes`, `is_active`) VALUES
('Tomar água suficiente', 'Beber pelo menos 2 litros de água', 'water-drop', 'binary', NULL, 1),
('Consumir proteína adequada', 'Atingir meta de proteínas do dia', 'leaf', 'binary', NULL, 1),
('Treinar', 'Realizar atividade física', 'dumbbell', 'duration', 60, 1),
('Dormir bem', 'Dormir pelo menos 7 horas', 'moon', 'duration', 480, 1),
('Meditar', 'Praticar meditação ou mindfulness', 'heart-pulse', 'duration', 15, 1),
('Tomar sol', 'Exposição solar para vitamina D', 'sun', 'duration', 20, 1),
('Fazer alongamento', 'Alongar o corpo', 'stretch', 'duration', 10, 1);

-- Atualizar a tabela sf_user_routine_log para referenciar a nova tabela de missões
-- Nota: routine_item_id agora referencia sf_routine_missions.id

