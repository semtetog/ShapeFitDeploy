<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$page_title = "Área de Membros";

// Buscar dados do usuário
$user_profile_data = getUserProfileData($conn, $user_id);

// === VERIFICAR ASSINATURA DO USUÁRIO ===
$stmt_subscription = $conn->prepare("
    SELECT 
        subscription_type,
        subscription_status,
        subscription_expires_at,
        features_unlocked
    FROM sf_user_subscriptions 
    WHERE user_id = ? AND subscription_status = 'active'
");
$stmt_subscription->bind_param("i", $user_id);
$stmt_subscription->execute();
$subscription_data = $stmt_subscription->get_result()->fetch_assoc();
$stmt_subscription->close();

// Se não tem assinatura ativa, criar uma básica para demonstração
if (!$subscription_data) {
    $subscription_data = [
        'subscription_type' => 'premium',
        'subscription_status' => 'active',
        'subscription_expires_at' => date('Y-m-d', strtotime('+30 days')),
        'features_unlocked' => 'chef_cooking,supplements,meal_plans,personalized_recipes'
    ];
}

// === BUSCAR CONTEÚDO DOS MÓDULOS ===
$modules = [
    'chef_cooking' => [
        'id' => 'chef_cooking',
        'name' => 'Chef de Cozinha',
        'description' => 'Receitas exclusivas e técnicas culinárias profissionais',
        'icon' => 'fas fa-utensils',
        'color' => '#ff6b35',
        'premium' => true,
        'content_count' => 45,
        'last_update' => '2024-01-15'
    ],
    'supplements' => [
        'id' => 'supplements',
        'name' => 'Suplementos',
        'description' => 'Guia completo sobre suplementação e nutrição',
        'icon' => 'fas fa-pills',
        'color' => '#4ade80',
        'premium' => true,
        'content_count' => 32,
        'last_update' => '2024-01-12'
    ],
    'meal_plans' => [
        'id' => 'meal_plans',
        'name' => 'Planos de Refeição',
        'description' => 'Planos alimentares personalizados por objetivos',
        'icon' => 'fas fa-calendar-alt',
        'color' => '#22d3ee',
        'premium' => true,
        'content_count' => 28,
        'last_update' => '2024-01-10'
    ],
    'personalized_recipes' => [
        'id' => 'personalized_recipes',
        'name' => 'Receitas Personalizadas',
        'description' => 'Receitas adaptadas ao seu perfil nutricional',
        'icon' => 'fas fa-heart',
        'color' => '#f87171',
        'premium' => true,
        'content_count' => 67,
        'last_update' => '2024-01-18'
    ]
];

// === BUSCAR CONTEÚDO RECENTE ===
$recent_content = [
    [
        'module' => 'chef_cooking',
        'title' => 'Técnicas de Corte Profissional',
        'description' => 'Aprenda os cortes básicos da culinária profissional',
        'type' => 'video',
        'duration' => '15 min',
        'difficulty' => 'Iniciante',
        'date' => '2024-01-18'
    ],
    [
        'module' => 'supplements',
        'title' => 'Guia de Whey Protein',
        'description' => 'Tudo que você precisa saber sobre proteína em pó',
        'type' => 'article',
        'duration' => '8 min',
        'difficulty' => 'Intermediário',
        'date' => '2024-01-17'
    ],
    [
        'module' => 'meal_plans',
        'title' => 'Plano Low Carb - 7 Dias',
        'description' => 'Plano completo para quem quer reduzir carboidratos',
        'type' => 'plan',
        'duration' => '7 dias',
        'difficulty' => 'Avançado',
        'date' => '2024-01-16'
    ],
    [
        'module' => 'personalized_recipes',
        'title' => 'Smoothie Detox Personalizado',
        'description' => 'Receita adaptada ao seu perfil nutricional',
        'type' => 'recipe',
        'duration' => '5 min',
        'difficulty' => 'Iniciante',
        'date' => '2024-01-15'
    ]
];

require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* === MEMBERS AREA PAGE === */
.members-container {
    padding: 0 24px;
    max-width: 100%;
}

.members-header {
    display: flex;
    align-items: center;
    padding: 16px 0;
    background: transparent;
    position: sticky;
    top: 0;
    z-index: 100;
    gap: 16px;
    margin-bottom: 20px;
}

.members-title {
    flex: 1;
    text-align: center;
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

/* Status da Assinatura */
.subscription-status {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 20px;
    text-align: center;
}

.subscription-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 20px;
    background: var(--primary-orange-gradient);
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 8px;
}

.subscription-info {
    color: var(--text-secondary);
    font-size: 12px;
    margin: 0;
}

/* Grid de Módulos */
.modules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.module-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.module-card:hover {
    transform: translateY(-4px);
    border-color: var(--accent-orange);
    box-shadow: 0 8px 24px rgba(255, 107, 53, 0.2);
}

.module-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-orange-gradient);
}

