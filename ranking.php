<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$initial_limit = 15; // Carregar apenas 15 inicialmente
$current_limit = isset($_GET['limit']) ? (int)$_GET['limit'] : $initial_limit;

// Buscar dados do usuário
$user_profile_data = getUserProfileData($conn, $user_id);

// ===================================================================
// === SISTEMA DE NÍVEIS POR CATEGORIA ==============================
// ===================================================================

$level_categories = [
    ['name' => 'Franguinho',           'threshold' => 0],
    ['name' => 'Frango',               'threshold' => 1500],
    ['name' => 'Frango de Elite',      'threshold' => 4000],
    ['name' => 'Atleta de Bronze',     'threshold' => 8000],
    ['name' => 'Atleta de Prata',      'threshold' => 14000],
    ['name' => 'Atleta de Ouro',       'threshold' => 22000],
    ['name' => 'Atleta de Platina',    'threshold' => 32000],
    ['name' => 'Atleta de Diamante',   'threshold' => 45000],
    ['name' => 'Atleta de Elite',      'threshold' => 60000],
    ['name' => 'Mestre',               'threshold' => 80000],
    ['name' => 'Virtuoso',             'threshold' => 105000],
    ['name' => 'Campeão',              'threshold' => 135000],
    ['name' => 'Titã',                 'threshold' => 170000],
    ['name' => 'Pioneiro',             'threshold' => 210000],
    ['name' => 'Lenda',                'threshold' => 255000],
];

function toRoman($number) {
    $romans = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
    return $romans[$number] ?? 'X';
}

function getUserLevel($points, $categories) {
    foreach ($categories as $index => $category) {
        if ($points < $category['threshold']) {
            $previous_category = $index > 0 ? $categories[$index - 1] : ['threshold' => 0];
            $sublevel = min(10, max(1, floor(($points - $previous_category['threshold']) / (($category['threshold'] - $previous_category['threshold']) / 10)) + 1));
            return $category['name'] . ' ' . toRoman($sublevel);
        }
    }
    $last_category = end($categories);
    return $last_category['name'] . ' X';
}

// ===================================================================
// === BUSCAR DADOS DO RANKING ======================================
// ===================================================================

$rankings = [];
$stmt = $conn->prepare("SELECT u.id, u.name, u.points, up.profile_image_filename, up.gender, RANK() OVER (ORDER BY u.points DESC, u.name ASC) as user_rank FROM sf_users u LEFT JOIN sf_user_profiles up ON u.id = up.user_id ORDER BY user_rank ASC LIMIT ?");
$stmt->bind_param("i", $current_limit);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['level'] = getUserLevel($row['points'], $level_categories);
    $rankings[] = $row;
}
$stmt->close();

// Verificar se há mais usuários para carregar
$total_users_stmt = $conn->prepare("SELECT COUNT(*) as total FROM sf_users");
$total_users_stmt->execute();
$total_users_result = $total_users_stmt->get_result();
$total_users = $total_users_result->fetch_assoc()['total'];
$total_users_stmt->close();

$has_more_users = $current_limit < $total_users;

// Verificar se o usuário atual está na lista carregada
$current_user_in_loaded_list = false;
$current_user_data = null;

foreach ($rankings as $ranking) {
    if ($ranking['id'] == $user_id) {
        $current_user_in_loaded_list = true;
        break;
    }
}

// Card especial só aparece se o usuário NÃO estiver na lista carregada
$show_current_user_card = false;
if (!$current_user_in_loaded_list && $user_id) {
    $show_current_user_card = true;
    
    // Buscar dados do usuário atual separadamente
    $stmt_user = $conn->prepare("SELECT u.id, u.name, u.points, up.profile_image_filename, up.gender, r.user_rank FROM sf_users u LEFT JOIN sf_user_profiles up ON u.id = up.user_id JOIN (SELECT id, RANK() OVER (ORDER BY points DESC, name ASC) as user_rank FROM sf_users) r ON u.id = r.id WHERE u.id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $current_user_data = $stmt_user->get_result()->fetch_assoc();
    
    if ($current_user_data) {
        $current_user_data['level'] = getUserLevel($current_user_data['points'], $level_categories);
    }
    $stmt_user->close();
}

/**
 * Gera HTML da foto de perfil (com ícone laranja se não tiver foto)
 */
function getUserProfileImageHtml($player_data, $size = 'normal') {
    if (!empty($player_data['profile_image_filename'])) {
        $image_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($player_data['profile_image_filename']);
        $class = $size === 'large' ? 'player-avatar-large' : 'player-avatar';
        return '<div class="' . $class . '"><img src="' . $image_url . '" alt="Foto de Perfil"></div>';
    } else {
        $class = $size === 'large' ? 'player-avatar-large' : 'player-avatar';
        return '<div class="' . $class . '"><i class="fas fa-user"></i></div>';
    }
}

// --- PREPARAÇÃO PARA O LAYOUT ---
$page_title = "Ranking";
$extra_js = ['script.js'];

require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* CSS baseado no estilo exato do more_options.php */

/* Layout baseado no more_options */
.ranking-page-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 20px 8px 20px 8px;
}

