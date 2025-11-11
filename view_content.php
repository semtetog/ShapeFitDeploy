<?php
// view_content.php - Página para visualizar conteúdo individual (vídeo ou PDF)

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$content_id = (int)($_GET['id'] ?? 0);

if ($content_id <= 0) {
    header("Location: " . BASE_APP_URL . "/content.php");
    exit();
}

// Verificar se usuário completou onboarding
$user_profile_data = getUserProfileData($conn, $user_id);
if (!$user_profile_data || !$user_profile_data['onboarding_complete']) {
    header("Location: " . BASE_APP_URL . "/onboarding/onboarding.php");
    exit();
}

// Buscar grupos do usuário
$user_group_ids = [];
try {
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

// Buscar conteúdo
$content = null;
try {
    $check_content_table = $conn->query("SHOW TABLES LIKE 'sf_member_content'");
    if ($check_content_table && $check_content_table->num_rows > 0) {
        $where_conditions = ["mc.id = ?"];
        $params = [$content_id];
        $types = 'i';
        
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
        
        $content_query = "SELECT mc.* FROM sf_member_content mc";
        if (!empty($where_conditions)) {
            $content_query .= " WHERE " . implode(" AND ", $where_conditions);
        }
        
        $stmt_content = $conn->prepare($content_query);
        if ($stmt_content) {
            if (!empty($params) && !empty($types)) {
                $stmt_content->bind_param($types, ...$params);
            }
            $stmt_content->execute();
            $content_result = $stmt_content->get_result();
            if ($content_result->num_rows > 0) {
                $content = $content_result->fetch_assoc();
            }
            $stmt_content->close();
        }
    }
} catch (Exception $e) {
    // Erro ao buscar conteúdo
    error_log("Erro ao buscar conteúdo: " . $e->getMessage());
}

if (!$content) {
    header("Location: " . BASE_APP_URL . "/content.php");
    exit();
}

// Buscar arquivos da tabela sf_content_files se existir
$content_files = [];
try {
    $check_files_table = $conn->query("SHOW TABLES LIKE 'sf_content_files'");
    if ($check_files_table && $check_files_table->num_rows > 0) {
        // Buscar todos os arquivos
        $stmt_files = $conn->prepare("SELECT * FROM sf_content_files WHERE content_id = ? ORDER BY display_order ASC, created_at ASC");
        $stmt_files->bind_param("i", $content_id);
        $stmt_files->execute();
        $files_result = $stmt_files->get_result();
        $all_files = [];
        while ($file_row = $files_result->fetch_assoc()) {
            $all_files[] = $file_row;
        }
        $stmt_files->close();
        
        // Separar vídeos e PDFs, ordenando: vídeos primeiro, PDFs por último
        $videos = [];
        $pdfs = [];
        foreach ($all_files as $file) {
            $is_pdf = false;
            if (!empty($file['mime_type'])) {
                $is_pdf = $file['mime_type'] === 'application/pdf';
            } else {
                $ext = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION));
                $is_pdf = $ext === 'pdf';
            }
            
            if ($is_pdf) {
                $pdfs[] = $file;
            } else {
                $videos[] = $file;
            }
        }
        
        // Combinar: vídeos primeiro, depois PDFs
        $content_files = array_merge($videos, $pdfs);
    }
    
    // Se não há arquivos na tabela, usar campos antigos do sf_member_content (compatibilidade)
    if (empty($content_files)) {
        if (!empty($content['file_path'])) {
            $content_files[] = [
                'id' => null,
                'file_path' => $content['file_path'],
                'file_name' => $content['file_name'] ?? null,
                'file_size' => $content['file_size'] ?? null,
                'mime_type' => $content['mime_type'] ?? null,
                'thumbnail_url' => $content['thumbnail_url'] ?? null,
                'video_title' => $content['video_title'] ?? null,
                'display_order' => 0
            ];
        }
    }
} catch (Exception $e) {
    // Erro ao buscar arquivos - usar método antigo
    if (!empty($content['file_path'])) {
        $content_files[] = [
            'id' => null,
            'file_path' => $content['file_path'],
            'file_name' => $content['file_name'] ?? null,
            'mime_type' => $content['mime_type'] ?? null,
            'thumbnail_url' => $content['thumbnail_url'] ?? null,
            'video_title' => $content['video_title'] ?? null,
        ];
    }
}

