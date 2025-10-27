-- Script limpo para criar apenas as tabelas de planos alimentares
-- (assumindo que sf_admins já existe)

-- Tabela para planos alimentares dos nutricionistas
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES sf_users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES sf_admins(id) ON DELETE CASCADE
);

-- Tabela para refeições do plano alimentar
CREATE TABLE IF NOT EXISTS sf_diet_plan_meals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    diet_plan_id INT NOT NULL,
    meal_name VARCHAR(100) NOT NULL,
    meal_time TIME,
    calories INT NOT NULL,
    protein_g DECIMAL(8,2) NOT NULL,
    carbs_g DECIMAL(8,2) NOT NULL,
    fat_g DECIMAL(8,2) NOT NULL,
    description TEXT,
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (diet_plan_id) REFERENCES sf_diet_plans(id) ON DELETE CASCADE
);

-- Tabela para alimentos específicos do plano
CREATE TABLE IF NOT EXISTS sf_diet_plan_foods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meal_id INT NOT NULL,
    food_name VARCHAR(255) NOT NULL,
    quantity DECIMAL(8,2) NOT NULL,
    unit VARCHAR(50) NOT NULL,
    calories INT NOT NULL,
    protein_g DECIMAL(8,2) NOT NULL,
    carbs_g DECIMAL(8,2) NOT NULL,
    fat_g DECIMAL(8,2) NOT NULL,
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meal_id) REFERENCES sf_diet_plan_meals(id) ON DELETE CASCADE
);

-- Tabela para grupos de usuários
CREATE TABLE IF NOT EXISTS sf_user_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    group_name VARCHAR(255) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#ff6b00',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES sf_admins(id) ON DELETE CASCADE
);

-- Tabela para membros dos grupos
CREATE TABLE IF NOT EXISTS sf_user_group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES sf_user_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sf_users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_user (group_id, user_id)
);

-- Tabela para conteúdos da área de membros
CREATE TABLE IF NOT EXISTS sf_member_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content_type ENUM('video', 'article', 'recipe', 'supplement', 'workout', 'other') NOT NULL,
    content_url VARCHAR(500),
    content_text LONGTEXT,
    thumbnail_url VARCHAR(500),
    is_premium BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES sf_admins(id) ON DELETE CASCADE
);

-- Tabela para distribuição de conteúdo por usuário/grupo
CREATE TABLE IF NOT EXISTS sf_content_distribution (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    target_type ENUM('user', 'group') NOT NULL,
    target_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT NOT NULL,
    FOREIGN KEY (content_id) REFERENCES sf_member_content(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES sf_admins(id) ON DELETE CASCADE
);

-- Tabela para desafios/grupos de desafio
CREATE TABLE IF NOT EXISTS sf_challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    challenge_name VARCHAR(255) NOT NULL,
    description TEXT,
    challenge_type ENUM('steps', 'exercise', 'hydration', 'nutrition', 'weight', 'custom') NOT NULL,
    target_value DECIMAL(10,2),
    target_unit VARCHAR(50),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES sf_admins(id) ON DELETE CASCADE
);

-- Tabela para participantes dos desafios
CREATE TABLE IF NOT EXISTS sf_challenge_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    current_progress DECIMAL(10,2) DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (challenge_id) REFERENCES sf_challenges(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sf_users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_challenge_user (challenge_id, user_id)
);

-- Índices para performance
CREATE INDEX idx_diet_plans_user_id ON sf_diet_plans(user_id);
CREATE INDEX idx_diet_plans_admin_id ON sf_diet_plans(admin_id);
CREATE INDEX idx_diet_plans_active ON sf_diet_plans(is_active);
CREATE INDEX idx_diet_plan_meals_plan_id ON sf_diet_plan_meals(diet_plan_id);
CREATE INDEX idx_diet_plan_foods_meal_id ON sf_diet_plan_foods(meal_id);
CREATE INDEX idx_user_groups_admin_id ON sf_user_groups(admin_id);
CREATE INDEX idx_member_content_admin_id ON sf_member_content(admin_id);
CREATE INDEX idx_member_content_type ON sf_member_content(content_type);
CREATE INDEX idx_content_distribution_content_id ON sf_content_distribution(content_id);
CREATE INDEX idx_challenges_admin_id ON sf_challenges(admin_id);
CREATE INDEX idx_challenge_participants_challenge_id ON sf_challenge_participants(challenge_id);




