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

// Buscar grupos e usuários para o modal
$groups_query = "SELECT id, group_name as name FROM sf_user_groups WHERE admin_id = $admin_id AND is_active = 1 ORDER BY group_name";
$groups_result = $conn->query($groups_query);
$groups = $groups_result->fetch_all(MYSQLI_ASSOC);

$users_query = "SELECT u.id, u.name, u.email, up.profile_image_filename 
                FROM sf_users u 
                LEFT JOIN sf_user_profiles up ON u.id = up.user_id 
                WHERE u.onboarding_complete = 1
                ORDER BY u.name";
$users_result = $conn->query($users_query);
$users = $users_result->fetch_all(MYSQLI_ASSOC);

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

.filter-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 1.25rem !important;
    margin-bottom: 2rem !important;
}

.filter-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.search-input {
    flex: 1;
    min-width: 250px;
    padding: 0.875rem 1.25rem;
    font-size: 0.95rem;
    color: var(--text-primary);
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    outline: none;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
}

.search-input:focus {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.checkin-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(100%, 420px), 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.checkin-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 12px !important;
    padding: 1.25rem !important;
    transition: all 0.3s ease !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 1rem !important;
    min-height: 200px !important;
}

.checkin-card:hover {
    border-color: var(--accent-orange) !important;
    background: rgba(255, 255, 255, 0.08) !important;
    transform: translateY(-2px);
}

.checkin-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}

.checkin-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    flex: 1;
}

.checkin-meta {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.checkin-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: auto;
}

.btn-action {
    flex: 1;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    border: 1px solid var(--glass-border);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.875rem;
}

.btn-action:hover {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    color: white;
}

.btn-action.btn-danger:hover {
    background: #ef4444;
    border-color: #ef4444;
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

/* Modal Styles - Premium Design */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(12px);
    animation: fadeIn 0.4s ease;
    overflow-y: auto;
    padding: 20px;
    box-sizing: border-box;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-content {
    background: var(--surface-color);
    margin: 0 auto;
    padding: 0;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    width: 100%;
    max-width: 900px;
    max-height: 90vh;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.6);
    animation: slideIn 0.4s ease;
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
}

.modal-header {
    background: linear-gradient(135deg, var(--accent-orange) 0%, #ff8533 100%);
    color: white;
    padding: 25px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
    border-radius: 16px 16px 0 0;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
}

.modal-close {
    color: white;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.15);
    flex-shrink: 0;
    outline: none;
    border: none;
    box-shadow: none;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: scale(1.05);
}

.modal-close:active {
    transform: scale(0.95);
    background: rgba(255, 255, 255, 0.35);
}

.modal-body {
    padding: 30px;
    background: var(--surface-color);
    flex: 1;
    overflow-y: auto;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--text-primary);
    font-weight: 600;
    font-size: 0.95rem;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.875rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 0.95rem;
    font-family: 'Montserrat', sans-serif;
    outline: none;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.form-group input[type="checkbox"] {
    width: auto;
    margin: 0;
    cursor: pointer;
}

.questions-list {
    margin-top: 1rem;
}

.question-item {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    position: relative;
    transition: all 0.3s ease;
}

.question-item:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 107, 0, 0.3);
}

.question-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.question-item-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-icon {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 0.5rem;
    transition: color 0.3s ease;
}

.btn-icon:hover {
    color: var(--accent-orange);
}

.distribution-section {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--glass-border);
}

.distribution-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.distribution-tab {
    padding: 0.75rem 1.5rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
}

.distribution-tab.active {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    color: white;
}

.distribution-content {
    max-height: 300px;
    overflow-y: auto;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    border: 1px solid var(--glass-border);
}

.distribution-content::-webkit-scrollbar {
    width: 8px;
}

.distribution-content::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
}

.distribution-content::-webkit-scrollbar-thumb {
    background: rgba(255, 107, 0, 0.3);
    border-radius: 4px;
}

.distribution-content::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 107, 0, 0.5);
}

.distribution-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
}

.distribution-item-name {
    color: var(--text-primary);
    font-weight: 500;
}

.distribution-item-remove {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    padding: 0.25rem 0.5rem;
}

.distribution-item-remove:hover {
    opacity: 0.8;
}