// Registrar visualização
try {
    $check_views_table = $conn->query("SHOW TABLES LIKE 'sf_content_views'");
    if ($check_views_table && $check_views_table->num_rows > 0) {
        $stmt_view = $conn->prepare("INSERT INTO sf_content_views (content_id, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE viewed_at = NOW()");
        if ($stmt_view) {
            $stmt_view->bind_param("ii", $content_id, $user_id);
            $stmt_view->execute();
            $stmt_view->close();
        }
    }
} catch (Exception $e) {
    // Ignorar erro de visualização
}

$page_title = htmlspecialchars($content['title']);

require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
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
    max-width: 900px;
    margin: 0 auto;
    padding: calc(env(safe-area-inset-top, 0px) + 20px) 24px 24px;
}

.page-header {
    display: flex;
    align-items: center;
    margin-bottom: 24px;
    gap: 1rem;
    justify-content: flex-start;
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

.content-container {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
}

.content-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 16px 0;
}

.content-description {
    font-size: 1rem;
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0 0 24px 0;
}

.content-media {
    width: 100%;
    margin: 24px 0;
    border-radius: 12px;
    overflow: hidden;
}

.content-video {
    width: 100%;
    aspect-ratio: 16 / 9;
    background: #000;
    border-radius: 12px;
    object-fit: contain;
}

.content-pdf-card {
    width: 100%;
    aspect-ratio: 16 / 9;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    color: var(--text-primary);
    position: relative;
    overflow: hidden;
}

.content-pdf-card:hover {
    background: rgba(255, 107, 0, 0.1);
    border-color: var(--accent-orange);
    transform: translateY(-2px);
}

.content-pdf-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255, 107, 0, 0.05) 0%, rgba(255, 107, 0, 0.1) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.content-pdf-card:hover::before {
    opacity: 1;
}

.content-pdf-icon {
    font-size: 4rem;
    color: var(--accent-orange);
    margin-bottom: 16px;
    position: relative;
    z-index: 1;
    transition: transform 0.3s ease;
}

.content-pdf-card:hover .content-pdf-icon {
    transform: scale(1.1);
}

.content-pdf-label {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 8px;
}

.content-pdf-label i {
    font-size: 0.875rem;
}

.content-meta {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    padding-top: 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.content-meta-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
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
}

.empty-state-icon i {
    font-size: 3rem;
    color: var(--accent-orange);
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

/* Separador laranja moderno (igual ao modal de calendário) */
.content-separator {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 32px 0;
    gap: 1rem;
}

.content-separator-line {
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--accent-orange), transparent);
    flex: 1;
    position: relative;
}

.content-separator-line::before {
    content: '';
    position: absolute;
    top: -1px;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, var(--accent-orange), transparent);
    opacity: 0.3;
    filter: blur(1px);
}

