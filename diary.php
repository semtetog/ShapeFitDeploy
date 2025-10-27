<?php
// public_html/diary.php - VERSÃO REFATORADA COM ESTILO COMPACTO

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

$user_id = $_SESSION['user_id'];
$selected_date_str = $_GET['date'] ?? date('Y-m-d');
$date_obj = DateTime::createFromFormat('Y-m-d', $selected_date_str);
if (!$date_obj) {
    $selected_date_str = date('Y-m-d');
    $date_obj = new DateTime();
}

$today_str = date('Y-m-d');
$selected_date_display = ($selected_date_str == $today_str) ? "Hoje" : (($selected_date_str == date('Y-m-d', strtotime('-1 day'))) ? "Ontem" : $date_obj->format('d/m/Y'));

// --- Busca de Dados ---
$daily_tracking = getDailyTrackingRecord($conn, $user_id, $selected_date_str);
$kcal_consumed = $daily_tracking['kcal_consumed'] ?? 0;
$carbs_consumed = $daily_tracking['carbs_consumed_g'] ?? 0;
$protein_consumed = $daily_tracking['protein_consumed_g'] ?? 0;
$fat_consumed = $daily_tracking['fat_consumed_g'] ?? 0;

$stmt_profile = $conn->prepare("SELECT weight_kg, dob, gender, height_cm, objective, exercise_frequency FROM sf_user_profiles WHERE user_id = ?");
$stmt_profile->bind_param("i", $user_id);
$stmt_profile->execute();
$user_profile_data = $stmt_profile->get_result()->fetch_assoc();
$stmt_profile->close();

$total_calories_goal = 2000;
$macros_goal = ['protein_g' => 150, 'carbs_g' => 200, 'fat_g' => 60];
if ($user_profile_data) {
    $age = calculateAge($user_profile_data['dob']);
    $exercise_frequency = $user_profile_data['exercise_frequency'] ?? 'sedentary';
    $total_calories_goal = calculateTargetDailyCalories($user_profile_data['gender'], (float)$user_profile_data['weight_kg'], (int)$user_profile_data['height_cm'], $age, $exercise_frequency, $user_profile_data['objective']);
    $macros_goal = calculateMacronutrients($total_calories_goal, $user_profile_data['objective']);
}

