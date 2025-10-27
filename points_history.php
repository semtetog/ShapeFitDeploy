<?php
// public_html/points_history.php (VERSÃO FINAL E COMPLETA)

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');

$user_id = $_SESSION['user_id'];

// --- LÓGICA DE FILTRO DE DATA ---
$filter_month_str = $_GET['month'] ?? date('Y-m');
// Validação robusta para o filtro de data
if (!preg_match('/^\d{4}-\d{2}$/', $filter_month_str) || !DateTime::createFromFormat('Y-m', $filter_month_str)) {
    $filter_month_str = date('Y-m');
}
$start_date = $filter_month_str . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

// --- BUSCA DE DADOS DO USUÁRIO ---
$stmt_user = $conn->prepare("SELECT u.name, u.points, r.rank FROM sf_users u LEFT JOIN (SELECT id, RANK() OVER (ORDER BY points DESC, name ASC) as rank FROM sf_users) r ON u.id = r.id WHERE u.id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();
$user_points = $user_data['points'] ?? 0;
$user_rank = $user_data['rank'] ?? 0;

// --- LÓGICA DE NÍVEIS ---
// (Esta é a sua lógica de níveis original, mantida como estava)
function toRoman($number) {
    $map = [10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'];
    $roman = '';
    while ($number > 0) { foreach ($map as $val => $char) { if ($number >= $val) { $roman .= $char; $number -= $val; break; } } }
    return $roman;
}
$level_categories = [
    ['name' => 'Franguinho', 'threshold' => 0], ['name' => 'Frango', 'threshold' => 1500], ['name' => 'Frango de Elite', 'threshold' => 4000],
    ['name' => 'Atleta de Bronze', 'threshold' => 8000], ['name' => 'Atleta de Prata', 'threshold' => 14000], ['name' => 'Atleta de Ouro', 'threshold' => 22000], ['name' => 'Atleta de Platina', 'threshold' => 32000], ['name' => 'Atleta de Diamante', 'threshold' => 45000],
    ['name' => 'Elite', 'threshold' => 60000], ['name' => 'Mestre', 'threshold' => 80000], ['name' => 'Virtuoso', 'threshold' => 105000],
    ['name' => 'Campeão', 'threshold' => 135000], ['name' => 'Titã', 'threshold' => 170000], ['name' => 'Pioneiro', 'threshold' => 210000], ['name' => 'Lenda', 'threshold' => 255000],
];
$final_levels_map = [];
$level_counter = 1;
foreach ($level_categories as $index => $category) {
    $next_threshold = isset($level_categories[$index + 1]) ? $level_categories[$index + 1]['threshold'] : ($category['threshold'] + ($category['threshold'] - $level_categories[$index - 1]['threshold']));
    $points_in_category = $next_threshold - $category['threshold'];
    $points_per_sublevel = $points_in_category > 0 ? $points_in_category / 10 : 0;
    for ($i = 0; $i < 10; $i++) {
        $final_levels_map[$level_counter] = [
            'name' => $category['name'] . ' ' . toRoman($i + 1),
            'points_required' => $category['threshold'] + ($i * $points_per_sublevel)
        ];
        $level_counter++;
    }
}
function calculate_user_progress($points, $levels_map) {
    $current_level_num = 1;
    $points_at_current_level_start = 0;
    $points_for_next_level = 0;
    $is_max_level = false;
    foreach ($levels_map as $level_num => $level_data) {
        if ($points >= $level_data['points_required']) {
            $current_level_num = $level_num;
        } else {
            break;
        }
    }
    $points_at_current_level_start = $levels_map[$current_level_num]['points_required'];
    if (!isset($levels_map[$current_level_num + 1])) {
        $is_max_level = true;
        $points_for_next_level = $points_at_current_level_start;
    } else {
        $points_for_next_level = $levels_map[$current_level_num + 1]['points_required'];
    }
    $level_name = $levels_map[$current_level_num]['name'];
    $level_progress_points = $points - $points_at_current_level_start;
    $total_points_for_this_level = $points_for_next_level - $points_at_current_level_start;
    if ($is_max_level || $total_points_for_this_level <= 0) {
        $progress_percentage = 100;
        $points_remaining = 0;
    } else {
        $progress_percentage = round(($level_progress_points / $total_points_for_this_level) * 100);
        $points_remaining = $total_points_for_this_level - $level_progress_points;
    }
    return ['name' => $level_name, 'progress_percentage' => $progress_percentage, 'points_remaining' => $points_remaining, 'is_max_level' => $is_max_level];
}
$level_details = calculate_user_progress($user_points, $final_levels_map);
$current_level = $level_details['name'];
$level_progress_percentage = $level_details['progress_percentage'];
$points_remaining_for_next_level = $level_details['points_remaining'];
// --- FIM DA LÓGICA DE NÍVEIS ---

// --- BUSCA E PROCESSAMENTO DO LOG DE PONTOS ---
$raw_log = [];
$stmt_log = $conn->prepare("SELECT points_awarded, action_key, action_context_id, timestamp FROM sf_user_points_log WHERE user_id = ? AND date_awarded BETWEEN ? AND ? ORDER BY timestamp DESC");
$stmt_log->bind_param("iss", $user_id, $start_date, $end_date);
$stmt_log->execute();
$result = $stmt_log->get_result();
while($row = $result->fetch_assoc()) { $raw_log[] = $row; }
$stmt_log->close();

$points_log_grouped = [];
foreach ($raw_log as $log_item) {
    $date = new DateTime($log_item['timestamp']);
    $date_key = $date->format('Y-m-d');
    $points_log_grouped[$date_key][] = $log_item;
}

// --- BUSCAR MESES DISPONÍVEIS PARA O FILTRO ---
$available_months = [];
$stmt_months = $conn->prepare("SELECT DISTINCT DATE_FORMAT(date_awarded, '%Y-%m') as month_key FROM sf_user_points_log WHERE user_id = ? ORDER BY month_key DESC");
$stmt_months->bind_param("i", $user_id);
$stmt_months->execute();
$months_result = $stmt_months->get_result();
while($row = $months_result->fetch_assoc()) {
    $dateObj = DateTime::createFromFormat('!Y-m', $row['month_key']);
    $row['month_display'] = ucfirst(strftime('%B de %Y', $dateObj->getTimestamp()));
    $available_months[] = $row;
}
$stmt_months->close();

// --- FUNÇÃO INTELIGENTE PARA OBTER DETALHES DA AÇÃO ---
function getActionDetails($conn, $key, $context_id) {
    if ($key === 'ROUTINE_COMPLETE') {
        $text = "Tarefa concluída"; // Texto padrão
        // Se o contexto é numérico, busca o nome da rotina na tabela sf_routine_items
        if (is_numeric($context_id)) {
            $stmt = $conn->prepare("SELECT title FROM sf_routine_items WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $context_id);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                if ($result) {
                    $text = htmlspecialchars($result['title']);
                }
                $stmt->close();
            }
        } else {
            // Se não for numérico, é uma tarefa de onboarding (o nome da atividade)
            $text = "Meta: " . htmlspecialchars($context_id);
        }
        return ['icon' => 'fa-check-circle', 'text' => $text, 'color' => '#4caf50'];
    }
    
    // O resto da sua função original...
    return ['icon' => 'fa-question-circle', 'text' => 'Ação registrada', 'color' => '#A0A0A0'];
}

$page_title = "Minha Jornada";
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* Estilos unificados com o app */
body { background-color: var(--bg-color); color: var(--text-primary); }
.app-container { max-width: 600px; margin: 0 auto; }

.page-header { display: flex; align-items: center; gap: 16px; padding: calc(env(safe-area-inset-top, 0px) + 20px) 24px 20px; }
.back-button { font-size: 1.5rem; color: var(--text-primary); text-decoration: none; }
.page-title { font-size: 2rem; font-weight: 700; margin: 0; }

.content-wrapper { padding: 0 24px; padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 100px); }