.content-separator-dots {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.content-separator-dot {
    width: 4px;
    height: 4px;
    background: var(--accent-orange);
    border-radius: 50%;
}
</style>

<div class="app-container">
    <div class="page-header">
        <a href="<?php echo BASE_APP_URL; ?>/content.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="page-title">
            <?php echo htmlspecialchars($content['title']); ?>
        </h1>
    </div>

    <div class="content-container">
        <?php if (!empty($content['description'])): ?>
            <p class="content-description"><?php echo nl2br(htmlspecialchars($content['description'])); ?></p>
        <?php endif; ?>

        <?php if (empty($content_files)): ?>
            <!-- Sem arquivo -->
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>Arquivo não disponível</h3>
                <p>O arquivo deste conteúdo não está disponível no momento.</p>
            </div>
        <?php else: ?>
            <!-- Lista de arquivos -->
            <div class="files-list" style="display: flex; flex-direction: column;">
                <?php foreach ($content_files as $index => $file): ?>
                    <?php
                    // Determinar tipo de arquivo
                    $is_video = false;
                    $is_pdf = false;
                    
                    if (!empty($file['mime_type'])) {
                        $is_video = strpos($file['mime_type'], 'video/') === 0;
                        $is_pdf = $file['mime_type'] === 'application/pdf';
                    } else {
                        // Fallback: verificar extensão
                        $ext = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION));
                        $is_video = in_array($ext, ['mp4', 'mov', 'avi', 'webm']);
                        $is_pdf = $ext === 'pdf';
                    }
                    
                    // Construir URL correta do arquivo
                    $file_url = $file['file_path'];
                    if (!empty($file_url) && !preg_match('/^https?:\/\//', $file_url) && !preg_match('/^\//', $file_url)) {
                        $file_url = '/' . ltrim($file_url, '/');
                    }
                    ?>
                    
                    <?php if ($is_video): ?>
                        <!-- Vídeo -->
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--accent-orange);">
                                <?php echo htmlspecialchars($file['video_title'] ?? 'Sem título'); ?>
                            </h3>
                            <div class="content-media">
                                <?php
                                // Se tiver thumbnail, usar como poster
                                // Se não tiver, o navegador vai mostrar a primeira frame automaticamente
                                $poster = '';
                                if (!empty($file['thumbnail_url'])) {
                                    $poster = $file['thumbnail_url'];
                                    if (!preg_match('/^https?:\/\//', $poster) && !preg_match('/^\//', $poster)) {
                                        $poster = '/' . ltrim($poster, '/');
                                    }
                                }
                                ?>
                                <video class="content-video" controls preload="metadata" <?php echo !empty($poster) ? 'poster="' . htmlspecialchars($poster) . '"' : ''; ?>>
                                    <source src="<?php echo htmlspecialchars($file_url); ?>" type="<?php echo htmlspecialchars($file['mime_type'] ?? 'video/mp4'); ?>">
                                    Seu navegador não suporta a reprodução de vídeos.
                                </video>
                            </div>
                        </div>
                    <?php elseif ($is_pdf): ?>
                        <!-- PDF -->
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--accent-orange);">
                                <?php echo htmlspecialchars($file['video_title'] ?? 'Sem título'); ?>
                            </h3>
                            <a href="<?php echo htmlspecialchars($file_url); ?>" 
                               onclick="event.preventDefault(); var link = document.createElement('a'); link.href = this.href; link.target = '_blank'; link.rel = 'noopener noreferrer'; document.body.appendChild(link); link.click(); document.body.removeChild(link); return false;"
                               class="content-pdf-card">
                                <i class="fas fa-file-pdf content-pdf-icon"></i>
                                <div class="content-pdf-label">
                                    <span>Abrir PDF</span>
                                    <i class="fas fa-external-link-alt"></i>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($index < count($content_files) - 1): ?>
                        <!-- Separador laranja entre arquivos -->
                        <div class="content-separator">
                            <div class="content-separator-line"></div>
                            <div class="content-separator-dots">
                                <div class="content-separator-dot"></div>
                                <div class="content-separator-dot"></div>
                                <div class="content-separator-dot"></div>
                            </div>
                            <div class="content-separator-line"></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="content-meta">
            <div class="content-meta-item">
                <i class="fas fa-calendar"></i>
                <span><?php 
                    // Usar updated_at se existir, senão usar created_at
                    $date_to_show = !empty($content['updated_at']) && $content['updated_at'] !== '0000-00-00 00:00:00' 
                        ? $content['updated_at'] 
                        : $content['created_at'];
                    echo date('d/m/Y', strtotime($date_to_show)); 
                ?></span>
            </div>
        </div>
    </div>
</div>

<?php 
// Adicionar bottom nav
$current_page_script = 'view_content.php';
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