// LÓGICA DE BUSCA CORRETA COM LEFT JOIN
$logged_meals = [];
$stmt_meals = $conn->prepare("
    SELECT 
        log.id, 
        log.meal_type, 
        log.custom_meal_name, 
        log.kcal_consumed,
        log.protein_consumed_g,
        log.carbs_consumed_g,
        log.fat_consumed_g,
        log.logged_at,
        r.name as recipe_name,
        r.image_filename
    FROM sf_user_meal_log log
    LEFT JOIN sf_recipes r ON log.recipe_id = r.id
    WHERE log.user_id = ? AND log.date_consumed = ?
    ORDER BY log.logged_at ASC
");
$stmt_meals->bind_param("is", $user_id, $selected_date_str);
$stmt_meals->execute();
$result_meals = $stmt_meals->get_result();
while ($meal = $result_meals->fetch_assoc()) {
    $logged_meals[] = $meal;
}
$stmt_meals->close();

// Agrupar refeições por tipo
$meal_groups = [];
$meal_types = [
    'breakfast' => 'Café da Manhã',
    'morning_snack' => 'Lanche da Manhã',
    'lunch' => 'Almoço',
    'afternoon_snack' => 'Lanche da Tarde',
    'dinner' => 'Jantar',
    'supper' => 'Ceia'
];

foreach ($logged_meals as $meal) {
    $type = $meal['meal_type'];
    if (!isset($meal_groups[$type])) {
        $meal_groups[$type] = [];
    }
    $meal_groups[$type][] = $meal;
}

$page_title = "Diário Alimentar";
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* === DIARY PAGE - ESTILO COMPACTO E MODERNO === */

/* Header compacto */
.diary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-title {
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.date-selector {
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(255, 255, 255, 0.04);
    padding: 8px 16px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.date-nav-arrow {
    color: var(--text-secondary);
    font-size: 16px;
    text-decoration: none;
    transition: color 0.2s ease;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.date-nav-arrow:hover {
    color: var(--accent-orange);
}

.date-nav-arrow.disabled {
    opacity: 0.3;
    pointer-events: none;
}

#current-diary-date {
    font-weight: 600;
    color: var(--text-primary);
    font-size: 14px;
}

/* Resumo nutricional compacto */
.nutrition-summary {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 20px;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    text-align: center;
}

.nutrition-card {
    padding: 12px 8px;
}

.nutrition-card h3 {
    font-size: 11px;
    color: var(--text-secondary);
    margin: 0 0 6px 0;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.nutrition-value {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.nutrition-goal {
    font-size: 10px;
    color: var(--text-secondary);
    margin-top: 2px;
}

/* Lista de refeições compacta */
.meals-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.meal-group {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    overflow: hidden;
}

.meal-group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.02);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.meal-group-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.meal-group-total {
    font-size: 12px;
    color: var(--accent-orange);
    font-weight: 600;
}

.meal-items {
    padding: 12px 16px;
}

.meal-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.meal-item:last-child {
    border-bottom: none;
}

.meal-item-info {
    flex: 1;
}

.meal-item-name {
    font-size: 14px;
    color: var(--text-primary);
    margin: 0 0 2px 0;
    font-weight: 500;
}

.meal-item-details {
    font-size: 11px;
    color: var(--text-secondary);
    margin: 0;
}

.meal-item-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.meal-item-kcal {
    font-size: 12px;
    color: var(--accent-orange);
    font-weight: 600;
}

.meal-edit-btn {
    color: var(--text-secondary);
    font-size: 0.9rem;
    padding: 6px;
    border-radius: 6px;
    transition: all 0.2s ease;
    text-decoration: none;
}

.meal-edit-btn:hover {
    color: var(--accent-orange);
    background: rgba(255, 107, 0, 0.1);
}

/* Seção de adicionar refeição integrada */
.add-meal-section {
    margin-top: 20px;
    padding: 16px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
}

.add-meal-btn-integrated {
    width: 100%;
    height: 48px;
    border-radius: 12px;
    background: var(--accent-orange);
    border: none;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.add-meal-btn-integrated:hover {
    background: #ff7a1a;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(255, 107, 0, 0.3);
}

.add-meal-btn-integrated i {
    font-size: 16px;
}

/* Estado vazio */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 48px;
    color: var(--accent-orange);
    margin-bottom: 16px;
}

.empty-state h3 {
    font-size: 18px;
    color: var(--text-primary);
    margin: 0 0 8px 0;
}

.empty-state p {
    font-size: 14px;
    margin: 0;
}

/* Responsividade */
@media (max-width: 768px) {
    .nutrition-summary {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
        padding: 12px;
    }
    
    .nutrition-card {
        padding: 8px 4px;
    }
    
    .nutrition-value {
        font-size: 14px;
    }
    
    .nutrition-card h3 {
        font-size: 10px;
    }
    
    .page-title {
        font-size: 20px;
    }
    
    .date-selector {
        padding: 6px 12px;
        gap: 8px;
    }
    
    .add-meal-section {
        margin-top: 16px;
        padding: 12px;
    }
    
    .add-meal-btn-integrated {
        height: 44px;
        font-size: 13px;
    }
}

/* Adicione este novo bloco ao seu CSS na página diary.php */
.app-container {
    padding-top: 24px; /* Fallback */
    padding-top: env(safe-area-inset-top);
}
</style>

<div class="app-container">
    <!-- Header com navegação de data -->
    <div class="diary-header">
        <h1 class="page-title">Diário</h1>
        <div class="date-selector">
            <a href="?date=<?php echo date('Y-m-d', strtotime($selected_date_str . ' -1 day')); ?>" 
               class="date-nav-arrow <?php echo ($selected_date_str == date('Y-m-d', strtotime('-7 days'))) ? 'disabled' : ''; ?>">
                <i class="fas fa-chevron-left"></i>
            </a>
            <span id="current-diary-date"><?php echo $selected_date_display; ?></span>
            <a href="?date=<?php echo date('Y-m-d', strtotime($selected_date_str . ' +1 day')); ?>" 
               class="date-nav-arrow <?php echo ($selected_date_str >= $today_str) ? 'disabled' : ''; ?>">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </div>

    <!-- Resumo nutricional compacto -->
    <div class="nutrition-summary">
        <div class="nutrition-card">
            <h3>Calorias</h3>
            <div class="nutrition-value"><?php echo round($kcal_consumed); ?></div>
            <div class="nutrition-goal">/ <?php echo $total_calories_goal; ?> kcal</div>
        </div>
        <div class="nutrition-card">
            <h3>Proteínas</h3>
            <div class="nutrition-value"><?php echo round($protein_consumed); ?>g</div>
            <div class="nutrition-goal">/ <?php echo $macros_goal['protein_g']; ?>g</div>
        </div>
        <div class="nutrition-card">
            <h3>Carboidratos</h3>
            <div class="nutrition-value"><?php echo round($carbs_consumed); ?>g</div>
            <div class="nutrition-goal">/ <?php echo $macros_goal['carbs_g']; ?>g</div>
        </div>
        <div class="nutrition-card">
            <h3>Gorduras</h3>
            <div class="nutrition-value"><?php echo round($fat_consumed); ?>g</div>
            <div class="nutrition-goal">/ <?php echo $macros_goal['fat_g']; ?>g</div>
        </div>
    </div>

    <!-- Lista de refeições -->
    <div class="meals-list">
        <?php if (empty($meal_groups)): ?>
            <div class="empty-state">
                <i class="fas fa-utensils"></i>
                <h3>Nenhuma refeição registrada</h3>
                <p>Adicione sua primeira refeição do dia</p>
            </div>
        <?php else: ?>
            <?php foreach ($meal_groups as $meal_type => $meals): ?>
                <?php 
                $type_name = $meal_types[$meal_type];
                $meal_group_kcal = array_sum(array_column($meals, 'kcal_consumed'));
                ?>
                <div class="meal-group">
                    <div class="meal-group-header">
                        <h3 class="meal-group-title"><?php echo htmlspecialchars($type_name); ?></h3>
                        <div class="meal-group-total"><?php echo round($meal_group_kcal); ?> kcal</div>
                    </div>
                    <div class="meal-items">
                        <?php foreach ($meals as $meal): ?>
                            <div class="meal-item">
                                <div class="meal-item-info">
                                    <div class="meal-item-name">
                                        <?php echo htmlspecialchars($meal['recipe_name'] ?: $meal['custom_meal_name']); ?>
                                    </div>
                                    <div class="meal-item-details">
                                        P: <?php echo round($meal['protein_consumed_g']); ?>g | 
                                        C: <?php echo round($meal['carbs_consumed_g']); ?>g | 
                                        G: <?php echo round($meal['fat_consumed_g']); ?>g
                                    </div>
                                </div>
                                <div class="meal-item-actions">
                                    <div class="meal-item-kcal"><?php echo round($meal['kcal_consumed']); ?> kcal</div>
                                    <a href="<?php echo BASE_APP_URL; ?>/edit_meal.php?id=<?php echo $meal['id']; ?>" class="meal-edit-btn" title="Editar refeição">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Botão de adicionar refeição integrado -->
    <div class="add-meal-section">
        <button class="add-meal-btn-integrated" onclick="window.location.href='<?php echo BASE_APP_URL; ?>/add_food_to_diary.php?date=<?php echo $selected_date_str; ?>'">
            <i class="fas fa-plus"></i>
            <span>Adicionar Refeição</span>
        </button>
    </div>
</div>

<?php require_once APP_ROOT_PATH . '/includes/layout_bottom_nav.php'; ?>
<?php require_once APP_ROOT_PATH . '/includes/layout_footer.php'; ?>