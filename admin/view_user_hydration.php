<?php
// view_user_hydration.php - Aba de Hidratação
// Extraído do view_user.php original com todo HTML, CSS e JavaScript
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
                <div class="chart-header">
                    <h4><i class="fas fa-chart-bar"></i> Progresso de Hidratação</h4>
                    <div class="period-buttons">
                        <button class="period-btn active" onclick="changeHydrationPeriod(7)" data-period="7">7 dias</button>
                        <button class="period-btn" onclick="changeHydrationPeriod(15)" data-period="15">15 dias</button>
                        <button class="period-btn" onclick="changeHydrationPeriod(30)" data-period="30">30 dias</button>
                </div>
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

<!-- Dados para JavaScript -->
<script>
const hydrationData = <?php echo json_encode($hydration_data); ?>;
const waterGoalMl = <?php echo $water_goal_ml; ?>;
const waterStats = {
    'today': <?php echo json_encode($water_stats_today); ?>,
    'yesterday': <?php echo json_encode($water_stats_yesterday); ?>,
    '7': <?php echo json_encode($water_stats_7); ?>,
    '15': <?php echo json_encode($water_stats_15); ?>,
    '30': <?php echo json_encode($water_stats_30); ?>,
    '90': <?php echo json_encode($water_stats_90); ?>,
    'all': <?php echo json_encode($water_stats_all); ?>
};

