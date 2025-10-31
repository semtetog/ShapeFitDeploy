<?php
// public_html/main_app.php (VERSÃO COM A ORGANIZAÇÃO CORRETA DOS CARDS E LÓGICA CORRIGIDA)

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// --- CONFIGURAÇÃO INICIAL ---
$user_id = $_SESSION['user_id'];
$current_date = date('Y-m-d');

// --- BUSCA DE DADOS UNIFICADA ---
$user_profile_data = getUserProfileData($conn, $user_id);
if (!$user_profile_data || !$user_profile_data['onboarding_complete']) {
    header("Location: " . BASE_APP_URL . "/onboarding/onboarding.php");
    exit();
}

// --- LÓGICA DO BANNER DE PESO ---
$show_edit_button = true;
$days_until_next_weight_update = 0;
try {
    $stmt_last_weight = $conn->prepare("SELECT MAX(date_recorded) AS last_date FROM sf_user_weight_history WHERE user_id = ?");
    if ($stmt_last_weight) {
        $stmt_last_weight->bind_param("i", $user_id);
        $stmt_last_weight->execute();
        $result = $stmt_last_weight->get_result()->fetch_assoc();
        $stmt_last_weight->close();
        if ($result && !empty($result['last_date'])) {
            $last_log_date = new DateTime($result['last_date']);
            $unlock_date = (clone $last_log_date)->modify('+7 days');
            $today = new DateTime('today');
            if ($today < $unlock_date) {
                $show_edit_button = false;
                $days_until_next_weight_update = (int)$today->diff($unlock_date)->days;
                if ($days_until_next_weight_update == 0) $days_until_next_weight_update = 1;
            }
        }
    }
} catch (Exception $e) { error_log("Erro ao processar data de peso: " . $e->getMessage()); }

// --- LÓGICA DO CARD DE RANKING ---
$stmt_my_rank = $conn->prepare("SELECT rank, points FROM (SELECT id, points, RANK() OVER (ORDER BY points DESC) as rank FROM sf_users) as r WHERE id = ?");
$stmt_my_rank->bind_param("i", $user_id);
$stmt_my_rank->execute();
$my_rank_result = $stmt_my_rank->get_result()->fetch_assoc();
$my_rank = $my_rank_result['rank'] ?? 'N/A';
$my_points = $my_rank_result['points'] ?? 0;

$opponent_rank = ($my_rank > 1) ? $my_rank - 1 : 2;
if ($opponent_rank > 0) {
    $stmt_opponent = $conn->prepare("SELECT * FROM (SELECT u.id, u.name, u.points, up.profile_image_filename, up.gender, RANK() OVER (ORDER BY u.points DESC) as rank FROM sf_users u LEFT JOIN sf_user_profiles up ON u.id = up.user_id) as ranked_users WHERE rank = ? LIMIT 1");
    $stmt_opponent->bind_param("i", $opponent_rank);
    $stmt_opponent->execute();
    $opponent_data = $stmt_opponent->get_result()->fetch_assoc();
    $stmt_opponent->close();
} else { $opponent_data = null; }

$user_progress_percentage = 0;
if ($my_rank > 1 && isset($opponent_data['points']) && $opponent_data['points'] > 0) { $user_progress_percentage = min(100, round(($my_points / $opponent_data['points']) * 100)); }
elseif ($my_rank == 1) { $user_progress_percentage = 100; }

// --- DADOS DE CONSUMO DIÁRIO ---
$user_points = $user_profile_data['points'] ?? 0;

$gender = $user_profile_data['gender'] ?? 'male';
$weight_kg = (float)($user_profile_data['weight_kg'] ?? 70);
$height_cm = (int)($user_profile_data['height_cm'] ?? 170);
$dob = $user_profile_data['dob'] ?? date('Y-m-d', strtotime('-30 years'));
$exercise_frequency = $user_profile_data['exercise_frequency'] ?? 'sedentary';
$objective = $user_profile_data['objective'] ?? 'maintain';

$age_years = calculateAge($dob);

// PRIORIZAR METAS CUSTOMIZADAS se existirem
if (!empty($user_profile_data['custom_calories_goal'])) {
    $total_daily_calories_goal = (int)$user_profile_data['custom_calories_goal'];
} else {
    $total_daily_calories_goal = calculateTargetDailyCalories($gender, $weight_kg, $height_cm, $age_years, $exercise_frequency, $objective);
}

if (!empty($user_profile_data['custom_protein_goal_g']) && !empty($user_profile_data['custom_carbs_goal_g']) && !empty($user_profile_data['custom_fat_goal_g'])) {
    $macros_goal = [
        'protein_g' => (float)$user_profile_data['custom_protein_goal_g'],
        'carbs_g' => (float)$user_profile_data['custom_carbs_goal_g'],
        'fat_g' => (float)$user_profile_data['custom_fat_goal_g']
    ];
} else {
    $macros_goal = calculateMacronutrients($total_daily_calories_goal, $objective);
}

$daily_tracking = getDailyTrackingRecord($conn, $user_id, $current_date);
$kcal_consumed = $daily_tracking['kcal_consumed'] ?? 0;
$carbs_consumed = $daily_tracking['carbs_consumed_g'] ?? 0.00;
$protein_consumed = $daily_tracking['protein_consumed_g'] ?? 0.00;
$fat_consumed = $daily_tracking['fat_consumed_g'] ?? 0.00;

$water_consumed = $daily_tracking['water_consumed_cups'] ?? 0;

$water_goal_data = getWaterIntakeSuggestion($weight_kg);
$water_goal_cups = $water_goal_data['cups'];
$water_goal_ml = $water_goal_data['total_ml'];
$CUP_SIZE_IN_ML = 250;
$water_consumed_ml = $water_consumed * $CUP_SIZE_IN_ML;
$current_weight_display = number_format($weight_kg, 1, ',', '.') . "kg";


// --- DADOS DA ROTINA ---
$routine_items = getRoutineItemsForUser($conn, $user_id, $current_date, $user_profile_data);
$total_missions = count($routine_items);
$completed_missions = 0;
foreach ($routine_items as $item) {
    if ($item['completion_status'] == 1) {
        $completed_missions++;
    }
}

// --- SUGESTÕES DE REFEIÇÃO ---
$meal_suggestion_data = getMealSuggestions($conn);

