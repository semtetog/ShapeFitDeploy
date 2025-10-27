<?php
// admin/dashboard.php (VERSÃO FINAL COMPLETA E CORRIGIDA)

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php'; // Usamos a função calculateAge

requireAdminLogin();

$page_slug = 'dashboard';
$page_title = 'Dashboard';
$extra_js = ['charts_logic.js'];

// --- LÓGICA PARA BUSCAR DADOS DO DASHBOARD ---

// 1. Contagem de usuários
$total_users_result = $conn->query("SELECT COUNT(id) as total FROM sf_users");
$total_users = $total_users_result->fetch_assoc()['total'] ?? 0;

// 2. Contagem de cardápios (diários)
$total_diaries_result = $conn->query("SELECT COUNT(DISTINCT user_id, date_consumed) as total FROM sf_user_meal_log");
$total_diaries = $total_diaries_result->fetch_assoc()['total'] ?? 0;

// 3. Dados para o gráfico de Novos Usuários por Mês
$new_users_data_query = $conn->query("SELECT MONTH(created_at) as month, COUNT(id) as count FROM sf_users WHERE YEAR(created_at) = YEAR(CURDATE()) GROUP BY MONTH(created_at) ORDER BY month ASC");
$new_users_chart_data = array_fill(1, 12, 0);
while($row = $new_users_data_query->fetch_assoc()) {
    $new_users_chart_data[(int)$row['month']] = (int)$row['count'];
}

// 4. Dados para o Gráfico de Distribuição por Gênero (Pizza)
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

// 5. Dados para o Gráfico de Objetivos (Barras)
$objective_data_query = $conn->query("SELECT objective, COUNT(id) as count FROM sf_user_profiles GROUP BY objective");
$objective_chart_data = ['labels' => [], 'data' => []];
$objective_names = [
    'lose_fat' => 'Emagrecimento',
    'gain_muscle' => 'Hipertrofia',
    'maintain_weight' => 'Manter Peso'
];
while($row = $objective_data_query->fetch_assoc()) {
    // --- CORREÇÃO APLICADA AQUI ---
    // Converte a chave do banco para minúsculas para garantir a correspondência
    $objective_key = strtolower($row['objective']);
    $objective_chart_data['labels'][] = $objective_names[$objective_key] ?? ucfirst($row['objective']);
    $objective_chart_data['data'][] = (int)$row['count'];
}

// 6. Dados para o Gráfico de Faixa Etária (Barras)
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

// 7. Dados para o Gráfico de IMC (Barras)
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

require_once __DIR__ . '/includes/header.php';
?>

<h2>Dashboard</h2>

<!-- Cards de Estatísticas -->
<div class="stats-cards-grid">
    <div class="stat-card">
        <h3>Usuários</h3>
        <p class="stat-value"><?php echo $total_users; ?></p>
    </div>
    <div class="stat-card">
        <h3>Cardápios no diário</h3>
        <p class="stat-value"><?php echo $total_diaries; ?></p>
    </div>
</div>

<!-- Grid de Gráficos -->
<div class="dashboard-grid">
    <div class="dashboard-card large-card">
        <h3>Novos Usuários em <?php echo date('Y'); ?></h3>
        <canvas id="newUsersChart"></canvas>
    </div>
    <div class="dashboard-card">
        <h3>Distribuição por Gênero</h3>
        <canvas id="genderChart"></canvas>
    </div>
    <div class="dashboard-card">
        <h3>Objetivos dos Usuários</h3>
        <canvas id="objectivesChart"></canvas>
    </div>
    <div class="dashboard-card">
        <h3>Distribuição por Faixa Etária</h3>
        <canvas id="ageChart"></canvas>
    </div>
    <div class="dashboard-card">
        <h3>Distribuição por IMC</h3>
        <canvas id="imcChart"></canvas>
    </div>
</div>

<!-- Passando TODOS os dados do PHP para o JavaScript -->
<script>
    const chartData = {
        newUsers: <?php echo json_encode(array_values($new_users_chart_data)); ?>,
        genderDistribution: <?php echo json_encode($gender_chart_data); ?>,
        objectivesDistribution: <?php echo json_encode($objective_chart_data); ?>,
        ageDistribution: <?php echo json_encode($age_chart_data); ?>,
        imcDistribution: <?php echo json_encode($imc_chart_data); ?>
    };
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>