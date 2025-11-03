<?php
// Calcula variáveis específicas da aba de Hidratação
$avg_percentage_7 = $water_stats_7['avg_percentage'];

// Determinar status geral
if ($avg_percentage_7 >= 90) {
    $status_text = 'Excelente';
    $status_class = 'excellent';
    $status_icon = 'fa-check-circle';
} elseif ($avg_percentage_7 >= 70) {
    $status_text = 'Bom';
    $status_class = 'good';
    $status_icon = 'fa-check';
} elseif ($avg_percentage_7 >= 50) {
    $status_text = 'Regular';
    $status_class = 'fair';
    $status_icon = 'fa-exclamation-triangle';
} elseif ($avg_percentage_7 >= 30) {
    $status_text = 'Abaixo da meta';
    $status_class = 'poor';
    $status_icon = 'fa-exclamation';
} else {
    $status_text = 'Crítico';
    $status_class = 'critical';
    $status_icon = 'fa-times-circle';
}
?>

<div id="tab-hydration" class="tab-content">
    <div class="hydration-container">
        
        <!-- 1. CARD RESUMO COMPACTO -->
        <div class="hydration-summary-card">
            <div class="summary-main">
                <div class="summary-icon">
                    <i class="fas fa-tint"></i>
                    </div>
                <div class="summary-info">
                    <h3>Hidratação</h3>
                    <div class="summary-meta">Meta diária: <strong><?php echo $water_goal_ml; ?>ml</strong></div>
                    <div class="summary-description">Baseado nos registros de hidratação do paciente no aplicativo</div>
                    </div>
                <div class="summary-status status-<?php echo $status_class; ?>">
                    <i class="fas <?php echo $status_icon; ?>"></i>
                    <span><?php echo $status_text; ?></span>
                </div>
                    </div>
            <div class="summary-stats">
                <div class="summary-stat">
                    <div class="stat-value"><?php echo $water_stats_7['avg_ml']; ?>ml</div>
                    <div class="stat-label">Média de Água</div>
                    <div class="stat-description">Últimos 7 dias</div>
                    </div>
                <div class="summary-stat">
                    <div class="stat-value"><?php echo $water_stats_7['avg_percentage']; ?>%</div>
                    <div class="stat-label">
                        Aderência Geral
                        <i class="fas fa-question-circle help-icon" onclick="openHelpModal('hydration-adherence')" title="Clique para saber mais"></i>
                </div>
                    <div class="stat-description">Meta de hidratação atingida</div>
                    </div>
                <div class="summary-stat">
                    <div class="stat-value"><?php echo $water_stats_7['days_with_consumption']; ?>/<?php echo $water_stats_7['total_days']; ?></div>
                    <div class="stat-label">Dias com Registro</div>
                    <div class="stat-description"><?php echo $water_stats_7['adherence_percentage']; ?>% de aderência</div>
                </div>
            </div>
        </div>


        <!-- 3. GRÁFICO COM BOTÕES DE PERÍODO -->
        <div class="chart-section">
            <div class="hydration-chart-improved">
                <div class="chart-header" style="position: relative; padding-bottom: 3rem;">
                    <h4><i class="fas fa-chart-bar"></i> Progresso de Hidratação</h4>
                    <button class="diary-calendar-icon-btn" onclick="showHydrationCalendar()" id="hydration-period-btn" title="Selecionar período" style="position: absolute; top: 50%; right: 0; transform: translateY(-50%); width: auto; min-width: auto; padding: 12px 24px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; font-family: 'Montserrat', sans-serif; display: flex; align-items: center; gap: 12px; white-space: nowrap;">
                        <i class="fas fa-calendar-alt"></i> <span id="hydration-period-text">Últimos 7 dias</span>
                    </button>
            </div>
                <div class="improved-chart" id="hydration-chart">
                <?php if (empty($hydration_data)): ?>
                    <div class="empty-chart">
                        <i class="fas fa-tint"></i>
                        <p>Nenhum registro encontrado</p>
                    </div>
                <?php else: ?>
                    <div class="improved-bars" id="hydration-bars">
                        <?php 
                        $display_data = array_slice($hydration_data, 0, 7);
                        foreach ($display_data as $day): 
                            $limitedPercentage = min($day['percentage'], 100);
                            $barHeight = 0;
                            if ($limitedPercentage === 0) {
                                $barHeight = 0;
                            } else if ($limitedPercentage === 100) {
                                $barHeight = 160;
                            } else {
                                $barHeight = ($limitedPercentage / 100) * 160;
                            }
                        ?>
                            <div class="improved-bar-container">
                                <div class="improved-bar-wrapper">
                                    <div class="improved-bar <?php echo $day['status']; ?>" style="height: <?php echo $barHeight; ?>px"></div>
                                    <div class="bar-percentage-text"><?php echo $limitedPercentage; ?>%</div>
                                    <div class="improved-goal-line"></div>
                                </div>
                                <div class="improved-bar-info">
                                    <span class="improved-date"><?php echo date('d/m', strtotime($day['date'])); ?></span>
                                    <span class="improved-ml"><?php echo $day['ml']; ?>ml</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 4. MÉDIAS DE PERÍODOS (COMPACTO) -->
        <div class="hydration-periods-compact">
            <h4><i class="fas fa-calendar-alt" style="color: var(--accent-orange);"></i> Médias de Consumo por Período</h4>
            <p class="section-description">Análise do consumo de água médio em diferentes períodos para identificar tendências e padrões de hidratação.</p>
            <div class="periods-grid">
                <div class="period-item">
                    <span class="period-label">Última Semana</span>
                    <span class="period-value"><?php echo $water_stats_7['avg_ml']; ?>ml</span>
                    <span class="period-percentage"><?php echo $water_stats_7['avg_percentage']; ?>% da meta</span>
                    <div class="period-details">Média dos últimos 7 dias</div>
            </div>
                <div class="period-item">
                    <span class="period-label">Última Quinzena</span>
                    <span class="period-value"><?php echo $water_stats_15['avg_ml']; ?>ml</span>
                    <span class="period-percentage"><?php echo $water_stats_15['avg_percentage']; ?>% da meta</span>
                    <div class="period-details">Média dos últimos 15 dias</div>
                    </div>
                <div class="period-item">
                    <span class="period-label">Último Mês</span>
                    <span class="period-value"><?php echo $water_stats_30['avg_ml']; ?>ml</span>
                    <span class="period-percentage"><?php echo $water_stats_30['avg_percentage']; ?>% da meta</span>
                    <div class="period-details">Média dos últimos 30 dias</div>
                            </div>
                            </div>
                        </div>

    </div>
