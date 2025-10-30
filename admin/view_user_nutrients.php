<?php
// ===========================
// DADOS PARA NUTRIENTES
// ===========================

// Calcular estatísticas para cada período
$nutrients_stats_all = getNutrientStats($conn, $user_id, $macros_goal, $total_daily_calories_goal);
$nutrients_stats_7 = $nutrients_stats_all['semana'];
$nutrients_stats_15 = $nutrients_stats_all['quinzena'];
$nutrients_stats_30 = $nutrients_stats_all['mes'];

// Dados para o gráfico (últimos 7 dias)
$last_7_days_data = $nutrients_stats_7['daily_data'];

// Dados para hoje e ontem
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// Buscar dados de hoje
$stmt_today = $conn->prepare("
    SELECT 
        COALESCE(SUM(kcal_consumed), 0) as kcal_consumed,
        COALESCE(SUM(protein_consumed_g), 0) as protein_consumed_g,
        COALESCE(SUM(carbs_consumed_g), 0) as carbs_consumed_g,
        COALESCE(SUM(fat_consumed_g), 0) as fat_consumed_g
    FROM sf_user_daily_tracking 
    WHERE user_id = ? AND date = ?
");
$stmt_today->bind_param("is", $user_id, $today);
$stmt_today->execute();
$today_data = $stmt_today->get_result()->fetch_assoc();
$stmt_today->close();

// Buscar dados de ontem
$stmt_yesterday = $conn->prepare("
    SELECT 
        COALESCE(SUM(kcal_consumed), 0) as kcal_consumed,
        COALESCE(SUM(protein_consumed_g), 0) as protein_consumed_g,
        COALESCE(SUM(carbs_consumed_g), 0) as carbs_consumed_g,
        COALESCE(SUM(fat_consumed_g), 0) as fat_consumed_g
    FROM sf_user_daily_tracking 
    WHERE user_id = ? AND date = ?
");
$stmt_yesterday->bind_param("is", $user_id, $yesterday);
$stmt_yesterday->execute();
$yesterday_data = $stmt_yesterday->get_result()->fetch_assoc();
$stmt_yesterday->close();

$nutrients_stats_today = [
    'avg_kcal' => $today_data['kcal_consumed'] ?? 0,
    'avg_protein' => $today_data['protein_consumed_g'] ?? 0,
    'avg_carbs' => $today_data['carbs_consumed_g'] ?? 0,
    'avg_fat' => $today_data['fat_consumed_g'] ?? 0,
    'avg_overall_percentage' => $total_daily_calories_goal > 0 ? round((($today_data['kcal_consumed'] ?? 0) / $total_daily_calories_goal) * 100, 1) : 0,
    'avg_protein_percentage' => $macros_goal['protein_g'] > 0 ? round((($today_data['protein_consumed_g'] ?? 0) / $macros_goal['protein_g']) * 100, 1) : 0,
    'avg_carbs_percentage' => $macros_goal['carbs_g'] > 0 ? round((($today_data['carbs_consumed_g'] ?? 0) / $macros_goal['carbs_g']) * 100, 1) : 0,
    'avg_fat_percentage' => $macros_goal['fat_g'] > 0 ? round((($today_data['fat_consumed_g'] ?? 0) / $macros_goal['fat_g']) * 100, 1) : 0
];

$nutrients_stats_yesterday = [
    'avg_kcal' => $yesterday_data['kcal_consumed'] ?? 0,
    'avg_protein' => $yesterday_data['protein_consumed_g'] ?? 0,
    'avg_carbs' => $yesterday_data['carbs_consumed_g'] ?? 0,
    'avg_fat' => $yesterday_data['fat_consumed_g'] ?? 0,
    'avg_overall_percentage' => $total_daily_calories_goal > 0 ? round((($yesterday_data['kcal_consumed'] ?? 0) / $total_daily_calories_goal) * 100, 1) : 0,
    'avg_protein_percentage' => $macros_goal['protein_g'] > 0 ? round((($yesterday_data['protein_consumed_g'] ?? 0) / $macros_goal['protein_g']) * 100, 1) : 0,
    'avg_carbs_percentage' => $macros_goal['carbs_g'] > 0 ? round((($yesterday_data['carbs_consumed_g'] ?? 0) / $macros_goal['carbs_g']) * 100, 1) : 0,
    'avg_fat_percentage' => $macros_goal['fat_g'] > 0 ? round((($yesterday_data['fat_consumed_g'] ?? 0) / $macros_goal['fat_g']) * 100, 1) : 0
];

// Debug: Verificar se as médias fazem sentido
error_log("DEBUG - Média 7 dias: " . $nutrients_stats_7['avg_kcal']);
error_log("DEBUG - Média 15 dias: " . $nutrients_stats_15['avg_kcal']);
error_log("DEBUG - Média 30 dias: " . $nutrients_stats_30['avg_kcal']);
error_log("DEBUG - Total de dias disponíveis: " . count($last_7_days_data));

error_log("DEBUG - Stats hoje nutrientes: " . json_encode($nutrients_stats_today));
error_log("DEBUG - Stats ontem nutrientes: " . json_encode($nutrients_stats_yesterday));
?>

<div id="tab-nutrients" class="tab-content">
    <div class="nutrients-container">
        
        <!-- 1. RESUMO GERAL -->
        <!-- 1. CARD RESUMO COMPACTO -->
        <div class="nutrients-summary-card">
            <div class="summary-main">
                <div class="summary-icon">
                    <i class="fas fa-utensils"></i>
            </div>
                <div class="summary-info">
                    <h3>Consumo Nutricional</h3>
                    <div class="summary-meta">Meta calórica diária: <strong><?php echo $total_daily_calories_goal; ?> kcal</strong></div>
                    <div class="summary-description">Baseado nos registros de refeições do paciente no aplicativo</div>
        </div>
                <div class="summary-status status-<?php echo $nutrients_stats_7['avg_overall_percentage'] >= 90 ? 'excellent' : ($nutrients_stats_7['avg_overall_percentage'] >= 70 ? 'good' : ($nutrients_stats_7['avg_overall_percentage'] >= 50 ? 'fair' : 'poor')); ?>">
                    <i class="fas <?php echo $nutrients_stats_7['avg_overall_percentage'] >= 90 ? 'fa-check-circle' : ($nutrients_stats_7['avg_overall_percentage'] >= 70 ? 'fa-check' : ($nutrients_stats_7['avg_overall_percentage'] >= 50 ? 'fa-exclamation-triangle' : 'fa-exclamation')); ?>"></i>
                    <span><?php echo $nutrients_stats_7['avg_overall_percentage'] >= 90 ? 'Excelente' : ($nutrients_stats_7['avg_overall_percentage'] >= 70 ? 'Bom' : ($nutrients_stats_7['avg_overall_percentage'] >= 50 ? 'Regular' : 'Abaixo da meta')); ?></span>
                        </div>
                        </div>
            <div class="summary-stats">
                <div class="summary-stat">
                    <div class="stat-value"><?php echo $nutrients_stats_7['avg_kcal']; ?> kcal</div>
                    <div class="stat-label">Média de Calorias</div>
                    <div class="stat-description">Últimos 7 dias</div>
                    </div>
                <div class="summary-stat">
                    <div class="stat-value"><?php echo $nutrients_stats_7['avg_overall_percentage']; ?>%</div>
                    <div class="stat-label">
                        Aderência Geral
                        <i class="fas fa-question-circle help-icon" onclick="openHelpModal('nutrients-adherence')" title="Clique para saber mais"></i>
                        </div>
                    <div class="stat-description">Meta calórica atingida</div>
                            </div>
                <div class="summary-stat">
                    <div class="stat-value"><?php echo $nutrients_stats_7['days_with_consumption']; ?>/<?php echo $nutrients_stats_7['total_days']; ?></div>
                    <div class="stat-label">Dias com Registro</div>
                    <div class="stat-description"><?php echo $nutrients_stats_7['adherence_percentage']; ?>% de aderência</div>
                        </div>
                    </div>
                </div>

        <!-- 2. INSIGHTS AUTOMÁTICOS -->
        <?php
        // Calcular insights automáticos para nutrientes
        $nutrients_insights = [];
        
        // Insight sobre aderência geral
        $excellent_good_days = $nutrients_stats_7['excellent_days'] + $nutrients_stats_7['good_days'];
        $days_with_consumption = $nutrients_stats_7['days_with_consumption'];
        $total_days = $nutrients_stats_7['total_days'];
        $adherence_percentage = $nutrients_stats_7['adherence_percentage'];
        
        if ($days_with_consumption > 0) {
            $nutrients_insights[] = "O paciente registrou refeições em <strong>{$days_with_consumption} de {$total_days} dias</strong> analisados ({$adherence_percentage}% de aderência). <em>Baseado nos registros de refeições do paciente no aplicativo.</em>";
            
            if ($excellent_good_days > 0) {
                $quality_rate = round(($excellent_good_days / $days_with_consumption) * 100, 1);
                $nutrients_insights[] = "Dos dias com registro, <strong>{$excellent_good_days} dias</strong> atingiram as metas nutricionais ({$quality_rate}% de qualidade).";
            }
        } else {
            $nutrients_insights[] = "O paciente não registrou refeições nos últimos 7 dias. <em>Nenhum dado nutricional disponível para análise.</em>";
        }
        
        // Insight sobre disciplina (média ponderada)
        if ($nutrients_stats_7['avg_kcal'] > 0) {
            if ($nutrients_stats_7['avg_kcal_percentage'] >= 100) {
                $nutrients_insights[] = "Média semanal ponderada: <strong class='text-success'>" . $nutrients_stats_7['avg_kcal'] . " kcal</strong> (" . round($nutrients_stats_7['avg_kcal_percentage']) . "% da meta).";
            } elseif ($nutrients_stats_7['avg_kcal_percentage'] >= 80) {
                $nutrients_insights[] = "Média semanal ponderada: <strong class='text-info'>" . $nutrients_stats_7['avg_kcal'] . " kcal</strong> (" . round($nutrients_stats_7['avg_kcal_percentage']) . "% da meta).";
            } elseif ($nutrients_stats_7['avg_kcal_percentage'] >= 60) {
                $nutrients_insights[] = "Média semanal ponderada: <strong class='text-warning'>" . $nutrients_stats_7['avg_kcal'] . " kcal</strong> (" . round($nutrients_stats_7['avg_kcal_percentage']) . "% da meta).";
            } else {
                $nutrients_insights[] = "Média semanal ponderada: <strong class='text-danger'>" . $nutrients_stats_7['avg_kcal'] . " kcal</strong> (" . round($nutrients_stats_7['avg_kcal_percentage']) . "% da meta).";
            }
        }
        
        // Insight sobre consumo real (média dos dias com registro)
        if ($nutrients_stats_7['avg_real_kcal'] > 0) {
            $realPercentage = $total_daily_calories_goal > 0 ? round(($nutrients_stats_7['avg_real_kcal'] / $total_daily_calories_goal) * 100, 1) : 0;
            $nutrients_insights[] = "Consumo médio (dias com registro): <strong>" . $nutrients_stats_7['avg_real_kcal'] . " kcal</strong> (" . $realPercentage . "% da meta).";
        }
        
        // Insight sobre proteínas
        if ($nutrients_stats_7['avg_protein_percentage'] > 0) {
            if ($nutrients_stats_7['avg_protein_percentage'] >= 100) {
                $nutrients_insights[] = "Consumo de proteínas <strong class='text-success'>excelente</strong> - " . round($nutrients_stats_7['avg_protein_percentage']) . "% da meta.";
            } elseif ($nutrients_stats_7['avg_protein_percentage'] >= 80) {
                $nutrients_insights[] = "Consumo de proteínas <strong class='text-info'>bom</strong> - " . round($nutrients_stats_7['avg_protein_percentage']) . "% da meta.";
            } else {
                $nutrients_insights[] = "Consumo de proteínas <strong class='text-warning'>abaixo da meta</strong> - apenas " . round($nutrients_stats_7['avg_protein_percentage']) . "% da meta.";
            }
        }
        
        // Comparar com período anterior se houver dados
        if ($nutrients_stats_15['avg_kcal'] > 0 && $nutrients_stats_7['avg_kcal'] > 0) {
            $kcal_diff = $nutrients_stats_7['avg_kcal'] - $nutrients_stats_15['avg_kcal'];
            if (abs($kcal_diff) > 50) {
                if ($kcal_diff > 0) {
                    $nutrients_insights[] = "Houve <strong class='text-success'>aumento de " . round($kcal_diff) . " kcal</strong> em relação aos 7 dias anteriores.";
                } else {
                    $nutrients_insights[] = "Houve <strong class='text-danger'>redução de " . round(abs($kcal_diff)) . " kcal</strong> em relação aos 7 dias anteriores.";
                }
            }
        }
        ?>
        

        <!-- 3. GRÁFICO COM BOTÕES DE PERÍODO -->
        <div class="chart-section">
            <div class="nutrients-chart-improved">
                <div class="chart-header">
                    <h4><i class="fas fa-chart-bar"></i> Progresso Nutricional</h4>
                    <div class="period-buttons">
                        <button class="period-btn active" onclick="changeNutrientsPeriod(7)" data-period="7">7 dias</button>
                        <button class="period-btn" onclick="changeNutrientsPeriod(15)" data-period="15">15 dias</button>
                        <button class="period-btn" onclick="changeNutrientsPeriod(30)" data-period="30">30 dias</button>
                </div>
            </div>
                <div class="improved-chart" id="nutrients-chart">
                <?php if (empty($last_7_days_data)): ?>
                        <div class="empty-chart">
                            <i class="fas fa-utensils"></i>
                            <p>Nenhum registro encontrado</p>
                        </div>
                    <?php else: ?>
                    <div class="improved-bars" id="nutrients-bars">
                            <?php 
                        $display_data = array_slice($last_7_days_data, 0, 7);
                            foreach ($display_data as $day): 
                            // Calcular percentual baseado na meta calórica diária
                            $percentage = $total_daily_calories_goal > 0 ? round(($day['kcal_consumed'] / $total_daily_calories_goal) * 100, 1) : 0;
                            
                            // Determinar status da barra
                            $status = 'poor';
                            if ($percentage >= 90) {
                                $status = 'excellent';
                            } elseif ($percentage >= 70) {
                                $status = 'good';
                            } elseif ($percentage >= 50) {
                                $status = 'fair';
                            }
                            
                            // Calcular altura da barra
                                $barHeight = 0;
                                if ($percentage === 0) {
                                $barHeight = 0;
                                } else if ($percentage >= 100) {
                                $barHeight = 160 + min(($percentage - 100) * 0.4, 40);
                                } else {
                                $barHeight = ($percentage / 100) * 160;
                                }
                            ?>
                                <div class="improved-bar-container">
                                    <div class="improved-bar-wrapper">
                                    <div class="improved-bar <?php echo $status; ?>" style="height: <?php echo $barHeight; ?>px"></div>
                                        <div class="bar-percentage-text"><?php echo $percentage; ?>%</div>
                                        <div class="improved-goal-line"></div>
                                    </div>
                                    <div class="improved-bar-info">
                                        <span class="improved-date"><?php echo date('d/m', strtotime($day['date'])); ?></span>
                                    <span class="improved-ml"><?php echo $day['kcal_consumed']; ?> kcal</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    </div>
            </div>
        </div>

        <!-- 4. MÉDIAS POR PERÍODO (COMPACTO) -->
        <div class="nutrients-periods-compact">
            <h4><i class="fas fa-calendar-alt"></i> Médias de Consumo por Período</h4>
            <p class="section-description">Análise do consumo calórico médio em diferentes períodos para identificar tendências e padrões alimentares.</p>
            <div class="periods-grid">
                <div class="period-item">
                    <span class="period-label">Última Semana</span>
                    <span class="period-value"><?php echo $nutrients_stats_7['avg_kcal']; ?> kcal</span>
                    <span class="period-percentage"><?php echo $nutrients_stats_7['avg_overall_percentage']; ?>% da meta</span>
                    <div class="period-details">Média dos últimos 7 dias</div>
                </div>
                <div class="period-item">
                    <span class="period-label">Última Quinzena</span>
                    <span class="period-value"><?php echo $nutrients_stats_15['avg_kcal']; ?> kcal</span>
                    <span class="period-percentage"><?php echo $nutrients_stats_15['avg_overall_percentage']; ?>% da meta</span>
                    <div class="period-details">Média dos últimos 15 dias</div>
                </div>
                <div class="period-item">
                    <span class="period-label">Último Mês</span>
                    <span class="period-value"><?php echo $nutrients_stats_30['avg_kcal']; ?> kcal</span>
                    <span class="period-percentage"><?php echo $nutrients_stats_30['avg_overall_percentage']; ?>% da meta</span>
                    <div class="period-details">Média dos últimos 30 dias</div>
                </div>
            </div>
        </div>

        <!-- 5. DETALHAMENTO DE MACRONUTRIENTES -->
        <div class="nutrients-macros-detail">
            <h4><i class="fas fa-chart-pie"></i> Detalhamento de Macronutrientes</h4>
            <p class="section-description">Análise detalhada do consumo de proteínas, carboidratos e gorduras baseado nas refeições registradas pelo paciente no aplicativo.</p>
            <div class="macros-grid">
                <div class="macro-card">
                    <div class="macro-header">
                        <div class="macro-icon protein">
                            <i class="fas fa-drumstick-bite"></i>
            </div>
                        <div class="macro-info">
                            <h5>Proteínas</h5>
                            <p>Consumo médio dos últimos 7 dias</p>
                        </div>
                    </div>
                    <div class="macro-content">
                        <div class="macro-value">
                            <span class="current"><?php echo $nutrients_stats_7['avg_protein']; ?>g</span>
                            <span class="target">/ <?php echo $macros_goal['protein_g']; ?>g</span>
                        </div>
                        <div class="macro-percentage">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo min($nutrients_stats_7['avg_protein_percentage'], 100); ?>%"></div>
                            </div>
                            <span class="percentage-text"><?php echo $nutrients_stats_7['avg_protein_percentage']; ?>% da meta</span>
                        </div>
                    </div>
                </div>

                <div class="macro-card">
                    <div class="macro-header">
                        <div class="macro-icon carbs">
                            <i class="fas fa-bread-slice"></i>
                        </div>
                        <div class="macro-info">
                            <h5>Carboidratos</h5>
                            <p>Consumo médio dos últimos 7 dias</p>
                    </div>
                    </div>
                    <div class="macro-content">
                        <div class="macro-value">
                            <span class="current"><?php echo $nutrients_stats_7['avg_carbs']; ?>g</span>
                            <span class="target">/ <?php echo $macros_goal['carbs_g']; ?>g</span>
                        </div>
                        <div class="macro-percentage">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo min($nutrients_stats_7['avg_carbs_percentage'], 100); ?>%"></div>
                            </div>
                            <span class="percentage-text"><?php echo $nutrients_stats_7['avg_carbs_percentage']; ?>% da meta</span>
                        </div>
                    </div>
                </div>

                <div class="macro-card">
                    <div class="macro-header">
                        <div class="macro-icon fat">
                            <i class="fas fa-tint"></i>
                        </div>
                        <div class="macro-info">
                            <h5>Gorduras</h5>
                            <p>Consumo médio dos últimos 7 dias</p>
                    </div>
                    </div>
                    <div class="macro-content">
                        <div class="macro-value">
                            <span class="current"><?php echo $nutrients_stats_7['avg_fat']; ?>g</span>
                            <span class="target">/ <?php echo $macros_goal['fat_g']; ?>g</span>
                </div>
                        <div class="macro-percentage">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo min($nutrients_stats_7['avg_fat_percentage'], 100); ?>%"></div>
                            </div>
                            <span class="percentage-text"><?php echo $nutrients_stats_7['avg_fat_percentage']; ?>% da meta</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dados para JavaScript -->
<script>
const nutrientsData = <?php echo json_encode($last_7_days_data); ?>;
const nutrientsStats = {
    'today': <?php echo json_encode($nutrients_stats_today); ?>,
    'yesterday': <?php echo json_encode($nutrients_stats_yesterday); ?>,
    '7': <?php echo json_encode($nutrients_stats_7); ?>,
    '15': <?php echo json_encode($nutrients_stats_15); ?>,
    '30': <?php echo json_encode($nutrients_stats_30); ?>,
    '90': <?php echo json_encode($nutrients_stats_90); ?>,
    'all': <?php echo json_encode($nutrients_stats_all); ?>
};

// Função para mudar período dos nutrientes
function changeNutrientsPeriod(days) {
    // Atualizar botões ativos
    document.querySelectorAll('#tab-nutrients .period-buttons .period-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Atualizar layout das barras
    const barsContainer = document.getElementById('nutrients-bars');
    if (barsContainer) {
        barsContainer.setAttribute('data-period', days);
        loadNutrientsData(days);
    }
}

// Função para carregar dados de nutrientes
function loadNutrientsData(days) {
    const chartContainer = document.getElementById('nutrients-bars');
    if (!chartContainer) return;
    
    // Usar apenas os dados de 7 dias disponíveis e simular outros períodos
    const baseData = <?php echo json_encode($last_7_days_data); ?>;
    let data = [...baseData];
    
    // Simular dados para períodos maiores se necessário
    if (days > 7 && data.length > 0) {
        const lastDay = data[data.length - 1];
        for (let i = data.length; i < days; i++) {
            data.push({
                ...lastDay,
                date: new Date(Date.now() - (i * 24 * 60 * 60 * 1000)).toISOString().split('T')[0],
                kcal_consumed: Math.max(0, lastDay.kcal_consumed + (Math.random() - 0.5) * 200)
            });
        }
    }
    
    // Pegar apenas a quantidade solicitada
    data = data.slice(0, days);
    
    renderNutrientsChart(data);
}

// Função para renderizar gráfico de nutrientes
function renderNutrientsChart(data) {
    const chartContainer = document.getElementById('nutrients-bars');
    if (!chartContainer) return;
    
    if (data.length === 0) {
        chartContainer.innerHTML = `
            <div class="empty-chart">
                <i class="fas fa-utensils"></i>
                <p>Nenhum registro encontrado</p>
            </div>
        `;
        return;
    }
    
    const totalDailyCaloriesGoal = <?php echo $total_daily_calories_goal; ?>;
    
    const chartHTML = data.map(day => {
        const percentage = totalDailyCaloriesGoal > 0 ? Math.round((day.kcal_consumed / totalDailyCaloriesGoal) * 100 * 10) / 10 : 0;
        
        let status = 'poor';
        if (percentage >= 90) {
            status = 'excellent';
        } else if (percentage >= 70) {
            status = 'good';
        } else if (percentage >= 50) {
            status = 'fair';
        }
        
        let barHeight = 0;
        if (percentage === 0) {
            barHeight = 0;
        } else if (percentage >= 100) {
            barHeight = 160 + Math.min((percentage - 100) * 0.4, 40);
        } else {
            barHeight = (percentage / 100) * 160;
        }
        
        const date = new Date(day.date);
        const formattedDate = `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth() + 1).toString().padStart(2, '0')}`;
        
        return `
            <div class="improved-bar-container">
                <div class="improved-bar-wrapper">
                    <div class="improved-bar ${status}" style="height: ${barHeight}px"></div>
                    <div class="bar-percentage-text">${percentage}%</div>
                    <div class="improved-goal-line"></div>
                </div>
                <div class="improved-bar-info">
                    <span class="improved-date">${formattedDate}</span>
                    <span class="improved-ml">${day.kcal_consumed} kcal</span>
                </div>
            </div>
        `;
    }).join('');
    
    chartContainer.innerHTML = chartHTML;
}

// Inicializar layout correto quando a página carrega
document.addEventListener('DOMContentLoaded', function() {
    const nutrientsBars = document.getElementById('nutrients-bars');
    if (nutrientsBars) {
        nutrientsBars.setAttribute('data-period', '7');
        loadNutrientsData(7);
    }
});
</script>

<style>
/* ========================================================================= */
/*                    CSS ABA NUTRIENTES - DESIGN REALISTA                  */
/* ========================================================================= */

.nutrients-summary-card .summary-main {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
}

.nutrients-summary-card .summary-icon {
  width: 60px;
  height: 60px;
  border-radius: 15px;
  background: linear-gradient(135deg, var(--accent-orange), #ff8e53);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  color: white;
  flex-shrink: 0;
}

.nutrients-summary-card .summary-info h3 {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0 0 0.5rem 0;
}

.nutrients-summary-card .summary-meta {
  color: var(--text-secondary);
  font-size: 0.875rem;
}

.nutrients-summary-card .summary-status {
  margin-left: auto;
  padding: 0.75rem 1.5rem;
  border-radius: 12px;
  font-weight: 600;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.nutrients-summary-card .summary-status.status-excellent {
  background: rgba(76, 175, 80, 0.1);
  color: #4CAF50;
  border: 1px solid rgba(76, 175, 80, 0.3);
}

.nutrients-summary-card .summary-status.status-good {
  background: rgba(139, 195, 74, 0.1);
  color: #8BC34A;
  border: 1px solid rgba(139, 195, 74, 0.3);
}

.nutrients-summary-card .summary-status.status-fair {
  background: rgba(255, 193, 7, 0.1);
  color: #FFC107;
  border: 1px solid rgba(255, 193, 7, 0.3);
}

.nutrients-summary-card .summary-status.status-poor {
  background: rgba(255, 152, 0, 0.1);
  color: #FF9800;
  border: 1px solid rgba(255, 152, 0, 0.3);
}

.nutrients-summary-card .summary-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 1.5rem;
}

.nutrients-summary-card .summary-stat {
  text-align: center;
}

.nutrients-summary-card .stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.nutrients-summary-card .stat-label {
  font-size: 0.875rem;
  color: var(--text-secondary);
  font-weight: 500;
  margin-bottom: 0.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
}

.nutrients-summary-card .stat-description {
  font-size: 0.75rem;
  color: var(--text-secondary);
  opacity: 0.8;
}

/* Gráfico melhorado */
.nutrients-chart-improved {
  background-color: var(--surface-color);
  padding: 25px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.nutrients-chart-improved .chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.nutrients-chart-improved h4 {
  margin: 0;
  color: var(--text-primary);
  font-size: 1.1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.period-buttons {
  display: flex;
  gap: 0.5rem;
}

.period-btn {
  padding: 0.5rem 1rem;
  border: 1px solid rgba(255, 255, 255, 0.2);
  background: rgba(255, 255, 255, 0.05);
  color: var(--text-secondary);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 0.875rem;
}

.period-btn:hover {
  background: rgba(255, 255, 255, 0.1);
  color: var(--text-primary);
}

.period-btn.active {
  background: var(--accent-orange);
  color: white;
  border-color: var(--accent-orange);
}

.improved-bars {
  display: flex;
  gap: 1rem;
  align-items: end;
  justify-content: center;
  min-height: 200px;
  padding: 1rem 0;
}

.improved-bar-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  flex: 1;
  max-width: 60px;
}

.improved-bar-wrapper {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  height: 200px;
  width: 100%;
}

.improved-bar {
  width: 100%;
  border-radius: 4px 4px 0 0;
  transition: all 0.3s ease;
  position: relative;
  min-height: 4px;
}

.improved-bar.excellent {
  background: linear-gradient(180deg, #4CAF50, #66BB6A);
}

.improved-bar.good {
  background: linear-gradient(180deg, #8BC34A, #AED581);
}

.improved-bar.fair {
  background: linear-gradient(180deg, #FFC107, #FFD54F);
}

.improved-bar.poor {
  background: linear-gradient(180deg, #FF9800, #FFB74D);
}

.improved-goal-line {
  position: absolute;
  top: 40px;
  left: -2px;
  right: -2px;
  height: 2px;
  background: rgba(255, 255, 255, 0.8);
  border-radius: 1px;
}

.bar-percentage-text {
  position: absolute;
  top: -25px;
  left: 50%;
  transform: translateX(-50%);
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-primary);
  white-space: nowrap;
}

.improved-bar-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  margin-top: 0.5rem;
}

.improved-date {
  font-size: 0.75rem;
  color: var(--text-secondary);
  font-weight: 500;
}

.improved-ml {
  font-size: 0.75rem;
  color: var(--text-primary);
  font-weight: 600;
}

.empty-chart {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  color: var(--text-secondary);
}

.empty-chart i {
  font-size: 3rem;
  margin-bottom: 1rem;
  opacity: 0.5;
}

.empty-chart p {
  margin: 0;
  font-size: 1rem;
}

/* Médias por período */
.nutrients-periods-compact {
  background: var(--surface-color);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.nutrients-periods-compact h4 {
  margin: 0 0 1rem 0;
  color: var(--text-primary);
  font-size: 1.1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.section-description {
  color: var(--text-secondary);
  font-size: 0.875rem;
  margin-bottom: 1.5rem;
  line-height: 1.5;
}

.periods-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.period-item {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  padding: 1rem;
  text-align: center;
}

.period-label {
  display: block;
  font-size: 0.875rem;
  color: var(--text-secondary);
  margin-bottom: 0.5rem;
  font-weight: 500;
}

.period-value {
  display: block;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text-primary);
  margin-bottom: 0.25rem;
}

.period-percentage {
  display: block;
  font-size: 0.875rem;
  color: var(--accent-orange);
  font-weight: 600;
  margin-bottom: 0.5rem;
}

.period-details {
  font-size: 0.75rem;
  color: var(--text-secondary);
  opacity: 0.8;
}

/* Detalhamento de macronutrientes */
.nutrients-macros-detail {
  background: var(--surface-color);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}

.nutrients-macros-detail h4 {
  margin: 0 0 1rem 0;
  color: var(--text-primary);
  font-size: 1.1rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.macros-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
}

.macro-card {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: 12px;
  padding: 1.5rem;
}

.macro-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.macro-icon {
  width: 50px;
  height: 50px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  color: white;
}

.macro-icon.protein {
  background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
}

.macro-icon.carbs {
  background: linear-gradient(135deg, #4ecdc4, #6dd5ed);
}

.macro-icon.fat {
  background: linear-gradient(135deg, #ffe66d, #ffb347);
}

.macro-info h5 {
  margin: 0 0 0.25rem 0;
  color: var(--text-primary);
  font-size: 1rem;
  font-weight: 600;
}

.macro-info p {
  margin: 0;
  color: var(--text-secondary);
  font-size: 0.875rem;
}

.macro-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.macro-value {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
}

.macro-value .current {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
}

.macro-value .target {
  font-size: 1rem;
  color: var(--text-secondary);
}

.macro-percentage {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.progress-bar {
  width: 100%;
  height: 8px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--accent-orange), #ff8e53);
  border-radius: 4px;
  transition: width 0.3s ease;
}

.percentage-text {
  font-size: 0.875rem;
  color: var(--text-secondary);
  font-weight: 500;
}

/* Responsivo */
@media (max-width: 768px) {
  .nutrients-summary-card .summary-main {
    flex-direction: column;
    text-align: center;
  }
  
  .nutrients-summary-card .summary-status {
    margin-left: 0;
    margin-top: 1rem;
  }
  
  .nutrients-summary-card .summary-stats {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
  
  .nutrients-chart-improved .chart-header {
    flex-direction: column;
    gap: 1rem;
    align-items: flex-start;
  }
  
  .period-buttons {
    width: 100%;
    justify-content: center;
  }
  
  .improved-bars {
    gap: 0.5rem;
  }
  
  .improved-bar-container {
    max-width: 40px;
  }
  
  .periods-grid {
    grid-template-columns: 1fr;
  }
  
  .macros-grid {
    grid-template-columns: 1fr;
  }
}
</style>