.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:24px;
    padding: 0 12px;
}
.header-actions{
    display:flex;
    align-items:center;
    gap:.75rem
}
.points-counter-badge{
    display:flex;
    align-items:center;
    gap:8px;
    height:44px;
    padding:0 16px;
    border-radius:22px;
    background-color:var(--surface-color);
    border:1px solid var(--border-color);
    color:var(--text-primary);
    text-decoration:none;
    transition:all .2s ease
}
.points-counter-badge:hover{
    border-color:var(--accent-orange)
}
.points-counter-badge i{
    color:var(--accent-orange);
    font-size:1rem
}
.points-counter-badge span{
    font-weight:600;
    font-size:1rem
}

.page-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-title i {
    color: var(--accent-orange);
    font-size: 1.8rem;
}

/* Pódio sem background geral */
.podium-section {
    margin-bottom: 20px;
}

.podium-container {
    display: flex;
    justify-content: center;
    align-items: flex-end;
    gap: 20px;
    margin-bottom: 32px;
    padding: 24px 0;
}

.podium-place {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    position: relative;
}

.podium-place.first {
    order: 2;
    transform: scale(1.1);
}

.podium-place.second {
    order: 1;
}

.podium-place.third {
    order: 3;
}

.player-avatar-large {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background-color: rgba(255,255,255,0.05);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 2px solid var(--accent-orange);
    margin-bottom: 12px;
}

.player-avatar-large img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.player-avatar-large i {
    color: var(--accent-orange);
    font-size: 1.8rem;
}

.podium-place.first .player-avatar-large {
    width: 80px;
    height: 80px;
    border-width: 3px;
}

.podium-place.first .player-avatar-large i {
    font-size: 2.2rem;
}

.rank-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    color: white;
}

.podium-place.first .rank-badge {
    background: linear-gradient(135deg, #ffd700, #ffed4e);
    color: #333;
    width: 36px;
    height: 36px;
    font-size: 1rem;
}

.podium-place.second .rank-badge {
    background: linear-gradient(135deg, #c0c0c0, #e8e8e8);
    color: #333;
}

.podium-place.third .rank-badge {
    background: linear-gradient(135deg, #cd7f32, #daa520);
    color: white;
}

.podium-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.podium-level {
    font-size: 0.85rem;
    color: var(--accent-orange);
    font-weight: 500;
    margin-bottom: 4px;
}

.podium-points {
    font-size: 0.8rem;
    color: var(--text-secondary);
    font-weight: 500;
}

/* Lista de ranking com componentes coloridos, backgrounds sem saturação */
.ranking-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.ranking-item {
    display: grid;
    grid-template-columns: 32px 1fr auto;
    align-items: center;
    padding: 18px 20px;
    margin-bottom: 8px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 20px;
    transition: all 0.2s ease;
    width: 100%;
    gap: 16px;
}

/* Números perfeitamente alinhados */
.rank-position {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    font-size: 0.9rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    flex-shrink: 0;
    align-self: center;
    margin: auto 0;
}

.ranking-item.current-user .rank-position {
    color: var(--accent-orange);
    background: rgba(255, 140, 0, 0.15);
}

.ranking-item:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.1);
    transform: translateY(-2px);
}

.ranking-item.current-user {
    background: rgba(255, 107, 0, 0.1);
    border-color: var(--accent-orange);
}

/* Números de ranking removidos para dar mais espaço */

.player-content {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.player-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background-color: rgba(255,255,255,0.05);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border: 2px solid var(--accent-orange);
    flex-shrink: 0;
}

.player-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.player-avatar i {
    color: var(--accent-orange);
    font-size: 1.2rem;
}

.player-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
    flex: 1;
    max-width: calc(100% - 44px - 12px);
}

