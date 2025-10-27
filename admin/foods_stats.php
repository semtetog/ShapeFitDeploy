<?php
// admin/foods_stats.php - Estatísticas detalhadas de alimentos

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'foods';
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

// Alimentos mais ricos em carboidratos
$most_carbs = $conn->query("
    SELECT name_pt, carbohydrate_g_100g, source_table 
    FROM sf_food_items 
    ORDER BY carbohydrate_g_100g DESC 
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Alimentos mais ricos em gordura
$most_fat = $conn->query("
    SELECT name_pt, fat_g_100g, source_table 
    FROM sf_food_items 
    ORDER BY fat_g_100g DESC 
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

<style>
.admin-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 16px;
    text-align: center;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
    color: var(--accent-orange);
    margin-bottom: 4px;
}

.stat-label {
    font-size: 12px;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.section {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.section-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--accent-orange);
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.nutrition-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.nutrition-card {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 12px;
    text-align: center;
}

.nutrition-metric {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.nutrition-value {
    font-size: 12px;
    color: var(--text-secondary);
}

.top-foods-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.top-foods-table th,
.top-foods-table td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.top-foods-table th {
    background: rgba(255, 255, 255, 0.05);
    font-weight: 600;
    color: var(--text-primary);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.top-foods-table td {
    color: var(--text-secondary);
}

.food-name {
    font-weight: 500;
    color: var(--text-primary);
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.source-badge {
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
}

.source-taco {
    background: rgba(255, 193, 7, 0.1);
    color: #ffc107;
}

.source-sonia {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.source-usda {
    background: rgba(0, 123, 255, 0.1);
    color: #007bff;
}

.source-priority {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
}

.chart-container {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 20px;
}

.btn {
    background: var(--accent-orange);
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s ease;
    margin: 5px;
}

.btn:hover {
    background: #ff7a1a;
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .nutrition-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .top-foods-table {
        font-size: 11px;
    }
    
    .top-foods-table th,
    .top-foods-table td {
        padding: 6px 8px;
    }
}
</style>

<div class="admin-container">
    <h1><i class="fas fa-chart-bar"></i> Estatísticas de Alimentos</h1>
    
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
                        default: echo htmlspecialchars($source);
                    }
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Estatísticas Nutricionais -->
    <div class="section">
        <h2 class="section-title"><i class="fas fa-chart-line"></i> Estatísticas Nutricionais</h2>
        
        <div class="nutrition-grid">
            <div class="nutrition-card">
                <div class="nutrition-metric">Calorias (100g)</div>
                <div class="nutrition-value">
                    Média: <?php echo number_format($nutrition_stats['avg_calories'], 1); ?> kcal<br>
                    Min: <?php echo number_format($nutrition_stats['min_calories'], 1); ?> kcal<br>
                    Max: <?php echo number_format($nutrition_stats['max_calories'], 1); ?> kcal
                </div>
            </div>
            
            <div class="nutrition-card">
                <div class="nutrition-metric">Proteína (100g)</div>
                <div class="nutrition-value">
                    Média: <?php echo number_format($nutrition_stats['avg_protein'], 1); ?>g<br>
                    Min: <?php echo number_format($nutrition_stats['min_protein'], 1); ?>g<br>
                    Max: <?php echo number_format($nutrition_stats['max_protein'], 1); ?>g
                </div>
            </div>
            
            <div class="nutrition-card">
                <div class="nutrition-metric">Carboidratos (100g)</div>
                <div class="nutrition-value">
                    Média: <?php echo number_format($nutrition_stats['avg_carbs'], 1); ?>g<br>
                    Min: <?php echo number_format($nutrition_stats['min_carbs'], 1); ?>g<br>
                    Max: <?php echo number_format($nutrition_stats['max_carbs'], 1); ?>g
                </div>
            </div>
            
            <div class="nutrition-card">
                <div class="nutrition-metric">Gordura (100g)</div>
                <div class="nutrition-value">
                    Média: <?php echo number_format($nutrition_stats['avg_fat'], 1); ?>g<br>
                    Min: <?php echo number_format($nutrition_stats['min_fat'], 1); ?>g<br>
                    Max: <?php echo number_format($nutrition_stats['max_fat'], 1); ?>g
                </div>
            </div>
        </div>
    </div>
    
    <!-- Distribuição de Calorias -->
    <div class="section">
        <h2 class="section-title"><i class="fas fa-chart-pie"></i> Distribuição de Calorias</h2>
        
        <div class="chart-container">
            <canvas id="calorieChart" width="400" height="200"></canvas>
        </div>
        
        <table class="top-foods-table">
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
    
    <!-- Alimentos Mais Calóricos -->
    <div class="section">
        <h2 class="section-title"><i class="fas fa-fire"></i> Alimentos Mais Calóricos</h2>
        
        <table class="top-foods-table">
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
                        <td>
                            <div class="food-name" title="<?php echo htmlspecialchars($food['name_pt']); ?>">
                                <?php echo htmlspecialchars($food['name_pt']); ?>
                            </div>
                        </td>
                        <td><?php echo number_format($food['energy_kcal_100g'], 1); ?> kcal</td>
                        <td>
                            <span class="source-badge 
                                <?php 
                                switch ($food['source_table']) {
                                    case 'TACO': echo 'source-taco'; break;
                                    case 'Sonia Tucunduva': echo 'source-sonia'; break;
                                    case 'Sonia Tucunduva (Prioridade)': echo 'source-priority'; break;
                                    case 'USDA': echo 'source-usda'; break;
                                    default: echo 'source-taco';
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
    
    <!-- Alimentos Mais Proteicos -->
    <div class="section">
        <h2 class="section-title"><i class="fas fa-dumbbell"></i> Alimentos Mais Proteicos</h2>
        
        <table class="top-foods-table">
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
                        <td>
                            <div class="food-name" title="<?php echo htmlspecialchars($food['name_pt']); ?>">
                                <?php echo htmlspecialchars($food['name_pt']); ?>
                            </div>
                        </td>
                        <td><?php echo number_format($food['protein_g_100g'], 1); ?>g</td>
                        <td>
                            <span class="source-badge 
                                <?php 
                                switch ($food['source_table']) {
                                    case 'TACO': echo 'source-taco'; break;
                                    case 'Sonia Tucunduva': echo 'source-sonia'; break;
                                    case 'Sonia Tucunduva (Prioridade)': echo 'source-priority'; break;
                                    case 'USDA': echo 'source-usda'; break;
                                    default: echo 'source-taco';
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
    
    <!-- Alimentos Mais Ricos em Carboidratos -->
    <div class="section">
        <h2 class="section-title"><i class="fas fa-bread-slice"></i> Alimentos Mais Ricos em Carboidratos</h2>
        
        <table class="top-foods-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nome</th>
                    <th>Carboidratos (100g)</th>
                    <th>Fonte</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($most_carbs as $index => $food): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <div class="food-name" title="<?php echo htmlspecialchars($food['name_pt']); ?>">
                                <?php echo htmlspecialchars($food['name_pt']); ?>
                            </div>
                        </td>
                        <td><?php echo number_format($food['carbohydrate_g_100g'], 1); ?>g</td>
                        <td>
                            <span class="source-badge 
                                <?php 
                                switch ($food['source_table']) {
                                    case 'TACO': echo 'source-taco'; break;
                                    case 'Sonia Tucunduva': echo 'source-sonia'; break;
                                    case 'Sonia Tucunduva (Prioridade)': echo 'source-priority'; break;
                                    case 'USDA': echo 'source-usda'; break;
                                    default: echo 'source-taco';
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
    
    <!-- Alimentos Mais Ricos em Gordura -->
    <div class="section">
        <h2 class="section-title"><i class="fas fa-seedling"></i> Alimentos Mais Ricos em Gordura</h2>
        
        <table class="top-foods-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nome</th>
                    <th>Gordura (100g)</th>
                    <th>Fonte</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($most_fat as $index => $food): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td>
                            <div class="food-name" title="<?php echo htmlspecialchars($food['name_pt']); ?>">
                                <?php echo htmlspecialchars($food['name_pt']); ?>
                            </div>
                        </td>
                        <td><?php echo number_format($food['fat_g_100g'], 1); ?>g</td>
                        <td>
                            <span class="source-badge 
                                <?php 
                                switch ($food['source_table']) {
                                    case 'TACO': echo 'source-taco'; break;
                                    case 'Sonia Tucunduva': echo 'source-sonia'; break;
                                    case 'Sonia Tucunduva (Prioridade)': echo 'source-priority'; break;
                                    case 'USDA': echo 'source-usda'; break;
                                    default: echo 'source-taco';
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
    
    <div style="text-align: center; margin-top: 20px;">
        <a href="foods_management.php" class="btn"><i class="fas fa-cogs"></i> Gerenciar Alimentos</a>
        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar ao Dashboard</a>
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
                backgroundColor: 'rgba(255, 107, 0, 0.6)',
                borderColor: 'rgba(255, 107, 0, 1)',
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
                        color: '#A0A0A0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#A0A0A0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#EAEAEA'
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
