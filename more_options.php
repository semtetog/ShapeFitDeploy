<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
// A função getUserProfileData já busca todos os dados necessários
$user_profile_data = getUserProfileData($conn, $user_id);

if (!$user_profile_data) {
    // Redireciona ou mostra um erro se não encontrar o perfil
    // (Isso é uma boa prática para evitar erros mais abaixo)
    header("Location: " . BASE_APP_URL . "/main_app.php?error=profile_not_found");
    exit();
}

// --- LÓGICA CORRETA PARA PEGAR O PRIMEIRO NOME ---
$first_name = htmlspecialchars(explode(' ', $user_profile_data['name'])[0]);

// =========================================================================
//      LÓGICA PARA A FOTO DE PERFIL (IGUAL AO MAIN_APP E EDIT_PROFILE)
// =========================================================================
$profile_image_html = '';
// A coluna `profile_image_filename` agora vem de $user_profile_data
$custom_photo_filename = $user_profile_data['profile_image_filename'] ?? null;

if ($custom_photo_filename) {
    $profile_image_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($custom_photo_filename);
    $profile_image_html = '<img src="' . $profile_image_url . '" alt="Foto de Perfil" class="profile-picture">';
} else {
    // Usar ícone laranja como no main_app e edit_profile
    $profile_image_html = '<div class="profile-picture profile-icon-placeholder"><i class="fas fa-user"></i></div>';
}
// =========================================================================


// --- PREPARAÇÃO PARA O LAYOUT ---
$page_title = "Mais";
$extra_js = ['script.js'];
$extra_css = ['pages/_more_options.css'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* CSS do layout nativo para mobile - Mais Opções */
.settings-page-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 20px 8px 20px 8px;
}

/* Card de perfil - estilo nativo */
.profile-card {
    display: flex;
    align-items: center;
    padding: 20px;
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.3s ease;
    margin-bottom: 24px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.profile-card:hover {
    background: rgba(255, 107, 0, 0.1);
    border-color: var(--accent-orange);
    transform: translateY(-2px);
}

.profile-picture {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 16px;
    border: 3px solid var(--accent-orange);
}

.profile-icon-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.05);
    border: 3px solid var(--accent-orange);
    margin-right: 16px;
}

.profile-icon-placeholder i {
    color: var(--accent-orange);
    font-size: 1.5rem;
}

.profile-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.profile-name {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.profile-action {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0;
}

.arrow-icon {
    color: var(--accent-orange);
    font-size: 1.2rem;
}

/* Grid de opções principais - estilo nativo */
.options-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.option-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.3s ease;
    text-align: center;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    min-height: 120px;
}

.option-card:hover {
    background: rgba(255, 107, 0, 0.1);
    border-color: var(--accent-orange);
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
}

.option-icon {
    font-size: 2.5rem;
    margin-bottom: 12px;
    transition: transform 0.3s ease;
}

.option-card:hover .option-icon {
    transform: scale(1.1);
}

.option-label {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

/* Lista de opções - estilo nativo */
.options-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.option-item {
    display: flex;
    align-items: center;
    padding: 18px 20px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.2s ease;
    margin-bottom: 8px;
    position: relative;
    overflow: hidden;
}

.option-item:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 107, 0, 0.2);
    transform: translateY(-1px);
}

.option-item.logout-link {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.2);
}

.option-item.logout-link:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: #ef4444;
}


.list-icon {
    font-size: 1.3rem;
    margin-right: 16px;
    width: 24px;
    text-align: center;
    transition: transform 0.2s ease;
}

.option-item:hover .list-icon {
    transform: scale(1.1);
}

.option-item span {
    flex: 1;
    font-size: 1rem;
    font-weight: 500;
    margin: 0;
}

.arrow-icon-list {
    color: var(--text-secondary);
    font-size: 0.9rem;
    transition: transform 0.2s ease;
}

.option-item:hover .arrow-icon-list {
    transform: translateX(4px);
}

/* Seções */
.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 20px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: center;
}

/* Cards com glassmorphism */
.glass-card {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

/* Responsividade */
@media (max-width: 768px) {
    .settings-page-grid {
        padding: 20px 6px 20px 6px;
    }
    
    .glass-card {
        padding: 20px;
    }
    
    .option-card {
        padding: 20px 12px;
        min-height: 100px;
    }
    
    .option-icon {
        font-size: 2rem;
    }
    
    .option-label {
        font-size: 0.9rem;
    }
    
    .profile-card {
        padding: 16px;
    }
    
    .profile-picture {
        width: 56px;
        height: 56px;
    }
    
    .profile-name {
        font-size: 1.2rem;
    }
}
</style>

<div class="app-container">
    <section class="settings-page-grid">
        <!-- Card de perfil -->
        <a href="<?php echo BASE_APP_URL; ?>/edit_profile.php" class="profile-card">
            <?php echo $profile_image_html; ?>
            <div class="profile-info">
                <h2 class="profile-name"><?php echo $first_name; ?></h2>
                <p class="profile-action">Ver e editar perfil</p>
            </div>
            <i class="fas fa-chevron-right arrow-icon"></i>
        </a>

        <!-- Grade de opções principais -->
        <div class="glass-card">
            <h3 class="section-title">Principais</h3>
            <div class="options-grid">
                <a href="<?php echo BASE_APP_URL; ?>/dashboard.php" class="option-card">
                    <i class="fas fa-bullseye option-icon" style="color: var(--accent-orange);"></i>
                    <span class="option-label">Minha meta</span>
                </a>
                <a href="<?php echo BASE_APP_URL; ?>/progress.php" class="option-card">
                    <i class="fas fa-chart-line option-icon" style="color: #3b82f6;"></i>
                    <span class="option-label">Progresso</span>
                </a>
                <a href="<?php echo BASE_APP_URL; ?>/routine.php" class="option-card">
                    <i class="fas fa-tasks option-icon" style="color: #22c55e;"></i>
                    <span class="option-label">Rotina</span>
                </a>
                <a href="<?php echo BASE_APP_URL; ?>/ranking.php" class="option-card">
                    <i class="fas fa-trophy option-icon" style="color: #f59e0b;"></i>
                    <span class="option-label">Ranking</span>
                </a>
            </div>
        </div>

        <!-- Lista de opções secundárias -->
        <div class="glass-card">
            <h3 class="section-title">Outros</h3>
            <ul class="options-list">
                <li>
                    <a href="<?php echo BASE_APP_URL; ?>/favorite_recipes.php" class="option-item">
                        <i class="fas fa-heart list-icon" style="color: #ef4444;"></i>
                        <span>Meus Favoritos</span>
                        <i class="fas fa-chevron-right arrow-icon-list"></i>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_APP_URL; ?>/measurements_progress.php" class="option-item">
                        <i class="fas fa-camera list-icon" style="color: #8b5cf6;"></i>
                        <span>Fotos e Medidas</span>
                        <i class="fas fa-chevron-right arrow-icon-list"></i>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Opções de conta -->
        <div class="glass-card">
            <h3 class="section-title">Conta</h3>
            <ul class="options-list">
                <li>
                    <a href="<?php echo BASE_APP_URL; ?>/auth/logout.php" class="option-item logout-link">
                        <i class="fas fa-sign-out-alt list-icon"></i>
                        <span>Sair da Conta</span>
                    </a>
                </li>
            </ul>
        </div>
    </section>
</div>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>