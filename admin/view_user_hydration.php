<!-- view_user_hydration.php -->
<!-- Conteúdo completo da aba Hidratação: HTML, CSS e JS -->
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

<script>
// Dados de hidratação do PHP (escopo global)
const hydrationData = <?php echo json_encode($hydration_data); ?>;
const waterStats = {
    '7': <?php echo json_encode($water_stats_7); ?>,
    '15': <?php echo json_encode($water_stats_15); ?>,
    '30': <?php echo json_encode($water_stats_30); ?>
};

// Função para mudar período da hidratação
function changeHydrationPeriod(period) {
    // Remover classe active de todos os botões
    document.querySelectorAll('#tab-hydration .period-btn').forEach(btn => btn.classList.remove('active'));
    
    // Adicionar classe active ao botão clicado
    event.target.classList.add('active');
    
    // Atualizar display
    updateHydrationDisplay(period);
}

// Função para atualizar display da hidratação
function updateHydrationDisplay(period) {
    const stats = waterStats[period];
    
    // Atualizar estatísticas principais
    document.querySelectorAll('#tab-hydration .stat-value')[0].textContent = stats.avg_ml + 'ml';
    document.querySelectorAll('#tab-hydration .stat-value')[1].textContent = stats.avg_percentage + '%';
    document.querySelectorAll('#tab-hydration .stat-value')[2].textContent = stats.days_with_consumption + '/' + stats.total_days;
    
    // Atualizar círculo de porcentagem
    const circle = document.getElementById('avg-percentage-circle');
    if (circle) {
        circle.style.setProperty('--percentage', stats.avg_percentage);
    }
    
    // Atualizar período
    let periodText;
    if (period === 7) {
        periodText = 'Período: Últimos 7 dias (média dos últimos 7 dias)';
    } else if (period === 15) {
        periodText = 'Período: Últimos 15 dias (média dos últimos 15 dias)';
    } else if (period === 30) {
        periodText = 'Período: Últimos 30 dias (média dos últimos 30 dias)';
    }
    document.getElementById('hydration-period-info').textContent = periodText;

    // Atualizar gráfico de hidratação
    const hydrationBars = document.getElementById('hydration-bars');
    if (hydrationBars) {
        const displayData = hydrationData.slice(0, period);
        
        hydrationBars.innerHTML = displayData.map(day => {
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
    }
}

// Inicializar círculo de porcentagem
document.addEventListener('DOMContentLoaded', function() {
    const circle = document.getElementById('avg-percentage-circle');
    if (circle) {
        const initialPercentage = waterStats['7'].avg_percentage;
        circle.style.setProperty('--percentage', initialPercentage);
    }
});
</script>
</div>
