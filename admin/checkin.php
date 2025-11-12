<?php
// admin/checkin.php - Gerenciamento de Check-in

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'checkin';
$page_title = 'Check-in';

$admin_id = $_SESSION['admin_id'] ?? 1;

// --- Lógica de busca e filtro ---
$search_term = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// --- Criar check-in padrão se não existir ---
$default_checkin_query = "SELECT id FROM sf_checkin_configs WHERE admin_id = ? AND name = 'Feedback Semanal' LIMIT 1";
$stmt_default = $conn->prepare($default_checkin_query);
$stmt_default->bind_param("i", $admin_id);
$stmt_default->execute();
$default_result = $stmt_default->get_result();

if ($default_result->num_rows === 0) {
    // Criar check-in padrão
    $conn->begin_transaction();
    try {
        // Inserir configuração
        $insert_config = "INSERT INTO sf_checkin_configs (admin_id, name, description, day_of_week, is_active, created_at, updated_at) VALUES (?, 'Feedback Semanal', 'Check-in semanal padrão para acompanhamento dos pacientes', 0, 1, NOW(), NOW())";
        $stmt_insert = $conn->prepare($insert_config);
        $stmt_insert->bind_param("i", $admin_id);
        $stmt_insert->execute();
        $default_checkin_id = $conn->insert_id;
        $stmt_insert->close();
        
        // Inserir perguntas padrão
        $default_questions = [
            ['question_text' => 'Seu nome completo', 'question_type' => 'text', 'order_index' => 0],
            ['question_text' => 'Você teve alguma mudança significativa na rotina?', 'question_type' => 'text', 'order_index' => 1],
            ['question_text' => 'O que tem achado do plano? Tem tido dificuldade com alguma parte do planejamento?', 'question_type' => 'text', 'order_index' => 2],
            ['question_text' => 'Nessa semana você acabou faltando algum treino ou aeróbico?', 'question_type' => 'multiple_choice', 'options' => json_encode(['Sim', 'Não']), 'order_index' => 3],
            ['question_text' => 'Quantos treinos ao todo você fez essa última semana?', 'question_type' => 'text', 'order_index' => 4],
            ['question_text' => 'Tiveram refeições sociais essa semana? Houve alguma refeição fora do plano?', 'question_type' => 'multiple_choice', 'options' => json_encode(['Sim', 'Não']), 'order_index' => 5],
            ['question_text' => 'O que você teve de refeições fora do planejado?', 'question_type' => 'text', 'order_index' => 6],
            ['question_text' => 'Em relação ao seu apetite, se fosse dar uma nota de 0 a 10, qual você daria? (Apetite é VONTADE de comer)', 'question_type' => 'scale', 'options' => json_encode(['Muita vontade de comer! (10)', '7.5', '5', '2.5', 'Nenhuma vontade de comer (0)']), 'order_index' => 7],
            ['question_text' => 'E em relação aos seus níveis de fome durante o dia, a sensação de barriga vazia e de que a comida não tem sido suficiente:', 'question_type' => 'scale', 'options' => json_encode(['Muita fome! (10)', '7.5', '5', '2.5', 'Fome zerada (0)']), 'order_index' => 8],
            ['question_text' => 'E a motivação? Tá acordando todos os dias no gás pra cuidar da saúde? De 0 a 10!', 'question_type' => 'scale', 'options' => json_encode(['Motivação nas alturas! (10)', '7.5', '5', '2.5', 'Sem motivação nenhuma (0)']), 'order_index' => 9],
            ['question_text' => 'Em relação ao desejo por furar o cardápio, comer umas gostosuras e tudo mais, como está? De 0 a 10!', 'question_type' => 'scale', 'options' => json_encode(['Muita vontade de furar! (10)', '7.5', '5', '2.5', 'Nenhuma vontade de furar! (0)']), 'order_index' => 10],
            ['question_text' => 'Pensando nisso, como está o seu humor atualmente? Se fosse classificar de 0 a 10?', 'question_type' => 'scale', 'options' => json_encode(['Humor está maravilhoso! (10)', '7.5', '5', '2.5', 'Humor está péssimo! (0)']), 'order_index' => 11],
            ['question_text' => 'Como que está o seu sono? Olhando tanto pra quantidade quanto pra qualidade?', 'question_type' => 'scale', 'options' => json_encode(['Dormindo feito um bebê (10)', '7.5', '5', '2.5', 'Sono está horrível! (0)']), 'order_index' => 12],
            ['question_text' => 'Você vem se recuperando bem? Tanto dos exercícios quanto das atividades do dia-a-dia? De 0 a 10!', 'question_type' => 'scale', 'options' => json_encode(['Recuperação incrível (10)', '7.5', '5', '2.5', 'Estou sempre quebrado! (0)']), 'order_index' => 13],
            ['question_text' => 'Está indo a banheiro todos os dias? Como está seu intestino? Dê uma nota de 0 a 10!', 'question_type' => 'scale', 'options' => json_encode(['Intestino reloginho, perfeito! (10)', '7.5', '5', '2.5', 'Intestino travado (0)']), 'order_index' => 14],
            ['question_text' => 'E a sua performance, tanto nos exercícios quanto nas atividades mentais, vai bem? De 0 a 10!', 'question_type' => 'scale', 'options' => json_encode(['Performance em alta! (10)', '7.5', '5', '2.5', 'Está em baixa, viu? (0)']), 'order_index' => 15],
            ['question_text' => 'E o estresse? Se fosse classificar de 0 a 10 os níveis de estresse na sua vida, como está?', 'question_type' => 'scale', 'options' => json_encode(['Vida muito estressante! (10)', '7.5', '5', '2.5', 'Vida tranquila! (0)']), 'order_index' => 16],
            ['question_text' => 'De todos esses marcadores que você acabou de me passar, tem algum problema específico que você queira comentar sobre?', 'question_type' => 'text', 'order_index' => 17],
            ['question_text' => 'Se fosse pra dar uma nota pra essa semana, qual você daria?', 'question_type' => 'scale', 'options' => json_encode(['Nota 10, foi maravilhosa!', 'Nota 7.5, foi boa mas poderia ter sido melhor', 'Nota 5.0, foi mediana', 'Nota 2.5, não foi tão boa', 'Nota 0, foi péssima']), 'order_index' => 18],
            ['question_text' => 'Caso você tenha se pesado essa semana, me informe abaixo seu peso atual', 'question_type' => 'text', 'order_index' => 19],
        ];
        
        $stmt_question = $conn->prepare("INSERT INTO sf_checkin_questions (config_id, question_text, question_type, options, order_index, is_required, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())");
        foreach ($default_questions as $q) {
            $options = $q['options'] ?? null;
            $stmt_question->bind_param("isssi", $default_checkin_id, $q['question_text'], $q['question_type'], $options, $q['order_index']);
            $stmt_question->execute();
        }
        $stmt_question->close();
        
        // Distribuir para todos os usuários (sem grupos específicos, todos podem ver)
        // Não vamos adicionar distribuições específicas, deixando disponível para todos
        
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Erro ao criar check-in padrão: " . $e->getMessage());
    }
}
$stmt_default->close();