.player-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.player-level {
    font-size: 0.8rem;
    color: var(--accent-orange);
    font-weight: 500;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.player-points {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-align: right;
    flex-shrink: 0;
    white-space: nowrap;
}

.ranking-item.current-user .player-points {
    color: var(--accent-orange);
}

/* Botão Carregar Mais */
.load-more-container {
    display: flex;
    justify-content: center;
    margin-top: 20px;
    padding: 0 20px;
}

.load-more-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    color: var(--text-primary);
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.load-more-btn:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.load-more-btn i {
    font-size: 0.8rem;
    transition: transform 0.3s ease;
}

.load-more-btn:hover i {
    transform: translateY(2px);
}

.load-more-btn .users-count {
    color: var(--text-secondary);
    font-size: 0.8rem;
    font-weight: 400;
}

.load-more-btn.loading {
    opacity: 0.7;
    cursor: not-allowed;
}

.load-more-btn.loading i {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Card do usuário atual com componentes coloridos */
.current-user-card {
    background: linear-gradient(135deg, rgba(255, 107, 0, 0.15), rgba(255, 140, 0, 0.08));
    border: 2px solid var(--accent-orange);
    border-radius: 20px;
    padding: 16px;
    margin-top: 16px;
}

.current-user-card .ranking-item {
    background: transparent;
    border: none;
    padding: 14px 16px;
    margin: 0;
    align-items: center;
}

.current-user-card .ranking-item:hover {
    background: transparent;
    transform: none;
}

.current-user-card .rank-position {
    color: var(--accent-orange);
    background: rgba(255, 140, 0, 0.3);
}

.current-user-card .player-avatar {
    border-color: var(--accent-orange);
    border-width: 3px;
    width: 40px;
    height: 40px;
}

.current-user-card .player-name {
    color: var(--text-primary);
    font-size: 1rem;
}

.current-user-card .player-level {
    color: var(--accent-orange);
    font-size: 0.8rem;
}

.current-user-card .player-points {
    color: var(--accent-orange);
    font-size: 0.9rem;
    font-weight: 700;
}

/* Cards com glassmorphism - igual ao more_options */
.glass-card {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
}

/* Responsividade */
@media (max-width: 768px) {
    .ranking-page-grid {
        padding: 20px 6px 20px 6px;
    }
    
    .glass-card {
        padding: 20px;
    }
    
    .podium-container {
        gap: 16px;
    }
    
    .player-avatar-large {
        width: 56px;
        height: 56px;
    }
    
    .podium-place.first .player-avatar-large {
        width: 72px;
        height: 72px;
    }
    
    .ranking-item {
        padding: 16px 18px;
        gap: 12px;
    }
    
    .rank-position {
        width: 28px;
        height: 28px;
    font-size: 0.8rem;
        border-radius: 10px;
        align-self: center;
        margin: auto 0;
    }
    
    .player-avatar {
        width: 40px;
        height: 40px;
    }
    
    .player-info {
        max-width: calc(100% - 40px - 12px);
    }
    
    .player-name {
        font-size: 0.95rem;
    }
    
    .player-level {
        font-size: 0.75rem;
    }
    
    .player-points {
        font-size: 0.85rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loadMoreBtn = document.getElementById('load-more-btn');
    
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const currentLimit = parseInt(this.dataset.currentLimit);
            const totalUsers = parseInt(this.dataset.totalUsers);
            const newLimit = Math.min(currentLimit + 15, totalUsers);
            
            // Mostrar loading
            this.classList.add('loading');
            this.innerHTML = '<i class="fas fa-spinner"></i>Carregando...';
            
            // Fazer requisição para carregar mais usuários
            const url = new URL(window.location);
            url.searchParams.set('limit', newLimit);
            
            fetch(url.toString())
                .then(response => response.text())
                .then(html => {
                    // Criar um elemento temporário para extrair apenas a lista
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    
                    // Encontrar a nova lista de usuários
                    const newList = tempDiv.querySelector('.ranking-list');
                    const newLoadMore = tempDiv.querySelector('.load-more-container');
                    
                    if (newList) {
                        // Adicionar novos usuários à lista existente
                        const currentList = document.querySelector('.ranking-list');
                        const newUsers = Array.from(newList.children).slice(currentLimit);
                        
                        newUsers.forEach(user => {
                            currentList.appendChild(user);
                        });
                        
                        // Atualizar ou remover o botão "Carregar Mais"
                        const currentLoadMore = document.querySelector('.load-more-container');
                        if (newLoadMore && newLimit < totalUsers) {
                            currentLoadMore.innerHTML = newLoadMore.innerHTML;
                            // Reconfigurar o novo botão
                            const newBtn = currentLoadMore.querySelector('.load-more-btn');
                            if (newBtn) {
                                newBtn.addEventListener('click', arguments.callee);
                            }
                        } else {
                            currentLoadMore.remove();
                        }
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar mais usuários:', error);
                    // Restaurar botão em caso de erro
                    this.classList.remove('loading');
                    this.innerHTML = '<i class="fas fa-chevron-down"></i>Carregar Mais<span class="users-count">(' + (totalUsers - currentLimit) + ' restantes)</span>';
                });
        });
    }
});
</script>

