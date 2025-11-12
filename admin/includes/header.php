<?php
// admin/includes/header.php (VERSÃO FINAL CORRIGIDA)

// 1. Inclui o config.php principal PRIMEIRO para ter acesso às constantes.
require_once __DIR__ . '/../../includes/config.php';

// 2. Inicia a sessão se ainda não foi iniciada.
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// 3. Agora podemos definir BASE_ADMIN_URL com segurança.
if (!defined('BASE_ADMIN_URL')) {
    define('BASE_ADMIN_URL', BASE_APP_URL . '/admin'); // Usa BASE_APP_URL do seu config principal
}

// 4. Buscar dados do admin para exibir foto e nome
$admin_data = null;
if (isset($_SESSION['admin_id'])) {
    require_once __DIR__ . '/../../includes/db.php';
    $stmt = $conn->prepare("SELECT id, full_name, profile_image_filename FROM sf_admins WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $admin_data = $result->fetch_assoc();
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>Painel Admin ShapeFIT</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo BASE_APP_URL; ?>/assets/images/SHAPE-FIT-LOGO.png">
    <link rel="shortcut icon" type="image/png" href="<?php echo BASE_APP_URL; ?>/assets/images/SHAPE-FIT-LOGO.png">
    <link rel="apple-touch-icon" href="<?php echo BASE_APP_URL; ?>/assets/images/SHAPE-FIT-LOGO.png">
    
    <!-- Fonte Montserrat -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Agora os caminhos usarão a constante corretamente -->
    <link rel="stylesheet" href="<?php echo BASE_ADMIN_URL; ?>/assets/css/admin_novo_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_ADMIN_URL; ?>/assets/css/view_user_addon.css?v=<?php echo time(); ?>">
    
    <?php if (!empty($extra_css) && is_array($extra_css)): ?>
        <?php foreach ($extra_css as $css_file): ?>
            <link rel="stylesheet" href="<?php echo BASE_ADMIN_URL; ?>/assets/css/<?php echo htmlspecialchars($css_file); ?>?v=<?php echo time(); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
</head>
<body>
    <div class="admin-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="<?php echo BASE_ADMIN_URL; ?>/dashboard.php" class="logo">
                    <img src="<?php echo BASE_ASSET_URL; ?>/assets/images/SHAPE-FIT-LOGO.png" alt="ShapeFIT" class="sidebar-logo-img">
                    <span>ShapeFIT</span>
                </a>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="<?php echo ($page_slug ?? '') === 'dashboard' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_ADMIN_URL; ?>/dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a>
                    </li>
                    <li class="<?php echo ($page_slug ?? '') === 'users' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_ADMIN_URL; ?>/users.php"><i class="fas fa-users"></i> Pacientes</a>
                    </li>
                    <li class="<?php echo ($page_slug ?? '') === 'recipes' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_ADMIN_URL; ?>/recipes.php"><i class="fas fa-utensils"></i> Receitas</a>
                    </li>
                    <li class="<?php echo ($page_slug ?? '') === 'food_classification' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_ADMIN_URL; ?>/food_classification.php"><i class="fas fa-tags"></i> Classificar Alimentos</a>
                    </li>
                    <li class="<?php echo ($page_slug ?? '') === 'foods' ? 'active' : ''; ?>">
                        <a href="<?php echo BASE_ADMIN_URL; ?>/foods_management_new.php"><i class="fas fa-utensils"></i> Alimentos</a>
                    </li>
        <li class="<?php echo ($page_slug ?? '') === 'checkin' ? 'active' : ''; ?>">
            <a href="<?php echo BASE_ADMIN_URL; ?>/checkin.php"><i class="fas fa-clipboard-check"></i> Check-in</a>
        </li>
        <li class="<?php echo ($page_slug ?? '') === 'challenge_groups' ? 'active' : ''; ?>">
            <a href="<?php echo BASE_ADMIN_URL; ?>/challenge_groups.php"><i class="fas fa-users"></i> Grupos de Desafio</a>
        </li>
        <li class="<?php echo ($page_slug ?? '') === 'content_management' ? 'active' : ''; ?>">
            <a href="<?php echo BASE_ADMIN_URL; ?>/content_management.php"><i class="fas fa-edit"></i> Gerenciar Conteúdo</a>
        </li>
        <li class="<?php echo ($page_slug ?? '') === 'user_groups' ? 'active' : ''; ?>">
            <a href="<?php echo BASE_ADMIN_URL; ?>/user_groups.php"><i class="fas fa-layer-group"></i> Grupos de Usuários</a>
        </li>
                      <li class="<?php if ($page_slug === 'ranks') echo 'active'; ?>">
            <a href="ranks.php"><i class="fas fa-trophy"></i> Ranking</a>
        </li>
                </ul>
            </nav>
            
            <!-- Card de Perfil do Admin -->
            <div class="sidebar-admin-profile">
                <div class="admin-profile-card" onclick="window.location.href='<?php echo BASE_ADMIN_URL; ?>/edit_profile.php'" style="cursor: pointer;">
                    <div class="admin-avatar">
                        <?php
                        $admin_name = $admin_data['full_name'] ?? $_SESSION['admin_name'] ?? 'Administrador';
                        $has_photo = false;
                        $avatar_url = '';
                        
                        if (!empty($admin_data['profile_image_filename']) && file_exists(APP_ROOT_PATH . '/assets/images/users/' . $admin_data['profile_image_filename'])) {
                            $avatar_url = BASE_APP_URL . '/assets/images/users/' . htmlspecialchars($admin_data['profile_image_filename']);
                            $has_photo = true;
                        }
                        
                        if ($has_photo):
                        ?>
                            <img src="<?php echo $avatar_url; ?>" alt="<?php echo htmlspecialchars($admin_name); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <?php
                            $name_parts = explode(' ', trim($admin_name));
                            $initials = count($name_parts) > 1 
                                ? strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1)) 
                                : (!empty($name_parts[0]) ? strtoupper(substr($name_parts[0], 0, 2)) : 'AD');
                            ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; font-weight: 600; color: var(--accent-orange);">
                                <?php echo $initials; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="admin-info">
                        <div class="admin-name"><?php echo htmlspecialchars($admin_name); ?></div>
                        <div class="admin-role">Administrador</div>
                    </div>
                </div>
                <div class="admin-controls">
                    <button class="admin-logout-btn" onclick="window.location.href='<?php echo BASE_ADMIN_URL; ?>/logout.php'" title="Sair">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Sair</span>
                    </button>
                </div>
            </div>
            
        </aside>
        <main class="main-content">
            <header class="main-header">
                <div class="user-profile-card">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Administrador'); ?></div>
                        <div class="user-role">Administrador do Sistema</div>
                    </div>
                    <div class="user-actions">
                        <button class="action-btn logout-btn" onclick="window.location.href='<?php echo BASE_ADMIN_URL; ?>/logout.php'" title="Sair">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </div>
                </div>
            </header>
            <div class="content-wrapper">
                <?php if (isset($_SESSION['admin_alert'])): ?>
                    <div class="admin-alert alert-<?php echo $_SESSION['admin_alert']['type']; ?>">
                        <i class="fas fa-<?php echo $_SESSION['admin_alert']['type'] === 'success' ? 'check-circle' : ($_SESSION['admin_alert']['type'] === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
                        <?php echo htmlspecialchars($_SESSION['admin_alert']['message']); ?>
                        <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php unset($_SESSION['admin_alert']); ?>
                <?php endif; ?>