// --- PREPARAÇÃO PARA O LAYOUT ---
$page_title = "Dashboard";
$extra_js = ['script.js'];
$extra_css = ['pages/_dashboard.css'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>
<style>
    .lottie-animation-container {
        width: 100%;
        height: 100%;
    }
    .main-carousel .lottie-slide.active {
        opacity: 1 !important;
    }
</style>

<style>
/* CSS do layout moderno */
.header{display:flex;justify-content:flex-end;align-items:center}.header-actions{display:flex;align-items:center;gap:.75rem}.points-counter-badge{display:flex;align-items:center;gap:8px;height:44px;padding:0 16px;border-radius:22px;background-color:var(--surface-color);border:1px solid var(--border-color);color:var(--text-primary);text-decoration:none;transition:all .2s ease}.points-counter-badge:hover{border-color:var(--accent-orange)}.points-counter-badge i{color:var(--accent-orange);font-size:1rem}.points-counter-badge span{font-weight:600;font-size:1rem}
.profile-icon{display:flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:50%;border:1px solid var(--border-color);background-color:var(--surface-color);overflow:hidden;transition:border-color .2s ease}
.profile-icon:hover{border-color:var(--accent-orange)}
.profile-icon img{width:100%;height:100%;object-fit:cover}
.profile-icon i{color:var(--accent-orange);font-size:1.2rem;}

/* ============================================== */
/* --- CSS PARA OS CARDS INDIVIDUAIS DE RESUMO --- */
/* ============================================== */
.card-weight { display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; position: relative; gap: 4px; padding: 24px; }
.card-weight span { font-size: 0.9rem; color: var(--text-secondary); }
.card-weight strong { font-size: 2.2rem; line-height: 1.2; color: var(--text-primary); }
.card-weight .countdown { font-size: 2rem; }
.card-weight .edit-button { position: absolute; top: 16px; right: 16px; background: none; border: none; color: var(--text-secondary); cursor: pointer; padding: 5px; transition: color 0.2s ease; }
.card-weight .edit-button:hover { color: var(--accent-orange); }
.card-weight .edit-button svg { width: 20px; height: 20px; }

.card-hydration { padding: 20px 24px; overflow: hidden; }
.card-hydration .hydration-content { display: grid; grid-template-columns: minmax(0,1fr) 160px; align-items: center; gap: 12px; }
.card-hydration .hydration-info { flex-grow: 1; display: flex; flex-direction: column; min-width: 0; }
.card-hydration h3 { margin: 0 0 10px 0; font-size: 1.1rem; color: var(--text-primary); }
.card-hydration .water-status { font-size: 1.5rem; font-weight: 600; color: var(--text-primary); margin-bottom: 15px; }
.card-hydration .water-status span:last-child { font-size: 1rem; color: var(--text-secondary); }
.card-hydration .water-controls { display: flex; flex-direction: column; gap: 12px; }
.card-hydration .water-input-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.card-hydration .inline-input-row { display: flex; align-items: center; gap: 8px; flex-wrap: nowrap; margin-left: 24px; }
.card-hydration .water-number-input { width: 120px; padding: 12px 14px; border-radius: 14px; border: 1px solid var(--glass-border); background: rgba(255,255,255,0.05); color: var(--text-primary); font-weight: 600; text-align: center; }
.card-hydration .water-number-input::placeholder { color: rgba(255,255,255,0.35); }
.card-hydration .circle-btn { width: 64px; height: 64px; border-radius: 50%; border: 1px solid var(--glass-border); background: rgba(255,255,255,0.06); color: var(--text-primary); font-size: 1.8rem; font-weight: 700; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background-color 0.2s ease, border-color 0.2s ease; }
.card-hydration .circle-btn:hover { background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.2); }
.card-hydration .circle-btn.accent { border: none; background-image: var(--primary-orange-gradient); color: var(--text-primary); }
.card-hydration .circle-btn:disabled { opacity: 0.5; cursor: default; filter: grayscale(0.2); }
.card-hydration .water-select { padding: 10px 12px; border-radius: 14px; border: 1px solid var(--glass-border); background: rgba(255,255,255,0.05); color: var(--text-primary); font-weight: 700; text-transform: uppercase; min-width: 78px; }
.card-hydration .full-btn { width: 100%; padding: 12px 16px; border-radius: 14px; border: 1px solid var(--glass-border); background: rgba(255,255,255,0.06); color: var(--text-primary); font-weight: 700; cursor: pointer; text-align: center; }
.card-hydration .quick-add-row { display: flex; flex-wrap: wrap; gap: 8px; }
.card-hydration .quick-add { padding: 8px 12px; border-radius: 999px; border: 1px solid var(--glass-border); background: rgba(255,255,255,0.06); color: var(--text-primary); font-weight: 600; cursor: pointer; transition: background-color 0.2s ease, border-color 0.2s ease; }
.card-hydration .quick-add:hover { background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.2); }
.card-hydration .water-drop-container-svg { width: 160px; height: 160px; display: flex; align-items: center; justify-content: center; justify-self: end; }
.card-hydration .water-controls { margin-top: 8px; }
.card-hydration .water-input-row { flex-wrap: wrap; }
.card-hydration .water-input-row > * { flex: 0 0 auto; }
.card-hydration .quick-add-row { margin-top: 4px; }
.card-hydration .divider { width: 100%; height: 1px; background: var(--glass-border); margin: 6px 0 2px; opacity: 0.6; }


/* ======================================= */
/* --- CORREÇÃO PARA O CARD DE ÁGUA MOBILE --- */
/* ======================================= */
@media (max-width: 480px) {
    .card-hydration {
        padding: 20px 24px; /* Padding uniforme */
    }
    .card-hydration .hydration-content {
        /* Muda para layout de coluna em telas pequenas */
        grid-template-columns: 1fr;
        gap: 20px;
    }
    .card-hydration .water-drop-container-svg {
        /* Centraliza a gota d'água */
        justify-self: center;
        width: 150px; /* Levemente menor para caber melhor */
        height: 150px;
        order: -1; /* Coloca a gota no topo */
    }
    .card-hydration .hydration-info {
        /* Centraliza todo o bloco de informações */
        align-items: center;
        text-align: center;
    }
    .card-hydration .water-controls,
    .card-hydration .water-input-row,
    .card-hydration .inline-input-row {
        /* Centraliza os controles */
        justify-content: center;
        margin-left: 0; /* Remove a margem que estava causando o deslocamento */
    }
     .card-hydration .inline-input-row {
        /* Garante que o input e o select fiquem bem alinhados */
        width: 100%;
    }
    .card-hydration .water-number-input {
        /* Ocupa o espaço disponível para evitar quebra de linha */
        flex-grow: 1;
    }
}


/* Transição suave para o movimento do grupo do nível da água */
#animated-water-drop #water-level-group { 
    transition: transform 0.7s cubic-bezier(0.65, 0, 0.35, 1);
}

/* Animação das ondas da água */
@keyframes wave-animation {
    0% { transform: translateX(0); }
    50% { transform: translateX(-100px); }
    100% { transform: translateX(0); }
}
@keyframes wave-animation-2 {
    0% { transform: translateX(0); }
    50% { transform: translateX(100px); }
    100% { transform: translateX(0); }
}
#animated-water-drop #wave1 {
    animation: wave-animation 8s linear infinite;
}
#animated-water-drop #wave2 {
    animation: wave-animation-2 10s linear infinite alternate;
}

/* Corrige flicker/preto em mobile ao abrir o select: remove filtros e animações pesadas em telas touch */
@media (hover: none) and (pointer: coarse) {
    #animated-water-drop #water-level-group { filter: none !important; }
    #animated-water-drop #wave1,
    #animated-water-drop #wave2 { animation: none !important; }
}

/* Esta regra força os cards específicos a ocuparem a largura total do grid */
.card-weight,
.card-hydration,
.card-consumption,
.card-missions,
.card-meal-cta,
.card-suggestions,
.card-action-item {
    grid-column: 1 / -1;
}

/* ======================================= */
/* --- CSS DOS NOVOS CARDS DE AÇÃO --- */
/* ======================================= */
.card-action-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 24px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.card-action-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(255, 107, 53, 0.15);
}

.card-action-item .action-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.card-action-item .action-icon.premium {
    background: var(--primary-orange-gradient);
}

.card-action-item .action-icon i {
    font-size: 1.5rem;
    color: var(--accent-orange);
}

.card-action-item .action-icon.premium i {
    color: var(--text-primary);
}

.card-action-item:hover .action-icon {
    transform: scale(1.1);
}

.card-action-item .action-content {
    flex: 1;
    min-width: 0;
}

.card-action-item .action-content h3 {
    margin: 0 0 4px 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.card-action-item .action-content p {
    margin: 0;
    font-size: 0.85rem;
    color: var(--text-secondary);
    line-height: 1.4;
}

.card-action-item .action-button {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.3s ease;
    color: var(--text-secondary);
    text-decoration: none;
}

.card-action-item:hover .action-button {
    background: var(--primary-orange-gradient);
    color: var(--text-primary);
    transform: translateX(4px);
}

.card-action-item .action-button i {
    font-size: 1rem;
}

/* Responsive */
@media (max-width: 480px) {
    .card-action-item {
        padding: 18px 20px;
        gap: 14px;
    }
    
    .card-action-item .action-icon {
        width: 48px;
        height: 48px;
    }
    
    .card-action-item .action-icon i {
        font-size: 1.3rem;
    }
    
    .card-action-item .action-content h3 {
        font-size: 1rem;
    }
    
    .card-action-item .action-content p {
        font-size: 0.8rem;
    }
}

/* Estilos para o modal de duração de exercício */
.duration-input-group {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 8px;
}

.duration-input-group input {
    flex: 1;
    padding: 12px 16px;
    border-radius: 8px;
    border: 1px solid var(--glass-border);
    background: rgba(255,255,255,0.05);
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: 600;
}

.duration-input-group input:focus {
    outline: none;
    border-color: var(--accent-orange);
}

.duration-unit {
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
    min-width: 60px;
}

.form-help {
    color: var(--text-secondary);
    font-size: 0.8rem;
    margin-top: 4px;
    display: block;
}

/* Estilos para modais (VERSÃO ROBUSTA) */
.modal-overlay {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background: rgba(0, 0, 0, 0.8) !important;
    
    /* MUDANÇA PRINCIPAL: Controle com visibility e opacity */
    display: flex !important; /* Deixe sempre flex para o alinhamento funcionar */
    visibility: hidden;
    opacity: 0;
    transition: opacity 0.3s ease, visibility 0s linear 0.3s; /* Animação suave */

    align-items: center !important;
    justify-content: center !important;
    z-index: 99999 !important;
    padding: 20px !important;
}

.modal-overlay.modal-visible {
    /* MUDANÇA PRINCIPAL: Apenas mude a visibilidade e opacidade */
    visibility: visible;
    opacity: 1;
    transition-delay: 0s; /* Garante que a transição de entrada seja imediata */
}

.modal-content {
    background: var(--surface-color) !important;
    border-radius: 16px !important;
    padding: 24px !important;
    max-width: 400px !important;
    width: 100% !important;
    max-height: 90vh !important;
    overflow-y: auto !important;
    border: 1px solid var(--border-color) !important;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3) !important;
    position: relative !important;
    z-index: 100000 !important;
}