.day-selector {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.day-option {
    padding: 0.75rem;
    text-align: center;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    font-size: 0.875rem;
}

.day-option:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.day-option.selected {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    color: white;
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

    <div class="filter-card">
        <div class="filter-row">
            <input type="text" 
                   class="search-input" 
                   placeholder="Buscar check-ins..." 
                   value="<?php echo htmlspecialchars($search_term); ?>"
                   onkeyup="if(event.key === 'Enter') searchCheckins(this.value)">
            <button class="btn-primary" onclick="searchCheckins(document.querySelector('.search-input').value)">
                <i class="fas fa-search"></i> Buscar
            </button>
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
                        <div style="flex: 1;">
                            <h3 class="checkin-name"><?php echo htmlspecialchars($checkin['name']); ?></h3>
                            <div class="checkin-meta">
                                <span><i class="fas fa-calendar"></i> 
                                    <?php 
                                    $days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                                    echo $days[$checkin['day_of_week']] ?? 'Não definido';
                                    ?>
                                </span>
                                <span><i class="fas fa-question-circle"></i> <?php echo $checkin['questions_count']; ?> perguntas</span>
                                <span><i class="fas fa-users"></i> <?php echo $checkin['distribution_count']; ?> distribuições</span>
                                <span><i class="fas fa-circle" style="color: <?php echo $checkin['is_active'] ? '#22c55e' : '#ef4444'; ?>; font-size: 0.5rem;"></i> 
                                    <?php echo $checkin['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="checkin-actions">
                        <button class="btn-action" onclick="viewResponses(<?php echo $checkin['id']; ?>)">
                            <i class="fas fa-eye"></i> Ver Respostas
                        </button>
                        <button class="btn-action" onclick="editCheckinFlow(<?php echo $checkin['id']; ?>)">
                            <i class="fas fa-project-diagram"></i> Editor de Fluxo
                        </button>
                        <button class="btn-action" onclick="editCheckin(<?php echo $checkin['id']; ?>)">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                        <button class="btn-action btn-danger" onclick="deleteCheckin(<?php echo $checkin['id']; ?>)">
                            <i class="fas fa-trash"></i> Excluir
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para criar/editar check-in -->
<div id="checkinModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Criar Check-in</h3>
            <button class="modal-close" onclick="closeCheckinModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="checkinForm" onsubmit="saveCheckin(event)">
                <input type="hidden" id="checkinId" name="checkin_id" value="0">
                
                <div class="form-group">
                    <label>Nome do Check-in *</label>
                    <input type="text" id="checkinName" name="name" required placeholder="Ex: Feedback Semanal">
                </div>

                <div class="form-group">
                    <label>Descrição</label>
                    <textarea id="checkinDescription" name="description" placeholder="Descrição opcional do check-in"></textarea>
                </div>

                <div class="form-group">
                    <label>Dia da Semana *</label>
                    <div class="day-selector">
                        <?php 
                        $days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                        for ($i = 0; $i < 7; $i++): 
                        ?>
                            <div class="day-option" data-day="<?php echo $i; ?>" onclick="selectDay(<?php echo $i; ?>)">
                                <?php echo $days[$i]; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" id="dayOfWeek" name="day_of_week" value="0">
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" id="checkinActive" name="is_active" checked style="width: auto; margin: 0;"> 
                        <span>Check-in ativo</span>
                    </label>
                </div>

            <div class="questions-list">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <label style="margin: 0;">Perguntas</label>
                    <button type="button" class="btn-primary" onclick="addQuestion()" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                        <i class="fas fa-plus"></i> Adicionar Pergunta
                    </button>
                </div>
                <div id="questionsContainer"></div>
            </div>

            <div class="distribution-section">
                <label>Distribuição</label>
                <div class="distribution-tabs">
                    <div class="distribution-tab active" onclick="switchDistributionTab('groups')">Grupos</div>
                    <div class="distribution-tab" onclick="switchDistributionTab('users')">Usuários</div>
                </div>
                <div id="groupsDistribution" class="distribution-content">
                    <?php foreach ($groups as $group): ?>
                        <div class="distribution-item">
                            <span class="distribution-item-name"><?php echo htmlspecialchars($group['name']); ?></span>
                            <button type="button" class="distribution-item-remove" onclick="toggleDistribution('group', <?php echo $group['id']; ?>, this)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="usersDistribution" class="distribution-content" style="display: none;">
                    <?php foreach ($users as $user): ?>
                        <div class="distribution-item">
                            <span class="distribution-item-name"><?php echo htmlspecialchars($user['name']); ?></span>
                            <button type="button" class="distribution-item-remove" onclick="toggleDistribution('user', <?php echo $user['id']; ?>, this)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            </form>
        </div>
        
        <div class="modal-footer" style="padding: 25px 30px; background: var(--surface-color); border-top: 1px solid var(--border-color); display: flex; gap: 1rem; justify-content: flex-end; flex-shrink: 0;">
            <button type="button" class="btn-action" onclick="closeCheckinModal()" style="min-width: 120px;">
                Cancelar
            </button>
            <button type="submit" form="checkinForm" class="btn-primary" style="min-width: 120px;">
                <i class="fas fa-save"></i> Salvar
            </button>
        </div>
    </div>
</div>

<script>
const questions = [];
let selectedDay = 0;
let distributionData = { groups: [], users: [] };

function openCreateCheckinModal() {
    document.getElementById('checkinId').value = '0';
    document.getElementById('checkinName').value = '';
    document.getElementById('checkinDescription').value = '';
    document.getElementById('checkinActive').checked = true;
    document.getElementById('modalTitle').textContent = 'Criar Check-in';
    questions.length = 0;
    distributionData = { groups: [], users: [] };
    selectedDay = 0;
    updateDaySelector();
    renderQuestions();
    updateDistributionUI();
    document.getElementById('checkinModal').classList.add('active');
}

function closeCheckinModal() {
    document.getElementById('checkinModal').classList.remove('active');
}

function selectDay(day) {
    selectedDay = day;
    updateDaySelector();
    document.getElementById('dayOfWeek').value = day;
}

function updateDaySelector() {
    document.querySelectorAll('.day-option').forEach((el, idx) => {
        el.classList.toggle('selected', idx === selectedDay);
    });
}

function addQuestion() {
    questions.push({
        id: null,
        question_text: '',
        question_type: 'text',
        options: null,
        order_index: questions.length,
        is_required: true
    });
    renderQuestions();
}

function removeQuestion(index) {
    questions.splice(index, 1);
    questions.forEach((q, idx) => q.order_index = idx);
    renderQuestions();
}

function renderQuestions() {
    const container = document.getElementById('questionsContainer');
    container.innerHTML = questions.map((q, idx) => `
        <div class="question-item">
            <div class="question-item-header">
                <strong>Pergunta ${idx + 1}</strong>
                <div class="question-item-actions">
                    <button type="button" class="btn-icon" onclick="removeQuestion(${idx})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label>Texto da Pergunta *</label>
                <textarea onchange="questions[${idx}].question_text = this.value" required>${q.question_text}</textarea>
            </div>
            <div class="form-group">
                <label>Tipo</label>
                <select onchange="questions[${idx}].question_type = this.value; updateQuestionType(${idx})">
                    <option value="text" ${q.question_type === 'text' ? 'selected' : ''}>Texto Livre</option>
                    <option value="multiple_choice" ${q.question_type === 'multiple_choice' ? 'selected' : ''}>Múltipla Escolha</option>
                    <option value="scale" ${q.question_type === 'scale' ? 'selected' : ''}>Escala (0-10)</option>
                </select>
            </div>
            <div id="questionOptions${idx}" style="display: ${q.question_type === 'text' ? 'none' : 'block'};">
                <label>Opções (uma por linha)</label>
                <textarea onchange="updateQuestionOptions(${idx}, this.value)" placeholder="Exemplo para escala:&#10;0&#10;2.5&#10;5&#10;7.5&#10;10"></textarea>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" ${q.is_required ? 'checked' : ''} onchange="questions[${idx}].is_required = this.checked">
                    Obrigatória
                </label>
            </div>
        </div>
    `).join('');
}

function updateQuestionType(index) {
    renderQuestions();
}

function updateQuestionOptions(index, value) {
    const lines = value.split('\n').filter(l => l.trim());
    questions[index].options = lines.length > 0 ? JSON.stringify(lines) : null;
}

function switchDistributionTab(tab) {
    document.querySelectorAll('.distribution-tab').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');
    document.getElementById('groupsDistribution').style.display = tab === 'groups' ? 'block' : 'none';
    document.getElementById('usersDistribution').style.display = tab === 'users' ? 'block' : 'none';
}

function toggleDistribution(type, id, button) {
    const key = type === 'group' ? 'groups' : 'users';
    const index = distributionData[key].indexOf(id);
    
    if (index > -1) {
        distributionData[key].splice(index, 1);
        button.innerHTML = '<i class="fas fa-plus"></i>';
        button.style.color = '';
    } else {
        distributionData[key].push(id);
        button.innerHTML = '<i class="fas fa-check"></i>';
        button.style.color = '#22c55e';
    }
}

function updateDistributionUI() {
    // Atualizar UI baseado em distributionData
    document.querySelectorAll('#groupsDistribution .distribution-item').forEach(item => {
        const id = parseInt(item.querySelector('button').onclick.toString().match(/\d+/)[0]);
        const button = item.querySelector('button');
        if (distributionData.groups.includes(id)) {
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.style.color = '#22c55e';
        }
    });
}

function saveCheckin(event) {
    event.preventDefault();
    
    const formData = new FormData();
    formData.append('action', 'save');
    formData.append('checkin_id', document.getElementById('checkinId').value);
    formData.append('name', document.getElementById('checkinName').value);
    formData.append('description', document.getElementById('checkinDescription').value);
    formData.append('day_of_week', selectedDay);
    formData.append('is_active', document.getElementById('checkinActive').checked ? '1' : '0');
    formData.append('questions', JSON.stringify(questions));
    formData.append('distribution', JSON.stringify(distributionData));
    
    fetch('ajax_checkin.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Check-in salvo com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar check-in');
    });
}

function editCheckin(id) {
    fetch('ajax_checkin.php?action=get&checkin_id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const checkin = data.checkin;
                document.getElementById('checkinId').value = checkin.id;
                document.getElementById('checkinName').value = checkin.name;
                document.getElementById('checkinDescription').value = checkin.description || '';
                document.getElementById('checkinActive').checked = checkin.is_active == 1;
                selectedDay = checkin.day_of_week;
                updateDaySelector();
                questions.length = 0;
                if (checkin.questions) {
                    questions.push(...checkin.questions);
                }
                renderQuestions();
                distributionData = checkin.distribution || { groups: [], users: [] };
                updateDistributionUI();
                document.getElementById('modalTitle').textContent = 'Editar Check-in';
                document.getElementById('checkinModal').classList.add('active');
            }
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