</div>

<!-- Dados para JavaScript da aba Hidratação -->
<script>
const userIdHydration = <?php echo $user_id; ?>;
let currentHydrationPeriod = 'last7'; // 'last7', 'month', 'week'
let currentHydrationStartDate = null;
let currentHydrationEndDate = null;

// Carregar últimos 7 dias por padrão
async function loadLast7DaysHydration() {
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 6); // Últimos 7 dias (incluindo hoje)
    
    const startStr = startDate.toISOString().split('T')[0];
    const endStr = endDate.toISOString().split('T')[0];
    
    await loadHydrationData(startStr, endStr, 'Últimos 7 dias');
}

// Carregar dados de hidratação por período
async function loadHydrationData(startDate, endDate, periodLabel) {
    const chartContainer = document.getElementById('hydration-bars');
    if (!chartContainer) return;
    
    // Mostrar loading
    chartContainer.innerHTML = `
        <div class="empty-chart">
            <div class="loading-spinner"></div>
            <p>Carregando dados...</p>
        </div>
    `;
    
    try {
        const response = await fetch(`ajax_get_chart_data.php?user_id=${userIdHydration}&type=hydration&start_date=${startDate}&end_date=${endDate}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            renderHydrationChart(result.data);
            updateHydrationPeriodButton(periodLabel);
            
            // Salvar período atual
            currentHydrationPeriod = periodLabel;
            currentHydrationStartDate = startDate;
            currentHydrationEndDate = endDate;
        } else {
            chartContainer.innerHTML = `
                <div class="empty-chart">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Erro ao carregar dados</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Erro ao carregar dados de hidratação:', error);
        chartContainer.innerHTML = `
            <div class="empty-chart">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Erro ao carregar dados</p>
            </div>
        `;
    }
}

// Função para renderizar gráfico de hidratação
function renderHydrationChart(data) {
    const chartContainer = document.getElementById('hydration-bars');
    if (!chartContainer) return;
    
    if (data.length === 0) {
        chartContainer.innerHTML = `
            <div class="empty-chart">
                <i class="fas fa-tint"></i>
                <p>Nenhum registro encontrado</p>
            </div>
        `;
        return;
    }
    
    // Aplicar o atributo data-period baseado na quantidade de dados
    const period = data.length;
    chartContainer.setAttribute('data-period', period > 7 ? (period > 15 ? '30' : '15') : '7');
    
    // Se for apenas um dia, adicionar classe especial para melhor uso do espaço
    if (period === 1) {
        chartContainer.classList.add('single-day-chart');
    } else {
        chartContainer.classList.remove('single-day-chart');
    }
    
    let chartHTML = '';
    data.forEach(day => {
        const limitedPercentage = Math.min(day.percentage || 0, 100);
        let barHeight = 0;
        if (limitedPercentage === 0) {
            barHeight = 0;
        } else if (limitedPercentage === 100) {
            barHeight = 160;
        } else {
            barHeight = (limitedPercentage / 100) * 160;
        }
        
        const status = day.status || 'empty';
        
        chartHTML += `
            <div class="improved-bar-container">
                <div class="improved-bar-wrapper">
                    <div class="improved-bar ${status}" style="height: ${barHeight}px"></div>
                    <div class="bar-percentage-text">${limitedPercentage}%</div>
                    <div class="improved-goal-line"></div>
                </div>
                <div class="improved-bar-info">
                    <span class="improved-date">${new Date(day.date + 'T00:00:00').toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit'})}</span>
                    <span class="improved-ml">${day.ml || 0}ml</span>
                </div>
            </div>
        `;
    });
    
    chartContainer.innerHTML = chartHTML;
}

// Atualizar texto do botão de período
function updateHydrationPeriodButton(label) {
    const btn = document.getElementById('hydration-period-btn');
    if (btn) {
        btn.innerHTML = `<i class="fas fa-calendar-alt"></i> ${label}`;
    }
}

// Mostrar modal de calendário para hidratação
function showHydrationCalendar() {
    if (typeof window.openChartCalendar === 'function') {
        window.openChartCalendar('hydration');
    } else if (typeof openChartCalendar === 'function') {
        openChartCalendar('hydration');
    } else {
        console.error('openChartCalendar não está definida');
    }
}
window.showHydrationCalendar = showHydrationCalendar;

// Inicializar gráfico quando a aba for ativada
document.addEventListener('DOMContentLoaded', function() {
    const hydrationBars = document.getElementById('hydration-bars');
    if (hydrationBars) {
        hydrationBars.setAttribute('data-period', '7');
    }
    
    // Resetar e carregar últimos 7 dias quando a aba for clicada
    const tabLink = document.querySelector('[data-tab="hydration"]');
    if (tabLink) {
        tabLink.addEventListener('click', function() {
            setTimeout(() => {
                loadLast7DaysHydration();
            }, 100);
        });
    }
    
    // Carregar últimos 7 dias ao inicializar
    loadLast7DaysHydration();
});
</script>