<div class="app-container">
    <header class="header">
        <h1 class="page-title">
            <i class="fas fa-trophy"></i>
            Ranking
        </h1>
        
        <div class="header-actions">
            <a href="<?php echo BASE_APP_URL; ?>/points_history.php" class="points-counter-badge">
                <i class="fas fa-star"></i>
                <span><?php echo number_format($user_profile_data['points'] ?? 0, 0, ',', '.'); ?></span>
            </a>
        </div>
    </header>

    <section class="ranking-page-grid">
    <!-- Pódio -->
    <div class="podium-section">
            <div class="podium-container">
            <!-- 2º Lugar -->
            <div class="podium-place second">
                    <?php echo isset($rankings[1]) ? getUserProfileImageHtml($rankings[1], 'large') : '<div class="player-avatar-large"><i class="fas fa-user"></i></div>'; ?>
                    <div class="rank-badge">2</div>
                <span class="podium-name"><?php echo isset($rankings[1]) ? htmlspecialchars(explode(' ', $rankings[1]['name'])[0]) : '-'; ?></span>
                <span class="podium-level"><?php echo isset($rankings[1]) ? $rankings[1]['level'] : '-'; ?></span>
                <span class="podium-points"><?php echo isset($rankings[1]) ? number_format($rankings[1]['points'], 0, ',', '.') . ' pts' : '-'; ?></span>
            </div>
            
            <!-- 1º Lugar -->
            <div class="podium-place first">
                    <?php echo isset($rankings[0]) ? getUserProfileImageHtml($rankings[0], 'large') : '<div class="player-avatar-large"><i class="fas fa-user"></i></div>'; ?>
                    <div class="rank-badge"><i class="fas fa-crown"></i></div>
                <span class="podium-name"><?php echo isset($rankings[0]) ? htmlspecialchars(explode(' ', $rankings[0]['name'])[0]) : '-'; ?></span>
                <span class="podium-level"><?php echo isset($rankings[0]) ? $rankings[0]['level'] : '-'; ?></span>
                <span class="podium-points"><?php echo isset($rankings[0]) ? number_format($rankings[0]['points'], 0, ',', '.') . ' pts' : '-'; ?></span>
            </div>
            
            <!-- 3º Lugar -->
            <div class="podium-place third">
                    <?php echo isset($rankings[2]) ? getUserProfileImageHtml($rankings[2], 'large') : '<div class="player-avatar-large"><i class="fas fa-user"></i></div>'; ?>
                    <div class="rank-badge">3</div>
                <span class="podium-name"><?php echo isset($rankings[2]) ? htmlspecialchars(explode(' ', $rankings[2]['name'])[0]) : '-'; ?></span>
                <span class="podium-level"><?php echo isset($rankings[2]) ? $rankings[2]['level'] : '-'; ?></span>
                <span class="podium-points"><?php echo isset($rankings[2]) ? number_format($rankings[2]['points'], 0, ',', '.') . ' pts' : '-'; ?></span>
        </div>
    </div>
        
        <?php if (!empty($rankings)): ?>
            <ul class="ranking-list">
                <?php foreach (array_slice($rankings, 3) as $player): 
                    $is_current_user = ($player['id'] == $user_id);
                ?>
                    <li class="ranking-item <?php if($is_current_user) echo 'current-user'; ?>">
                            <div class="rank-position"><?php echo $player['user_rank']; ?></div>
                            <div class="player-content">
                                <?php echo getUserProfileImageHtml($player); ?>
                                <div class="player-info">
                                    <h3 class="player-name"><?php echo htmlspecialchars($player['name']); ?></h3>
                                    <p class="player-level"><?php echo $player['level']; ?></p>
                        </div>
                            </div>
                            <span class="player-points"><?php echo number_format($player['points'], 0, ',', '.'); ?> pts</span>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <?php if ($has_more_users): ?>
            <div class="load-more-container">
                <button id="load-more-btn" class="load-more-btn" data-current-limit="<?php echo $current_limit; ?>" data-total-users="<?php echo $total_users; ?>">
                    <i class="fas fa-chevron-down"></i>
                    Carregar Mais
                    <span class="users-count">(<?php echo $total_users - $current_limit; ?> restantes)</span>
                </button>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    </section>
</div>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php'; 
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>