// --- Estatísticas gerais ---
$stats = [];

// Total de check-ins
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM sf_checkin_configs WHERE admin_id = $admin_id")->fetch_assoc()['count'];

// Por status
$stats_query = "SELECT is_active, COUNT(*) as count 
                FROM sf_checkin_configs 
                WHERE admin_id = $admin_id
                GROUP BY is_active";
$stats_result = $conn->query($stats_query);
$stats_by_status = [1 => 0, 0 => 0];
while ($row = $stats_result->fetch_assoc()) {
    $stats_by_status[$row['is_active']] = $row['count'];
}
$stats['active'] = $stats_by_status[1];
$stats['inactive'] = $stats_by_status[0];

// Total de respostas
$stats['responses'] = $conn->query("SELECT COUNT(DISTINCT user_id, config_id, DATE(submitted_at)) as count FROM sf_checkin_responses")->fetch_assoc()['count'];

// --- Construir query de busca ---
$sql = "SELECT 
    cc.*,
    COUNT(DISTINCT cd.id) as distribution_count,
    COUNT(DISTINCT cq.id) as questions_count
    FROM sf_checkin_configs cc
    LEFT JOIN sf_checkin_distribution cd ON cc.id = cd.config_id
    LEFT JOIN sf_checkin_questions cq ON cc.id = cq.config_id
    WHERE cc.admin_id = ?";
