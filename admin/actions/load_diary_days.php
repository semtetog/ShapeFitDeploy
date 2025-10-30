<?php
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions_admin.php';
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
// Aceita 'date' (um único dia) ou 'end_date'+'days'
$requestedDate = $_GET['date'] ?? null;
if ($requestedDate) {
    $endDate = $requestedDate;
    $daysToShow = 1;
    $startDate = $requestedDate;
} else {
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $daysToShow = isset($_GET['days']) ? (int)$_GET['days'] : 1; // Padrão: 1 dia
    $startDate = date('Y-m-d', strtotime($endDate . " -" . ($daysToShow - 1) . " days"));
}

// Usar a função correta do functions_admin.php
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

<div class="diary-content-day" data-date="<?php echo $date; ?>" data-kcal="<?php echo (int)round($day_total_kcal); ?>" data-protein="<?php echo (int)round($day_total_prot); ?>" data-carbs="<?php echo (int)round($day_total_carb); ?>" data-fat="<?php echo (int)round($day_total_fat); ?>">
    <!-- Cabeçalho e resumos virão do container pai; abaixo apenas conteúdo do dia -->
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
        <?php if (empty($meals)): ?>
            <div class="diary-empty-state">
                <i class="fas fa-utensils"></i>
                <p>Nenhum registro neste dia</p>
            </div>
        <?php else: ?>
            <?php 
            // Mapear nomes dos tipos de refeição (com TODOS os tipos possíveis)
            $meal_type_names = [
                'breakfast' => 'Café da Manhã',
                'morning_snack' => 'Lanche da Manhã',
                'lunch' => 'Almoço',
                'afternoon_snack' => 'Lanche da Tarde',
                'dinner' => 'Jantar',
                'evening_snack' => 'Ceia',
                'supper' => 'Ceia',
                'pre_workout' => 'Pré-Treino',
                'pre-workout' => 'Pré-Treino',
                'post_workout' => 'Pós-Treino',
                'post-workout' => 'Pós-Treino'
            ];
            
            // Ordenar cards por horário de registro (mais cedo primeiro)
            uasort($meals, function($a, $b) {
                $time_a = strtotime(reset($a)['logged_at'] ?? '9999-12-31 23:59:59');
                $time_b = strtotime(reset($b)['logged_at'] ?? '9999-12-31 23:59:59');
                return $time_a <=> $time_b;
            });
            
            foreach ($meals as $meal_type_slug => $items): 
                $total_kcal = array_sum(array_column($items, 'kcal_consumed'));
                $total_prot = array_sum(array_column($items, 'protein_consumed_g'));
                $total_carb = array_sum(array_column($items, 'carbs_consumed_g'));
                $total_fat = array_sum(array_column($items, 'fat_consumed_g'));
                
            ?>
                <div class="diary-meal-card">
                    <div class="diary-meal-header">
                        <div class="diary-meal-icon">
                            <?php
                            $meal_icons = [
                                'breakfast' => 'fa-coffee',
                                'morning_snack' => 'fa-apple-alt',
                                'lunch' => 'fa-drumstick-bite',
                                'afternoon_snack' => 'fa-cookie-bite',
                                'dinner' => 'fa-pizza-slice',
                                'evening_snack' => 'fa-ice-cream',
                                'supper' => 'fa-ice-cream',
                                'pre_workout' => 'fa-dumbbell',
                                'pre-workout' => 'fa-dumbbell',
                                'post_workout' => 'fa-trophy',
                                'post-workout' => 'fa-trophy'
                            ];
                            $icon = $meal_icons[$meal_type_slug] ?? 'fa-utensils';
                            ?>
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="diary-meal-info">
                            <h5 style="margin: 0;"><?php echo $meal_type_names[$meal_type_slug] ?? ucfirst($meal_type_slug); ?></h5>
                            <span class="diary-meal-totals">
                                <strong><?php echo round($total_kcal); ?> kcal</strong> • 
                                P:<?php echo round($total_prot); ?>g • 
                                C:<?php echo round($total_carb); ?>g • 
                                G:<?php echo round($total_fat); ?>g
                            </span>
                        </div>
                    </div>
                    <ul class="diary-food-list">
                        <?php foreach ($items as $item): 
                            $logged_at = $item['logged_at'] ?? null;
                            $item_time = '';
                            if ($logged_at) {
                                $timestamp = strtotime($logged_at);
                                $item_time = date('H:i', $timestamp);
                            }
                        ?>
                            <li>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span class="food-name"><?php echo htmlspecialchars($item['food_name']); ?></span>
                                    <?php if ($item_time): ?>
                                        <span style="font-size: 0.8rem; color: var(--accent-orange); font-weight: 500; white-space: nowrap;">
                                            <i class="fas fa-clock" style="margin-right: 2px;"></i><?php echo $item_time; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span class="food-quantity"><?php echo htmlspecialchars($item['quantity_display']); ?></span>
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