.module-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.module-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--primary-orange-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-primary);
    font-size: 18px;
}

.module-info h3 {
    color: var(--text-primary);
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 4px 0;
}

.module-info p {
    color: var(--text-secondary);
    font-size: 12px;
    margin: 0;
}

.module-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding: 8px 0;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.stat-item {
    text-align: center;
}

.stat-value {
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 2px;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 10px;
}

.module-actions {
    display: flex;
    gap: 8px;
}

.module-btn {
    flex: 1;
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}

.module-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--accent-orange);
}

.module-btn.primary {
    background: var(--primary-orange-gradient);
    border: none;
    color: var(--text-primary);
}

.module-btn.primary:hover {
    filter: brightness(1.1);
}

/* Conteúdo Recente */
.recent-section {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
}

.section-title {
    color: var(--text-primary);
    font-size: 16px;
    font-weight: 600;
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.recent-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 12px;
}

.recent-item {
    background: rgba(255, 255, 255, 0.06);
    border-radius: 12px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.recent-item:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-2px);
}

.recent-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.recent-title {
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 4px 0;
    line-height: 1.3;
}

.recent-type {
    padding: 2px 6px;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
}

.recent-type.video {
    background: rgba(255, 107, 53, 0.2);
    color: var(--accent-orange);
}

.recent-type.article {
    background: rgba(34, 211, 238, 0.2);
    color: #22d3ee;
}

.recent-type.plan {
    background: rgba(74, 222, 128, 0.2);
    color: #4ade80;
}

.recent-type.recipe {
    background: rgba(248, 113, 113, 0.2);
    color: #f87171;
}

.recent-description {
    color: var(--text-secondary);
    font-size: 12px;
    margin: 0 0 8px 0;
    line-height: 1.4;
}

.recent-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 10px;
    color: var(--text-secondary);
}

.recent-duration {
    display: flex;
    align-items: center;
    gap: 4px;
}

.recent-date {
    display: flex;
    align-items: center;
    gap: 4px;
}

/* Progresso de Aprendizado */
.progress-section {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 20px;
}

.progress-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.progress-item {
    background: rgba(255, 255, 255, 0.06);
    border-radius: 12px;
    padding: 16px;
    text-align: center;
}

.progress-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--primary-orange-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-primary);
    font-size: 20px;
    margin: 0 auto 12px auto;
}

.progress-title {
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 8px 0;
}

.progress-value {
    color: var(--accent-orange);
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 4px;
}

.progress-label {
    color: var(--text-secondary);
    font-size: 10px;
}

/* Botões de Ação */
.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}