$conditions = [];
$params = [$admin_id];
$types = 'i';

if (!empty($search_term)) {
    $conditions[] = "cc.name LIKE ?";
    $params[] = '%' . $search_term . '%';
    $types .= 's';
}

if (!empty($status_filter)) {
    $conditions[] = $status_filter === 'active' ? "cc.is_active = 1" : "cc.is_active = 0";
}

if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}

$sql .= " GROUP BY cc.id ORDER BY cc.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

// Executar query
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $checkins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $checkins = [];
}

// Contar total para paginação
$count_sql = "SELECT COUNT(*) as count FROM sf_checkin_configs cc WHERE cc.admin_id = ?";
$count_params = [$admin_id];
$count_types = 'i';

if (!empty($search_term)) {
    $count_sql .= " AND cc.name LIKE ?";
    $count_params[] = '%' . $search_term . '%';
    $count_types .= 's';
}

if (!empty($status_filter)) {
    $count_sql .= $status_filter === 'active' ? " AND cc.is_active = 1" : " AND cc.is_active = 0";
}

$count_stmt = $conn->prepare($count_sql);
if ($count_stmt) {
    $count_stmt->bind_param($count_types, ...$count_params);
    $count_stmt->execute();
    $total_items = $count_stmt->get_result()->fetch_assoc()['count'];
    $count_stmt->close();
} else {
    $total_items = 0;
}

$total_pages = ceil($total_items / $per_page);


require_once __DIR__ . '/includes/header.php';
?>

<style>
.checkin-page {
    padding: 1.5rem 2rem;
    min-height: 100vh;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.btn-add-checkin-circular {
    width: 64px;
    height: 64px;
    min-width: 64px;
    min-height: 64px;
    max-width: 64px;
    max-height: 64px;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.08);
    border: 1px solid rgba(255, 107, 0, 0.2);
    color: var(--accent-orange);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    margin: 0;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.btn-add-checkin-circular:hover {
    background: rgba(255, 107, 0, 0.15);
    border-color: var(--accent-orange);
    transform: scale(1.05);
}

.btn-add-checkin-circular i {
    font-size: 1.5rem;
}

.checkin-page * {
    box-shadow: none !important;
}

.header-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 1.5rem !important;
    margin-bottom: 2rem !important;
}

.header-title {
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    flex-wrap: wrap;
}

.header-title h2 {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-top: 1.5rem;
}

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}

.stat-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 16px !important;
    padding: 1.25rem 1rem !important;
    text-align: center !important;
    transition: all 0.3s ease !important;
    cursor: pointer !important;
    aspect-ratio: 1.4 !important;
    min-height: 110px !important;
    display: flex !important;
    flex-direction: column !important;
    justify-content: center !important;
    align-items: center !important;
}

.stat-card:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: var(--accent-orange) !important;
    transform: translateY(-2px);
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: var(--accent-orange);
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Toggle Switch - igual challenge_groups.php */
.toggle-switch-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.toggle-switch-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-secondary);
    min-width: 50px;
    text-align: left;
    transition: color 0.3s ease;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
    cursor: pointer;
    flex-shrink: 0;
}

.toggle-switch-input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-switch-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(107, 114, 128, 0.3);
    transition: 0.3s;
    border-radius: 26px;
}

.toggle-switch-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

.toggle-switch-input:checked + .toggle-switch-slider {
    background-color: #22C55E;
}

