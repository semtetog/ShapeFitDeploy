<?php
// admin/view_content.php - Página para visualizar conteúdo individual (vídeo ou PDF) - Admin

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$content_id = (int)($_GET['id'] ?? 0);

if ($content_id <= 0) {
    header("Location: " . BASE_APP_URL . "/admin/content_management.php");
    exit();
}

// Buscar conteúdo
$content = null;
try {
    $check_content_table = $conn->query("SHOW TABLES LIKE 'sf_member_content'");
    if ($check_content_table && $check_content_table->num_rows > 0) {
        $content_query = "SELECT mc.*, a.full_name as author_name FROM sf_member_content mc LEFT JOIN sf_admins a ON mc.admin_id = a.id WHERE mc.id = ?";
        $stmt_content = $conn->prepare($content_query);
        if ($stmt_content) {
            $stmt_content->bind_param("i", $content_id);
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
    header("Location: " . BASE_APP_URL . "/admin/content_management.php");
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

$page_title = htmlspecialchars($content['title']);

require_once __DIR__ . '/includes/header.php';
?>

<style>
.content-view-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px;
}

.page-header {
    display: flex;
    align-items: center;
    margin-bottom: 24px;
    gap: 1rem;
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

.content-pdf-icon {
    font-size: 4rem;
    color: var(--accent-orange);
    margin-bottom: 16px;
    position: relative;
    z-index: 1;
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
</style>

<div class="content-view-container">
    <div class="page-header">
        <a href="<?php echo BASE_APP_URL; ?>/admin/content_management.php" class="back-button">
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
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>Arquivo não disponível</h3>
                <p>O arquivo deste conteúdo não está disponível no momento.</p>
            </div>
        <?php else: ?>
            <div class="files-list" style="display: flex; flex-direction: column;">
                <?php foreach ($content_files as $index => $file): ?>
                    <?php
                    $is_video = false;
                    $is_pdf = false;
                    
                    if (!empty($file['mime_type'])) {
                        $is_video = strpos($file['mime_type'], 'video/') === 0;
                        $is_pdf = $file['mime_type'] === 'application/pdf';
                    } else {
                        $ext = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION));
                        $is_video = in_array($ext, ['mp4', 'mov', 'avi', 'webm']);
                        $is_pdf = $ext === 'pdf';
                    }
                    
                    $file_url = $file['file_path'];
                    if (!empty($file_url) && !preg_match('/^https?:\/\//', $file_url) && !preg_match('/^\//', $file_url)) {
                        $file_url = '/' . ltrim($file_url, '/');
                    }
                    ?>
                    
                    <?php if ($is_video): ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--accent-orange);">
                                <?php echo htmlspecialchars($file['video_title'] ?? 'Sem título'); ?>
                            </h3>
                            <div class="content-media">
                                <?php
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
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: var(--accent-orange);">
                                <?php echo htmlspecialchars($file['video_title'] ?? 'Sem título'); ?>
                            </h3>
                            <a href="<?php echo htmlspecialchars($file_url); ?>" target="_blank" class="content-pdf-card">
                                <i class="fas fa-file-pdf content-pdf-icon"></i>
                                <div class="content-pdf-label">
                                    <span>Abrir PDF</span>
                                    <i class="fas fa-external-link-alt"></i>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($index < count($content_files) - 1): ?>
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
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

