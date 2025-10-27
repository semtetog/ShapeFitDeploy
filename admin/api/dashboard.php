<?php
// admin-react/api/dashboard.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

// Verifica se está logado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Conexão com o banco
$conn = getConnection();

try {
    // Estatísticas do dashboard (MESMAS DO PAINEL ANTIGO)
    $stats = [];
    
    // 1. Total de usuários (Pacientes)
    $result = $conn->query("SELECT COUNT(*) as total FROM sf_users");
    $stats['total_users'] = $result->fetch_assoc()['total'];
    
    // 2. Cardápios registrados (Diários)
    $result = $conn->query("SELECT COUNT(DISTINCT user_id, date_consumed) as total FROM sf_user_meal_log");
    $stats['total_diaries'] = $result->fetch_assoc()['total'];
    
    // 3. Receitas ativas
    $result = $conn->query("SELECT COUNT(*) as total FROM sf_recipes");
    $stats['total_recipes'] = $result->fetch_assoc()['total'];
    
    // 4. Alimentos classificados (CORRIGIDO: sf_food_items)
    $result = $conn->query("SELECT COUNT(*) as total FROM sf_food_items");
    $stats['total_foods'] = $result->fetch_assoc()['total'];
    
    // 5. Dados para gráficos (MESMOS DO PAINEL ANTIGO)
    
    // Novos usuários por mês
    $new_users_data_query = $conn->query("SELECT MONTH(created_at) as month, COUNT(id) as count FROM sf_users WHERE YEAR(created_at) = YEAR(CURDATE()) GROUP BY MONTH(created_at) ORDER BY month ASC");
    $new_users_chart_data = array_fill(1, 12, 0);
    while($row = $new_users_data_query->fetch_assoc()) {
        $new_users_chart_data[(int)$row['month']] = (int)$row['count'];
    }
    
    // Distribuição por gênero
    $gender_data_query = $conn->query("SELECT gender, COUNT(id) as count FROM sf_user_profiles GROUP BY gender");
    $gender_chart_data = ['labels' => [], 'data' => []];
    while($row = $gender_data_query->fetch_assoc()) {
        $gender_label = 'Outro';
        if (strtolower($row['gender']) === 'male') {
            $gender_label = 'Masculino';
        } elseif (strtolower($row['gender']) === 'female') {
            $gender_label = 'Feminino';
        }
        $gender_chart_data['labels'][] = $gender_label;
        $gender_chart_data['data'][] = (int)$row['count'];
    }
    
    // Objetivos dos usuários
    $objective_data_query = $conn->query("SELECT objective, COUNT(id) as count FROM sf_user_profiles GROUP BY objective");
    $objective_chart_data = ['labels' => [], 'data' => []];
    $objective_names = [
        'lose_fat' => 'Emagrecimento',
        'gain_muscle' => 'Hipertrofia',
        'maintain_weight' => 'Manter Peso'
    ];
    while($row = $objective_data_query->fetch_assoc()) {
        $objective_key = strtolower($row['objective']);
        $objective_chart_data['labels'][] = $objective_names[$objective_key] ?? ucfirst($row['objective']);
        $objective_chart_data['data'][] = (int)$row['count'];
    }
    
    // Faixa etária
    $age_data_query = $conn->query("SELECT dob FROM sf_user_profiles WHERE dob IS NOT NULL AND dob != '0000-00-00'");
    $age_distribution = ['15-24' => 0, '25-34' => 0, '35-44' => 0, '45-54' => 0, '55-64' => 0, '65+' => 0];
    while($row = $age_data_query->fetch_assoc()) {
        $age = calculateAge($row['dob']);
        if ($age >= 15 && $age <= 24) $age_distribution['15-24']++;
        elseif ($age >= 25 && $age <= 34) $age_distribution['25-34']++;
        elseif ($age >= 35 && $age <= 44) $age_distribution['35-44']++;
        elseif ($age >= 45 && $age <= 54) $age_distribution['45-54']++;
        elseif ($age >= 55 && $age <= 64) $age_distribution['55-64']++;
        elseif ($age >= 65) $age_distribution['65+']++;
    }
    $age_chart_data = ['labels' => array_keys($age_distribution), 'data' => array_values($age_distribution)];
    
    // IMC
    $imc_data_query = $conn->query("SELECT weight_kg, height_cm FROM sf_user_profiles WHERE weight_kg > 0 AND height_cm > 0");
    $imc_distribution = ['Abaixo do peso' => 0, 'Peso Ideal' => 0, 'Sobrepeso' => 0, 'Obesidade' => 0];
    while($row = $imc_data_query->fetch_assoc()) {
        $imc = calculateIMC((float)$row['weight_kg'], (int)$row['height_cm']);
        $category = getIMCCategory($imc);
        if (str_contains($category, 'Obesidade')) {
            $imc_distribution['Obesidade']++;
        } elseif (isset($imc_distribution[$category])) {
            $imc_distribution[$category]++;
        }
    }
    $imc_chart_data = ['labels' => array_keys($imc_distribution), 'data' => array_values($imc_distribution)];
    
    // Usuários recentes
    $result = $conn->query("
        SELECT id, name, email, created_at 
        FROM sf_users 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recent_users = [];
    while ($row = $result->fetch_assoc()) {
        $recent_users[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => $stats,
            'recent_users' => $recent_users,
            'charts' => [
                'newUsers' => array_values($new_users_chart_data),
                'genderDistribution' => $gender_chart_data,
                'objectivesDistribution' => $objective_chart_data,
                'ageDistribution' => $age_chart_data,
                'imcDistribution' => $imc_chart_data
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Dashboard API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

$conn->close();
?>