.action-btn {
    padding: 16px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.action-btn:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.action-btn.primary {
    background: var(--primary-orange-gradient);
    border: none;
    color: var(--text-primary);
}

.action-btn.primary:hover {
    filter: brightness(1.1);
}

/* Modal de Módulo */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.modal-content {
    background: var(--bg-secondary);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 24px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

.modal-overlay.active .modal-content {
    transform: scale(1);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-title {
    color: var(--text-primary);
    font-size: 18px;
    font-weight: 600;
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 20px;
    cursor: pointer;
    padding: 4px;
}

.modal-body {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.modal-description {
    color: var(--text-secondary);
    font-size: 14px;
    line-height: 1.5;
}

.modal-features {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-primary);
    font-size: 14px;
}

.feature-item i {
    color: #4ade80;
    font-size: 12px;
}

.modal-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}

.btn-modal {
    flex: 1;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-modal.primary {
    background: var(--primary-orange-gradient);
    border: none;
    color: var(--text-primary);
}

.btn-modal:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--accent-orange);
}

.btn-modal.primary:hover {
    filter: brightness(1.1);
}

/* Responsive */
@media (max-width: 768px) {
    .modules-grid {
        grid-template-columns: 1fr;
    }
    
    .recent-grid {
        grid-template-columns: 1fr;
    }
    
    .progress-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .progress-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-actions {
        flex-direction: column;
    }
}
</style>

<div class="app-container">
    <div class="members-container">
        <!-- Header -->
        <div class="members-header">
            <h1 class="members-title">Área de Membros</h1>
        </div>

        <!-- Status da Assinatura -->
        <div class="subscription-status">
            <div class="subscription-badge">
                <i class="fas fa-crown"></i>
                <?php echo ucfirst($subscription_data['subscription_type']); ?>
            </div>
            <p class="subscription-info">
                Válido até <?php echo date('d/m/Y', strtotime($subscription_data['subscription_expires_at'])); ?>
            </p>
        </div>

        <!-- Grid de Módulos -->
        <div class="modules-grid">
            <?php foreach ($modules as $module): ?>
                <div class="module-card" onclick="openModuleModal('<?php echo $module['id']; ?>')">
                    <div class="module-header">
                        <div class="module-icon">
                            <i class="<?php echo $module['icon']; ?>"></i>
                        </div>
                        <div class="module-info">
                            <h3><?php echo htmlspecialchars($module['name']); ?></h3>
                            <p><?php echo htmlspecialchars($module['description']); ?></p>
                        </div>
                    </div>

                    <div class="module-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $module['content_count']; ?></div>
                            <div class="stat-label">Conteúdos</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo date('d/m', strtotime($module['last_update'])); ?></div>
                            <div class="stat-label">Atualizado</div>
                        </div>
                    </div>

                    <div class="module-actions">
                        <button class="module-btn primary" onclick="accessModule('<?php echo $module['id']; ?>', event)">
                            <i class="fas fa-play"></i>
                            Acessar
                        </button>
                        <button class="module-btn" onclick="previewModule('<?php echo $module['id']; ?>', event)">
                            <i class="fas fa-eye"></i>
                            Prévia
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Conteúdo Recente -->
        <div class="recent-section">
            <h3 class="section-title">
                <i class="fas fa-clock"></i>
                Conteúdo Recente
            </h3>
            <div class="recent-grid">
                <?php foreach ($recent_content as $content): ?>
                    <div class="recent-item" onclick="openContent('<?php echo $content['module']; ?>', '<?php echo $content['title']; ?>')">
                        <div class="recent-header">
                            <h4 class="recent-title"><?php echo htmlspecialchars($content['title']); ?></h4>
                            <span class="recent-type <?php echo $content['type']; ?>"><?php echo $content['type']; ?></span>
                        </div>
                        <p class="recent-description"><?php echo htmlspecialchars($content['description']); ?></p>
                        <div class="recent-meta">
                            <div class="recent-duration">
                                <i class="fas fa-clock"></i>
                                <?php echo $content['duration']; ?>
                            </div>
                            <div class="recent-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('d/m', strtotime($content['date'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Progresso de Aprendizado -->
        <div class="progress-section">
            <h3 class="section-title">
                <i class="fas fa-chart-line"></i>
                Seu Progresso
            </h3>
            <div class="progress-grid">
                <div class="progress-item">
                    <div class="progress-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h4 class="progress-title">Conteúdos Concluídos</h4>
                    <div class="progress-value">24</div>
                    <div class="progress-label">de 172 total</div>
                </div>
                <div class="progress-item">
                    <div class="progress-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h4 class="progress-title">Certificados</h4>
                    <div class="progress-value">3</div>
                    <div class="progress-label">módulos completos</div>
                </div>
                <div class="progress-item">
                    <div class="progress-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h4 class="progress-title">Tempo de Estudo</h4>
                    <div class="progress-value">12h</div>
                    <div class="progress-label">esta semana</div>
                </div>
                <div class="progress-item">
                    <div class="progress-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h4 class="progress-title">Pontuação Média</h4>
                    <div class="progress-value">4.8</div>
                    <div class="progress-label">de 5.0</div>
                </div>
            </div>
        </div>

        <!-- Botões de Ação -->
        <div class="action-buttons">
            <a href="<?php echo BASE_APP_URL; ?>/weekly_checkin.php" class="action-btn">
                <i class="fas fa-calendar-week"></i>
                Continuar Check-in Semanal
            </a>
            <a href="<?php echo BASE_APP_URL; ?>/challenge_rooms.php" class="action-btn">
                <i class="fas fa-trophy"></i>
                Ver Salas de Desafio
            </a>
            <button class="action-btn primary" onclick="upgradeSubscription()">
                <i class="fas fa-crown"></i>
                Upgrade Premium
            </button>
        </div>
    </div>
</div>

<!-- Modal de Módulo -->
<div class="modal-overlay" id="moduleModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">Módulo</h3>
            <button class="modal-close" onclick="closeModuleModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p class="modal-description" id="modalDescription">
                Descrição do módulo será carregada aqui.
            </p>
            <div class="modal-features" id="modalFeatures">
                <!-- Features serão carregadas dinamicamente -->
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn-modal" onclick="closeModuleModal()">Fechar</button>
            <button class="btn-modal primary" onclick="accessModuleFromModal()">Acessar Módulo</button>
        </div>
    </div>
</div>

<script>
// === MODULE DATA ===
const modulesData = <?php echo json_encode($modules); ?>;

// === MODAL FUNCTIONS ===
function openModuleModal(moduleId) {
    const module = modulesData[moduleId];
    if (!module) return;
    
    document.getElementById('modalTitle').textContent = module.name;
    document.getElementById('modalDescription').textContent = module.description;
    
    // Adicionar features do módulo
    const featuresContainer = document.getElementById('modalFeatures');
    const features = getModuleFeatures(moduleId);
    
    featuresContainer.innerHTML = features.map(feature => `
        <div class="feature-item">
            <i class="fas fa-check"></i>
            <span>${feature}</span>
        </div>
    `).join('');
    
    // Armazenar módulo atual
    window.currentModuleId = moduleId;
    
    document.getElementById('moduleModal').classList.add('active');
}

function closeModuleModal() {
    document.getElementById('moduleModal').classList.remove('active');
    window.currentModuleId = null;
}

// Fechar modal ao clicar fora
document.getElementById('moduleModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModuleModal();
    }
});

