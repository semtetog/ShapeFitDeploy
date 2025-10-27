-- Tabela para grupos de desafio
CREATE TABLE IF NOT EXISTS sf_challenge_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
    goals JSON, -- Metas do desafio (passos, exercício, hidratação, etc.)
    created_by INT NOT NULL, -- ID do nutricionista que criou o grupo
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES sf_users(id) ON DELETE CASCADE
);

-- Tabela para membros dos grupos de desafio
CREATE TABLE IF NOT EXISTS sf_challenge_group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
    FOREIGN KEY (group_id) REFERENCES sf_challenge_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sf_users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_user (group_id, user_id)
);

-- Tabela para progresso dos membros nos desafios
CREATE TABLE IF NOT EXISTS sf_challenge_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    steps INT DEFAULT 0,
    exercise_minutes INT DEFAULT 0,
    calories_burned DECIMAL(10, 2) DEFAULT 0,
    water_ml INT DEFAULT 0,
    healthy_meals INT DEFAULT 0,
    meditation_minutes INT DEFAULT 0,
    sleep_hours DECIMAL(4, 2) DEFAULT 0,
    points_earned INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES sf_challenge_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sf_users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_user_date (group_id, user_id, date)
);

-- Tabela para ranking dos grupos
CREATE TABLE IF NOT EXISTS sf_challenge_rankings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    total_points INT DEFAULT 0,
    position INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES sf_challenge_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sf_users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_user_ranking (group_id, user_id)
);

-- Tabela para notificações dos grupos
CREATE TABLE IF NOT EXISTS sf_challenge_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT, -- NULL para notificação geral do grupo
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'success', 'achievement') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES sf_challenge_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sf_users(id) ON DELETE CASCADE
);

-- Inserir dados de exemplo
INSERT INTO sf_challenge_groups (name, description, start_date, end_date, status, goals, created_by) VALUES
('Desafio 30 Dias', 'Desafio de 30 dias para melhorar hábitos alimentares e exercícios', '2024-10-01', '2024-10-31', 'active', '{"steps": 10000, "exercise": 30, "hydration": 2000, "nutrition": true}', 1),
('Grupo de Verão', 'Preparação para o verão com foco em hidratação e exercícios', '2024-09-15', '2024-12-15', 'active', '{"steps": 12000, "exercise": 45, "hydration": 2500, "nutrition": true}', 1),
('Desafio Detox', 'Desafio de 15 dias para desintoxicação e bem-estar', '2024-10-15', '2024-10-30', 'inactive', '{"steps": 8000, "exercise": 20, "hydration": 3000, "meditation": 15}', 1);




