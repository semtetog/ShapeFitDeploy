<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$user_data = getUserProfileData($conn, $user_id);
if (!$user_data) { die("Erro ao carregar dados."); }


// ===================================================================
// === INÍCIO: CÓDIGO PARA CÁLCULO DE NÍVEL ==========================
// ===================================================================

// 1. Obter os pontos do usuário
$user_points = $user_data['points'] ?? 0; // Assumindo que getUserProfileData já retorna os pontos. 
                                         // Se não, teríamos que fazer uma query rápida aqui.

// 2. Definir o sistema de níveis
$level_categories = [
    ['name' => 'Franguinho', 'threshold' => 0], ['name' => 'Frango', 'threshold' => 1500], ['name' => 'Frango de Elite', 'threshold' => 4000],
    ['name' => 'Atleta de Bronze', 'threshold' => 8000], ['name' => 'Atleta de Prata', 'threshold' => 14000], ['name' => 'Atleta de Ouro', 'threshold' => 22000], ['name' => 'Atleta de Platina', 'threshold' => 32000], ['name' => 'Atleta de Diamante', 'threshold' => 45000],
    ['name' => 'Elite', 'threshold' => 60000], ['name' => 'Mestre', 'threshold' => 80000], ['name' => 'Virtuoso', 'threshold' => 105000],
    ['name' => 'Campeão', 'threshold' => 135000], ['name' => 'Titã', 'threshold' => 170000], ['name' => 'Pioneiro', 'threshold' => 210000], ['name' => 'Lenda', 'threshold' => 255000],
];

function toRoman($number) {
    $map = [10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'];
    $roman = '';
    while ($number > 0) { foreach ($map as $val => $char) { if ($number >= $val) { $roman .= $char; $number -= $val; break; } } }
    return $roman;
}

// 3. Função para calcular o nome do nível (versão simples, sem progresso)
function getUserLevelName($points, $categories) {
    $current_category = null;
    for ($i = count($categories) - 1; $i >= 0; $i--) {
        if ($points >= $categories[$i]['threshold']) {
            $current_category = $categories[$i];
            $next_threshold = isset($categories[$i + 1]) ? $categories[$i + 1]['threshold'] : ($current_category['threshold'] * 2);
            $points_in_category = $next_threshold - $current_category['threshold'];
            $points_per_sublevel = $points_in_category > 0 ? $points_in_category / 10 : 0;
            $points_into_this_category = $points - $current_category['threshold'];
            $sub_level = floor($points_into_this_category / $points_per_sublevel) + 1;
            $sub_level = max(1, min(10, $sub_level));
            if ($points >= $next_threshold && $next_threshold > $current_category['threshold']) { $sub_level = 10; }
            return $current_category['name'] . ' ' . toRoman($sub_level);
        }
    }
    return $categories[0]['name'] . ' I'; // Padrão caso algo dê errado
}

// 4. Calcular o nível do usuário atual
$current_user_level_name = getUserLevelName($user_points, $level_categories);

// ===================================================================
// === FIM: CÓDIGO PARA CÁLCULO DE NÍVEL ============================
// ===================================================================


$imc = calculateIMC((float)$user_data['weight_kg'], (int)$user_data['height_cm']);
$imc_category = getIMCCategory($imc);

// --- PREPARAÇÃO PARA O LAYOUT ---
$page_title = "Meu Perfil";
$extra_js = ['script.js'];
$extra_css = ['pages/_profile_overview.css'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* CSS do layout moderno para perfil */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 0;
    margin-bottom: 20px;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.points-counter-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    height: 44px;
    padding: 0 16px;
    border-radius: 22px;
    background-color: var(--surface-color);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.2s ease;
}

.points-counter-badge:hover {
    border-color: var(--accent-orange);
}

.points-counter-badge i {
    color: var(--accent-orange);
    font-size: 1rem;
}

.points-counter-badge span {
    font-weight: 600;
    font-size: 1rem;
}

/* Estilo do container principal */
.main-app-container {
    max-width: 100%;
    margin: 0 auto;
    padding: 0 20px 100px 20px;
    min-height: 100vh;
    background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
}

/* Cards com glassmorphism */
.glass-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.page-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

/* Card de resumo */
.profile-summary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 24px;
}

