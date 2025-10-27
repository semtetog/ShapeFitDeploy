<?php
// public_html/challenge_rooms_new.php
// SISTEMA COMPLETO DE SALAS DE DESAFIO - INTERFACE DO USUÁRIO

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];

// Buscar todos os desafios em que o usuário participa (ativos ou agendados)
$user_challenges = getUserActiveChallenges($conn, $user_id);

$page_title = "Salas de Desafio";
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* ===== ESTILOS PARA A PÁGINA DE DESAFIOS ===== */
body { 
    background-color: var(--bg-color); 
    color: var(--text-primary); 
}

.app-container { 
    max-width: 600px; 
    margin: 0 auto; 
    padding-bottom: 120px; 
}

.page-header {
    padding: calc(env(safe-area-inset-top, 0px) + 20px) 24px 20px;
    background: linear-gradient(135deg, var(--primary-orange-gradient));
    margin-bottom: 24px;
}

.page-title { 
    font-size: 2rem; 
    font-weight: 700; 
    color: white; 
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.page-subtitle {
    color: rgba(255, 255, 255, 0.9);
    margin: 8px 0 0 0;
    font-size: 1rem;
}

.challenges-container {
    padding: 0 24px;
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.no-challenges-card {
    text-align: center;
    padding: 60px 24px;
    background: var(--glass-bg);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    opacity: 0.8;
}

.no-challenges-card i { 
    font-size: 4rem; 
    color: var(--accent-orange); 
    margin-bottom: 20px; 
}

.no-challenges-card h3 {
    margin: 0 0 12px 0;
    color: var(--text-primary);
    font-size: 1.3rem;
}

.no-challenges-card p { 
    margin: 0; 
    color: var(--text-secondary); 
    font-size: 1rem; 
    line-height: 1.5;
}

.challenge-card {
    background: var(--glass-bg);
    border-radius: 20px;
    border: 1px solid var(--border-color);
    overflow: hidden;
    transition: all 0.3s ease;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

.challenge-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.challenge-header {
    padding: 24px;
    background: linear-gradient(135deg, rgba(255, 107, 53, 0.1) 0%, rgba(255, 107, 53, 0.05) 100%);
    border-bottom: 1px solid var(--border-color);
}

.challenge-header h3 { 
    margin: 0 0 8px 0; 
    font-size: 1.4rem; 
    color: var(--text-primary);
    font-weight: 600;
}

.challenge-meta { 
    display: flex; 
    align-items: center; 
    gap: 16px; 
    font-size: 0.9rem; 
    color: var(--text-secondary); 
    flex-wrap: wrap;
}

.challenge-meta span { 
    display: flex; 
    align-items: center; 
    gap: 6px; 
}

.challenge-meta i {
    color: var(--accent-orange);
    font-size: 0.9rem;
}

.challenge-status {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.challenge-status.active { 
    background-color: rgba(34, 197, 94, 0.15); 
    color: #22c55e; 
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.challenge-status.scheduled { 
    background-color: rgba(255, 193, 7, 0.15); 
    color: #f59e0b; 
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.challenge-content {
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.ranking-section, .rules-section {
    background: rgba(255, 255, 255, 0.02);
    border-radius: 12px;
    padding: 20px;
    border: 1px solid var(--border-color);
}

.section-title { 
    font-size: 1.1rem; 
    font-weight: 600; 
    margin-bottom: 16px; 
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i {
    color: var(--accent-orange);
}

.ranking-list { 
    list-style: none; 
    padding: 0; 
    margin: 0; 
    display: flex; 
    flex-direction: column; 
    gap: 12px; 
}

.ranking-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.2s ease;
}

.ranking-item:hover {
    background: rgba(255, 107, 53, 0.05);
    transform: translateX(4px);
}

.ranking-item.is-user { 
    border: 2px solid var(--accent-orange); 
    background: rgba(255, 107, 53, 0.08);
    box-shadow: 0 4px 12px rgba(255, 107, 53, 0.2);
}

.rank-position { 
    font-size: 1rem; 
    font-weight: 700; 
    color: var(--text-secondary); 
    width: 30px; 
    text-align: center; 
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    padding: 4px 8px;
}

.rank-position.top-3 {
    background: linear-gradient(135deg, var(--accent-orange), var(--primary-orange));
    color: white;
}

.rank-avatar { 
    width: 44px; 
    height: 44px; 
    border-radius: 50%; 
    overflow: hidden; 
    border: 2px solid rgba(255, 255, 255, 0.1);
}

.rank-avatar img { 
    width: 100%; 
    height: 100%; 
    object-fit: cover; 
}

.rank-avatar i { 
    font-size: 1.3rem; 
    color: var(--accent-orange); 
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    background: rgba(255, 107, 53, 0.1);
}

.rank-name { 
    flex-grow: 1; 
    font-weight: 600; 
    color: var(--text-primary);
    font-size: 0.95rem;
}

.rank-score { 
    font-size: 1.1rem; 
    font-weight: 700; 
    color: var(--accent-orange);
}

.rules-list { 
    list-style: none; 
    padding: 0; 
    margin: 0; 
    display: flex; 
    flex-direction: column; 
    gap: 8px;
}

.rule-item { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    padding: 12px 16px; 
    background: rgba(255, 255, 255, 0.03);
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.rule-item span:first-child { 
    color: var(--text-secondary); 
    font-size: 0.9rem;
}

.rule-item span:last-child { 
    font-weight: 600; 
    color: #4CAF50; 
    background: rgba(76, 175, 80, 0.1);
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.9rem;
}

.empty-ranking {
    text-align: center;
    color: var(--text-secondary);
    font-style: italic;
    padding: 20px;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 8px;
}

/* Responsividade */
@media (max-width: 768px) {
    .app-container {
        padding-bottom: 100px;
    }
    
    .page-header {
        padding: calc(env(safe-area-inset-top, 0px) + 16px) 20px 16px;
    }
    
    .page-title {
        font-size: 1.8rem;
    }
    
    .challenges-container {
        padding: 0 20px;
        gap: 20px;
    }
    
    .challenge-header, .challenge-content {
        padding: 20px;
    }
    
    .challenge-meta {
        gap: 12px;
        font-size: 0.85rem;
    }
    
    .ranking-section, .rules-section {
        padding: 16px;
    }
}

@media (max-width: 480px) {
    .challenge-header, .challenge-content {
        padding: 16px;
    }
    
    .rank-avatar {
        width: 40px;
        height: 40px;
    }
    
    .ranking-item {
        padding: 10px;
    }
}
</style>

<div class="app-container">
    <div class="page-header">
        <h1 class="page-title">🏆 Salas de Desafio</h1>
        <p class="page-subtitle">Competa com outros usuários e ganhe pontos</p>
    </div>

    <div class="challenges-container">
        <?php if (empty($user_challenges)): ?>
            <div class="no-challenges-card">
                <i class="fas fa-trophy"></i>
                <h3>Nenhum desafio ativo</h3>
                <p>Você ainda não está participando de nenhum desafio. Entre em contato com seu nutricionista para ser adicionado a um desafio.</p>
            </div>
        <?php else: ?>
            <?php foreach ($user_challenges as $challenge): 
                // Buscar o ranking para este desafio
                $ranking_data = getChallengeRanking($conn, $challenge['id']);
                
                // Buscar as regras para este desafio
                $rules_data = getChallengeRules($conn, $challenge['id']);

                // Mapear nomes de ações para texto amigável
                $action_names = [
                    'mission_complete' => 'Completar Missão Diária',
                    'water_goal' => 'Atingir Meta de Hidratação',
                    'protein_goal' => 'Atingir Meta de Proteína',
                    'lenient_water_goal' => 'Meta de Hidratação Flexível',
                    'lenient_protein_goal' => 'Meta de Proteína Flexível'
                ];
            ?>
                <div class="challenge-card">
                    <div class="challenge-header">
                        <h3><?php echo htmlspecialchars($challenge['name']); ?></h3>
                        <div class="challenge-meta">
                            <span><i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($challenge['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($challenge['end_date'])); ?></span>
                            <span class="challenge-status <?php echo $challenge['status']; ?>">
                                <?php echo $challenge['status'] === 'active' ? 'Ativo' : 'Agendado'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="challenge-content">
                        <!-- Ranking Section -->
                        <div class="ranking-section">
                            <h4 class="section-title">
                                <i class="fas fa-trophy"></i>
                                Ranking
                            </h4>
                            <ul class="ranking-list">
                                <?php if (empty($ranking_data)): ?>
                                    <li class="empty-ranking">
                                        O ranking será exibido aqui quando a pontuação começar.
                                    </li>
                                <?php else: ?>
                                    <?php $rank = 1; foreach ($ranking_data as $player): ?>
                                    <li class="ranking-item <?php echo ($player['id'] == $user_id) ? 'is-user' : ''; ?>">
                                        <span class="rank-position <?php echo $rank <= 3 ? 'top-3' : ''; ?>"><?php echo $rank++; ?>º</span>
                                        <div class="rank-avatar">
                                            <?php if (!empty($player['profile_image_filename'])): ?>
                                                <img src="<?php echo BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($player['profile_image_filename']); ?>" alt="Foto de <?php echo htmlspecialchars($player['name']); ?>">
                                            <?php else: ?>
                                                <i class="fas fa-user"></i>
                                            <?php endif; ?>
                                        </div>
                                        <span class="rank-name">
                                            <?php echo ($player['id'] == $user_id) ? 'Você' : htmlspecialchars(explode(' ', $player['name'])[0]); ?>
                                        </span>
                                        <span class="rank-score"><?php echo number_format($player['score'], 0, ',', '.'); ?> pts</span>
                                    </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                        
                        <!-- Rules Section -->
                        <div class="rules-section">
                            <h4 class="section-title">
                                <i class="fas fa-gamepad"></i>
                                Como Pontuar
                            </h4>
                            <ul class="rules-list">
                                <?php foreach ($rules_data as $rule): ?>
                                <li class="rule-item">
                                    <span><?php echo $action_names[$rule['action_type']] ?? ucfirst(str_replace('_', ' ', $rule['action_type'])); ?></span>
                                    <span>+<?php echo $rule['points_awarded']; ?> pts</span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <?php if (!empty($challenge['description'])): ?>
                        <div class="challenge-description">
                            <h4 class="section-title">
                                <i class="fas fa-info-circle"></i>
                                Sobre o Desafio
                            </h4>
                            <p style="color: var(--text-secondary); line-height: 1.6; margin: 0;">
                                <?php echo nl2br(htmlspecialchars($challenge['description'])); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
require_once APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>