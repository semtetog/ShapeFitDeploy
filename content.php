<?php
// public_html/content.php - Página para usuários visualizarem todos os conteúdos

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];

// Verificar se usuário completou onboarding
$user_profile_data = getUserProfileData($conn, $user_id);
if (!$user_profile_data || !$user_profile_data['onboarding_complete']) {
    header("Location: " . BASE_APP_URL . "/onboarding/onboarding.php");
    exit();
}

// --- BUSCAR CONTEÚDOS DISPONÍVEIS PARA O USUÁRIO ---
// Buscar grupos do usuário
$user_group_ids = [];
try {
    // Verificar se a tabela existe
    $check_table = $conn->query("SHOW TABLES LIKE 'sf_user_group_members'");
    if ($check_table && $check_table->num_rows > 0) {
        $user_groups_query = "SELECT group_id FROM sf_user_group_members WHERE user_id = ?";
        $stmt_user_groups = $conn->prepare($user_groups_query);
        if ($stmt_user_groups) {
            $stmt_user_groups->bind_param("i", $user_id);
            $stmt_user_groups->execute();
            $user_groups_result = $stmt_user_groups->get_result();
            while ($row = $user_groups_result->fetch_assoc()) {
                $user_group_ids[] = $row['group_id'];
            }
            $stmt_user_groups->close();
        }
    }
} catch (Exception $e) {
    // Se a tabela não existir ou houver erro, simplesmente não busca grupos
    $user_group_ids = [];
}

// Verificar se as colunas existem
$has_target_type = false;
$has_target_id = false;
$has_status = false;

try {
    $check_content_table = $conn->query("SHOW TABLES LIKE 'sf_member_content'");
    if ($check_content_table && $check_content_table->num_rows > 0) {
        $check_target_type = $conn->query("SHOW COLUMNS FROM sf_member_content LIKE 'target_type'");
        if ($check_target_type && $check_target_type->num_rows > 0) {
            $has_target_type = true;
        }
        $check_target_id = $conn->query("SHOW COLUMNS FROM sf_member_content LIKE 'target_id'");
        if ($check_target_id && $check_target_id->num_rows > 0) {
            $has_target_id = true;
        }
        $check_status = $conn->query("SHOW COLUMNS FROM sf_member_content LIKE 'status'");
        if ($check_status && $check_status->num_rows > 0) {
            $has_status = true;
        }
    }
} catch (Exception $e) {
    // Ignorar
}

// Buscar conteúdos disponíveis (apenas ativos)
$user_contents = [];
try {
    // Verificar se a tabela existe
    $check_content_table = $conn->query("SHOW TABLES LIKE 'sf_member_content'");
    if ($check_content_table && $check_content_table->num_rows > 0) {
        // Construir query baseado nas colunas existentes
        $where_conditions = [];
        $params = [];
        $types = '';
        
        // Status
        if ($has_status) {
            $where_conditions[] = "mc.status = 'active'";
        } else {
            // Se não tem coluna status, usar is_active (se existir) ou mostrar todos
            $check_is_active = $conn->query("SHOW COLUMNS FROM sf_member_content LIKE 'is_active'");
            if ($check_is_active && $check_is_active->num_rows > 0) {
                $where_conditions[] = "mc.is_active = 1";
            }
        }
        
        // Target type e target_id
        if ($has_target_type && $has_target_id) {
            $target_conditions = ["mc.target_type = 'all'"];
            if (!empty($user_group_ids)) {
                $placeholders = implode(',', array_fill(0, count($user_group_ids), '?'));
                $target_conditions[] = "(mc.target_type = 'user' AND mc.target_id = ?)";
                $target_conditions[] = "(mc.target_type = 'group' AND mc.target_id IN ($placeholders))";
                $params = array_merge($params, [$user_id], $user_group_ids);
                $types .= str_repeat('i', count($user_group_ids) + 1);
            } else {
                $target_conditions[] = "(mc.target_type = 'user' AND mc.target_id = ?)";
                $params[] = $user_id;
                $types .= 'i';
            }
            $where_conditions[] = "(" . implode(" OR ", $target_conditions) . ")";
        }
        
        // Verificar se a tabela de arquivos existe
        $check_files_table = $conn->query("SHOW TABLES LIKE 'sf_content_files'");
        $has_files_table = ($check_files_table && $check_files_table->num_rows > 0);
        
        if ($has_files_table) {
            // Usar JOIN com sf_content_files para buscar apenas conteúdos com arquivos
            $content_query = "SELECT DISTINCT mc.*, a.full_name as author_name, a.profile_image_filename 
                              FROM sf_member_content mc 
                              LEFT JOIN sf_admins a ON mc.admin_id = a.id
                              INNER JOIN sf_content_files cf ON mc.id = cf.content_id";
            if (!empty($where_conditions)) {
                $content_query .= " WHERE " . implode(" AND ", $where_conditions);
            } else {
                $content_query .= " WHERE 1=1";
            }
        } else {
            // Método antigo: verificar file_path em sf_member_content
            $content_query = "SELECT mc.*, a.full_name as author_name, a.profile_image_filename 
                              FROM sf_member_content mc 
                              LEFT JOIN sf_admins a ON mc.admin_id = a.id";
            if (!empty($where_conditions)) {
                $content_query .= " WHERE " . implode(" AND ", $where_conditions);
            } else {
                $content_query .= " WHERE ";
            }
            // Garantir que apenas conteúdos com arquivo sejam mostrados
            if (!empty($where_conditions)) {
                $content_query .= " AND mc.file_path IS NOT NULL AND mc.file_path != ''";
            } else {
                $content_query .= " mc.file_path IS NOT NULL AND mc.file_path != ''";
            }
        }
        $content_query .= " ORDER BY mc.created_at DESC";
        
        $stmt_content = $conn->prepare($content_query);
        if ($stmt_content) {
            if (!empty($params) && !empty($types)) {
                $stmt_content->bind_param($types, ...$params);
            }
            $stmt_content->execute();
            $content_result = $stmt_content->get_result();
            while ($row = $content_result->fetch_assoc()) {
                // Se estamos usando a tabela de arquivos, buscar o primeiro arquivo para thumbnail
                if ($has_files_table) {
                    $stmt_file = $conn->prepare("SELECT * FROM sf_content_files WHERE content_id = ? ORDER BY display_order ASC, created_at ASC LIMIT 1");
                    $stmt_file->bind_param("i", $row['id']);
                    $stmt_file->execute();
                    $file_result = $stmt_file->get_result();
                    if ($file_row = $file_result->fetch_assoc()) {
                        // Usar dados do primeiro arquivo para thumbnail e tipo
                        $row['thumbnail_url'] = $file_row['thumbnail_url'];
                        $row['file_path'] = $file_row['file_path'];
                        $row['mime_type'] = $file_row['mime_type'];
                        // Determinar content_type baseado no mime_type se necessário
                        if (empty($row['content_type']) && !empty($file_row['mime_type'])) {
                            if (strpos($file_row['mime_type'], 'video/') === 0) {
                                $row['content_type'] = 'videos';
                            } elseif ($file_row['mime_type'] === 'application/pdf') {
                                $row['content_type'] = 'pdf';
                            }
                        }
                    }
                    $stmt_file->close();
                }
                $user_contents[] = $row;
            }
            $stmt_content->close();
        }
    }
} catch (Exception $e) {
    // Log do erro para debug (remover em produção)
    error_log("Erro ao buscar conteúdos: " . $e->getMessage());
    $user_contents = [];
}