.summary-card {
    text-align: center;
    padding: 20px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.card-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.main-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.card-unit {
    font-size: 1rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.card-sub-label {
    font-size: 0.9rem;
    color: var(--accent-orange);
    font-weight: 600;
    margin-top: 8px;
}

.view-history-link {
    display: inline-block;
    margin-top: 12px;
    color: var(--accent-orange);
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 500;
    transition: color 0.2s ease;
}

.view-history-link:hover {
    color: #ff8c00;
}

/* Informações do usuário */
.user-info {
    margin-bottom: 24px;
}

.info-group-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 16px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    font-size: 1rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.info-value {
    font-size: 1rem;
    color: var(--text-primary);
    font-weight: 600;
}

.info-item-link {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 0;
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.2s ease;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.info-item-link:hover {
    color: var(--accent-orange);
}

.info-item-link i {
    color: var(--accent-orange);
    font-size: 0.9rem;
}

/* Card de estatísticas */
.stats-card {
    margin-bottom: 24px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.stat-item {
    text-align: center;
    padding: 16px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.stat-label {
    font-size: 0.8rem;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

/* Responsividade */
@media (max-width: 768px) {
    .profile-summary {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .main-app-container {
        padding: 0 16px 100px 16px;
    }
    
    .glass-card {
        padding: 20px;
    }
}
</style>

<div class="main-app-container">
    <!-- Header -->
    <div class="header">
        <h1 class="page-title">Perfil</h1>
        
        <div class="header-actions">
            <a href="<?php echo BASE_APP_URL; ?>/points_history.php" class="points-counter-badge">
                <i class="fas fa-coins"></i>
                <span><?php echo number_format($user_data['points'] ?? 0); ?> pts</span>
            </a>
        </div>
    </div>

    <!-- Resumo do perfil -->
    <div class="glass-card">
        <div class="profile-summary">
            <div class="summary-card">
                <div class="card-label">IMC</div>
                <div class="main-value"><?php echo number_format($imc, 1); ?></div>
                <div class="card-sub-label"><?php echo htmlspecialchars($imc_category); ?></div>
            </div>
            <div class="summary-card">
                <div class="card-label">Peso Atual</div>
                <div class="main-value"><?php echo number_format((float)$user_data['weight_kg'], 1); ?></div>
                <div class="card-unit">kg</div>
                <a href="<?php echo BASE_APP_URL; ?>/progress.php" class="view-history-link">Ver histórico</a>
            </div>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="glass-card stats-card">
        <h3 class="info-group-title">Estatísticas</h3>
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-value"><?php echo number_format($user_data['points'] ?? 0); ?></div>
                <div class="stat-label">Pontos</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo $user_data['height_cm']; ?></div>
                <div class="stat-label">Altura (cm)</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo calculateAge($user_data['dob']); ?></div>
                <div class="stat-label">Idade</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?php echo ucfirst($user_data['gender']); ?></div>
                <div class="stat-label">Gênero</div>
            </div>
        </div>
    </div>

    <!-- Informações do usuário -->
    <div class="glass-card user-info">
        <h3 class="info-group-title">Minhas Informações</h3>
        
        <div class="info-item">
            <span class="info-label">Nome</span>
            <span class="info-value"><?php echo htmlspecialchars($user_data['name']); ?></span>
        </div>
        
        <div class="info-item">
            <span class="info-label">Nível</span>
            <span class="info-value"><?php echo htmlspecialchars($current_user_level_name); ?></span>
        </div>
        
        <div class="info-item">
            <span class="info-label">Objetivo</span>
            <span class="info-value">
                <?php
                $objectives = ['lose_fat' => 'Perder Gordura', 'maintain_weight' => 'Manter Peso', 'gain_muscle' => 'Ganhar Massa'];
                echo htmlspecialchars($objectives[$user_data['objective']] ?? 'Não definido');
                ?>
            </span>
        </div>
        
        <a href="<?php echo BASE_APP_URL; ?>/edit_profile.php" class="info-item-link">
            <span class="info-label">Editar perfil e metas</span>
            <i class="fas fa-chevron-right"></i>
        </a>
    </div>
</div>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>