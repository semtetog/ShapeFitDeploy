-- Tabela para grupos de usuários
CREATE TABLE IF NOT EXISTS sf_user_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by INT NOT NULL, -- ID do nutricionista que criou o grupo
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES sf_users(id) ON DELETE CASCADE
);

-- Tabela para membros dos grupos de usuários
CREATE TABLE IF NOT EXISTS sf_user_group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    user_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    added_by INT NOT NULL, -- ID do nutricionista que adicionou o membro
    FOREIGN KEY (group_id) REFERENCES sf_user_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sf_users(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES sf_users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_user (group_id, user_id)
);

-- Tabela para distribuição de conteúdo por grupo
CREATE TABLE IF NOT EXISTS sf_group_content_distribution (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    content_id INT NOT NULL,
    distributed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    distributed_by INT NOT NULL, -- ID do nutricionista que distribuiu o conteúdo
    FOREIGN KEY (group_id) REFERENCES sf_user_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES sf_member_content(id) ON DELETE CASCADE,
    FOREIGN KEY (distributed_by) REFERENCES sf_users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_content (group_id, content_id)
);

-- Tabela para notificações de grupo
CREATE TABLE IF NOT EXISTS sf_group_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'success', 'achievement') DEFAULT 'info',
    created_by INT NOT NULL, -- ID do nutricionista que criou a notificação
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES sf_user_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES sf_users(id) ON DELETE CASCADE
);

-- Tabela para leitura de notificações de grupo
CREATE TABLE IF NOT EXISTS sf_group_notification_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (notification_id) REFERENCES sf_group_notifications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sf_users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_notification_user (notification_id, user_id)
);

-- Inserir grupos de exemplo
INSERT INTO sf_user_groups (name, description, status, created_by) VALUES
('Grupo Premium', 'Pacientes com plano premium com acesso a conteúdo exclusivo', 'active', 1),
('Grupo Iniciantes', 'Pacientes novos que estão começando sua jornada de saúde', 'active', 1),
('Grupo Atletas', 'Pacientes que praticam atividade física regularmente', 'active', 1),
('Grupo Gestantes', 'Pacientes gestantes com necessidades nutricionais específicas', 'active', 1),
('Grupo Diabéticos', 'Pacientes com diabetes que precisam de acompanhamento especial', 'active', 1);

-- Inserir alguns membros de exemplo (assumindo que existem usuários com IDs 2, 3, 4, 5)
INSERT INTO sf_user_group_members (group_id, user_id, added_by) VALUES
(1, 2, 1), -- Usuário 2 no Grupo Premium
(1, 3, 1), -- Usuário 3 no Grupo Premium
(2, 4, 1), -- Usuário 4 no Grupo Iniciantes
(2, 5, 1), -- Usuário 5 no Grupo Iniciantes
(3, 2, 1), -- Usuário 2 no Grupo Atletas
(3, 4, 1); -- Usuário 4 no Grupo Atletas




