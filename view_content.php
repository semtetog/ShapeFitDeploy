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
        $stmt_files = $conn->prepare("SELECT * FROM sf_content_files WHERE content_id = ? ORDER BY display_order ASC, created_at ASC");
        $stmt_files->bind_param("i", $content_id);
        $stmt_files->execute();
        $files_result = $stmt_files->get_result();
        while ($file_row = $files_result->fetch_assoc()) {
            $content_files[] = $file_row;
        }
        $stmt_files->close();
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
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.back-button:hover {
    background: rgba(255, 107, 0, 0.1);
    border-color: var(--accent-orange);
    color: var(--accent-orange);
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

.content-pdf {
    width: 100%;
    height: 600px;
    border: none;
    border-radius: 12px;
}

.content-pdf-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: var(--accent-orange);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    margin-top: 16px;
}

.content-pdf-link:hover {
    background: #ff8c33;
    transform: translateY(-2px);
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
            <div class="files-list" style="display: flex; flex-direction: column; gap: 32px;">
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
                            <?php if (!empty($file['video_title'])): ?>
                                <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--accent-orange);">
                                    <?php echo htmlspecialchars($file['video_title']); ?>
                                </h3>
                            <?php endif; ?>
                            <div class="content-media">
                                <?php
                                // Se tiver thumbnail, usar como poster
                                $poster = '';
                                if (!empty($file['thumbnail_url'])) {
                                    $poster = $file['thumbnail_url'];
                                    if (!preg_match('/^https?:\/\//', $poster) && !preg_match('/^\//', $poster)) {
                                        $poster = '/' . ltrim($poster, '/');
                                    }
                                }
                                ?>
                                <video class="content-video" controls <?php echo !empty($poster) ? 'poster="' . htmlspecialchars($poster) . '"' : ''; ?>>
                                    <source src="<?php echo htmlspecialchars($file_url); ?>" type="<?php echo htmlspecialchars($file['mime_type'] ?? 'video/mp4'); ?>">
                                    Seu navegador não suporta a reprodução de vídeos.
                                </video>
                            </div>
                        </div>
                    <?php elseif ($is_pdf): ?>
                        <!-- PDF -->
                        <div class="content-media">
                            <iframe class="content-pdf" src="<?php echo htmlspecialchars($file_url); ?>#toolbar=0" type="application/pdf">
                                <p>Seu navegador não suporta PDFs. <a href="<?php echo htmlspecialchars($file_url); ?>" target="_blank" class="content-pdf-link">
                                    <i class="fas fa-download"></i> Baixar PDF
                                </a></p>
                            </iframe>
                            <a href="<?php echo htmlspecialchars($file_url); ?>" target="_blank" class="content-pdf-link">
                                <i class="fas fa-external-link-alt"></i> Abrir PDF em nova aba
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="content-meta">
            <div class="content-meta-item">
                <i class="fas fa-calendar"></i>
                <span><?php echo date('d/m/Y', strtotime($content['created_at'])); ?></span>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_ROOT_PATH . '/includes/layout_footer.php'; ?>