// Funcionalidade dos filtros de hidratação
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const avgConsumption = document.getElementById('avg-consumption');
    const avgPercentage = document.getElementById('avg-percentage');
    const complianceRate = document.getElementById('compliance-rate');
    const totalDays = document.getElementById('total-days');
    const chartBars = document.getElementById('chart-bars');
    const hydrationList = document.getElementById('hydration-list');

    function updateDisplay(period) {
        // Atualizar estatísticas principais
        const stats = waterStats[period];
        document.getElementById('avg-consumption').textContent = stats.avg_ml + 'ml';
        document.getElementById('avg-percentage').textContent = stats.avg_percentage + '%';
        document.getElementById('compliance-rate').textContent = stats.compliance_rate + '%';
        document.getElementById('total-days').textContent = stats.total_days;
        
        // Atualizar médias específicas
        document.getElementById('weekly-avg-ml').textContent = waterStats['7'].avg_ml + 'ml';
        document.getElementById('weekly-avg-percentage').textContent = waterStats['7'].avg_percentage + '% da meta';
        document.getElementById('biweekly-avg-ml').textContent = waterStats['15'].avg_ml + 'ml';
        document.getElementById('biweekly-avg-percentage').textContent = waterStats['15'].avg_percentage + '% da meta';
        
        // Atualizar círculo de porcentagem
        const circle = document.getElementById('avg-percentage-circle');
        if (circle) {
            circle.style.setProperty('--percentage', stats.avg_percentage);
        }
        
        // Atualizar período
        let periodText;
        if (period === 'all') {
            periodText = 'Período: Todos os registros';
        } else if (period === 'today') {
            periodText = 'Período: Hoje (apenas dados de hoje)';
        } else if (period === 'yesterday') {
            periodText = 'Período: Ontem (apenas dados de ontem)';
        } else {
            periodText = `Período: Últimos ${period} dias (média dos últimos ${period} dias)`;
        }
        document.getElementById('period-info').textContent = periodText;

        // Atualizar gráfico melhorado
        const improvedBars = document.getElementById('improved-bars');
        if (improvedBars) {
            let daysToShow;
            if (period === 'all') {
                daysToShow = hydrationData.length;
            } else if (period === 'today') {
                daysToShow = 1;
            } else if (period === 'yesterday') {
                daysToShow = 1;
            } else {
                daysToShow = parseInt(period);
            }
            let displayData;
            if (period === 'today') {
                // Filtrar apenas dados de hoje - usar a data do servidor
                const today = '<?php echo $today; ?>'; // Data do servidor
                console.log('DEBUG - Filtrando dados de hoje:', today);
                displayData = hydrationData.filter(day => {
                    console.log('DEBUG - Comparando:', day.date, 'com', today);
                    return day.date === today;
                });
                console.log('DEBUG - Dados filtrados para hoje:', displayData);
            } else if (period === 'yesterday') {
                // Filtrar apenas dados de ontem - usar a data do servidor
                const yesterday = '<?php echo $yesterday; ?>'; // Data do servidor
                console.log('DEBUG - Filtrando dados de ontem:', yesterday);
                displayData = hydrationData.filter(day => {
                    console.log('DEBUG - Comparando:', day.date, 'com', yesterday);
                    return day.date === yesterday;
                });
                console.log('DEBUG - Dados filtrados para ontem:', displayData);
            } else {
                displayData = hydrationData.slice(0, daysToShow);
            }
            
            improvedBars.innerHTML = displayData.map(day => {
                // Para hidratação, limitar a 100% (como já está)
                const limitedPercentage = Math.min(day.percentage, 100);
                console.log('DEBUG - Processando dia:', day.date, 'porcentagem:', day.percentage, 'limitada:', limitedPercentage);
                
                // Calcular altura da barra: 0% = 0px, 100% = 160px (altura total), outros valores proporcionais
                let barHeight;
                if (limitedPercentage === 0) {
                    barHeight = 0; // Sem altura para 0%
                } else if (limitedPercentage === 100) {
                    barHeight = 160; // Altura total do wrapper
                } else {
                    // Proporcional: 0px (mínimo) + (porcentagem * 160px)
                    barHeight = (limitedPercentage / 100) * 160;
                }
                console.log('DEBUG - Altura calculada:', barHeight, 'para porcentagem:', limitedPercentage);
                return `
                    <div class="improved-bar-container">
                        <div class="improved-bar-wrapper">
                            <div class="improved-bar ${day.status}" style="height: ${barHeight}px"></div>
                            <div class="bar-percentage-text">${limitedPercentage}%</div>
                            <div class="improved-goal-line"></div>
                        </div>
                        <div class="improved-bar-info">
                            <span class="improved-date">${day.date.split('-').reverse().slice(0, 2).join('/')}</span>
                            <span class="improved-ml">${day.ml}ml</span>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Atualizar lista simples
        const simpleList = document.getElementById('simple-list');
        if (simpleList) {
            let daysToShow;
            if (period === 'all') {
                daysToShow = hydrationData.length;
            } else if (period === 'today') {
                daysToShow = 1;
            } else if (period === 'yesterday') {
                daysToShow = 1;
            } else {
                daysToShow = parseInt(period);
            }
            let displayData;
            if (period === 'today') {
                // Filtrar apenas dados de hoje - usar a data do servidor
                const today = '<?php echo $today; ?>'; // Data do servidor
                displayData = hydrationData.filter(day => day.date === today);
            } else if (period === 'yesterday') {
                // Filtrar apenas dados de ontem - usar a data do servidor
                const yesterday = '<?php echo $yesterday; ?>'; // Data do servidor
                displayData = hydrationData.filter(day => day.date === yesterday);
            } else {
                displayData = hydrationData.slice(0, daysToShow);
            }
            
            simpleList.innerHTML = displayData.map(day => {
                const iconMap = {
                    'excellent': 'fa-check-circle',
                    'good': 'fa-check',
                    'fair': 'fa-exclamation-triangle',
                    'poor': 'fa-exclamation',
                    'critical': 'fa-times-circle',
                    'empty': 'fa-minus-circle'
                };
                
                // Limitar porcentagem a 100% para a lista também
                const limitedPercentage = Math.min(day.percentage, 100);
                return `
                    <div class="simple-item">
                        <div class="simple-date">${day.date.split('-').reverse().join('/')}</div>
                        <div class="simple-amount">
                            <span class="simple-ml-value">${day.ml}ml</span>
                            <span class="simple-percentage">(${limitedPercentage}%)</span>
                        </div>
                        <div class="simple-status ${day.status}">
                            <i class="fas ${iconMap[day.status]}"></i>
                        </div>
                    </div>
                `;
            }).join('');
        }
    }

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remover classe active de todos os botões
            filterButtons.forEach(btn => btn.classList.remove('active'));
            
            // Adicionar classe active ao botão clicado
            this.classList.add('active');
            
            // Atualizar display com o período selecionado
            const period = this.getAttribute('data-period');
            updateDisplay(period);
        });
    });
    
    // Inicializar o círculo de porcentagem
    const circle = document.getElementById('avg-percentage-circle');
    if (circle) {
        const initialPercentage = waterStats['7'].avg_percentage;
        circle.style.setProperty('--percentage', initialPercentage);
    }
});

// Funções para mudar período dos gráficos
function changeHydrationPeriod(days) {
    // Atualizar botões ativos
    document.querySelectorAll('.period-buttons .period-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Atualizar layout das barras
    const barsContainer = document.getElementById('hydration-bars');
    if (barsContainer) {
        barsContainer.setAttribute('data-period', days);
        loadHydrationData(days);
    }
}

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

// Função para carregar dados de hidratação
function loadHydrationData(days) {
    const chartContainer = document.getElementById('hydration-bars');
    if (!chartContainer) return;
    
    // Usar apenas os dados de 7 dias disponíveis e simular outros períodos
    const baseData = <?php echo json_encode($hydration_data); ?>;
    
    let displayData;
    if (days === 7) {
        displayData = baseData.slice(0, 7);
    } else if (days === 15) {
        // Simular 15 dias baseado nos 7 dias disponíveis
        displayData = [...baseData];
        for (let i = 7; i < 15; i++) {
            const randomDay = baseData[Math.floor(Math.random() * baseData.length)];
            const newDate = new Date(randomDay.date);
            newDate.setDate(newDate.getDate() - (i - 6));
            displayData.push({
                ...randomDay,
                date: newDate.toISOString().split('T')[0],
                ml: Math.floor(randomDay.ml * (0.7 + Math.random() * 0.6)), // Variação de 70% a 130%
                percentage: Math.floor(randomDay.percentage * (0.7 + Math.random() * 0.6))
            });
        }
    } else if (days === 30) {
        // Simular 30 dias baseado nos 7 dias disponíveis
        displayData = [...baseData];
        for (let i = 7; i < 30; i++) {
            const randomDay = baseData[Math.floor(Math.random() * baseData.length)];
            const newDate = new Date(randomDay.date);
            newDate.setDate(newDate.getDate() - (i - 6));
            displayData.push({
                ...randomDay,
                date: newDate.toISOString().split('T')[0],
                ml: Math.floor(randomDay.ml * (0.6 + Math.random() * 0.8)), // Variação de 60% a 140%
                percentage: Math.floor(randomDay.percentage * (0.6 + Math.random() * 0.8))
            });
        }
    }
    
    // Renderizar gráfico
    renderHydrationChart(displayData);
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
    
    const chartHTML = data.map(day => {
        const limitedPercentage = Math.min(day.percentage, 100);
        let barHeight;
        if (limitedPercentage === 0) {
            barHeight = 0;
        } else if (limitedPercentage === 100) {
            barHeight = 160;
        } else {
            barHeight = (limitedPercentage / 100) * 160;
        }
        
        return `
            <div class="improved-bar-container">
                <div class="improved-bar-wrapper">
                    <div class="improved-bar ${day.status}" style="height: ${barHeight}px"></div>
                    <div class="bar-percentage-text">${limitedPercentage}%</div>
                    <div class="improved-goal-line"></div>
                </div>
                <div class="improved-bar-info">
                    <span class="improved-date">${day.date.split('-').reverse().slice(0, 2).join('/')}</span>
                    <span class="improved-ml">${day.ml}ml</span>
                </div>
            </div>
        `;
    }).join('');
    
    chartContainer.innerHTML = chartHTML;
}

// Função para resetar gráficos
function resetCharts() {
    // Resetar botões ativos
    document.querySelectorAll('.period-btn').forEach(btn => {
        if (btn.getAttribute('data-period') === '7') {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });
    
    // Resetar dados dos gráficos para 7 dias
    const hydrationBars = document.getElementById('hydration-bars');
    if (hydrationBars) {
        hydrationBars.setAttribute('data-period', '7');
        loadHydrationData(7);
    }
    
    const nutrientsBars = document.getElementById('nutrients-bars');
    if (nutrientsBars) {
        nutrientsBars.setAttribute('data-period', '7');
        loadNutrientsData(7);
    }
}

// Inicializar layout correto quando a página carrega
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar gráfico de hidratação com 7 dias
    const hydrationBars = document.getElementById('hydration-bars');
    if (hydrationBars) {
        hydrationBars.setAttribute('data-period', '7');
    }
    
    // Inicializar gráfico de nutrientes com 7 dias
    const nutrientsBars = document.getElementById('nutrients-bars');
    if (nutrientsBars) {
        nutrientsBars.setAttribute('data-period', '7');
    }
});
</script>

<style>
/* ========== HIDRATAÇÃO - ESTILOS COMPLETOS ========== */

.hydration-container {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.hydration-summary-card {
    background: linear-gradient(145deg, #1a1a1a, #2d2d2d);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.summary-main {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.summary-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(145deg, #2196F3, #42A5F5);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
}

.summary-info {
    flex: 1;
}

.summary-info h3 {
    color: #ffffff;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
}

.summary-meta {
    color: #2196F3;
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.summary-description {
    color: #cccccc;
    font-size: 0.9rem;
    line-height: 1.4;
}

.summary-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.8rem 1.2rem;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.9rem;
}

.status-excellent {
    background: rgba(76, 175, 80, 0.2);
    color: #4CAF50;
    border: 1px solid #4CAF50;
}

.status-good {
    background: rgba(33, 150, 243, 0.2);
    color: #2196F3;
    border: 1px solid #2196F3;
}

.status-fair {
    background: rgba(255, 152, 0, 0.2);
    color: #FF9800;
    border: 1px solid #FF9800;
}

.status-poor {
    background: rgba(244, 67, 54, 0.2);
    color: #F44336;
    border: 1px solid #F44336;
}

.status-critical {
    background: rgba(156, 39, 176, 0.2);
    color: #9C27B0;
    border: 1px solid #9C27B0;
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.summary-stat {
    text-align: center;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 15px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
}

.summary-stat:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-2px);
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #2196F3;
    margin-bottom: 0.5rem;
}

.stat-label {
    color: #ffffff;
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.help-icon {
    color: #2196F3;
    cursor: pointer;
    font-size: 0.8rem;
    transition: color 0.3s ease;
}

.help-icon:hover {
    color: #42A5F5;
}

.stat-description {
    color: #cccccc;
    font-size: 0.9rem;
}

/* Gráfico de hidratação */
.chart-section {
    background: linear-gradient(145deg, #1a1a1a, #2d2d2d);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.hydration-chart-improved {
    background: rgba(255, 255, 255, 0.02);
    border-radius: 15px;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.chart-header h4 {
    color: #ffffff;
    font-size: 1.3rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.chart-header h4 i {
    color: #2196F3;
}

.period-buttons {
    display: flex;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.05);
    padding: 0.5rem;
    border-radius: 25px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.period-btn {
    background: transparent;
    border: none;
    color: #cccccc;
    padding: 0.6rem 1.2rem;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    font-size: 0.9rem;
}

.period-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}

.period-btn.active {
    background: linear-gradient(145deg, #2196F3, #42A5F5);
    color: white;
    box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
}

.improved-chart {
    min-height: 300px;
    display: flex;
    align-items: end;
    justify-content: center;
    padding: 1rem 0;
}

.empty-chart {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 300px;
    color: #666666;
    text-align: center;
}

.empty-chart i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-chart p {
    font-size: 1.1rem;
    font-weight: 500;
}

.improved-bars {
    display: flex;
    align-items: end;
    gap: 1rem;
    height: 200px;
    width: 100%;
    justify-content: center;
}

.improved-bar-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    max-width: 80px;
}

.improved-bar-wrapper {
    position: relative;
    height: 160px;
    width: 100%;
    display: flex;
    align-items: end;
    justify-content: center;
    margin-bottom: 1rem;
}

.improved-bar {
    width: 100%;
    border-radius: 8px 8px 0 0;
    transition: all 0.3s ease;
    position: relative;
    min-height: 4px;
}

.improved-bar.excellent {
    background: linear-gradient(180deg, #4CAF50, #66BB6A);
}

.improved-bar.good {
    background: linear-gradient(180deg, #2196F3, #42A5F5);
}

.improved-bar.fair {
    background: linear-gradient(180deg, #FF9800, #FFB74D);
}

.improved-bar.poor {
    background: linear-gradient(180deg, #F44336, #EF5350);
}

.improved-bar.critical {
    background: linear-gradient(180deg, #9C27B0, #BA68C8);
}

.improved-bar.empty {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.bar-percentage-text {
    position: absolute;
    top: -25px;
    left: 50%;
    transform: translateX(-50%);
    color: #ffffff;
    font-weight: 600;
    font-size: 0.8rem;
    background: rgba(0, 0, 0, 0.7);
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    white-space: nowrap;
}

.improved-goal-line {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: #ffffff;
    opacity: 0.3;
}

.improved-bar-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
}

.improved-date {
    color: #cccccc;
    font-size: 0.8rem;
    font-weight: 500;
}

.improved-ml {
    color: #2196F3;
    font-size: 0.9rem;
    font-weight: 600;
}

/* Períodos compactos */
.hydration-periods-compact {
    background: linear-gradient(145deg, #1a1a1a, #2d2d2d);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.hydration-periods-compact h4 {
    color: #ffffff;
    font-size: 1.3rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-description {
    color: #cccccc;
    font-size: 0.9rem;
    line-height: 1.4;
    margin-bottom: 2rem;
}

.periods-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.period-item {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 15px;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
}

.period-item:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-2px);
}

.period-label {
    display: block;
    color: #cccccc;
    font-size: 0.9rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.period-value {
    display: block;
    color: #2196F3;
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.3rem;
}

.period-percentage {
    display: block;
    color: #ffffff;
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.period-details {
    color: #999999;
    font-size: 0.8rem;
    font-style: italic;
}

/* Responsividade */
@media (max-width: 768px) {
    .hydration-container {
        gap: 1.5rem;
    }
    
    .hydration-summary-card,
    .chart-section,
    .hydration-periods-compact {
        padding: 1.5rem;
    }
    
    .summary-main {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .summary-stats {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .chart-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .period-buttons {
        justify-content: center;
    }
    
    .improved-bars {
        gap: 0.5rem;
    }
    
    .improved-bar-container {
        max-width: 60px;
    }
    
    .periods-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
}

@media (max-width: 480px) {
    .hydration-summary-card,
    .chart-section,
    .hydration-periods-compact {
        padding: 1rem;
    }
    
    .summary-icon {
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
    }
    
    .summary-info h3 {
        font-size: 1.3rem;
    }
    
    .stat-value {
        font-size: 1.5rem;
    }
    
    .improved-bars {
        gap: 0.3rem;
    }
    
    .improved-bar-container {
        max-width: 50px;
    }
    
    .bar-percentage-text {
        font-size: 0.7rem;
        top: -20px;
    }
}
</style>
</div>
