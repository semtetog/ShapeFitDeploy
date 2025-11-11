-- Adicionar coluna flow_data para armazenar o fluxo visual do check-in
ALTER TABLE `sf_checkin_configs` 
ADD COLUMN `flow_data` JSON NULL 
AFTER `description`;

