-- Sistema completo de Salas de Desafio
-- Criado para gerenciar grupos de desafio criados por nutricionistas

-- Tabela principal de salas de desafio
CREATE TABLE IF NOT EXISTS sf_challenge_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    admin_id INT NOT NULL, -- ID do nutricionista que criou
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
    goals JSON, -- Metas do desafio (passos, exercício, hidratação, etc.)
    rewards JSON, -- Recompensas para os vencedores
    max_participants INT DEFAULT 50,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES sf_admins(id) ON DELETE CASCADE
);

-- Tabela de membros das salas de desafio
CREATE TABLE IF NOT EXISTS sf_challenge_room_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challenge_room_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
    total_points INT DEFAULT 0,
    last_activity_at TIMESTAMP NULL,
    FOREIGN KEY (challenge_room_id) REFERENCES sf_challenge_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sf_users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_room_user (challenge_room_id, user_id)
);

-- Tabela de progresso diário dos membros
CREATE TABLE IF NOT EXISTS sf_challenge_daily_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challenge_room_id INT NOT NULL,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    steps_count INT DEFAULT 0,
    exercise_minutes INT DEFAULT 0,
    water_cups INT DEFAULT 0,
    calories_consumed DECIMAL(10,2) DEFAULT 0,
    points_earned INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (challenge_room_id) REFERENCES sf_challenge_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sf_users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_room_user_date (challenge_room_id, user_id, date)
);

-- Tabela de conquistas/medalhas por sala
CREATE TABLE IF NOT EXISTS sf_challenge_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challenge_room_id INT NOT NULL,
    user_id INT NOT NULL,
    achievement_type ENUM('first_place', 'second_place', 'third_place', 'most_active', 'most_consistent', 'most_improved') NOT NULL,
    points_awarded INT DEFAULT 0,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (challenge_room_id) REFERENCES sf_challenge_rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sf_users(id) ON DELETE CASCADE
);

-- Índices para performance
CREATE INDEX idx_challenge_rooms_status ON sf_challenge_rooms(status);
CREATE INDEX idx_challenge_rooms_dates ON sf_challenge_rooms(start_date, end_date);
CREATE INDEX idx_room_members_status ON sf_challenge_room_members(status);
CREATE INDEX idx_daily_progress_date ON sf_challenge_daily_progress(date);
CREATE INDEX idx_daily_progress_user_date ON sf_challenge_daily_progress(user_id, date);
