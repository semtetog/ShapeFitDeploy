-- Script para verificar a estrutura das tabelas existentes e criar as novas

-- 1. Verificar estrutura da tabela sf_admins
DESCRIBE sf_admins;

-- 2. Verificar estrutura da tabela sf_users
DESCRIBE sf_users;

-- 3. Verificar se as tabelas existem
SHOW TABLES LIKE 'sf_admins';
SHOW TABLES LIKE 'sf_users';

-- 4. Se tudo estiver correto, criar as tabelas uma por uma

-- Primeiro, criar a tabela sf_diet_plans sem foreign keys
CREATE TABLE IF NOT EXISTS sf_diet_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_id INT NOT NULL,
    plan_name VARCHAR(255) NOT NULL,
    description TEXT,
    total_calories INT NOT NULL,
    protein_g DECIMAL(8,2) NOT NULL,
    carbs_g DECIMAL(8,2) NOT NULL,
    fat_g DECIMAL(8,2) NOT NULL,
    water_ml INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Depois adicionar as foreign keys
ALTER TABLE sf_diet_plans 
ADD CONSTRAINT fk_diet_plans_user_id 
FOREIGN KEY (user_id) REFERENCES sf_users(id) ON DELETE CASCADE;

ALTER TABLE sf_diet_plans 
ADD CONSTRAINT fk_diet_plans_admin_id 
FOREIGN KEY (admin_id) REFERENCES sf_admins(id) ON DELETE CASCADE;