$page_title = "Conteúdos";

require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* Estilos para a página de conteúdos */
body {
    background-color: var(--bg-color);
    color: var(--text-primary);
    position: fixed;
    width: 100%;
    height: 100%;
    overflow: hidden;
}

html {
    height: 100%;
    overflow: hidden;
}

.app-container {
    max-width: 600px;
    margin: 0 auto;
    padding: calc(env(safe-area-inset-top, 0px) + 20px) 24px calc(60px + 20px + env(safe-area-inset-bottom, 0px)) 24px;
    height: 100vh;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    position: relative;
}

.page-header {
    display: flex;
    align-items: center;
    margin-bottom: 24px;
    gap: 1rem;
    justify-content: flex-start;
}

.page-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.back-button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.back-button:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--accent-orange);
    color: var(--accent-orange);
    transform: translateX(-2px);
}

.content-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    margin-bottom: 24px;
}

.content-card {
    padding: 20px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    transition: all 0.3s ease;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.content-card:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: var(--accent-orange);
    transform: translateY(-2px);
}

.content-card-header {
    display: flex;
    align-items: flex-start;
    gap: 16px;
}

.content-thumbnail {
    position: relative;
}

.content-thumbnail::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to bottom, transparent 0%, rgba(0, 0, 0, 0.1) 100%);
    pointer-events: none;
}

.content-info {
    flex: 1;
    min-width: 0;
}

.content-info h3 {
    margin: 0 0 8px 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.4;
}

.content-description {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-secondary);
    line-height: 1.5;
}

.content-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding-top: 12px;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.content-author {
    display: flex;
    align-items: center;
    gap: 8px;
}

.author-avatar,
.author-avatar-placeholder {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    flex-shrink: 0;
}

.author-avatar {
    object-fit: cover;
    border: 2px solid rgba(255, 107, 0, 0.3);
}

.author-avatar-placeholder {
    background: rgba(255, 107, 0, 0.2);
    border: 2px solid rgba(255, 107, 0, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--accent-orange);
}

.author-name {
    font-weight: 500;
    color: var(--text-primary);
}

.content-date {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--text-secondary);
}

.content-date i {
    font-size: 0.75rem;
}