// === MODULE FUNCTIONS ===
function accessModule(moduleId, event) {
    event.stopPropagation();
    
    // Aqui você implementaria o acesso ao módulo
    console.log('Accessing module:', moduleId);
    
    showNotification(`Acessando módulo ${modulesData[moduleId].name}...`, 'success');
    
    // Simular redirecionamento
    setTimeout(() => {
        window.location.href = `<?php echo BASE_APP_URL; ?>/module_content.php?id=${moduleId}`;
    }, 1000);
}

function previewModule(moduleId, event) {
    event.stopPropagation();
    openModuleModal(moduleId);
}

function accessModuleFromModal() {
    if (window.currentModuleId) {
        accessModule(window.currentModuleId);
        closeModuleModal();
    }
}

function openContent(moduleId, contentTitle) {
    // Aqui você implementaria a abertura do conteúdo específico
    console.log('Opening content:', moduleId, contentTitle);
    
    showNotification(`Abrindo "${contentTitle}"...`, 'info');
}

function upgradeSubscription() {
    // Aqui você implementaria o upgrade da assinatura
    console.log('Upgrading subscription...');
    
    showNotification('Redirecionando para upgrade...', 'info');
    
    // Simular redirecionamento
    setTimeout(() => {
        // window.location.href = '<?php echo BASE_APP_URL; ?>/subscription_upgrade.php';
        alert('Funcionalidade de upgrade em desenvolvimento');
    }, 1000);
}

// === HELPER FUNCTIONS ===
function getModuleFeatures(moduleId) {
    const features = {
        'chef_cooking': [
            'Receitas exclusivas de chefs profissionais',
            'Técnicas de corte e preparo',
            'Vídeos tutoriais passo a passo',
            'Lista de ingredientes e utensílios',
            'Dicas de apresentação'
        ],
        'supplements': [
            'Guia completo de suplementação',
            'Análise de cada tipo de suplemento',
            'Dosagens recomendadas',
                    'Interações e contraindicações',
            'Marcas e produtos recomendados'
        ],
        'meal_plans': [
            'Planos personalizados por objetivo',
            'Cronograma semanal completo',
            'Lista de compras automática',
            'Substituições e variações',
            'Acompanhamento nutricional'
        ],
        'personalized_recipes': [
            'Receitas adaptadas ao seu perfil',
            'Considera suas restrições alimentares',
            'Ajusta por preferências pessoais',
            'Calcula macros automaticamente',
            'Sugestões de acompanhamentos'
        ]
    };
    
    return features[moduleId] || ['Conteúdo exclusivo', 'Acesso premium', 'Suporte especializado'];
}

// === NOTIFICATION SYSTEM ===
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    Object.assign(notification.style, {
        position: 'fixed',
        top: '20px',
        right: '20px',
        padding: '12px 16px',
        borderRadius: '8px',
        color: 'white',
        fontWeight: '600',
        zIndex: '10000',
        opacity: '0',
        transform: 'translateX(100%)',
        transition: 'all 0.3s ease'
    });
    
    if (type === 'success') {
        notification.style.background = '#4ade80';
    } else if (type === 'error') {
        notification.style.background = '#f87171';
    } else {
        notification.style.background = '#22d3ee';
    }
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// === INITIALIZATION ===
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar funcionalidades da página
    console.log('Members area initialized');
});
</script>

<?php require_once APP_ROOT_PATH . '/includes/layout_footer.php'; ?>




