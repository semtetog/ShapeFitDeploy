<?php
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';
$conn = require __DIR__ . '/../../includes/db.php';

// Verificar se admin está logado (simplificado para AJAX)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die('Acesso não autorizado');
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$daysToShow = 30;
$startDate = date('Y-m-d', strtotime($endDate . " -" . ($daysToShow - 1) . " days"));

// Buscar histórico de refeições
function getGroupedMealHistory($conn, $user_id, $startDate, $endDate) {
    $stmt = $conn->prepare("
        SELECT 
            DATE(log.logged_at) as date,
            mt.name as meal_type,
            CASE mt.id
                WHEN 1 THEN 'lunch'
                WHEN 2 THEN 'breakfast'
                WHEN 3 THEN 'supper'
                WHEN 4 THEN 'dinner'
                WHEN 5 THEN 'snack'
                ELSE 'breakfast'
            END as meal_type_slug,
            f.name_pt as food_name,
            log.quantity,
            f.energy_kcal_100g as kcal_per_100g,
            f.protein_g_100g as protein_per_100g,
            f.carbohydrate_g_100g as carbs_per_100g,
            f.fat_g_100g as fat_per_100g
        FROM sf_user_meal_log log
        JOIN sf_food_items f ON log.food_id = f.id
        JOIN sf_meal_times mt ON log.meal_type_id = mt.id
        WHERE log.user_id = ? 
            AND DATE(log.logged_at) >= ? 
            AND DATE(log.logged_at) <= ?
        ORDER BY DATE(log.logged_at) DESC, log.logged_at DESC
    ");
    $stmt->bind_param("iss", $user_id, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $grouped = [];
    while ($row = $result->fetch_assoc()) {
        $date = $row['date'];
        $kcal_consumed = ($row['kcal_per_100g'] / 100) * $row['quantity'];
        $protein_consumed = ($row['protein_per_100g'] / 100) * $row['quantity'];
        $carbs_consumed = ($row['carbs_per_100g'] / 100) * $row['quantity'];
        $fat_consumed = ($row['fat_per_100g'] / 100) * $row['quantity'];
        
        if (!isset($grouped[$date])) {
            $grouped[$date] = [];
        }
        
        if (!isset($grouped[$date][$row['meal_type_slug']])) {
            $grouped[$date][$row['meal_type_slug']] = [];
        }
        
        $grouped[$date][$row['meal_type_slug']][] = [
            'name' => $row['food_name'],
            'quantity' => $row['quantity'],
            'kcal_consumed' => $kcal_consumed,
            'protein_consumed_g' => $protein_consumed,
            'carbs_consumed_g' => $carbs_consumed,
            'fat_consumed_g' => $fat_consumed
        ];
    }
    $stmt->close();
    return $grouped;
}

$meal_history = getGroupedMealHistory($conn, $user_id, $startDate, $endDate);

// Gerar array com TODOS os dias, mesmo se não houver dados
$all_dates = [];
for ($i = 0; $i < $daysToShow; $i++) {
    $current_date = date('Y-m-d', strtotime($endDate . " -$i days"));
    $all_dates[] = $current_date;
}

// Inverter ordem: mais antigo à esquerda, mais recente à direita
$all_dates = array_reverse($all_dates);

foreach ($all_dates as $date): 
    $meals = $meal_history[$date] ?? [];
    $day_total_kcal = 0;
    $day_total_prot = 0;
    $day_total_carb = 0;
    $day_total_fat = 0;
    
    if (!empty($meals)) {
        foreach ($meals as $meal_type_slug => $items) {
            $day_total_kcal += array_sum(array_column($items, 'kcal_consumed'));
            $day_total_prot += array_sum(array_column($items, 'protein_consumed_g'));
            $day_total_carb += array_sum(array_column($items, 'carbs_consumed_g'));
            $day_total_fat += array_sum(array_column($items, 'fat_consumed_g'));
        }
    }
    
    // Formatar data por extenso
    $timestamp = strtotime($date);
    $day_of_week = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][date('w', $timestamp)];
?>

<div class="diary-day-card" data-date="<?php echo $date; ?>">
    <div class="diary-day-summary" style="display: none;">
        <div class="diary-summary-item">
            <i class="fas fa-fire"></i>
            <span><?php echo round($day_total_kcal); ?> kcal</span>
        </div>
        <div class="diary-summary-macros">
            P: <?php echo round($day_total_prot); ?>g • 
            C: <?php echo round($day_total_carb); ?>g • 
            G: <?php echo round($day_total_fat); ?>g
        </div>
    </div>
    
    <div class="diary-day-meals">
        <?php if (empty($meals)): 
            $is_today = ($date === date('Y-m-d'));
            $is_future = (strtotime($date) > strtotime(date('Y-m-d')));
        ?>
            <div class="diary-empty-state">
                <?php if ($is_future): ?>
                    <i class="fas fa-calendar-alt"></i>
                    <p class="empty-state-title">Dia Futuro</p>
                    <p class="empty-state-message">Este dia ainda não chegou</p>
                <?php elseif ($is_today): ?>
                    <i class="fas fa-utensils"></i>
                    <p class="empty-state-title">Nenhum registro ainda hoje</p>
                    <p class="empty-state-message">Adicione alimentos ao seu diário</p>
                <?php else: ?>
                    <i class="fas fa-file-alt"></i>
                    <p class="empty-state-title">Nenhum registro neste dia</p>
                    <p class="empty-state-message">Não há registros para este dia</p>
                <?php endif; ?>
                <div class="empty-calories">
                    <i class="fas fa-fire"></i>
                    <span>0 kcal</span>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($meals as $meal_type_slug => $items): ?>
                <div class="diary-meal-card">
                    <div class="diary-meal-header">
                        <i class="fas fa-utensils"></i>
                        <span><?php echo ucfirst(str_replace('_', ' ', $meal_type_slug)); ?></span>
                    </div>
                    <ul class="diary-food-list">
                        <?php foreach ($items as $item): ?>
                            <li>
                                <span class="food-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                <span class="food-quantity"><?php echo round($item['quantity']); ?>g</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php 
endforeach; 
$conn->close();
?>