.toggle-switch-input:checked + .toggle-switch-slider:before {
    transform: translateX(24px);
}

/* Checkin Grid - Estilo igual challenge_groups.php */
.checkin-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 380px), 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
    width: 100%;
    box-sizing: border-box;
}

.checkin-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 12px !important;
    padding: 1rem !important;
    transition: all 0.3s ease !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 0.75rem !important;
    box-shadow: none !important;
    filter: none !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    width: 100% !important;
    box-sizing: border-box !important;
    overflow: hidden !important;
    min-width: 0 !important;
}

.checkin-card:hover {
    background: rgba(255, 255, 255, 0.08) !important;
    border-color: var(--accent-orange) !important;
    transform: none !important;
    box-shadow: none !important;
}

/* Header do card - igual challenge_groups.php */
.checkin-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
    flex-wrap: wrap;
    width: 100%;
    box-sizing: border-box;
    min-width: 0;
}

.checkin-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    flex: 1;
    min-width: 0;
    word-wrap: break-word;
    overflow-wrap: break-word;
    hyphens: auto;
}

.checkin-status {
    padding: 0.375rem 0.75rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    flex-shrink: 0;
}

.checkin-status.active {
    background: rgba(16, 185, 129, 0.2);
    color: #10B981;
    border: 1px solid #10B981;
}

.checkin-status.inactive {
    background: rgba(107, 114, 128, 0.2);
    color: #6B7280;
    border: 1px solid #6B7280;
}

.checkin-description {
    color: var(--text-secondary);
    font-size: 0.9rem;
    line-height: 1.5;
    margin: 0 0 1rem 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    word-wrap: break-word;
    overflow-wrap: break-word;
    width: 100%;
    box-sizing: border-box;
    flex-shrink: 0;
}

.checkin-info {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    width: 100%;
    box-sizing: border-box;
    flex-shrink: 0;
    margin-bottom: 0;
}

.checkin-info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
    flex-shrink: 0;
    min-width: 0;
    white-space: nowrap;
}

.checkin-info-item i {
    color: var(--accent-orange);
    font-size: 0.875rem;
}

/* Actions - igual challenge_groups.php */
.checkin-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: auto;
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    align-items: center;
    flex-wrap: wrap;
    width: 100%;
    box-sizing: border-box;
    min-width: 0;
    flex-shrink: 0;
}

.btn-action {
    flex: 1;
    min-width: 0;
    max-width: 100%;
    padding: 0.625rem 0.75rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 0.8125rem;
    white-space: nowrap;
    overflow: visible;
    text-overflow: clip;
    box-sizing: border-box;
    border: 1px solid;
    background: transparent;
    color: var(--text-primary);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
    position: relative;
    line-height: 1.2;
}

.btn-action i {
    flex-shrink: 0;
    font-size: 0.8125rem;
}

/* Garantir que o texto dos botões não seja cortado */
.btn-action {
    text-align: center;
}

.btn-action:hover {
    background: rgba(255, 107, 0, 0.1);
    border-color: var(--accent-orange);
    color: var(--accent-orange);
}

.btn-action.btn-view {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.3);
    color: #3B82F6;
}

.btn-action.btn-view:hover {
    background: rgba(59, 130, 246, 0.2);
    border-color: #3B82F6;
    color: #3B82F6;
}

.btn-action.btn-edit {
    background: rgba(255, 107, 0, 0.1);
    color: var(--accent-orange);
    border-color: rgba(255, 107, 0, 0.2);
}

.btn-action.btn-edit:hover {
    background: rgba(255, 107, 0, 0.2);
    border-color: var(--accent-orange);
}

.btn-action.btn-danger {
    background: rgba(239, 68, 68, 0.1);
    color: #EF4444;
    border-color: rgba(239, 68, 68, 0.2);
}

.btn-action.btn-danger:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: #EF4444;
}

