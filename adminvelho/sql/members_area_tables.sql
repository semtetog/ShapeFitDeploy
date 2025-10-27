-- Tabela para conteúdo da área de membros
CREATE TABLE IF NOT EXISTS sf_member_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content_type ENUM('chef', 'supplements', 'videos', 'articles') NOT NULL,
    file_path VARCHAR(500), -- Caminho para arquivo (vídeo, imagem, PDF, etc.)
    content_text LONGTEXT, -- Conteúdo em texto (para artigos)
    target_type ENUM('all', 'user', 'group') DEFAULT 'all',
    target_id INT, -- ID do usuário ou grupo específico (NULL para 'all')
    status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
    created_by INT NOT NULL, -- ID do nutricionista que criou o conteúdo
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES sf_users(id) ON DELETE CASCADE,
    FOREIGN KEY (target_id) REFERENCES sf_users(id) ON DELETE CASCADE -- Para target_type = 'user'
);

-- Tabela para categorias de conteúdo
CREATE TABLE IF NOT EXISTS sf_content_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50), -- Classe do ícone FontAwesome
    color VARCHAR(7), -- Código da cor em hex
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela para relacionar conteúdo com categorias
CREATE TABLE IF NOT EXISTS sf_content_category_relations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    category_id INT NOT NULL,
    FOREIGN KEY (content_id) REFERENCES sf_member_content(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES sf_content_categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_content_category (content_id, category_id)
);

-- Tabela para visualizações de conteúdo
CREATE TABLE IF NOT EXISTS sf_content_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    user_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES sf_member_content(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sf_users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_content_user_view (content_id, user_id)
);

-- Tabela para downloads de conteúdo
CREATE TABLE IF NOT EXISTS sf_content_downloads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    user_id INT NOT NULL,
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES sf_member_content(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sf_users(id) ON DELETE CASCADE
);

-- Tabela para favoritos de conteúdo
CREATE TABLE IF NOT EXISTS sf_content_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_id INT NOT NULL,
    user_id INT NOT NULL,
    favorited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (content_id) REFERENCES sf_member_content(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sf_users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_content_user_favorite (content_id, user_id)
);

-- Inserir categorias padrão
INSERT INTO sf_content_categories (name, description, icon, color) VALUES
('Receitas', 'Receitas saudáveis e nutritivas', 'fas fa-utensils', '#ff6b00'),
('Suplementos', 'Informações sobre suplementos alimentares', 'fas fa-pills', '#4caf50'),
('Exercícios', 'Vídeos e guias de exercícios', 'fas fa-dumbbell', '#2196f3'),
('Nutrição', 'Artigos sobre nutrição e alimentação', 'fas fa-apple-alt', '#ff9800'),
('Bem-estar', 'Conteúdo sobre bem-estar e qualidade de vida', 'fas fa-heart', '#e91e63'),
('Educação', 'Conteúdo educacional sobre saúde', 'fas fa-graduation-cap', '#9c27b0');

-- Inserir conteúdo de exemplo
INSERT INTO sf_member_content (title, description, content_type, file_path, target_type, created_by) VALUES
('Receita: Salada de Quinoa', 'Uma deliciosa salada de quinoa com vegetais frescos e temperos especiais', 'chef', 'assets/images/recipes/quinoa_salad.jpg', 'all', 1),
('Guia de Suplementos', 'Guia completo sobre suplementos alimentares e quando utilizá-los', 'supplements', 'assets/files/suplementos_guide.pdf', 'all', 1),
('Treino HIIT 20 Minutos', 'Vídeo com treino HIIT completo para queimar calorias em casa', 'videos', 'assets/videos/hiit_20min.mp4', 'all', 1),
('Alimentação Anti-inflamatória', 'Artigo sobre alimentos que ajudam a reduzir inflamações no corpo', 'articles', NULL, 'all', 1),
('Receita: Smoothie Verde', 'Smoothie energético com espinafre, banana e abacaxi', 'chef', 'assets/images/recipes/green_smoothie.jpg', 'group', 1),
('Suplementos para Atletas', 'Guia específico de suplementação para praticantes de atividade física', 'supplements', 'assets/files/suplementos_atletas.pdf', 'group', 1);




