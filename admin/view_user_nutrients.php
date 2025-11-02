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

        <!-- 3. GRÁFICO COM BOTÕES DE PERÍODO -->
        <div class="chart-section">
            <div class="nutrients-chart-improved">
                <div class="chart-header">
                    <h4><i class="fas fa-chart-bar"></i> Progresso Nutricional</h4>
                    <div class="period-buttons">
                        <button class="period-btn active" onclick="showNutrientsCalendar()" id="nutrients-period-btn" title="Selecionar período">
                            <i class="fas fa-calendar-alt"></i> Últimos 7 dias
                        </button>
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

<!-- Dados para JavaScript da aba Nutrientes -->
<script>
// Usar userId global (já declarado no início) ou declarar localmente
var userId = window.userId || <?php echo $user_id; ?>;
let currentNutrientsPeriod = 'last7'; // 'last7', 'month', 'week'
let currentNutrientsStartDate = null;
let currentNutrientsEndDate = null;

// Carregar últimos 7 dias por padrão
async function loadLast7DaysNutrients() {
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 6); // Últimos 7 dias (incluindo hoje)
    
    const startStr = startDate.toISOString().split('T')[0];
    const endStr = endDate.toISOString().split('T')[0];
    
    await loadNutrientsData(startStr, endStr, 'Últimos 7 dias');
}

// Carregar dados de nutrientes por período
async function loadNutrientsData(startDate, endDate, periodLabel) {
    const chartContainer = document.getElementById('nutrients-bars');
    if (!chartContainer) return;
    
    // Mostrar loading
    chartContainer.innerHTML = `
        <div class="empty-chart">
            <div class="loading-spinner"></div>
            <p>Carregando dados...</p>
        </div>
    `;
    
    try {
        const response = await fetch(`ajax_get_chart_data.php?user_id=${userId}&type=nutrients&start_date=${startDate}&end_date=${endDate}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            renderNutrientsChart(result.data);
            updateNutrientsPeriodButton(periodLabel);
            
            // Salvar período atual
            currentNutrientsPeriod = periodLabel;
            currentNutrientsStartDate = startDate;
            currentNutrientsEndDate = endDate;
        } else {
            chartContainer.innerHTML = `
                <div class="empty-chart">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Erro ao carregar dados</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Erro ao carregar dados de nutrientes:', error);
        chartContainer.innerHTML = `
            <div class="empty-chart">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Erro ao carregar dados</p>
            </div>
        `;
    }
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
    
    // Aplicar o atributo data-period baseado na quantidade de dados
    const period = data.length;
    chartContainer.setAttribute('data-period', period > 7 ? (period > 15 ? '30' : '15') : '7');
    
    const dailyGoal = <?php echo $total_daily_calories_goal; ?>;
    
    let chartHTML = '';
    data.forEach(day => {
        const percentage = dailyGoal > 0 ? Math.round((day.kcal_consumed / dailyGoal) * 100 * 10) / 10 : 0;
        
        let status = day.status || 'poor';
        if (percentage >= 90) {
            status = 'excellent';
        } else if (percentage >= 70) {
            status = 'good';
        } else if (percentage >= 50) {
            status = 'fair';
        } else {
            status = 'poor';
        }
        
        let barHeight = 0;
        if (percentage === 0) {
            barHeight = 0;
        } else if (percentage >= 100) {
            barHeight = 160 + Math.min((percentage - 100) * 0.4, 40);
        } else {
            barHeight = (percentage / 100) * 160;
        }
        
        chartHTML += `
            <div class="improved-bar-container">
                <div class="improved-bar-wrapper">
                    <div class="improved-bar ${status}" style="height: ${barHeight}px"></div>
                    <div class="bar-percentage-text">${percentage}%</div>
                    <div class="improved-goal-line"></div>
                </div>
                <div class="improved-bar-info">
                    <span class="improved-date">${new Date(day.date + 'T00:00:00').toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit'})}</span>
                    <span class="improved-ml">${day.kcal_consumed} kcal</span>
                </div>
            </div>
        `;
    });
    
    chartContainer.innerHTML = chartHTML;
}

// Atualizar texto do botão de período
function updateNutrientsPeriodButton(label) {
    const btn = document.getElementById('nutrients-period-btn');
    if (btn) {
        btn.innerHTML = `<i class="fas fa-calendar-alt"></i> ${label}`;
    }
}

// Mostrar modal de calendário para nutrientes
function showNutrientsCalendar() {
    if (typeof window.openChartCalendar === 'function') {
        window.openChartCalendar('nutrients');
    } else if (typeof openChartCalendar === 'function') {
        openChartCalendar('nutrients');
    } else {
        console.error('openChartCalendar não está definida');
    }
}
window.showNutrientsCalendar = showNutrientsCalendar;

// Inicializar gráfico quando a aba for ativada
document.addEventListener('DOMContentLoaded', function() {
    const nutrientsBars = document.getElementById('nutrients-bars');
    if (nutrientsBars) {
        nutrientsBars.setAttribute('data-period', '7');
    }
    
    // Resetar e carregar últimos 7 dias quando a aba for clicada
    const tabLink = document.querySelector('[data-tab="nutrients"]');
    if (tabLink) {
        tabLink.addEventListener('click', function() {
            setTimeout(() => {
                loadLast7DaysNutrients();
            }, 100);
        });
    }
    
    // Carregar últimos 7 dias ao inicializar
    loadLast7DaysNutrients();
});
</script>