/* Responsividade dos Cards de Check-in - igual challenge_groups.php */
@media (max-width: 1024px) {
    .checkin-grid {
        grid-template-columns: repeat(auto-fill, minmax(min(100%, 340px), 1fr));
        gap: 1.25rem;
    }
    
    .btn-action {
        font-size: 0.75rem;
        padding: 0.5rem 0.5rem;
        gap: 0.375rem;
        flex-shrink: 1;
        max-width: 100%;
    }
    
    .btn-action i {
        font-size: 0.75rem;
        flex-shrink: 0;
    }
}

@media (max-width: 768px) {
    .checkin-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .checkin-card {
        min-height: auto !important;
    }
    
    .checkin-actions {
        flex-direction: row;
        flex-wrap: wrap;
    }
    
    .btn-action {
        flex: 1 1 calc(50% - 0.25rem);
        min-width: 0;
        max-width: calc(50% - 0.25rem);
        font-size: 0.75rem;
        padding: 0.5rem 0.5rem;
    }
    
    .btn-action i {
        font-size: 0.75rem;
        flex-shrink: 0;
    }
    
    .checkin-card-header {
        flex-direction: row;
        align-items: center;
    }
}

@media (max-width: 480px) {
    .checkin-card {
        padding: 0.875rem !important;
        gap: 0.625rem !important;
    }
    
    .checkin-name {
        font-size: 1rem;
    }
    
    .checkin-actions {
        flex-direction: column;
    }
    
    .btn-action {
        flex: 1 1 100%;
        max-width: 100%;
        width: 100%;
    }
}

.btn-primary {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    color: white;
    padding: 0.875rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
}

.btn-primary:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

</style>

<div class="checkin-page">
    <div class="header-card">
        <div class="header-title">
            <div>
                <h2>Check-in</h2>
                <p>Gerencie os check-ins semanais dos seus pacientes</p>
            </div>
            <button class="btn-add-checkin-circular" onclick="openCreateCheckinModal()" title="Criar novo check-in">
                <i class="fas fa-plus"></i>
            </button>
        </div>

        <div class="stats-grid">
            <div class="stat-card" onclick="filterByStatus('')">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card" onclick="filterByStatus('active')">
                <div class="stat-number"><?php echo $stats['active']; ?></div>
                <div class="stat-label">Ativos</div>
            </div>
            <div class="stat-card" onclick="filterByStatus('inactive')">
                <div class="stat-number"><?php echo $stats['inactive']; ?></div>
                <div class="stat-label">Inativos</div>
            </div>
            <div class="stat-card" onclick="window.location.href='checkin_responses.php'">
                <div class="stat-number"><?php echo $stats['responses']; ?></div>
                <div class="stat-label">Respostas</div>
            </div>
        </div>
    </div>

    <div class="checkin-grid">
        <?php if (empty($checkins)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-secondary);">
                <i class="fas fa-clipboard-check" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <p>Nenhum check-in encontrado. Crie o primeiro!</p>
            </div>
        <?php else: ?>
            <?php foreach ($checkins as $checkin): ?>
                <div class="checkin-card">
                    <div class="checkin-card-header">
                        <h3 class="checkin-name"><?php echo htmlspecialchars($checkin['name']); ?></h3>
                        <div class="toggle-switch-wrapper" onclick="event.stopPropagation()">
                            <label class="toggle-switch">
                                <input type="checkbox" 
                                       class="toggle-switch-input checkin-status-toggle" 
                                       data-checkin-id="<?php echo $checkin['id']; ?>"
                                       <?php echo $checkin['is_active'] ? 'checked' : ''; ?>
                                       onchange="toggleCheckinStatus(<?php echo $checkin['id']; ?>, this.checked)">
                                <span class="toggle-switch-slider"></span>
                            </label>
                            <span class="toggle-switch-label checkin-status-label-<?php echo $checkin['id']; ?>" style="color: <?php echo $checkin['is_active'] ? '#22C55E' : '#EF4444'; ?>; font-weight: <?php echo $checkin['is_active'] ? '700' : '600'; ?>;"><?php echo $checkin['is_active'] ? 'Ativo' : 'Inativo'; ?></span>
                        </div>
                    </div>
                    <?php if (!empty($checkin['description'])): ?>
                        <p class="checkin-description"><?php echo htmlspecialchars($checkin['description']); ?></p>
                    <?php endif; ?>
                    
                    <div class="checkin-info">
                        <div class="checkin-info-item">
                            <i class="fas fa-calendar"></i>
                            <span>
                                <?php 
                                $days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                                echo $days[$checkin['day_of_week']] ?? 'Não definido';
                                ?>
                            </span>
                        </div>
                        <div class="checkin-info-item">
                            <i class="fas fa-question-circle"></i>
                            <span><?php echo $checkin['questions_count']; ?> perguntas</span>
                        </div>
                        <div class="checkin-info-item">
                            <i class="fas fa-users"></i>
                            <span><?php echo $checkin['distribution_count']; ?> distribuições</span>
                        </div>
                    </div>
                    
                    <div class="checkin-actions" onclick="event.stopPropagation()">
                        <button class="btn-action btn-view" onclick="viewResponses(<?php echo $checkin['id']; ?>)" title="Ver Respostas">
                            <i class="fas fa-eye"></i> Respostas
                        </button>
                        <button class="btn-action btn-edit" onclick="editCheckinFlow(<?php echo $checkin['id']; ?>)" title="Editor">
                            <i class="fas fa-edit"></i> Editor
                        </button>
                        <button class="btn-action btn-danger" onclick="deleteCheckin(<?php echo $checkin['id']; ?>)" title="Excluir">
                            <i class="fas fa-trash"></i> Excluir
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function openCreateCheckinModal() {
    // Criar check-in básico e redirecionar para o editor
    if (!confirm('Criar novo check-in? Você será redirecionado para o editor.')) {
        return;
    }
    
    fetch('ajax_checkin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'create_checkin',
            name: 'Novo Check-in',
            description: '',
            day_of_week: 0,
            is_active: 1
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'checkin_flow_editor.php?id=' + data.checkin_id;
        } else {
            alert('Erro ao criar check-in: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao criar check-in');
    });
}

