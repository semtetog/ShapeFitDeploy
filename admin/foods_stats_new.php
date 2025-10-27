<?php
// admin/foods_stats_new.php - Estatísticas de Alimentos - Design Profissional

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'foods_stats';
$page_title = 'Estatísticas de Alimentos';

// Estatísticas gerais
$stats = [];

// Total de alimentos
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM sf_food_items")->fetch_assoc()['count'];

// Por fonte
$stats_query = "SELECT source_table, COUNT(*) as count FROM sf_food_items GROUP BY source_table ORDER BY count DESC";
$stats_result = $conn->query($stats_query);
while ($row = $stats_result->fetch_assoc()) {
    $stats['by_source'][$row['source_table']] = $row['count'];
}

// Estatísticas nutricionais
$nutrition_stats = $conn->query("
    SELECT 
        AVG(energy_kcal_100g) as avg_calories,
        MIN(energy_kcal_100g) as min_calories,
        MAX(energy_kcal_100g) as max_calories,
        AVG(protein_g_100g) as avg_protein,
        MIN(protein_g_100g) as min_protein,
        MAX(protein_g_100g) as max_protein,
        AVG(carbohydrate_g_100g) as avg_carbs,
        MIN(carbohydrate_g_100g) as min_carbs,
        MAX(carbohydrate_g_100g) as max_carbs,
        AVG(fat_g_100g) as avg_fat,
        MIN(fat_g_100g) as min_fat,
        MAX(fat_g_100g) as max_fat
    FROM sf_food_items
")->fetch_assoc();

// Alimentos mais calóricos
$most_caloric = $conn->query("
    SELECT name_pt, energy_kcal_100g, source_table 
    FROM sf_food_items 
    ORDER BY energy_kcal_100g DESC 
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Alimentos mais proteicos
$most_protein = $conn->query("
    SELECT name_pt, protein_g_100g, source_table 
    FROM sf_food_items 
    ORDER BY protein_g_100g DESC 
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Distribuição de calorias
$calorie_ranges = $conn->query("
    SELECT 
        CASE 
            WHEN energy_kcal_100g < 50 THEN '0-50'
            WHEN energy_kcal_100g < 100 THEN '50-100'
            WHEN energy_kcal_100g < 200 THEN '100-200'
            WHEN energy_kcal_100g < 300 THEN '200-300'
            WHEN energy_kcal_100g < 500 THEN '300-500'
            ELSE '500+'
        END as range_label,
        COUNT(*) as count
    FROM sf_food_items 
    GROUP BY range_label 
    ORDER BY MIN(energy_kcal_100g)
")->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo BASE_ADMIN_URL; ?>/assets/css/foods_admin.css">

<div class="admin-container">
    <!-- Cabeçalho -->
    <div class="page-header">
        <h1>
            <i class="fas fa-chart-bar"></i>
            Estatísticas de Alimentos
        </h1>
    </div>
    
    <!-- Estatísticas Gerais -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label">Total de Alimentos</div>
        </div>
        
        <?php foreach ($stats['by_source'] as $source => $count): ?>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($count); ?></div>
                <div class="stat-label">
                    <?php 
                    switch ($source) {
                        case 'TACO': echo 'TACO'; break;
                        case 'Sonia Tucunduva': echo 'Sonia'; break;
                        case 'Sonia Tucunduva (Prioridade)': echo 'Sonia (Atualizado)'; break;
                        case 'USDA': echo 'USDA'; break;
                        case 'FatSecret': echo 'FatSecret'; break;
                        default: echo htmlspecialchars($source);
                    }
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Estatísticas Nutricionais -->
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-chart-line"></i>
                Estatísticas Nutricionais
            </h2>
        </div>
        <div class="section-content">
            <div class="form-grid">
                <div class="form-group">
                    <label>Calorias (100g)</label>
                    <div style="background: var(--primary-bg); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 12px;">
                        <div style="font-size: 18px; font-weight: 600; color: var(--accent-orange); margin-bottom: 4px;">
                            <?php echo number_format($nutrition_stats['avg_calories'], 1); ?> kcal
                        </div>
                        <div style="font-size: 12px; color: var(--text-secondary);">
                            Média | Min: <?php echo number_format($nutrition_stats['min_calories'], 1); ?> | Max: <?php echo number_format($nutrition_stats['max_calories'], 1); ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Proteína (100g)</label>
                    <div style="background: var(--primary-bg); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 12px;">
                        <div style="font-size: 18px; font-weight: 600; color: var(--accent-orange); margin-bottom: 4px;">
                            <?php echo number_format($nutrition_stats['avg_protein'], 1); ?>g
                        </div>
                        <div style="font-size: 12px; color: var(--text-secondary);">
                            Média | Min: <?php echo number_format($nutrition_stats['min_protein'], 1); ?>g | Max: <?php echo number_format($nutrition_stats['max_protein'], 1); ?>g
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Carboidratos (100g)</label>
                    <div style="background: var(--primary-bg); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 12px;">
                        <div style="font-size: 18px; font-weight: 600; color: var(--accent-orange); margin-bottom: 4px;">
                            <?php echo number_format($nutrition_stats['avg_carbs'], 1); ?>g
                        </div>
                        <div style="font-size: 12px; color: var(--text-secondary);">
                            Média | Min: <?php echo number_format($nutrition_stats['min_carbs'], 1); ?>g | Max: <?php echo number_format($nutrition_stats['max_carbs'], 1); ?>g
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Gordura (100g)</label>
                    <div style="background: var(--primary-bg); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 12px;">
                        <div style="font-size: 18px; font-weight: 600; color: var(--accent-orange); margin-bottom: 4px;">
                            <?php echo number_format($nutrition_stats['avg_fat'], 1); ?>g
                        </div>
                        <div style="font-size: 12px; color: var(--text-secondary);">
                            Média | Min: <?php echo number_format($nutrition_stats['min_fat'], 1); ?>g | Max: <?php echo number_format($nutrition_stats['max_fat'], 1); ?>g
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Distribuição de Calorias -->
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-chart-pie"></i>
                Distribuição de Calorias
            </h2>
        </div>
        <div class="section-content">
            <div style="background: var(--primary-bg); border: 1px solid var(--border-color); border-radius: var(--radius); padding: 20px; margin-bottom: 20px;">
                <canvas id="calorieChart" width="400" height="200"></canvas>
            </div>
            
            <div class="table-container">
                <div class="table-header">
                    <h3 class="table-title">Distribuição por Faixa de Calorias</h3>
                </div>
                <div class="table-content">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Faixa de Calorias</th>
                                <th>Quantidade</th>
                                <th>Percentual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($calorie_ranges as $range): ?>
                                <tr>
                                    <td><?php echo $range['range_label']; ?> kcal</td>
                                    <td><?php echo number_format($range['count']); ?></td>
                                    <td><?php echo number_format(($range['count'] / $stats['total']) * 100, 1); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alimentos Mais Calóricos -->
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-fire"></i>
                Alimentos Mais Calóricos
            </h2>
        </div>
        <div class="section-content">
            <div class="table-container">
                <div class="table-content">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nome</th>
                                <th>Calorias (100g)</th>
                                <th>Fonte</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($most_caloric as $index => $food): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($food['name_pt']); ?></td>
                                    <td><?php echo number_format($food['energy_kcal_100g'], 1); ?> kcal</td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                            switch ($food['source_table']) {
                                                case 'TACO': echo 'badge-taco'; break;
                                                case 'Sonia Tucunduva': echo 'badge-sonia'; break;
                                                case 'Sonia Tucunduva (Prioridade)': echo 'badge-priority'; break;
                                                case 'USDA': echo 'badge-usda'; break;
                                                case 'FatSecret': echo 'badge-fatsecret'; break;
                                                default: echo 'badge-taco';
                                            }
                                            ?>">
                                            <?php echo htmlspecialchars($food['source_table']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alimentos Mais Proteicos -->
    <div class="section">
        <div class="section-header">
            <h2 class="section-title">
                <i class="fas fa-dumbbell"></i>
                Alimentos Mais Proteicos
            </h2>
        </div>
        <div class="section-content">
            <div class="table-container">
                <div class="table-content">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nome</th>
                                <th>Proteína (100g)</th>
                                <th>Fonte</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($most_protein as $index => $food): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($food['name_pt']); ?></td>
                                    <td><?php echo number_format($food['protein_g_100g'], 1); ?>g</td>
                                    <td>
                                        <span class="badge 
                                            <?php 
                                            switch ($food['source_table']) {
                                                case 'TACO': echo 'badge-taco'; break;
                                                case 'Sonia Tucunduva': echo 'badge-sonia'; break;
                                                case 'Sonia Tucunduva (Prioridade)': echo 'badge-priority'; break;
                                                case 'USDA': echo 'badge-usda'; break;
                                                case 'FatSecret': echo 'badge-fatsecret'; break;
                                                default: echo 'badge-taco';
                                            }
                                            ?>">
                                            <?php echo htmlspecialchars($food['source_table']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Ações Finais -->
    <div class="text-center mt-3">
        <a href="foods_management_new.php" class="btn btn-primary">
            <i class="fas fa-cogs"></i>
            Gerenciar Alimentos
        </a>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Voltar ao Dashboard
        </a>
    </div>
</div>

<script>
// Gráfico de distribuição de calorias
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('calorieChart').getContext('2d');
    
    const calorieData = <?php echo json_encode($calorie_ranges); ?>;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: calorieData.map(item => item.range_label + ' kcal'),
            datasets: [{
                label: 'Quantidade de Alimentos',
                data: calorieData.map(item => item.count),
                backgroundColor: 'rgba(255, 107, 53, 0.6)',
                borderColor: 'rgba(255, 107, 53, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#b3b3b3'
                    },
                    grid: {
                        color: '#333'
                    }
                },
                x: {
                    ticks: {
                        color: '#b3b3b3'
                    },
                    grid: {
                        color: '#333'
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#ffffff'
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

