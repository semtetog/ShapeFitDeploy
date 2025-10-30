<!-- view_user_nutrients.php -->
<!-- Conteúdo completo da aba Nutrientes: HTML, CSS e JS -->

<div id="tab-nutrients" class="tab-content">
    <div class="view-user-tab">
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
    '90': <?php echo json_encode($nutrients_stats_90 ?? []); ?>,
    'all': <?php echo json_encode($nutrients_stats_all ?? []); ?>
};

// Função para mudar período dos nutrientes
function changeNutrientsPeriod(period) {
    // Remover classe active de todos os botões
    document.querySelectorAll('#tab-nutrients .period-btn').forEach(btn => btn.classList.remove('active'));
    
    // Adicionar classe active ao botão clicado
    event.target.classList.add('active');
    
    // Atualizar display
    updateNutrientsDisplay(period);
}

// Função para atualizar display dos nutrientes
function updateNutrientsDisplay(period) {
    const stats = nutrientsStats[period];
    
    // Atualizar estatísticas principais
    const avgKcalEl = document.querySelector('#tab-nutrients .stat-value');
    if (avgKcalEl) {
        avgKcalEl.textContent = stats.avg_kcal + ' kcal';
    }
    
    // Atualizar círculo de porcentagem
    const nutrientsCircle = document.getElementById('nutrients-percentage-circle');
    if (nutrientsCircle) {
        nutrientsCircle.style.setProperty('--percentage', stats.avg_overall_percentage);
    }
    
    // Atualizar gráfico de nutrientes
    const nutrientsBars = document.getElementById('nutrients-bars');
    if (nutrientsBars) {
        let daysToShow;
        if (period === 'all') {
            daysToShow = nutrientsData.length;
        } else if (period === 'today') {
            daysToShow = 1;
        } else if (period === 'yesterday') {
            daysToShow = 1;
        } else {
            daysToShow = parseInt(period);
        }
        
        let displayData;
        if (period === 'today') {
            const today = '<?php echo $today; ?>';
            displayData = nutrientsData.filter(day => day.date === today);
        } else if (period === 'yesterday') {
            const yesterday = '<?php echo $yesterday; ?>';
            displayData = nutrientsData.filter(day => day.date === yesterday);
        } else {
            displayData = nutrientsData.slice(0, daysToShow);
        }
        
        nutrientsBars.innerHTML = displayData.map(day => {
            const percentage = day.avg_percentage || 0;
            
            // Calcular altura da barra
            let barHeight;
            if (percentage === 0) {
                barHeight = 0;
            } else if (percentage >= 100) {
                barHeight = 160 + Math.min((percentage - 100) * 0.4, 40);
            } else {
                barHeight = (percentage / 100) * 160;
            }
            
            // Determinar status
            let status = 'poor';
            if (percentage >= 90) status = 'excellent';
            else if (percentage >= 70) status = 'good';
            else if (percentage >= 50) status = 'fair';
            
            return `
                <div class="improved-bar-container">
                    <div class="improved-bar-wrapper">
                        <div class="improved-bar ${status}" style="height: ${barHeight}px"></div>
                        <div class="bar-percentage-text">${percentage}%</div>
                        <div class="improved-goal-line"></div>
                    </div>
                    <div class="improved-bar-info">
                        <span class="improved-date">${day.date.split('-').reverse().slice(0, 2).join('/')}</span>
                        <span class="improved-ml">${day.kcal_consumed || day.kcal} kcal</span>
                    </div>
                </div>
            `;
        }).join('');
    }
}

// Inicializar nutrientes quando a aba for ativada
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar listener para mudança de abas
    document.querySelectorAll('.tab-link').forEach(tab => {
        tab.addEventListener('click', function() {
            if (this.dataset.tab === 'nutrients') {
                setTimeout(() => {
                    // Inicializar com período de 7 dias
                    updateNutrientsDisplay(7);
                }, 100);
            }
        });
    });
    
    // Inicializar o círculo de porcentagem de nutrientes
    const nutrientsCircle = document.getElementById('nutrients-percentage-circle');
    if (nutrientsCircle) {
        const initialPercentage = nutrientsStats['7'].avg_overall_percentage;
        nutrientsCircle.style.setProperty('--percentage', initialPercentage);
    }
});
</script>
    </div>
</div>