.content-categories {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.content-categories i {
    color: var(--accent-orange);
    font-size: 0.75rem;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 60vh;
}

.empty-state-icon {
    width: 120px;
    height: 120px;
    margin: 0 auto 24px;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.empty-state-icon i {
    font-size: 3rem;
    color: var(--accent-orange);
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    line-height: 1;
}

.empty-state h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 12px 0;
}

.empty-state p {
    font-size: 0.95rem;
    color: var(--text-secondary);
    margin: 0;
    line-height: 1.5;
}
</style>

<div class="app-container">
    <div class="page-header">
        <a href="<?php echo BASE_APP_URL; ?>/main_app.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="page-title">
            Conteúdos
        </h1>
    </div>

    <?php if (empty($user_contents)): ?>
        <!-- Estado vazio -->
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <h3>Nenhum conteúdo disponível</h3>
            <p>Nenhum conteúdo disponível no momento. Volte mais tarde!</p>
        </div>
    <?php else: ?>
        <!-- Lista de conteúdos -->
        <div class="content-grid">
            <?php foreach ($user_contents as $content): ?>
                <?php
                // Determinar ícone e tipo
                $content_icons = [
                    'chef' => 'fas fa-utensils',
                    'supplements' => 'fas fa-pills',
                    'videos' => 'fas fa-play',
                    'articles' => 'fas fa-file-alt',
                    'pdf' => 'fas fa-file-pdf'
                ];
                $content_labels = [
                    'chef' => 'Receitas',
                    'supplements' => 'Suplementos',
                    'videos' => 'Vídeos',
                    'articles' => 'Artigos',
                    'pdf' => 'PDFs'
                ];
                $icon = $content_icons[$content['content_type']] ?? 'fas fa-file-alt';
                $label = $content_labels[$content['content_type']] ?? ucfirst($content['content_type']);
                ?>
                <a href="<?php echo BASE_APP_URL; ?>/view_content.php?id=<?php echo $content['id']; ?>" class="content-card">
                    <?php if (!empty($content['thumbnail_url']) && $content['content_type'] === 'videos'): ?>
                        <!-- Thumbnail do vídeo -->
                        <?php
                        $thumbnail_url = $content['thumbnail_url'];
                        if (!preg_match('/^https?:\/\//', $thumbnail_url) && !preg_match('/^\//', $thumbnail_url)) {
                            $thumbnail_url = '/' . ltrim($thumbnail_url, '/');
                        }
                        ?>
                        <div class="content-thumbnail" style="width: 100%; height: 200px; border-radius: 12px; overflow: hidden; margin-bottom: 16px; background: rgba(0, 0, 0, 0.2); position: relative; pointer-events: none;">
                            <img src="<?php echo htmlspecialchars($thumbnail_url); ?>" 
                                 alt="<?php echo htmlspecialchars($content['title']); ?>" 
                                 style="width: 100%; height: 100%; object-fit: cover; display: block; pointer-events: none;">
                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0, 0, 0, 0.6); border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; pointer-events: none;">
                                <i class="fas fa-play"></i>
                            </div>
                        </div>
                    <?php elseif ($content['content_type'] === 'pdf'): ?>
                        <!-- Preview de PDF -->
                        <div class="content-thumbnail" style="width: 100%; height: 200px; border-radius: 12px; overflow: hidden; margin-bottom: 16px; background: rgba(255, 107, 0, 0.1); display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-file-pdf" style="font-size: 4rem; color: var(--accent-orange);"></i>
                        </div>
                    <?php endif; ?>
                    <div class="content-card-header">
                        <div class="content-info">
                            <h3><?php echo htmlspecialchars($content['title']); ?></h3>
                            <?php if (!empty($content['description'])): ?>
                                <p class="content-description"><?php echo htmlspecialchars($content['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="content-meta">
                        <?php if (!empty($content['author_name'])): ?>
                            <div class="content-author">
                                <?php
                                // Mostrar foto do autor se houver, senão placeholder
                                $author_photo = '';
                                if (!empty($content['profile_image_filename']) && file_exists(APP_ROOT_PATH . '/assets/images/users/' . $content['profile_image_filename'])) {
                                    $author_photo = BASE_APP_URL . '/assets/images/users/' . htmlspecialchars($content['profile_image_filename']);
                                }
                                ?>
                                <?php if ($author_photo): ?>
                                    <img src="<?php echo $author_photo; ?>" alt="<?php echo htmlspecialchars($content['author_name']); ?>" class="author-avatar">
                                <?php else: ?>
                                    <div class="author-avatar-placeholder">
                                        <?php
                                        $name_parts = explode(' ', trim($content['author_name']));
                                        $initials = count($name_parts) > 1 
                                            ? strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1)) 
                                            : (!empty($name_parts[0]) ? strtoupper(substr($name_parts[0], 0, 2)) : '??');
                                        echo $initials;
                                        ?>
                                    </div>
                                <?php endif; ?>
                                <span class="author-name"><?php echo htmlspecialchars($content['author_name']); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="content-date">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo date('d/m/Y', strtotime($content['created_at'])); ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php 
// Adicionar bottom nav
$current_page_script = 'content.php';
$nav_map = [
    'main_app.php' => 'home',
    'progress.php' => 'stats',
    'diary.php' => 'diary',
    'explore_recipes.php' => 'explore',
    'content.php' => 'explore',
    'view_content.php' => 'explore',
    'more_options.php' => 'settings'
];
$active_item = $nav_map[$current_page_script] ?? 'home';
require_once APP_ROOT_PATH . '/includes/layout_bottom_nav.php'; 
require_once APP_ROOT_PATH . '/includes/layout_footer.php'; 
?>