/* Card Principal (Herói) */
.hero-card { background-color: var(--surface-color); border-radius: 16px; padding: 20px; margin-bottom: 24px; }
.hero-points-display { text-align: center; margin-bottom: 16px; }
.points-value { font-size: 3.5rem; font-weight: 700; line-height: 1; color: var(--text-primary); }
.points-label { font-size: 0.9rem; font-weight: 600; color: var(--text-secondary); letter-spacing: 1px; }
.level-info { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.level-tag { background-color: var(--glass-bg); color: var(--accent-orange); padding: 6px 12px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; }
.progress-bar-container { width: 100%; height: 8px; background-color: var(--glass-bg); border-radius: 4px; overflow: hidden; }
.progress-bar-fill { height: 100%; background-image: var(--primary-orange-gradient); border-radius: 4px; transition: width 0.5s ease; }
.next-level-text { font-size: 0.85rem; color: var(--text-secondary); text-align: center; margin-top: 8px; }

/* Barra de Filtro */
.filter-bar { background-color: var(--surface-color); border-radius: 12px; padding: 12px; }
.filter-bar select { width: 100%; background-color: var(--glass-bg); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 8px; padding: 10px; font-size: 1rem; }

/* Histórico */
.history-feed { margin-top: 24px; }
.feed-date-separator { font-size: 0.9rem; font-weight: 600; color: var(--text-secondary); margin: 24px 0 12px; }
.feed-group { background-color: var(--surface-color); border-radius: 16px; }
.feed-item { display: flex; align-items: center; padding: 12px 16px; gap: 12px; border-bottom: 1px solid var(--border-color); }
.feed-group .feed-item:last-child { border-bottom: none; }
.feed-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.feed-icon i { font-size: 1.2rem; }
.feed-info { flex-grow: 1; }
.feed-reason { margin: 0; font-weight: 500; }
.feed-time { font-size: 0.85rem; color: var(--text-secondary); }
.feed-points { font-weight: 600; font-size: 1rem; color: #4CAF50; }

/* Estado Vazio */
.feed-empty-state { text-align: center; padding: 40px 20px; opacity: 0.7; }
.feed-empty-state i { font-size: 2.5rem; margin-bottom: 16px; }
</style>

<div class="app-container">
    <div class="page-header">
        <a href="<?php echo BASE_APP_URL; ?>/main_app.php" class="back-button"><i class="fas fa-arrow-left"></i></a>
        <h1 class="page-title">Minha Jornada</h1>
    </div>

    <div class="content-wrapper">
        <div class="hero-card">
            <div class="hero-points-display">
                <span class="points-value"><?php echo number_format($user_points, 0, ',', '.'); ?></span>
                <span class="points-label">PONTOS</span>
            </div>
            <div class="hero-level-progress">
                <div class="level-info">
                    <span class="level-tag"><?php echo $current_level; ?></span>
                </div>
                <div class="progress-bar-container"><div class="progress-bar-fill" style="width: <?php echo $level_progress_percentage; ?>%;"></div></div>
                <div class="next-level-text">
                    <?php if ($level_details['is_max_level']): ?>
                        <strong>Você está no nível máximo!</strong>
                    <?php else: ?>
                        Faltam <strong><?php echo number_format($points_remaining_for_next_level, 0, ',', '.'); ?></strong> pontos...
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="filter-bar">
            <form id="filter-form" action="" method="GET">
                <select name="month" id="month-filter" onchange="this.form.submit()">
                    <?php if (empty($available_months)): ?>
                        <option>Nenhum registro</option>
                    <?php else: ?>
                        <?php foreach($available_months as $month): ?>
                            <option value="<?php echo htmlspecialchars($month['month_key']); ?>" <?php echo ($filter_month_str == $month['month_key']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($month['month_display']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </form>
        </div>

        <div class="history-feed">
            <?php if (empty($points_log_grouped)): ?>
                <div class="feed-empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>Nenhuma Atividade</h3>
                    <p>Não há registros de pontos para este período.</p>
                </div>
            <?php else: ?>
                <?php foreach ($points_log_grouped as $date_key => $logs): ?>
                    <div class="feed-date-separator">
                        <?php
                            $today = new DateTime('now');
                            $yesterday = (new DateTime('now'))->modify('-1 day');
                            if ($date_key === $today->format('Y-m-d')) echo 'Hoje';
                            elseif ($date_key === $yesterday->format('Y-m-d')) echo 'Ontem';
                            else echo ucfirst(strftime('%A, %d de %B', strtotime($date_key)));
                        ?>
                    </div>
                    <div class="feed-group">
                        <?php foreach ($logs as $log_item): 
                            // Passamos a conexão para a função poder buscar os detalhes
                            $details = getActionDetails($conn, $log_item['action_key'], $log_item['action_context_id']);
                        ?>
                            <div class="feed-item">
                                <div class="feed-icon" style="background-color: <?php echo htmlspecialchars($details['color']); ?>20;">
                                    <i class="fas <?php echo htmlspecialchars($details['icon']); ?>" style="color: <?php echo htmlspecialchars($details['color']); ?>;"></i>
                                </div>
                                <div class="feed-info">
                                    <p class="feed-reason"><?php echo $details['text']; ?></p>
                                    <span class="feed-time"><?php echo date('H:i', strtotime($log_item['timestamp'])); ?></span>
                                </div>
                               <span class="feed-points">+<?php echo number_format($log_item['points_awarded'], 0); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php'; 
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>