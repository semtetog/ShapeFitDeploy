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

// Buscar conteúdos disponíveis (apenas ativos)
$content_query = "
    SELECT mc.*, 
           GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as categories
    FROM sf_member_content mc
    LEFT JOIN sf_content_category_relations ccr ON mc.id = ccr.content_id
    LEFT JOIN sf_categories c ON ccr.category_id = c.id
    WHERE mc.status = 'active'
    AND (
        mc.target_type = 'all'
        OR (mc.target_type = 'user' AND mc.target_id = ?)
        " . (!empty($user_group_ids) ? "OR (mc.target_type = 'group' AND mc.target_id IN (" . implode(',', array_fill(0, count($user_group_ids), '?')) . "))" : "") . "
    )
    GROUP BY mc.id
    ORDER BY mc.created_at DESC
";

// Buscar conteúdos disponíveis
$user_contents = [];
try {
    // Verificar se a tabela existe
    $check_content_table = $conn->query("SHOW TABLES LIKE 'sf_member_content'");
    if ($check_content_table && $check_content_table->num_rows > 0) {
        $stmt_content = $conn->prepare($content_query);
        if ($stmt_content) {
            if (!empty($user_group_ids)) {
                $params = array_merge([$user_id], $user_group_ids);
                $types = str_repeat('i', count($params));
                $stmt_content->bind_param($types, ...$params);
            } else {
                $stmt_content->bind_param("i", $user_id);
            }
            $stmt_content->execute();
            $content_result = $stmt_content->get_result();
            while ($row = $content_result->fetch_assoc()) {
                $user_contents[] = $row;
            }
            $stmt_content->close();
        }
    }
} catch (Exception $e) {
    // Se a tabela não existir ou houver erro, simplesmente não busca conteúdos
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
}

.app-container {
    max-width: 600px;
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

.content-type-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    background: rgba(255, 107, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-orange);
    font-size: 1.5rem;
    flex-shrink: 0;
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
    gap: 12px;
    flex-wrap: wrap;
    padding-top: 12px;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.content-type-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.2);
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--accent-orange);
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
                    <div class="content-card-header">
                        <div class="content-type-icon">
                            <i class="<?php echo $icon; ?>"></i>
                        </div>
                        <div class="content-info">
                            <h3><?php echo htmlspecialchars($content['title']); ?></h3>
                            <?php if (!empty($content['description'])): ?>
                                <p class="content-description"><?php echo htmlspecialchars($content['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="content-meta">
                        <span class="content-type-badge">
                            <i class="<?php echo $icon; ?>"></i>
                            <?php echo $label; ?>
                        </span>
                        <?php if (!empty($content['categories'])): ?>
                            <span class="content-categories">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($content['categories']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once APP_ROOT_PATH . '/includes/layout_footer.php'; ?>