.modal-content h2 {
    margin: 0 0 20px 0;
    color: var(--text-primary);
    font-size: 1.3rem;
    text-align: center;
}

.modal-body {
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--text-primary);
    font-weight: 600;
}

.form-input {
    width: 100%;
    padding: 12px 16px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--bg-color);
    color: var(--text-primary);
    font-size: 1rem;
}

.form-input:focus {
    outline: none;
    border-color: var(--accent-orange);
}

/* Input de horário customizado - LIMPO E SIMPLES */
.time-input {
    width: 100%;
    padding: 16px 20px;
    border-radius: 12px;
    border: 2px solid var(--border-color);
    background: var(--bg-color);
    color: var(--text-primary);
    font-size: 1.2rem;
    font-weight: 600;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-sizing: border-box;
    letter-spacing: 1px;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

.time-input:focus {
    outline: none;
    border-color: var(--accent-orange);
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
}

.modal-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.primary-button {
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1rem;
}

.primary-button.secondary-button {
    background: var(--surface-color);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.primary-button.secondary-button:hover {
    background: var(--border-color);
}

.primary-button:not(.secondary-button) {
    background: var(--accent-orange);
    color: white;
}

.primary-button:not(.secondary-button):hover {
    background: #e55a00;
}

/* Ajustes para mobile - inputs de horário */
@media (max-width: 480px) {
    .time-input {
        padding: 14px 16px;
        font-size: 1.1rem;
    }
}

.card-consumption { padding: 20px 24px; display: flex; flex-direction: column; gap: 18px; }
.card-consumption h3 { margin: 0; font-size: 1.1rem; color: var(--text-primary); }
.card-consumption .consumption-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; text-align: center; }
.card-consumption .consumption-item p { margin: 8px 0 0 0; font-size: 0.8rem; color: var(--text-secondary); font-weight: 500; }
.progress-circle .circular-chart { display: block; max-width: 100%; }
.progress-circle .circle-bg { fill: none; stroke: rgba(255, 255, 255, 0.1); stroke-width: 3.8; }
.progress-circle .circle { fill: none; stroke: url(#orange-gradient); stroke-width: 2.8; stroke-linecap: round; transition: stroke-dashoffset 0.5s ease-in-out; transform-origin: 50% 50%; transform: rotate(-90deg); }
.progress-circle .percentage-text { fill: var(--text-primary); font-size: 0.8rem; font-weight: 600; text-anchor: middle; }


/* ======================================================= */
/* --- CSS DAS MISSÕES --- */
/* ======================================================= */

.card-missions .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--glass-border);
}
.card-missions .view-all-link {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-decoration: none;
    transition: color 0.2s ease;
}
.card-missions .view-all-link:hover { color: var(--accent-orange); }

.missions-progress {display: flex;flex-direction: column;gap: 8px;}
.missions-progress-info {display: flex;justify-content: space-between;align-items: center;font-size: 0.9rem;color: var(--text-secondary);}
.missions-progress-info span:first-child {font-weight: 600;color: var(--text-primary);}
.progress-bar-missions {width: 100%;height: 6px;background-color: rgba(255, 255, 255, 0.1);border-radius: 3px;overflow: hidden;}
.progress-bar-missions-fill {height: 100%;border-radius: 3px;background-image: var(--primary-orange-gradient);transition: width 0.5s ease-in-out;}

.missions-carousel-container {
    position: relative;
    min-height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.mission-slide {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    position: absolute;
    width: 100%;
    height: 100%;
    opacity: 0;
    transform: scale(0.95);
    transition: opacity 0.4s ease, transform 0.4s ease;
    justify-content: space-between;
    padding: 10px 0;
}
.mission-slide.active {opacity: 1; transform: scale(1); z-index: 10;}
.mission-slide.completion-message {justify-content: center;}
.mission-icon i {
    background: var(--primary-orange-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-size: 2.5rem;
}
.mission-details {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    width: 100%;
}
.mission-details h4 {font-size: 1.1rem; font-weight: 600;color: var(--text-primary);margin: 0 0 4px 0;}
.mission-details span {font-size: 0.9rem; font-weight: 500;color: #4CAF50;}
.mission-duration-display {
    font-size: 0.85rem;
    color: var(--text-secondary);
    font-weight: 500;
    margin-top: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all 0.3s ease;
}
.mission-actions {display: flex;gap: 20px;width: 100%;justify-content: center;}
.mission-action-btn {
    width: 55px;
    height: 55px;
    border-radius: 50%;
    border: none;
    font-size: 1.6rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.mission-action-btn.skip-btn {background-color: rgba(255, 255, 255, 0.1);color: var(--text-secondary);}
.mission-action-btn.skip-btn:hover {background-color: rgba(255, 255, 255, 0.15);}
.mission-action-btn.complete-btn {background-image: var(--primary-orange-gradient);color: var(--text-primary);}
.mission-action-btn.complete-btn:hover {filter: brightness(1.1);}
.mission-action-btn.duration-btn {background-color: rgba(255, 193, 7, 0.2);color: #ffc107;border: 1px solid rgba(255, 193, 7, 0.3);}
.mission-action-btn.duration-btn:hover {background-color: rgba(255, 193, 7, 0.3);}
.mission-action-btn.duration-btn:focus {outline: none;box-shadow: none;}
.mission-action-btn.complete-btn.disabled {opacity: 0.5;cursor: not-allowed;filter: none;}
.mission-action-btn.complete-btn.disabled:hover {filter: none;}
.mission-action-btn.complete-btn.disabled:focus {outline: none;box-shadow: none;}
.completion-message i { font-size: 2.8rem; margin-bottom: 10px; }
.completion-message h4 { font-size: 1.2rem; }

/* CSS CARROSSEL DE SUGESTÕES */
.suggestions-carousel {padding: 0 24px;scroll-padding: 0 24px;}
.suggestions-carousel .suggestion-item:last-child {margin-right: 24px;}
.card-suggestions .card-header {padding: 0 24px;}
.player-info {display: flex;flex-direction: column;align-items: center;gap: 8px;color: var(--text-primary);}
.player-info .player-avatar {width: 48px;height: 48px;border-radius: 50%;background-color: var(--surface-color);display: flex;align-items: center;justify-content: center;overflow: hidden;}
.player-info .player-avatar img {width: 100%;height: 100%;object-fit: cover;}
.player-info .player-avatar i {color: var(--accent-orange);font-size: 1.5rem;}
.points-popup {
    position: fixed;
    bottom: 100px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
    padding: 16px 24px;
    border-radius: 16px;
    font-size: 1rem;
    font-weight: 600;
    z-index: 2000;
    opacity: 0;
    animation: pointsPopupAnimation 2.5s ease-in-out forwards;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    display: flex;
    align-items: center;
    gap: 12px;
}

.points-popup .star-icon {
    font-size: 1.2rem;
    color: var(--accent-orange);
    animation: starPulse 1s ease-in-out infinite;
}

@keyframes pointsPopupAnimation {
    0% { 
        opacity: 0; 
        transform: translate(-50%, 20px); 
    } 
    20% { 
        opacity: 1; 
        transform: translate(-50%, 0); 
    }
    80% { 
        opacity: 1; 
        transform: translate(-50%, 0); 
    } 
    100% { 
        opacity: 0; 
        transform: translate(-50%, -20px); 
    }
}

@keyframes starPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

/* Modal de Sono */

/* Botão de sono - IDÊNTICO ao duration-btn */
.mission-action-btn.sleep-btn {
    background-color: rgba(255, 193, 7, 0.2);
    color: #ffc107;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.mission-action-btn.sleep-btn:hover {
    background-color: rgba(255, 193, 7, 0.3);
}

.mission-action-btn.sleep-btn:focus {
    outline: none;
    box-shadow: none;
}
</style>

<div class="app-container">
    <input type="hidden" id="csrf_token_main_app" value="<?php echo $_SESSION['csrf_token']; ?>">
    
    <header class="header">
        <div class="header-actions">
            <a href="<?php echo BASE_APP_URL; ?>/points_history.php" class="points-counter-badge"><i class="fas fa-star"></i><span id="user-points-display"><?php echo number_format($user_points, 0, ',', '.'); ?></span></a>
            <a href="<?php echo BASE_APP_URL; ?>/edit_profile.php" class="profile-icon"><?php if (!empty($user_profile_data['profile_image_filename'])): ?><img src="<?php echo BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($user_profile_data['profile_image_filename']); ?>" alt="Foto de Perfil"><?php else: ?><i class="fas fa-user"></i><?php endif; ?></a>
        </div>
    </header>

    <section class="main-carousel">
        <!-- Trilho móvel que contém todos os slides -->
        <div class="carousel-track">
            <div class="lottie-slide" data-link="<?php echo BASE_APP_URL; ?>/explore_recipes.php">
                <div class="lottie-animation-container"></div>
            </div>
            <div class="lottie-slide" data-link="#">
                <div class="lottie-animation-container"></div>
            </div>
            <div class="lottie-slide" data-link="<?php echo BASE_APP_URL; ?>/routine.php">
                <div class="lottie-animation-container"></div>
            </div>
            <div class="lottie-slide" data-link="<?php echo BASE_APP_URL; ?>/progress.php">
                <div class="lottie-animation-container"></div>
            </div>
        </div>
        
        <div class="pagination-container"></div>
</section>


    <section class="dashboard-grid">
        <div class="glass-card card-ranking"><a href="<?php echo BASE_APP_URL; ?>/ranking.php" class="ranking-link">
            <div class="player-info left"><div class="player-avatar"><?php if (!empty($user_profile_data['profile_image_filename'])): ?><img src="<?php echo BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($user_profile_data['profile_image_filename']); ?>" alt="Sua foto"><?php else: ?><i class="fas fa-user"></i><?php endif; ?></div><span>Você</span></div>
            <div class="clash-center"><span class="clash-title <?php if ($my_rank == 1) echo 'winner'; ?>"><?php echo ($my_rank == 1) ? 'Você está no Topo!' : 'Disputa de Pontos'; ?></span><div class="progress-bar"><div class="progress-bar-fill" style="width: <?php echo $user_progress_percentage; ?>%;"></div></div><span class="rank-position">Sua Posição: <strong><?php echo $my_rank; ?>º</strong></span></div>
            <div class="player-info right"><?php if (isset($opponent_data)): ?><div class="player-avatar"><?php if (!empty($opponent_data['profile_image_filename'])): ?><img src="<?php echo BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($opponent_data['profile_image_filename']); ?>" alt="Foto do oponente"><?php else: ?><i class="fas fa-user"></i><?php endif; ?></div><span><?php echo htmlspecialchars(explode(' ', $opponent_data['name'])[0]); ?></span><?php endif; ?></div>
        </a></div>
        
        <div class="glass-card card-weight">
             <?php if ($show_edit_button): ?>
                <span>Peso Atual</span>
                <strong id="current-weight-value"><?php echo $current_weight_display; ?></strong>
                <button data-action="open-weight-modal" class="edit-button" aria-label="Editar peso">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>
                </button>
             <?php else: ?>
                <span>Próxima atualização em</span>
                <strong class="countdown"><?php echo $days_until_next_weight_update; ?> <?php echo ($days_until_next_weight_update > 1) ? 'dias' : 'dia'; ?></strong>
             <?php endif; ?>
        </div>

        <div class="glass-card card-hydration" id="water-card">
            <div class="hydration-content">
                <div class="hydration-info">
                    <h3>Hidratação</h3>
                    <div class="water-status" id="water-status-display">
                        <span id="water-amount-display"><?php echo $water_consumed_ml; ?></span> / <span><?php echo $water_goal_ml; ?> ml</span>
                    </div>
                    <div class="water-controls">
                        <div class="water-input-row">
                            <button type="button" id="water-remove-btn" class="circle-btn" aria-label="Remover">−</button>
                            <button type="button" id="water-add-btn" class="circle-btn accent" aria-label="Adicionar">+</button>
                        </div>
                        <div class="inline-input-row">
                            <input type="number" id="water-amount-input" class="water-number-input" min="0" step="10" value="" placeholder="EX: 250" aria-label="Quantidade consumida">
                            <select id="water-unit-select" class="water-select" aria-label="Unidade">
                                <option value="ml" selected>ML</option>
                                <option value="l">L</option>
                            </select>
                        </div>
                        
                    </div>
                </div>
                <div class="water-drop-container-svg">
                    <svg id="animated-water-drop" width="160" height="160" viewBox="0 0 275.785 275.785" xml:space="preserve">
                        <defs>
                            <clipPath id="drop-mask">
                                <!-- CORREÇÃO: Usando o mesmo caminho do contorno para o clipPath para evitar vazamentos -->
                                <path d="M137.893,9.223 c14.177,18.895,91.267,123.692,91.267,169.701c0,50.31-40.952,91.255-91.267,91.255c-50.324,0-91.268-40.945-91.268-91.255 C46.625,132.915,123.712,28.118,137.893,9.223z"/>
                            </clipPath>
                            <linearGradient id="water-gradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#4fc3f7" />
                                <stop offset="80%" stop-color="#1976d2" />
                            </linearGradient>
                            <!-- RESTAURADO: Filtro de glow para a água -->
                            <filter id="water-glow" x="-30%" y="-30%" width="160%" height="160%">
                                <feGaussianBlur stdDeviation="4" result="blur" />
                                <feComposite in="SourceGraphic" in2="blur" operator="over" />
                            </filter>
                        </defs>
                        <g clip-path="url(#drop-mask)">
                            <!-- RESTAURADO: Grupo com filtro de glow e que será movido pelo JS -->
                            <g id="water-level-group" transform="translate(0, 275.785)" filter="url(#water-glow)">
                                <!-- RESTAURADO: Ondas animadas em vez de um retângulo estático -->
                                <path id="wave1" d="M -400 10 C -300 15, -300 5, -200 10 C -100 15, -100 5, 0 10 C 100 15, 100 5, 200 10 C 300 15, 300 5, 400 10 L 400 280 H -400 Z" fill="url(#water-gradient)" opacity="0.9"/>
                                <path id="wave2" d="M -400 5 C -300 10, -300 0, -200 5 C -100 10, -100 0, 0 5 C 100 10, 100 0, 200 5 C 300 10, 300 0, 400 5 L 400 280 H -400 Z" fill="url(#water-gradient)" opacity="0.7"/>
                            </g>
                        </g>
                        <!-- Contorno da gota -->
                        <path d="M137.893,9.223 c14.177,18.895,91.267,123.692,91.267,169.701c0,50.31-40.952,91.255-91.267,91.255c-50.324,0-91.268-40.945-91.268-91.255 C46.625,132.915,123.712,28.118,137.893,9.223z" stroke="rgba(255, 255, 255, 0.4)" stroke-width="8" fill="none"/>
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="glass-card card-consumption">
            <h3>Seu Consumo Hoje</h3>
            <div class="consumption-grid">
                <div class="consumption-item">
                    <div class="progress-circle" data-value="<?php echo round($kcal_consumed); ?>" data-goal="<?php echo round($total_daily_calories_goal); ?>">
                         <svg viewBox="0 0 36 36" class="circular-chart"><defs><linearGradient id="orange-gradient" x1="0%" y1="0%" x2="0%" y2="100%"><stop offset="0%" stop-color="#FFAE00" /><stop offset="100%" stop-color="#F83600" /></linearGradient></defs><path class="circle-bg" d="M18 2.0845a 15.9155 15.9155 0 0 1 0 31.831a 15.9155 15.9155 0 0 1 0 -31.831" /><path class="circle" d="M18 2.0845a 15.9155 15.9155 0 0 1 0 31.831a 15.9155 15.9155 0 0 1 0 -31.831" /><text x="18" y="20.35" class="percentage-text"><?php echo round($kcal_consumed); ?></text></svg>
                    </div>
                    <p>Kcal</p>
                </div>
                 <div class="consumption-item">
                    <div class="progress-circle" data-value="<?php echo round($carbs_consumed); ?>" data-goal="<?php echo round($macros_goal['carbs_g']); ?>">
                         <svg viewBox="0 0 36 36" class="circular-chart"><path class="circle-bg" d="M18 2.0845a 15.9155 15.9155 0 0 1 0 31.831a 15.9155 15.9155 0 0 1 0 -31.831" /><path class="circle" d="M18 2.0845a 15.9155 15.9155 0 0 1 0 31.831a 15.9155 15.9155 0 0 1 0 -31.831" /><text x="18" y="20.35" class="percentage-text"><?php echo round($carbs_consumed); ?>g</text></svg>
                    </div>
                    <p>Carbs</p>
                </div>
                 <div class="consumption-item">
                    <div class="progress-circle" data-value="<?php echo round($protein_consumed); ?>" data-goal="<?php echo round($macros_goal['protein_g']); ?>">
                         <svg viewBox="0 0 36 36" class="circular-chart"><path class="circle-bg" d="M18 2.0845a 15.9155 15.9155 0 0 1 0 31.831a 15.9155 15.9155 0 0 1 0 -31.831" /><path class="circle" d="M18 2.0845a 15.9155 15.9155 0 0 1 0 31.831a 15.9155 15.9155 0 0 1 0 -31.831" /><text x="18" y="20.35" class="percentage-text"><?php echo round($protein_consumed); ?>g</text></svg>
                    </div>
                    <p>Proteína</p>
                </div>
                 <div class="consumption-item">
                    <div class="progress-circle" data-value="<?php echo round($fat_consumed); ?>" data-goal="<?php echo round($macros_goal['fat_g']); ?>">
                         <svg viewBox="0 0 36 36" class="circular-chart"><path class="circle-bg" d="M18 2.0845a 15.9155 15.9155 0 0 1 0 31.831a 15.9155 15.9155 0 0 1 0 -31.831" /><path class="circle" d="M18 2.0845a 15.9155 15.9155 0 0 1 0 31.831a 15.9155 15.9155 0 0 1 0 -31.831" /><text x="18" y="20.35" class="percentage-text"><?php echo round($fat_consumed); ?>g</text></svg>
                    </div>
                    <p>Gordura</p>
                </div>
            </div>
        </div>

        <?php if ($total_missions > 0): 
            $routine_progress_percentage = round(($completed_missions / $total_missions) * 100);
        ?>
        <div class="glass-card card-missions">
            <div class="card-header">
                <h3>Jornada Diária</h3>
                <a href="<?php echo BASE_APP_URL; ?>/routine.php" class="view-all-link">Ver mais</a>
            </div>
            <div class="missions-progress">
                <div class="missions-progress-info">
                    <span>Progresso</span>
                    <span id="missions-progress-text"><?php echo $completed_missions; ?> de <?php echo $total_missions; ?></span>
                </div>
                <div class="progress-bar-missions"><div class="progress-bar-missions-fill" id="missions-progress-bar" style="width: <?php echo $routine_progress_percentage; ?>%;"></div></div>
            </div>
            
            <div class="missions-carousel-container" id="missions-carousel">
                <?php foreach ($routine_items as $mission): ?>
                <div class="mission-slide" data-mission-id="<?php echo $mission['id']; ?>" data-completed="<?php echo $mission['completion_status']; ?>">
                    <div class="mission-icon"><i class="fas <?php echo htmlspecialchars($mission['icon_class']); ?>"></i></div>
                    <div class="mission-details">
                        <h4><?php echo htmlspecialchars($mission['title']); ?></h4>
                        <small class="mission-duration-display" style="display: none;"></small>
                    </div>
                    <div class="mission-actions">
                        <button class="mission-action-btn skip-btn" aria-label="Pular Missão"><i class="fas fa-times"></i></button>
                        <?php 
                        // Verificar se é missão de duração (exercício)
                        $is_duration = false;
                        $is_sleep = false;
                        
                        if (strpos($mission['id'], 'onboarding_') === 0) {
                            // Exercício onboarding - sempre é duração
                            $is_duration = true;
                        } elseif (isset($mission['is_exercise']) && $mission['is_exercise'] == 1) {
                            // Verificar se é sono ou duração baseado no exercise_type
                            if (isset($mission['exercise_type']) && $mission['exercise_type'] === 'sleep') {
                                $is_sleep = true;
                            } elseif (isset($mission['exercise_type']) && $mission['exercise_type'] === 'duration') {
                                $is_duration = true;
                            }
                        } elseif (strpos($mission['title'], 'sono') !== false || strpos($mission['title'], 'Sono') !== false) {
                            // Fallback para verificação por título
                            $is_sleep = true;
                        }
                        
                        if ($is_duration): ?>
                            <!-- Exercício com duração -->
                            <button class="mission-action-btn duration-btn" aria-label="Definir Duração" data-mission-id="<?php echo $mission['id']; ?>">
                                <i class="fas fa-clock"></i>
                            </button>
                            <button class="mission-action-btn complete-btn disabled" aria-label="Completar Missão">
                                <i class="fas fa-check"></i>
                            </button>
                        <?php elseif ($is_sleep): ?>
                            <!-- Item de sono - precisa de horários -->
                            <button class="mission-action-btn sleep-btn" aria-label="Registrar Sono" data-mission-id="<?php echo $mission['id']; ?>">
                                <i class="fas fa-clock"></i>
                            </button>
                            <button class="mission-action-btn complete-btn disabled" aria-label="Completar Missão">
                                <i class="fas fa-check"></i>
                            </button>
                        <?php else: ?>
                            <!-- Rotina normal -->
                            <button class="mission-action-btn complete-btn" aria-label="Completar Missão">
                                <i class="fas fa-check"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <!-- Card de conclusão -->
                <div class="mission-slide completion-message" id="all-missions-completed-card">
                    <div class="mission-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="mission-details"><h4>Parabéns!</h4><p>Você completou sua jornada de hoje.</p></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Modal de Sono (inicialmente oculto) -->
        <div class="modal-overlay" id="sleep-modal-main">
            <div class="modal-content glass-card">
                <h2>😴 Registrar Sono</h2>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="sleep-time-main">Hora que deitou:</label>
                        <input type="time" id="sleep-time-main" class="time-input" value="22:00">
                    </div>
                    <div class="form-group">
                        <label for="wake-time-main">Hora que acordou:</label>
                        <input type="time" id="wake-time-main" class="time-input" value="07:00">
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="primary-button secondary-button" data-action="close-modal">Cancelar</button>
                    <button type="button" class="primary-button" id="confirm-sleep-main">Registrar Sono</button>
                </div>
            </div>
        </div>
        
        <div class="glass-card card-meal-cta"><i class="fas fa-utensils"></i><h2><?php echo htmlspecialchars($meal_suggestion_data['greeting']); ?></h2><p>O que você vai comer agora?</p><a href="<?php echo BASE_APP_URL; ?>/add_food_to_diary.php?meal_type=<?php echo urlencode($meal_suggestion_data['db_param'] ?? 'lunch'); ?>&date=<?php echo $current_date; ?>" class="primary-button">Adicionar Refeição</a></div>
        
        <div class="card-suggestions"><div class="card-header"><h3>Sugestões para <?php echo htmlspecialchars($meal_suggestion_data['display_name']); ?></span></h3><a href="<?php echo BASE_APP_URL; ?>/explore_recipes.php?categories=<?php echo urlencode($meal_suggestion_data['category_id'] ?? ''); ?>" class="view-all-link">Ver mais</a></div><div class="carousel-wrapper"><div class="suggestions-carousel"><?php if (!empty($meal_suggestion_data['recipes'])): foreach($meal_suggestion_data['recipes'] as $recipe): ?><div class="suggestion-item glass-card"> <a href="<?php echo BASE_APP_URL; ?>/view_recipe.php?id=<?php echo $recipe['id']; ?>" class="suggestion-link"><div class="suggestion-image-container"><img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . htmlspecialchars($recipe['image_filename'] ? $recipe['image_filename'] : 'placeholder_food.jpg'); ?>" alt="<?php echo htmlspecialchars($recipe['name']); ?>"></div><div class="recipe-info"><h4><?php echo htmlspecialchars($recipe['name']); ?></h4><span><i class="fas fa-fire-alt"></i> <?php echo round($recipe['kcal_per_serving']); ?> kcal</span></div></a></div><?php endforeach; else: ?><div class="no-suggestions-card glass-card"><p>Nenhuma sugestão para esta refeição no momento.</p></div><?php endif; ?></div></div></div>
        
        <!-- Novos Cards no Final -->
        <div class="glass-card card-action-item">
            <div class="action-icon"><i class="fas fa-calendar-week"></i></div>
            <div class="action-content">
                <h3>Check-in Semanal</h3>
                <p>Acompanhe seu progresso semanal com gráficos e comparações</p>
            </div>
            <a href="<?php echo BASE_APP_URL; ?>/weekly_checkin.php" class="action-button">
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="glass-card card-action-item">
            <div class="action-icon"><i class="fas fa-trophy"></i></div>
            <div class="action-content">
                <h3>Salas de Desafio</h3>
                <p>Participe de desafios em grupo e ganhe recompensas</p>
            </div>
            <a href="<?php echo BASE_APP_URL; ?>/challenge_rooms_new.php" class="action-button">
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        
        <div class="glass-card card-action-item">
            <div class="action-icon premium"><i class="fas fa-crown"></i></div>
            <div class="action-content">
                <h3>Área de Membros</h3>
                <p>Conteúdo exclusivo premium e materiais especiais</p>
            </div>
            <a href="<?php echo BASE_APP_URL; ?>/members_area.php" class="action-button">
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </section>
</div>

<div class="modal-overlay" id="edit-weight-modal"><div class="modal-content glass-card"><h2>Atualizar seu Peso</h2><div class="modal-body"><div class="form-group"><label for="new-weight-input">Novo peso (kg)</label><input type="number" id="new-weight-input" class="form-input" placeholder="Ex: 75.5" step="0.1" value="<?php echo (float)($user_profile_data['weight_kg'] ?? 0); ?>"><small id="weight-error-message" class="error-message" style="display: none;"></small></div><div class="modal-actions"><button type="button" class="primary-button secondary-button" data-action="close-modal">Cancelar</button><button id="save-weight-btn" class="primary-button">Salvar</button></div></div></div></div>

<!-- Modal para duração de exercício -->
<div class="modal-overlay" id="exercise-duration-modal">
    <div class="modal-content glass-card">
        <h2>⏱️ Duração do Exercício</h2>
        <div class="modal-body">
            <div class="form-group">
                <label for="exercise-duration-input">Quanto tempo durou o exercício?</label>
                <div class="duration-input-group">
                    <input type="number" id="exercise-duration-input" class="form-input" placeholder="Ex: 45" min="15" max="300" value="60">
                    <span class="duration-unit">minutos</span>
                </div>
                <small class="form-help">Entre 15 e 300 minutos</small>
            </div>
            <div class="modal-actions">
                <button type="button" class="primary-button secondary-button" data-action="close-modal">Cancelar</button>
                <button type="button" class="primary-button" id="confirm-exercise-duration">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script>
    if (window.navigator.standalone === true) {document.addEventListener('click', function(event) {var target = event.target; while (target && target.nodeName !== 'A') { target = target.parentNode; } if (target && target.nodeName === 'A' && target.target !== '_blank') {event.preventDefault(); window.location.href = target.href;}}, false);}

    function showPointsPopup(message) {
        const popup = document.createElement('div');
        popup.className = 'points-popup';
        popup.innerHTML = `<i class="fas fa-star star-icon"></i>${message}`;
        document.body.appendChild(popup);
        setTimeout(() => { popup.remove(); }, 2500);
    }

    document.addEventListener('DOMContentLoaded', function() {
        // --- LÓGICA DO CARROSSEL DE MISSÕES ---
        const missionsCarousel = document.getElementById('missions-carousel');
        if (missionsCarousel) {
            let missionSlides = Array.from(missionsCarousel.querySelectorAll('.mission-slide:not(.completion-message)'));
            const completionCard = document.getElementById('all-missions-completed-card');
            let completedMissionsCount = <?php echo $completed_missions; ?>;
            const totalMissionsCount = <?php echo $total_missions; ?>;
            let pendingSlides = missionSlides.filter(slide => slide.dataset.completed === '0');
            
            function showCurrentMission() {
                missionSlides.forEach(s => s.classList.remove('active'));
                completionCard.classList.remove('active');
                if (pendingSlides.length > 0) {
                    pendingSlides[0].classList.add('active');
                } else {
                    completionCard.classList.add('active');
                }
            }
            
            function updateMissionsProgress() {
                const progressPercentage = totalMissionsCount > 0 ? (completedMissionsCount / totalMissionsCount) * 100 : 0;
                const progressBarFill = document.getElementById('missions-progress-bar');
                const progressText = document.getElementById('missions-progress-text');
                if (progressBarFill) { progressBarFill.style.width = `${progressPercentage}%`; }
                if (progressText) { progressText.textContent = `${completedMissionsCount} de ${totalMissionsCount}`; }
            }

            missionsCarousel.addEventListener('click', function(event) {
                // Prevenir múltiplos cliques
                if (event.target.disabled || event.target.classList.contains('processing')) {
                    return;
                }

                const completeButton = event.target.closest('.complete-btn');
                const skipButton = event.target.closest('.skip-btn');
                const durationButton = event.target.closest('.duration-btn');
                const sleepButton = event.target.closest('.sleep-btn');
                
                if (!completeButton && !skipButton && !durationButton && !sleepButton) return;
                
                const currentSlide = pendingSlides[0];
                if (!currentSlide) return;

                if (skipButton) {
                    pendingSlides.push(pendingSlides.shift());
                    showCurrentMission();
                } else if (durationButton) {
                    // Botão de duração clicado
                    const missionId = durationButton.dataset.missionId;
                    showExerciseDurationModal(missionId, currentSlide, durationButton);
                } else if (sleepButton) {
                    // Botão de sono clicado - apenas abre o modal
                    const modal = document.getElementById('sleep-modal-main');
                    if (modal) {
                        modal.classList.add('modal-visible');
                        document.body.style.overflow = 'hidden';
                    }
                } else if (completeButton) {
                    // Prevenir múltiplos cliques
                    if (completeButton.disabled || completeButton.classList.contains('processing')) {
                        return;
                    }

                    // Se o botão tem a classe .disabled, mostra o alerta e para.
                    if (completeButton.classList.contains('disabled')) {
                        const missionId = currentSlide.dataset.missionId;
                        if (String(missionId).startsWith('onboarding_')) {
                            alert('⚠️ Para completar, primeiro defina a duração do exercício!');
                        } 
                        else if (currentSlide.querySelector('.sleep-btn')) {
                            alert('⚠️ Para completar, primeiro registre seus horários de sono!');
                        }
                        return; // Impede que a tarefa seja completada.
                    }
                    
                    // Se não tiver a classe .disabled, completa a tarefa.
                    if (currentSlide.dataset.completed === '1') return;

                    const missionId = currentSlide.dataset.missionId;
                    
                    // Verificar se é uma atividade de exercício (onboarding_)
                    if (String(missionId).startsWith('onboarding_')) {
                        // Exercício onboarding - só funciona se já tiver duração definida
                        const durationBtn = currentSlide.querySelector('.duration-btn');
                        if (durationBtn && durationBtn.dataset.durationSet === 'true') {
                            completeExerciseWithDuration(missionId, durationBtn.dataset.duration, currentSlide, completeButton);
                        } else {
                            // Se chegou aqui por algum motivo (pouco provável com a verificação acima), mostre o popup
                            showPointsPopup('⚠️ Defina a duração do exercício primeiro!');
                        }
                    } else if (currentSlide.querySelector('.sleep-btn')) {
                        // Item de sono - completar diretamente (botão só fica habilitado após registrar)
                        completeSleepRoutine(missionId, completeButton);
                    } else {
                        // Completar diretamente para outras atividades
                        completeRoutineDirectly(missionId, completeButton);
                    }
                }
            });

            // Função para mostrar modal de duração de exercício
            // Função para mostrar modal de duração de exercício (VERSÃO ATUALIZADA)
            function showExerciseDurationModal(missionId, currentSlide, durationButton) {
                console.log('showExerciseDurationModal chamada!');
                const exerciseName = missionId.replace('onboarding_', '');
                const modal = document.getElementById('exercise-duration-modal');
                const durationInput = document.getElementById('exercise-duration-input');
                
                if (!modal) {
                    console.error('Modal não encontrado!');
                    return;
                }
                
                // Configurar o modal
                modal.querySelector('h2').textContent = `⏱️ Duração - ${exerciseName}`;
                
                // NOVO: Pré-preenche o valor se já foi definido (funcionalidade de edição)
                if (durationButton.dataset.durationSet === 'true') {
                    durationInput.value = durationButton.dataset.duration;
                } else {
                    durationInput.value = 60; // Valor padrão para a primeira vez
                }
                
                modal.classList.add('modal-visible');
                console.log('Modal deve estar visível agora!');
                
                const cancelBtn = modal.querySelector('[data-action="close-modal"]');
                if (cancelBtn) {
                    cancelBtn.onclick = () => {
                        modal.classList.remove('modal-visible');
                    };
                }
                
                document.getElementById('confirm-exercise-duration').onclick = () => {
                    const duration = parseInt(durationInput.value);
                    if (duration >= 15 && duration <= 300) {
                        modal.classList.remove('modal-visible');

                        // Salva a duração no botão para referência futura
                        durationButton.dataset.durationSet = 'true';
                        durationButton.dataset.duration = duration;

                        // NOVO: Garante que o botão de duração (agora edição) esteja visível
                        durationButton.style.display = 'flex'; 
                        
                        // Habilita o botão de completar original
                        const completeBtn = currentSlide.querySelector('.complete-btn.disabled');
                        if (completeBtn) {
                            completeBtn.disabled = false;
                            completeBtn.classList.remove('disabled');
                        }

                        // NOVO: Mostra o texto da duração no card
                        const durationDisplay = currentSlide.querySelector('.mission-duration-display');
                        if (durationDisplay) {
                            durationDisplay.innerHTML = `<i class="fas fa-stopwatch" style="font-size: 0.8em;"></i> ${duration} min`;
                            durationDisplay.style.display = 'flex';
                        }
                        
                        console.log('Duração definida:', duration, 'minutos');
                    } else {
                        alert('Por favor, insira uma duração entre 15 e 300 minutos.');
                    }
                };
            }

            // Função para completar exercício com duração
            function completeExerciseWithDuration(missionId, duration, currentSlide, completeButton) {
                const csrfToken = document.getElementById('csrf_token_main_app').value;
                const routineIdToSend = missionId.replace('onboarding_', '');
                
                completeButton.disabled = true;
                completeButton.classList.add('processing');

                const formData = new URLSearchParams();
                formData.append('routine_id', routineIdToSend);
                formData.append('duration_minutes', duration);
                formData.append('csrf_token', csrfToken);
                
                fetch('actions/complete_onboarding_routine.php', { method: 'POST', body: formData })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => { 
                            throw new Error(`Erro de Servidor (${response.status}): ${text}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        currentSlide.dataset.completed = '1';
                        completedMissionsCount++;
                        updateMissionsProgress();
                        pendingSlides.shift();
                        
                        const pointsDisplay = document.getElementById('user-points-display');
                        if (pointsDisplay && data.new_total_points) {
                            pointsDisplay.textContent = new Intl.NumberFormat('pt-BR').format(data.new_total_points);
                        }
                        if (data.points_awarded > 0) {
                            showPointsPopup(`+${data.points_awarded} Pontos`);
                        }
                        setTimeout(showCurrentMission, 300);
                    } else {
                        alert(data.message || 'Ocorreu um erro ao processar a solicitação.');
                        completeButton.disabled = false;
                        completeButton.style.opacity = '1';
                    }
                })
                .catch(error => {
                    console.error('Erro detalhado:', error);
                    alert('Falha na comunicação com o servidor. Verifique o console para mais detalhes.');
                    completeButton.disabled = false;
                    completeButton.classList.remove('processing');
                    completeButton.style.opacity = '1';
                });
            }

            // Função para completar rotina de sono
            function completeSleepRoutine(missionId, completeButton) {
                const csrfToken = document.getElementById('csrf_token_main_app').value;
                const sleepData = JSON.parse(sessionStorage.getItem('sleep_data'));
                
                completeButton.disabled = true;
                completeButton.classList.add('processing');

                const formData = new URLSearchParams();
                formData.append('routine_id', '8'); // ID do item de sono
                formData.append('sleep_time', sleepData.sleep_time);
                formData.append('wake_time', sleepData.wake_time);
                formData.append('csrf_token', csrfToken);
                
                fetch('actions/complete_sleep_routine.php', { 
                    method: 'POST', 
                    body: formData 
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Limpar dados do sessionStorage
                        sessionStorage.removeItem('sleep_data');
                        
                        // Atualizar o progresso das missões
                        const currentSlide = pendingSlides[0];
                        if (currentSlide) {
                            currentSlide.dataset.completed = '1';
                            completedMissionsCount++;
                            updateMissionsProgress();
                            pendingSlides.shift();
                            setTimeout(showCurrentMission, 300);
                        }
                        
                        // Atualizar pontos na interface
                        const pointsDisplay = document.getElementById('user-points-display');
                        if (pointsDisplay && data.new_total_points) {
                            pointsDisplay.textContent = new Intl.NumberFormat('pt-BR').format(data.new_total_points);
                        }
                        
                        if (data.points_awarded > 0) {
                            showPointsPopup(`+${data.points_awarded} Pontos`);
                        }
                    } else {
                        alert('Erro: ' + (data.message || 'Falha ao registrar sono'));
                        completeButton.disabled = false;
                        completeButton.classList.remove('processing');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro na comunicação com o servidor.');
                    completeButton.disabled = false;
                    completeButton.classList.remove('processing');
                });
            }

            // Função para completar rotinas normais (não exercícios)
            function completeRoutineDirectly(missionId, completeButton) {
                const csrfToken = document.getElementById('csrf_token_main_app').value;
                const endpoint = 'actions/complete_routine_item.php';
                
                completeButton.disabled = true;
                completeButton.classList.add('processing');

                const formData = new URLSearchParams();
                formData.append('routine_id', missionId);
                formData.append('csrf_token', csrfToken);
                
                fetch(endpoint, { method: 'POST', body: formData })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => { 
                            throw new Error(`Erro de Servidor (${response.status}): ${text}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const currentSlide = pendingSlides[0];
                        if (currentSlide) {
                            currentSlide.dataset.completed = '1';
                            completedMissionsCount++;
                            updateMissionsProgress();
                            pendingSlides.shift();
                            
                            const pointsDisplay = document.getElementById('user-points-display');
                            if (pointsDisplay && data.new_total_points) {
                                pointsDisplay.textContent = new Intl.NumberFormat('pt-BR').format(data.new_total_points);
                            }
                            if (data.points_awarded > 0) {
                                showPointsPopup(`+${data.points_awarded} Pontos`);
                            }
                            setTimeout(showCurrentMission, 300);
                        }
                    } else {
                        alert(data.message || 'Ocorreu um erro ao processar a solicitação.');
                        completeButton.disabled = false;
                        completeButton.classList.remove('processing');
                    }
                })
                .catch(error => {
                    console.error('Erro detalhado:', error);
                    alert('Falha na comunicação com o servidor. Verifique o console para mais detalhes.');
                    completeButton.disabled = false;
                    completeButton.classList.remove('processing');
                });
            }

            showCurrentMission();

            // Event listener para o botão de confirmar sono (dentro do escopo correto)
            document.getElementById('confirm-sleep-main').addEventListener('click', function() {
                const modal = document.getElementById('sleep-modal-main');
                const sleepTime = modal.querySelector('#sleep-time-main').value;
                const wakeTime = modal.querySelector('#wake-time-main').value;

                if (!sleepTime || !wakeTime) {
                    alert('Por favor, preencha ambos os horários.');
                    return;
                }

                if (sleepTime === wakeTime) {
                    alert('Os horários de dormir e acordar não podem ser iguais.');
                    return;
                }

                // Salvar dados no sessionStorage
                const sleepData = {
                    sleep_time: sleepTime,
                    wake_time: wakeTime
                };
                sessionStorage.setItem('sleep_data', JSON.stringify(sleepData));

                // Fechar modal
                modal.classList.remove('modal-visible');
                document.body.style.overflow = '';

            // Habilitar o botão de completar (igual aos exercícios)
            const currentSlide = pendingSlides[0];
            if (currentSlide) {
                const completeBtn = currentSlide.querySelector('.complete-btn.disabled');
                if (completeBtn) {
                    completeBtn.classList.remove('disabled');
                }
                
                // Mostrar duração do sono (igual aos exercícios)
                const durationDisplay = currentSlide.querySelector('.mission-duration-display');
                if (durationDisplay) {
                    const sleepTime = new Date(`2000-01-01T${sleepData.sleep_time}`);
                    const wakeTime = new Date(`2000-01-01T${sleepData.wake_time}`);
                    
                    // Calcular diferença em horas
                    let diffMs = wakeTime - sleepTime;
                    if (diffMs < 0) {
                        // Se acordou no dia seguinte
                        diffMs += 24 * 60 * 60 * 1000;
                    }
                    const diffHours = Math.round(diffMs / (60 * 60 * 1000) * 10) / 10;
                    
                    durationDisplay.innerHTML = `<i class="fas fa-moon" style="font-size: 0.8em;"></i> ${diffHours}h de sono`;
                    durationDisplay.style.display = 'flex';
                }
            }
            });
        }

        // --- LÓGICA DO CARD DE HIDRATAÇÃO ---
        const waterLevelGroup = document.getElementById('water-level-group');
        const waterAmountDisplay = document.getElementById('water-amount-display');
		const waterAmountInput = document.getElementById('water-amount-input');
		const waterAddBtn = document.getElementById('water-add-btn');
		const waterUnitSelect = document.getElementById('water-unit-select');

        let currentWater = <?php echo $water_consumed_ml; ?>;
        const waterGoal = <?php echo $water_goal_ml; ?>;
        const CUP_SIZE_ML = <?php echo $CUP_SIZE_IN_ML; ?>;
        const dropHeight = 275.785; 

        function updateWaterDrop(animated = true) {
            const percentage = waterGoal > 0 ? Math.min(currentWater / waterGoal, 1) : 0;
            const yTranslate = dropHeight * (1 - percentage);
            
            if (!animated) {
                waterLevelGroup.style.transition = 'none';
            }
            
            waterLevelGroup.setAttribute('transform', `translate(0, ${yTranslate})`);

            if (!animated) {
                // Força o navegador a aplicar o estilo sem transição imediatamente
                setTimeout(() => {
                    waterLevelGroup.style.transition = 'transform 0.7s cubic-bezier(0.65, 0, 0.35, 1)';
                }, 50);
            }
            waterAmountDisplay.textContent = Math.round(currentWater);
        }

        function updateWaterOnServer() {
            const csrfToken = document.getElementById('csrf_token_main_app').value;
            const formData = new URLSearchParams();
            
            // Converter ML de volta para copos para o servidor
            const waterInCups = Math.round(currentWater / CUP_SIZE_ML);
            formData.append('water_consumed', waterInCups);
            formData.append('csrf_token', csrfToken);

            fetch('api/update_water.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Erro do Servidor: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if (data.new_total_points !== undefined) {
                        const pointsDisplay = document.getElementById('user-points-display');
                        if (pointsDisplay) {
                            pointsDisplay.textContent = new Intl.NumberFormat('pt-BR').format(data.new_total_points);
                        }
                    }
                    if (data.points_awarded != 0) {
                        const sign = data.points_awarded > 0 ? '+' : '';
                        showPointsPopup(`${sign}${data.points_awarded} Pontos`);
                    }
                } else {
                    console.error('Falha ao atualizar a água no servidor:', data.message);
                }
            })
            .catch(err => {
                 console.error('Erro de conexão ou no servidor ao atualizar a água.', err);
            });
        }
        
		function clampToNonNegativeInteger(value) { value = Number(value) || 0; return value < 0 ? 0 : value; }
		function parseAmountToMl(amountValue) {
			const raw = String(amountValue || '').trim().toLowerCase();
			if (raw.endsWith('l') || (waterUnitSelect && waterUnitSelect.value === 'l')) {
				const n = parseFloat(raw.replace('l', '')) || 0;
				return Math.max(0, Math.round(n * 1000));
			}
			return Math.max(0, Math.round(parseFloat(raw) || 0));
		}

        let updateTimeout = null;
        function scheduleServerUpdate() {
            if (updateTimeout) clearTimeout(updateTimeout);
            updateTimeout = setTimeout(() => { updateWaterOnServer(); }, 600);
        }

        function mlToCups(ml) { return clampToNonNegativeInteger(Math.round((ml || 0) / CUP_SIZE_ML)); }

        function addMlAndUpdate(mlToAdd) {
            if (mlToAdd <= 0) return;
            currentWater = clampToNonNegativeInteger(currentWater + mlToAdd);
            updateWaterDrop();
            updateWaterOnServer();
        }

        function subMlAndUpdate(mlToSub) {
            if (mlToSub <= 0) return;
            currentWater = clampToNonNegativeInteger(currentWater - mlToSub);
            updateWaterDrop();
            updateWaterOnServer();
        }

        function updateControlsEnabled() {
            const amountMl = parseAmountToMl(waterAmountInput && waterAmountInput.value);
            const hasAmount = amountMl > 0;
            if (waterAddBtn) waterAddBtn.disabled = !hasAmount;
            if (waterRemoveBtn) waterRemoveBtn.disabled = !hasAmount;
        }

        if (waterAddBtn) {
            waterAddBtn.addEventListener('click', () => {
                let amountMl = parseAmountToMl(waterAmountInput && waterAmountInput.value);
                if (amountMl <= 0) return;
                addMlAndUpdate(amountMl);
                if (waterAmountInput) { waterAmountInput.value = ''; updateControlsEnabled(); }
            });
        }

		const waterRemoveBtn = document.getElementById('water-remove-btn');
		const waterRemoveFull = document.getElementById('water-remove-full');
        if (waterRemoveBtn) {
            waterRemoveBtn.addEventListener('click', () => {
				let amountMl = parseAmountToMl(waterAmountInput && waterAmountInput.value);
				if (amountMl <= 0) return;
                subMlAndUpdate(amountMl);
				if (waterAmountInput) { waterAmountInput.value = ''; updateControlsEnabled(); }
            });
        }
        if (waterRemoveFull) {
            waterRemoveFull.addEventListener('click', () => {
				let amountMl = parseAmountToMl(waterAmountInput && waterAmountInput.value);
				if (amountMl <= 0) return;
                subMlAndUpdate(amountMl);
				if (waterAmountInput) { waterAmountInput.value = ''; updateControlsEnabled(); }
            });
        }

		// Removidos chips de adição rápida conforme o design

        // Habilita/desabilita botões conforme o usuário digita
        if (waterAmountInput) {
            waterAmountInput.addEventListener('input', updateControlsEnabled);
        }
        if (waterUnitSelect) {
            waterUnitSelect.addEventListener('change', updateControlsEnabled);
        }

        updateWaterDrop(false);
        updateControlsEnabled();


        // --- LÓGICA DOS CÍRCULOS DE PROGRESSO ---
        document.querySelectorAll('.progress-circle').forEach(circleElement => {
            const value = parseFloat(circleElement.dataset.value) || 0;
            const goal = parseFloat(circleElement.dataset.goal) || 1;
            const circle = circleElement.querySelector('.circle');
            const radius = 15.9155;
            const circumference = 2 * Math.PI * radius;
            
            let percent = (value / goal);
            if (percent > 1) percent = 1;
            if (percent < 0) percent = 0;

            const offset = circumference - (percent * circumference);

            circle.style.strokeDasharray = `${circumference} ${circumference}`;
            setTimeout(() => {
                circle.style.strokeDashoffset = offset;
            }, 100);
        });

        // --- FUNCIONALIDADE DE SONO ---
        
        // Event listeners para fechar modal
        document.addEventListener('click', function(e) {
            if (e.target.closest('[data-action="close-modal"]')) {
                const modal = document.getElementById('sleep-modal-main');
                if (modal) {
                    modal.classList.remove('modal-visible');
                    document.body.style.overflow = '';
                }
            }
        });
        

    });


</script>

<?php
require_once APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>