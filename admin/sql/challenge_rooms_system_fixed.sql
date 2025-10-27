-- Sistema completo de Salas de Desafio - VERSÃO CORRIGIDA
-- Criado para gerenciar grupos de desafio criados por nutricionistas

-- Primeiro, verificar se as tabelas base existem
-- Se não existirem, criar versões básicas

-- Verificar se sf_admins existe, se não, criar
CREATE TABLE IF NOT EXISTS sf_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_admin (user_id)
);

-- Verificar se sf_users existe, se não, criar
CREATE TABLE IF NOT EXISTS sf_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email (email)
);

-- Tabela principal de salas de desafio
CREATE TABLE IF NOT EXISTS sf_challenge_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    admin_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
    goals JSON,
    rewards JSON,
    max_participants INT DEFAULT 50,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Adicionar chave estrangeira para admin_id se a tabela sf_admins existir
-- (Isso será feito após verificar se a tabela existe)

-- Tabela de membros das salas de desafio
CREATE TABLE IF NOT EXISTS sf_challenge_room_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challenge_room_id INT NOT NULL,
    user_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
    total_points INT DEFAULT 0,
    last_activity_at TIMESTAMP NULL,
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
    UNIQUE KEY unique_room_user_date (challenge_room_id, user_id, date)
);

-- Tabela de conquistas/medalhas por sala
CREATE TABLE IF NOT EXISTS sf_challenge_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challenge_room_id INT NOT NULL,
    user_id INT NOT NULL,
    achievement_type ENUM('first_place', 'second_place', 'third_place', 'most_active', 'most_consistent', 'most_improved') NOT NULL,
    points_awarded INT DEFAULT 0,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Agora adicionar as chaves estrangeiras se as tabelas existirem
-- (Isso será feito em um script separado para evitar erros)

-- Índices para performance
CREATE INDEX IF NOT EXISTS idx_challenge_rooms_status ON sf_challenge_rooms(status);
CREATE INDEX IF NOT EXISTS idx_challenge_rooms_dates ON sf_challenge_rooms(start_date, end_date);
CREATE INDEX IF NOT EXISTS idx_room_members_status ON sf_challenge_room_members(status);
CREATE INDEX IF NOT EXISTS idx_daily_progress_date ON sf_challenge_daily_progress(date);
CREATE INDEX IF NOT EXISTS idx_daily_progress_user_date ON sf_challenge_daily_progress(user_id, date);
