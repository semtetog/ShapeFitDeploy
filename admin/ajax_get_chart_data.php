<?php
// ajax_get_chart_data.php - Busca dados de gráfico de hidratação ou nutrientes por período

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
$conn = require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/functions_admin.php';
requireAdminLogin();

header('Content-Type: application/json');

$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING); // 'hydration' ou 'nutrients'
$start_date = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING);
$end_date = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING);

if (!$user_id || !$type || !$start_date || !$end_date) {
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos']);
    exit;
}

try {
    if ($type === 'hydration') {
        // Buscar meta de água do usuário
        $stmt = $conn->prepare("SELECT custom_water_goal_ml, weight_kg FROM sf_user_profiles WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $water_goal_ml = !empty($result['custom_water_goal_ml']) 
            ? (int)$result['custom_water_goal_ml'] 
            : getWaterIntakeSuggestion($result['weight_kg'] ?? 0)['total_ml'];
        
        // Buscar dados de hidratação
        $stmt = $conn->prepare("
            SELECT 
                DATE(date) AS dia,
                SUM(water_consumed_cups) AS total_cups
            FROM sf_user_daily_tracking
            WHERE user_id = ? 
              AND DATE(date) BETWEEN ? AND ?
            GROUP BY DATE(date)
            ORDER BY dia ASC
        ");
        $stmt->bind_param("iss", $user_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Gerar calendário completo do período (cronológico)
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $datas = [];
        
        // Gerar todos os dias do período (do início ao fim)
        $current = clone $start;
        while ($current <= $end) {
            $dataStr = $current->format('Y-m-d');
            $datas[$dataStr] = [
                'date' => $dataStr,
                'ml' => 0,
                'cups' => 0,
                'percentage' => 0,
                'status' => 'empty'
            ];
            $current->modify('+1 day');
        }
        
        // Preencher com dados reais
        while ($row = $result->fetch_assoc()) {
            $data = $row['dia'];
            $cups = (int)$row['total_cups'];
            $water_ml = $cups * 250;
            $percentage = $water_goal_ml > 0 ? min(round(($water_ml / $water_goal_ml) * 100, 1), 100) : 0;
            
            if (isset($datas[$data])) {
                $datas[$data]['ml'] = $water_ml;
                $datas[$data]['cups'] = $cups;
                $datas[$data]['percentage'] = $percentage;
                
                // Determinar status
                if ($percentage == 0) {
                    $datas[$data]['status'] = 'empty';
                } elseif ($percentage >= 100) {
                    $datas[$data]['status'] = 'excellent';
                } elseif ($percentage >= 90) {
                    $datas[$data]['status'] = 'good';
                } elseif ($percentage >= 70) {
                    $datas[$data]['status'] = 'fair';
                } elseif ($percentage >= 50) {
                    $datas[$data]['status'] = 'poor';
                } else {
                    $datas[$data]['status'] = 'critical';
                }
            }
        }
        
        $stmt->close();
        
        // Converter para array ordenado (mais antigo primeiro para exibição)
        ksort($datas);
        $daily_data = array_values($datas);
        
        echo json_encode([
            'success' => true,
            'data' => $daily_data
        ]);
        
    } elseif ($type === 'nutrients') {
        // Buscar metas nutricionais
        $stmt = $conn->prepare("
            SELECT 
                u.id, p.custom_calories_goal, p.weight_kg, p.height_cm, p.dob, 
                p.exercise_frequency, p.objective, p.gender
            FROM sf_users u
            LEFT JOIN sf_user_profiles p ON u.id = p.user_id
            WHERE u.id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $gender = $user_data['gender'] ?? 'male';
        $weight_kg = (float)($user_data['weight_kg'] ?? 70);
        $height_cm = (int)($user_data['height_cm'] ?? 170);
        $dob = $user_data['dob'] ?? date('Y-m-d', strtotime('-30 years'));
        $exercise_frequency = $user_data['exercise_frequency'] ?? 'sedentary';
        $objective = $user_data['objective'] ?? 'maintain';
        
        $age_years = calculateAge($dob);
        
        $total_daily_calories_goal = !empty($user_data['custom_calories_goal']) 
            ? (int)$user_data['custom_calories_goal']
            : calculateTargetDailyCalories($gender, $weight_kg, $height_cm, $age_years, $exercise_frequency, $objective);
        
        // Buscar dados de nutrientes
        $stmt = $conn->prepare("
            SELECT 
                DATE(date_consumed) AS dia,
                SUM(kcal_consumed) AS total_kcal,
                SUM(protein_consumed_g) AS total_protein,
                SUM(carbs_consumed_g) AS total_carbs,
                SUM(fat_consumed_g) AS total_fat
            FROM sf_user_meal_log
            WHERE user_id = ? 
              AND DATE(date_consumed) BETWEEN ? AND ?
            GROUP BY DATE(date_consumed)
            ORDER BY dia ASC
        ");
        $stmt->bind_param("iss", $user_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Gerar calendário completo do período (cronológico)
        $start = new DateTime($start_date);
        $end = new DateTime($end_date);
        $datas = [];
        
        // Gerar todos os dias do período (do início ao fim)
        $current = clone $start;
        while ($current <= $end) {
            $dataStr = $current->format('Y-m-d');
            $datas[$dataStr] = [
                'date' => $dataStr,
                'kcal_consumed' => 0,
                'protein_consumed_g' => 0,
                'carbs_consumed_g' => 0,
                'fat_consumed_g' => 0,
                'percentage' => 0,
                'status' => 'poor'
            ];
            $current->modify('+1 day');
        }
        
        // Preencher com dados reais
        while ($row = $result->fetch_assoc()) {
            $data = $row['dia'];
            $kcal = (int)$row['total_kcal'];
            $percentage = $total_daily_calories_goal > 0 ? round(($kcal / $total_daily_calories_goal) * 100, 1) : 0;
            
            if (isset($datas[$data])) {
                $datas[$data]['kcal_consumed'] = $kcal;
                $datas[$data]['protein_consumed_g'] = (float)$row['total_protein'];
                $datas[$data]['carbs_consumed_g'] = (float)$row['total_carbs'];
                $datas[$data]['fat_consumed_g'] = (float)$row['total_fat'];
                $datas[$data]['percentage'] = $percentage;
                
                // Determinar status
                if ($percentage >= 90) {
                    $datas[$data]['status'] = 'excellent';
                } elseif ($percentage >= 70) {
                    $datas[$data]['status'] = 'good';
                } elseif ($percentage >= 50) {
                    $datas[$data]['status'] = 'fair';
                } else {
                    $datas[$data]['status'] = 'poor';
                }
            }
        }
        
        $stmt->close();
        
        // Converter para array ordenado (mais antigo primeiro para exibição)
        ksort($datas);
        $daily_data = array_values($datas);
        
        echo json_encode([
            'success' => true,
            'data' => $daily_data
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tipo inválido']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>