function deleteCheckin(id) {
    if (!confirm('Tem certeza que deseja excluir este check-in?')) return;
    
    fetch('ajax_checkin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete', checkin_id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Check-in excluído com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    });
}

function viewCheckin(id) {
    // Função para quando clicar no card (pode abrir modal ou página de detalhes)
    viewResponses(id);
}

function viewResponses(id) {
    window.location.href = 'checkin_responses.php?id=' + id;
}

function editCheckinFlow(id) {
    window.location.href = 'checkin_flow_editor.php?id=' + id;
}

function searchCheckins(term) {
    const url = new URL(window.location);
    if (term) {
        url.searchParams.set('search', term);
    } else {
        url.searchParams.delete('search');
    }
    window.location.href = url.toString();
}

function filterByStatus(status) {
    const url = new URL(window.location);
    if (status) {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    window.location.href = url.toString();
}

// Toggle status do check-in em tempo real
function toggleCheckinStatus(checkinId, isActive) {
    const label = document.querySelector(`.checkin-status-label-${checkinId}`);
    
    fetch('ajax_checkin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'update_status',
            checkin_id: checkinId,
            is_active: isActive ? 1 : 0
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (label) {
                if (isActive) {
                    label.textContent = 'Ativo';
                    label.style.color = '#22C55E';
                    label.style.fontWeight = '700';
                } else {
                    label.textContent = 'Inativo';
                    label.style.color = '#EF4444';
                    label.style.fontWeight = '600';
                }
            }
        } else {
            alert('Erro ao atualizar status: ' + data.message);
            // Reverter toggle
            const toggle = document.querySelector(`.checkin-status-toggle[data-checkin-id="${checkinId}"]`);
            if (toggle) {
                toggle.checked = !isActive;
            }
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao atualizar status');
        // Reverter toggle
        const toggle = document.querySelector(`.checkin-status-toggle[data-checkin-id="${checkinId}"]`);
        if (toggle) {
            toggle.checked = !isActive;
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

