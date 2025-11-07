<!-- view_user_routine.php -->
<!-- Conteúdo completo da aba Rotina: HTML, CSS e JS -->
<div class="routine-container">
        
        <!-- CALENDÁRIO COM LÓGICA AJAX (IGUAL AO DIÁRIO) -->
        <div class="diary-slider-container">
            <div class="diary-header-redesign">
                <!-- Header com título e descrição -->
                <div class="diary-header-title-section">
                    <div class="diary-header-icon routine-header-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="diary-header-title">
                        <h3>Resumo da Rotina Semanal</h3>
                        <p>Acompanhamento de missões, treinos e sono</p>
                    </div>
                </div>
                
                <!-- Ano no topo -->
                <div class="diary-year" id="routineYear"><?php echo date('Y'); ?></div>
                
                <!-- Navegação e data principal -->
                <div class="diary-nav-row">
                    <button class="diary-nav-side diary-nav-left" onclick="navigateRoutineDate(-1)" type="button">
                        <i class="fas fa-chevron-left"></i>
                        <?php 
                        $weekdayNames = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                        $monthsShort = ['JAN','FEV','MAR','ABR','MAI','JUN','JUL','AGO','SET','OUT','NOV','DEZ'];
                        $monthsLower = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
                        $yesterday = date('Y-m-d', strtotime('-1 day'));
                        $yesterdayDay = (int)date('d', strtotime($yesterday));
                        $yesterdayMonth = (int)date('m', strtotime($yesterday));
                        ?>
                        <span id="routinePrevDate"><?php echo $yesterdayDay . ' ' . $monthsLower[$yesterdayMonth - 1]; ?></span>
                    </button>
                    
                    <div class="diary-main-date">
                        <div class="diary-day-month" id="routineDayMonth"><?php echo (int)date('d') . ' ' . $monthsShort[(int)date('n') - 1]; ?></div>
                        <div class="diary-weekday" id="routineWeekday"><?php echo $weekdayNames[date('w')]; ?></div>
                    </div>
                    
                    <button class="diary-nav-side diary-nav-right" onclick="navigateRoutineDate(1)" type="button" style="visibility: hidden;">
                        <span id="routineNextDate">-</span>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                
                <!-- Resumo de missões -->
                <div class="diary-summary-row">
                    <div class="diary-macros" id="routineSummaryProgress" style="justify-content: center;">
                        <span id="routineSummaryMissions">0/0 missões</span>
                    </div>
                </div>
                
                <!-- Botão de calendário -->
                <button class="diary-calendar-icon-btn" onclick="openRoutineCalendar()" type="button" title="Ver calendário">
                    <i class="fas fa-calendar-alt"></i>
                </button>
            </div>
            
            <!-- Container do conteúdo (substituído via AJAX) -->
            <div class="diary-slider-wrapper" id="routineSliderWrapper">
                <div class="diary-content-wrapper" id="routineContentWrapper">
                    <div class="diary-loading-state" id="routineLoadingState">
                        <div class="loading-spinner"></div>
                        <p>Carregando rotina...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEÇÃO DE ACOMPANHAMENTO: EXERCÍCIO FÍSICO -->
        <div class="chart-section" style="margin-top: 2rem;">
            <div class="exercise-chart-improved">
                <div class="chart-header">
                    <h4><i class="fas fa-dumbbell"></i> Exercício Físico</h4>
                    <div class="period-buttons">
                        <button class="period-btn active" onclick="showExerciseCalendar()" id="exercise-period-btn" title="Selecionar período">
                            <i class="fas fa-calendar-alt"></i> Últimos 7 dias
                        </button>
                    </div>
                </div>
                
                <div class="tracking-stats-grid" style="margin-bottom: 1.5rem;">
                    <?php
                    // Calcular estatísticas dos últimos 7 dias
                    $exercise_7_days = array_filter($routine_exercise_data, function($item) {
                        $itemDate = new DateTime($item['date']);
                        $now = new DateTime();
                        $diff = $now->diff($itemDate)->days;
                        return $diff < 7;
                    });
                    $exercise_total_minutes_7 = array_sum(array_column($exercise_7_days, 'total_minutes'));
                    $exercise_days_with_data_7 = count($exercise_7_days);
                    $exercise_avg_daily_7 = $exercise_days_with_data_7 > 0 ? round($exercise_total_minutes_7 / 7, 1) : 0;
                    $exercise_goal_reached_7 = $exercise_goal_daily_minutes > 0 ? round(($exercise_avg_daily_7 / $exercise_goal_daily_minutes) * 100, 0) : 0;
                    if ($exercise_goal_reached_7 > 100) $exercise_goal_reached_7 = 100;
                    ?>
                    <div class="tracking-stat">
                        <div class="stat-value" id="exerciseAvgDaily"><?php echo $exercise_avg_daily_7; ?> min</div>
                        <div class="stat-label">Média Diária</div>
                        <div class="stat-description" id="exercisePeriodDesc">Últimos 7 dias</div>
                    </div>
                    <div class="tracking-stat">
                        <div class="stat-value"><?php echo $exercise_goal_daily_minutes > 0 ? round($exercise_goal_daily_minutes, 0) : '0'; ?> min</div>
                        <div class="stat-label">Meta Diária</div>
                        <div class="stat-description">
                            <?php echo $exercise_goal_weekly_hours > 0 ? round($exercise_goal_weekly_hours, 1) . 'h/semana' : 'Sem meta'; ?>
                        </div>
                    </div>
                    <div class="tracking-stat">
                        <div class="stat-value <?php echo $exercise_days_with_data_7 >= 3 ? 'text-success' : ($exercise_days_with_data_7 > 0 ? 'text-warning' : 'text-danger'); ?>" id="exerciseDaysWithData">
                            <?php echo $exercise_days_with_data_7; ?>/7
                        </div>
                        <div class="stat-label">Dias com Exercício</div>
                        <div class="stat-description" id="exerciseGoalReached">
                            <?php echo $exercise_goal_daily_minutes > 0 ? $exercise_goal_reached_7 . '% da meta' : 'Sem meta definida'; ?>
                        </div>
                    </div>
                </div>
                
                <div class="improved-chart" id="exercise-chart">
                    <div class="improved-bars" id="exercise-bars">
                        <div class="empty-chart">
                            <div class="loading-spinner"></div>
                            <p>Carregando dados...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SEÇÃO DE ACOMPANHAMENTO: SONO -->
        <div class="chart-section">
            <div class="sleep-chart-improved">
                <div class="chart-header">
                    <h4><i class="fas fa-bed"></i> Sono</h4>
                    <div class="period-buttons">
                        <button class="period-btn active" onclick="showSleepCalendar()" id="sleep-period-btn" title="Selecionar período">
                            <i class="fas fa-calendar-alt"></i> Últimos 7 dias
                        </button>
                    </div>
                </div>
                
                <div class="tracking-stats-grid" style="margin-bottom: 1.5rem;" id="sleepStatsGrid">
                    <?php
                    // Calcular estatísticas dos últimos 7 dias
                    $sleep_7_days = array_filter($routine_sleep_data, function($item) {
                        $itemDate = new DateTime($item['date']);
                        $now = new DateTime();
                        $diff = $now->diff($itemDate)->days;
                        return $diff < 7;
                    });
                    $sleep_total_hours_7 = array_sum(array_column($sleep_7_days, 'sleep_hours'));
                    $sleep_days_with_data_7 = count($sleep_7_days);
                    $sleep_avg_daily_7 = $sleep_days_with_data_7 > 0 ? round($sleep_total_hours_7 / 7, 1) : 0;
                    $sleep_goal_reached_7 = $sleep_goal_hours > 0 ? round(($sleep_avg_daily_7 / $sleep_goal_hours) * 100, 0) : 0;
                    if ($sleep_goal_reached_7 > 100) $sleep_goal_reached_7 = 100;
                    
                    // Determinar status
                    $sleep_status = 'poor';
                    $sleep_status_text = 'Abaixo da meta';
                    if ($sleep_avg_daily_7 >= 7 && $sleep_avg_daily_7 <= 8) {
                        $sleep_status = 'excellent';
                        $sleep_status_text = 'Ideal';
                    } elseif ($sleep_avg_daily_7 >= 6.5 && $sleep_avg_daily_7 < 7) {
                        $sleep_status = 'good';
                        $sleep_status_text = 'Bom';
                    } elseif ($sleep_avg_daily_7 >= 6 && $sleep_avg_daily_7 < 6.5) {
                        $sleep_status = 'fair';
                        $sleep_status_text = 'Regular';
                    } elseif ($sleep_avg_daily_7 >= 5 && $sleep_avg_daily_7 < 6) {
                        $sleep_status = 'poor';
                        $sleep_status_text = 'Abaixo da meta';
                    } else {
                        $sleep_status = 'critical';
                        $sleep_status_text = 'Crítico';
                    }
                    ?>
                    <div class="tracking-stat">
                        <div class="stat-value" id="sleepAvgDaily"><?php echo $sleep_avg_daily_7; ?>h</div>
                        <div class="stat-label">Média Diária</div>
                        <div class="stat-description" id="sleepPeriodDesc">Últimos 7 dias</div>
                    </div>
                    <div class="tracking-stat">
                        <div class="stat-value">7.5h</div>
                        <div class="stat-label">Meta Diária</div>
                        <div class="stat-description">Ideal (7-8h)</div>
                    </div>
                    <div class="tracking-stat">
                        <div class="stat-value status-<?php echo $sleep_status; ?>" id="sleepDaysWithData">
                            <?php echo $sleep_days_with_data_7; ?>/7
                        </div>
                        <div class="stat-label">Dias Registrados</div>
                        <div class="stat-description" id="sleepStatusDesc"><?php echo $sleep_status_text; ?> - <?php echo $sleep_goal_reached_7; ?>% da meta</div>
                    </div>
                </div>
                
                <div class="improved-chart" id="sleep-chart">
                    <div class="improved-bars" id="sleep-bars">
                        <div class="empty-chart">
                            <div class="loading-spinner"></div>
                            <p>Carregando dados...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. CARD DE GERENCIAMENTO DE MISSÕES -->
        <div class="routine-missions-card">
            <div class="card-header">
                <div class="card-title">
                    <div class="title-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="title-content">
                        <h3>Missões do Usuário</h3>
                        <p>Gerencie as missões de rotina personalizadas para este paciente</p>
                    </div>
                </div>
                <button class="btn-add-mission-circular" onclick="openMissionModal()" title="Adicionar Missão">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            
            <div class="missions-grid" id="missions-container">
                <!-- Missões serão carregadas aqui via JavaScript -->
                <div class="loading-missions">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Carregando missões...</span>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
/* === CARD DE GERENCIAMENTO DE MISSÕES === */
.routine-missions-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 2rem;
    margin-top: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}

.routine-missions-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.routine-missions-card .card-title {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.routine-missions-card .title-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.routine-missions-card .title-icon i {
    font-size: 1.5rem;
    color: var(--accent-orange);
}

.routine-missions-card .title-content h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
}

.routine-missions-card .title-content p {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.routine-missions-card .missions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
}

.routine-missions-card .loading-missions {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 1rem;
    color: var(--text-secondary);
    gap: 1rem;
}

.routine-missions-card .loading-missions i {
    font-size: 2rem;
    color: var(--accent-orange);
}

.routine-missions-card .loading-missions span {
    font-size: 0.95rem;
}

/* Cards de missões individuais */
.mission-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 1.25rem;
    transition: all 0.3s ease;
}

.mission-card:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 107, 0, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.mission-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.mission-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.mission-icon i {
    font-size: 1.5rem;
    color: var(--accent-orange);
}

.mission-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-edit, .btn-delete {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.btn-edit:hover {
    background: rgba(33, 150, 243, 0.2);
    border-color: rgba(33, 150, 243, 0.4);
    color: #2196F3;
    transform: scale(1.05);
}

.btn-delete:hover {
    background: rgba(244, 67, 54, 0.2);
    border-color: rgba(244, 67, 54, 0.4);
    color: #F44336;
    transform: scale(1.05);
}

.mission-content h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.mission-description {
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
    color: var(--text-secondary);
    opacity: 0.9;
}

.mission-type {
    margin: 0 0 0.25rem 0;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.empty-missions {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 1rem;
    color: var(--text-secondary);
    gap: 1rem;
}

.empty-missions i {
    font-size: 3rem;
    color: rgba(255, 107, 0, 0.3);
}

.empty-missions p {
    font-size: 1rem;
    text-align: center;
}

/* Botão circular de adicionar missão (igual ao botão de calendário) */
.btn-add-mission-circular {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.08);
    border: 1px solid rgba(255, 107, 0, 0.2);
    color: var(--accent-orange);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.btn-add-mission-circular:hover {
    background: rgba(255, 107, 0, 0.15);
    border-color: var(--accent-orange);
    transform: scale(1.05);
}

.btn-add-mission-circular i {
    font-size: 1.5rem;
}

/* Seletor de ícones */
.icon-picker {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 0.75rem;
    margin-top: 0.5rem;
}

.icon-option {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.05);
    border: 2px solid rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.icon-option:hover {
    background: rgba(255, 107, 0, 0.1);
    border-color: rgba(255, 107, 0, 0.3);
    transform: scale(1.1);
}

.icon-option.selected {
    background: rgba(255, 107, 0, 0.2);
    border-color: var(--accent-orange);
}

.icon-option i {
    font-size: 1.25rem;
    color: var(--text-primary);
}

.icon-option.selected i {
    color: var(--accent-orange);
}

@media (max-width: 768px) {
    .routine-missions-card {
        padding: 1.5rem;
    }
    
    .routine-missions-card .card-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .routine-missions-card .btn-add-mission {
        width: 100%;
        justify-content: center;
    }
    
    .routine-missions-card .missions-grid {
        grid-template-columns: 1fr;
    }
}

/* === SEÇÃO DE ACOMPANHAMENTO: EXERCÍCIO FÍSICO E SONO === */
/* Espaçamento entre chart-sections (igual hidratação/nutrientes) */
.routine-container .chart-section {
    margin-bottom: 2rem;
}

.routine-container .chart-section:first-of-type {
    margin-top: 2rem;
}

.exercise-chart-improved,
.sleep-chart-improved {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

/* Garantir que as improved bars usem exatamente o mesmo CSS da hidratação */
.exercise-chart-improved .improved-chart,
.sleep-chart-improved .improved-chart {
    /* CSS padrão já aplicado em view_user_addon.css */
}

/* Comportamento padrão para 7 dias (igual hidratação) */
#exercise-bars,
#sleep-bars {
    display: flex;
    justify-content: space-around;
    align-items: flex-end;
    gap: 1rem;
    min-height: 240px;
    padding: 1rem 0.5rem 0.5rem;
    position: relative;
}

#exercise-bars .improved-bar-container,
#sleep-bars .improved-bar-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    min-width: 60px;
    max-width: 100px;
}

/* Quando há muitas barras (mais de 7), permitir wrap em múltiplas fileiras */
#exercise-bars.many-bars,
#sleep-bars.many-bars {
    justify-content: flex-start !important;
    flex-wrap: wrap !important;
    overflow-x: visible !important;
}

#exercise-bars.many-bars .improved-bar-container,
#sleep-bars.many-bars .improved-bar-container {
    flex: 0 0 calc((100% - (6 * 1rem)) / 7) !important;
    max-width: calc((100% - (6 * 1rem)) / 7) !important;
    min-width: 60px !important;
    width: calc((100% - (6 * 1rem)) / 7) !important;
}

/* Garantir que o container do chart permita wrap */
.exercise-chart-improved .improved-chart,
.sleep-chart-improved .improved-chart {
    overflow-x: visible !important;
    overflow-y: visible !important;
}


.tracking-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.tracking-stat {
    text-align: center;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.tracking-stat .stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
}

.tracking-stat .stat-value.text-success {
    color: #4CAF50;
}

.tracking-stat .stat-value.text-warning {
    color: #FF9800;
}

.tracking-stat .stat-value.text-danger {
    color: #F44336;
}

.tracking-stat .stat-value.status-excellent {
    color: #4CAF50;
}

.tracking-stat .stat-value.status-good {
    color: #8BC34A;
}

.tracking-stat .stat-value.status-fair {
    color: #FF9800;
}

.tracking-stat .stat-value.status-poor {
    color: #FF5722;
}

.tracking-stat .stat-value.status-critical {
    color: #F44336;
}

.tracking-stat .stat-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.tracking-stat .stat-description {
    font-size: 0.75rem;
    color: var(--text-secondary);
}


@media (max-width: 768px) {
    .tracking-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// ============ CONFIGURAÇÃO E INICIALIZAÇÃO DA ROTINA ============
const routineMonthNamesShort = ['JAN','FEV','MAR','ABR','MAI','JUN','JUL','AGO','SET','OUT','NOV','DEZ'];
const routineMonthNamesLower = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
const routineWeekdayNames = ['DOMINGO','SEGUNDA','TERÇA','QUARTA','QUINTA','SEXTA','SÁBADO'];

let currentRoutineDate = new Date(); // Data atualmente exibida na rotina
const routineUserId = <?php echo $user_id; ?>;

// ============ FUNÇÃO PRINCIPAL DE CARREGAMENTO ============
async function loadRoutineForDate(targetDate, direction = 0) {
    const dateStr = targetDate.toISOString().split('T')[0];
    console.log('[routine] Carregando data:', dateStr, 'direction:', direction);
    
    const wrapper = document.getElementById('routineContentWrapper');
    
    // Verificar se tem conteúdo para animar
    const hasContent = wrapper.innerHTML.trim() && !wrapper.querySelector('.diary-loading-state');
    
    // Se tiver conteúdo E direção, animar SAÍDA imediatamente
    if (hasContent && direction !== 0) {
        const translateX = direction > 0 ? '-100%' : '100%';
        wrapper.style.transition = 'transform 0.2s cubic-bezier(0.4, 0.0, 0.2, 1)';
        wrapper.style.transform = `translateX(${translateX})`;
        wrapper.style.opacity = '0';
    }
    
    try {
        // Chamar API
        const url = `actions/load_routine_days.php?user_id=${routineUserId}&date=${dateStr}`;
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
        
        const html = await response.text();
        
        if (!response.ok) {
            console.error('[routine] Erro na resposta:', html);
            throw new Error(`HTTP ${response.status}: ${html.substring(0, 200)}`);
        }
        
        if (html.trim()) {
            // Parse HTML e extrair dados
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            const dayContent = tempDiv.querySelector('.routine-content-day');
            
            if (dayContent) {
                // Extrair data-attributes do resumo
                const missions = parseInt(dayContent.dataset.missions || '0', 10);
                const total = parseInt(dayContent.dataset.total || '0', 10);
                
                // Atualizar cabeçalho DEPOIS do AJAX
                updateRoutineHeader(targetDate);
                
                // Se já animamos a saída, agora só inserir conteúdo e animar entrada
                if (hasContent && direction !== 0) {
                    // Inserir novo conteúdo
                    const missionsContainer = dayContent.querySelector('.routine-day-missions');
                    if (missionsContainer) {
                        wrapper.innerHTML = missionsContainer.outerHTML;
                    }
                    wrapper.style.transition = 'none';
                    wrapper.style.transform = `translateX(${direction > 0 ? '100%' : '-100%'})`;
                    wrapper.style.opacity = '1';
                    
                    // Forçar reflow
                    void wrapper.offsetHeight;
                    
                    // Animar entrada
                    wrapper.style.transition = 'transform 0.2s cubic-bezier(0.4, 0.0, 0.2, 1)';
                    wrapper.style.transform = 'translateX(0)';
                    
                    updateRoutineSummary(missions, total);
                    
                    // Resetar estilos
                    setTimeout(() => {
                        wrapper.style.transition = '';
                        wrapper.style.transform = '';
                        wrapper.style.opacity = '';
                    }, 200);
                } else {
                    // Primeira carga ou sem direção
                    const missionsContainer = dayContent.querySelector('.routine-day-missions');
                    if (missionsContainer) {
                        wrapper.innerHTML = missionsContainer.outerHTML;
                    }
                    wrapper.style.opacity = '1';
                    updateRoutineSummary(missions, total);
                }
            } else {
                // Dia sem dados
                updateRoutineHeader(targetDate);
                if (hasContent && direction !== 0) {
                    wrapper.innerHTML = '<div class="diary-empty-state"><i class="fas fa-calendar-day"></i><p>Nenhum registro neste dia</p></div>';
                    wrapper.style.transition = 'none';
                    wrapper.style.transform = `translateX(${direction > 0 ? '100%' : '-100%'})`;
                    wrapper.style.opacity = '1';
                    void wrapper.offsetHeight;
                    wrapper.style.transition = 'transform 0.2s cubic-bezier(0.4, 0.0, 0.2, 1)';
                    wrapper.style.transform = 'translateX(0)';
                    setTimeout(() => { wrapper.style.transition = ''; wrapper.style.transform = ''; wrapper.style.opacity = ''; }, 200);
                    updateRoutineSummary(0, 0);
                } else {
                    wrapper.innerHTML = '<div class="diary-empty-state"><i class="fas fa-calendar-day"></i><p>Nenhum registro neste dia</p></div>';
                    wrapper.style.opacity = '1';
                    updateRoutineSummary(0, 0);
                }
            }
        } else {
            // Resposta vazia = sem registros
            updateRoutineHeader(targetDate);
            if (hasContent && direction !== 0) {
                wrapper.innerHTML = '<div class="diary-empty-state"><i class="fas fa-calendar-day"></i><p>Nenhum registro neste dia</p></div>';
                wrapper.style.transition = 'none';
                wrapper.style.transform = `translateX(${direction > 0 ? '100%' : '-100%'})`;
                wrapper.style.opacity = '1';
                void wrapper.offsetHeight;
                wrapper.style.transition = 'transform 0.2s cubic-bezier(0.4, 0.0, 0.2, 1)';
                wrapper.style.transform = 'translateX(0)';
                setTimeout(() => { wrapper.style.transition = ''; wrapper.style.transform = ''; wrapper.style.opacity = ''; }, 200);
                updateRoutineSummary(0, 0);
            } else {
                wrapper.innerHTML = '<div class="diary-empty-state"><i class="fas fa-calendar-day"></i><p>Nenhum registro neste dia</p></div>';
                wrapper.style.opacity = '1';
                updateRoutineSummary(0, 0);
            }
        }
        
    } catch (error) {
        console.error('[routine] Erro ao carregar:', error);
        wrapper.innerHTML = '<div class="diary-error-state"><i class="fas fa-exclamation-triangle"></i><p>Erro ao carregar rotina. Tente novamente.</p></div>';
    }
}

// ============ ATUALIZAR CABEÇALHO (DATA) ============
function updateRoutineHeader(targetDate) {
    const year = targetDate.getFullYear();
    const day = targetDate.getDate();
    const monthIdx = targetDate.getMonth();
    const weekdayIdx = targetDate.getDay();
    
    // Atualizar elementos
    document.getElementById('routineYear').textContent = year;
    document.getElementById('routineDayMonth').textContent = `${day} ${routineMonthNamesShort[monthIdx]}`;
    document.getElementById('routineWeekday').textContent = routineWeekdayNames[weekdayIdx];
    
    // Atualizar botões de navegação
    const prevDate = new Date(targetDate);
    prevDate.setDate(prevDate.getDate() - 1);
    prevDate.setHours(0, 0, 0, 0);
    document.getElementById('routinePrevDate').textContent = `${prevDate.getDate()} ${routineMonthNamesLower[prevDate.getMonth()]}`;
    
    const nextDate = new Date(targetDate);
    nextDate.setDate(nextDate.getDate() + 1);
    nextDate.setHours(0, 0, 0, 0);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const nextBtn = document.querySelector('#routineSliderWrapper').parentElement.querySelector('.diary-nav-right');
    if (nextDate <= today) {
        document.getElementById('routineNextDate').textContent = `${nextDate.getDate()} ${routineMonthNamesLower[nextDate.getMonth()]}`;
        nextBtn.style.visibility = 'visible';
    } else {
        nextBtn.style.visibility = 'hidden';
    }
}

// ============ ATUALIZAR RESUMO (MISSÕES) ============
function updateRoutineSummary(missions, total) {
    const progress = total > 0 ? Math.round((missions / total) * 100) : 0;
    document.getElementById('routineSummaryMissions').textContent = `${missions}/${total} missões`;
}

// ============ NAVEGAÇÃO ENTRE DIAS ============
function navigateRoutineDate(direction) {
    const newDate = new Date(currentRoutineDate);
    newDate.setDate(newDate.getDate() + direction);
    newDate.setHours(0, 0, 0, 0);
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (newDate <= today) {
        currentRoutineDate = newDate;
        loadRoutineForDate(currentRoutineDate, direction);
    }
}

// Expor para handlers inline
window.navigateRoutineDate = navigateRoutineDate;

// ============ INICIALIZAÇÃO ============
(function initRoutine() {
    currentRoutineDate = new Date();
    currentRoutineDate.setHours(0, 0, 0, 0);
    loadRoutineForDate(currentRoutineDate);
    
    // Suporte a teclado
    document.addEventListener('keydown', function(e) {
        if (document.getElementById('tab-routine').classList.contains('active')) {
            if (e.key === 'ArrowLeft') navigateRoutineDate(-1);
            if (e.key === 'ArrowRight') navigateRoutineDate(1);
        }
    });
})();

// --- FUNCIONALIDADES DA ABA ROTINA ---
let stepsChart = null;
let exerciseChart = null;
let sleepChart = null;

// Função para calcular estatísticas de passos
function calculateStepsStats(data, period) {
    const today = new Date();
    const filteredData = data.filter(item => {
        const itemDate = new Date(item.date);
        const daysDiff = Math.floor((today - itemDate) / (1000 * 60 * 60 * 24));
        return daysDiff < period;
    });
    
    if (filteredData.length === 0) return { average: 0, total: 0 };
    
    const total = filteredData.reduce((sum, item) => sum + (parseInt(item.steps_daily) || 0), 0);
    const average = Math.round(total / filteredData.length);
    
    return { average, total };
}

// Função para calcular estatísticas de sono
function calculateSleepStats(data, period) {
    const today = new Date();
    const filteredData = data.filter(item => {
        const itemDate = new Date(item.date);
        const daysDiff = Math.floor((today - itemDate) / (1000 * 60 * 60 * 24));
        return daysDiff < period;
    });
    
    if (filteredData.length === 0) return { average: 0 };
    
    const total = filteredData.reduce((sum, item) => sum + (parseFloat(item.sleep_hours) || 0), 0);
    const average = Math.round((total / filteredData.length) * 10) / 10;
    
    return { average };
}

// Função para calcular estatísticas de exercícios
function calculateExerciseStats(data, period) {
    const today = new Date();
    const filteredData = data.filter(item => {
        const itemDate = new Date(item.updated_at);
        const daysDiff = Math.floor((today - itemDate) / (1000 * 60 * 60 * 24));
        return daysDiff < period;
    });
    
    const totalTime = filteredData.reduce((sum, item) => sum + (parseInt(item.duration_minutes) || 0), 0);
    
    return { count: filteredData.length, totalTime };
}

// Função para atualizar dados da rotina
function updateRoutineData(period = 7) {
    // Atualizar passos
    const stepsStats = calculateStepsStats(userViewData.routineData.steps, period);
    const stepsToday = userViewData.routineData.steps[0] ? parseInt(userViewData.routineData.steps[0].steps_daily) : 0;
    const stepsGoal = 10000;
    const stepsProgress = Math.min((stepsToday / stepsGoal) * 100, 100);
    
    document.getElementById('stepsToday').textContent = stepsToday.toLocaleString('pt-BR');
    document.getElementById('stepsProgress').style.width = stepsProgress + '%';
    document.getElementById('stepsProgressText').textContent = Math.round(stepsProgress) + '% da meta';
    
    // Atualizar sono
    const sleepStats = calculateSleepStats(userViewData.routineData.sleep, period);
    const sleepToday = userViewData.routineData.sleep[0] ? parseFloat(userViewData.routineData.sleep[0].sleep_hours) : 0;
    
    document.getElementById('sleepToday').textContent = sleepToday + 'h';
    document.getElementById('sleepAverage').textContent = 'Média: ' + sleepStats.average + 'h';
    
    // Atualizar exercícios
    const exerciseStats = calculateExerciseStats(userViewData.routineData.exercise, period);
    document.getElementById('exerciseTotalTime').textContent = exerciseStats.totalTime;
    document.getElementById('exerciseCount').textContent = exerciseStats.count + ' exercícios no período';
    
    // Atualizar gráficos
    updateStepsChart(period);
    updateExerciseChart(period);
    updateSleepChart(period);
}

// Função para atualizar gráfico de passos
function updateStepsChart(period) {
    const ctx = document.getElementById('stepsChart');
    if (!ctx) return;
    
    if (stepsChart) {
        stepsChart.destroy();
    }
    
    const today = new Date();
    const filteredData = userViewData.routineData.steps.filter(item => {
        const itemDate = new Date(item.date);
        const daysDiff = Math.floor((today - itemDate) / (1000 * 60 * 60 * 24));
        return daysDiff < period;
    }).reverse();
    
    const labels = filteredData.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    });
    
    const stepsData = filteredData.map(item => parseInt(item.steps_daily) || 0);
    
    stepsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Passos',
                data: stepsData,
                backgroundColor: 'rgba(33, 150, 243, 0.8)',
                borderColor: 'rgba(33, 150, 243, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            }
        }
    });
}

// Função para atualizar gráfico de exercícios
function updateExerciseChart(period) {
    const ctx = document.getElementById('exerciseChart');
    if (!ctx) return;
    
    if (exerciseChart) {
        exerciseChart.destroy();
    }
    
    const today = new Date();
    const filteredData = userViewData.routineData.exercise.filter(item => {
        const itemDate = new Date(item.updated_at);
        const daysDiff = Math.floor((today - itemDate) / (1000 * 60 * 60 * 24));
        return daysDiff < period;
    });
    
    // Agrupar por data
    const exerciseByDate = {};
    filteredData.forEach(item => {
        const date = item.updated_at.split(' ')[0]; // Pegar só a data
        if (!exerciseByDate[date]) {
            exerciseByDate[date] = 0;
        }
        exerciseByDate[date] += parseInt(item.duration_minutes) || 0;
    });
    
    // Ordenar datas e criar arrays para o gráfico
    const sortedDates = Object.keys(exerciseByDate).sort();
    const labels = sortedDates.map(date => {
        const d = new Date(date);
        return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    });
    
    const exerciseData = sortedDates.map(date => exerciseByDate[date]);
    
    exerciseChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Minutos de Exercício',
                data: exerciseData,
                backgroundColor: 'rgba(255, 152, 0, 0.8)',
                borderColor: 'rgba(255, 152, 0, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            }
        }
    });
}

// Função para atualizar gráfico de sono
function updateSleepChart(period) {
    const ctx = document.getElementById('sleepChart');
    if (!ctx) return;
    
    if (sleepChart) {
        sleepChart.destroy();
    }
    
    const today = new Date();
    const filteredData = userViewData.routineData.sleep.filter(item => {
        const itemDate = new Date(item.date);
        const daysDiff = Math.floor((today - itemDate) / (1000 * 60 * 60 * 24));
        return daysDiff < period;
    }).reverse();
    
    const labels = filteredData.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    });
    
    const sleepData = filteredData.map(item => parseFloat(item.sleep_hours) || 0);
    
    sleepChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Horas de Sono',
                data: sleepData,
                backgroundColor: 'rgba(156, 39, 176, 0.8)',
                borderColor: 'rgba(156, 39, 176, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 12,
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            }
        }
    });
}

// --- FUNCIONALIDADES DO RASTREIO SEMANAL ---

let currentWeekOffset = 0;
let weeklyChart = null;

// Dados para o rastreio semanal (serão preenchidos via PHP)
const weeklyData = <?php echo json_encode($last_7_days_data); ?>;
const dailyCalorieGoal = <?php echo $total_daily_calories_goal; ?>;

// Função para mudar a semana
function changeWeek(direction) {
    currentWeekOffset += direction;
    updateWeeklyDisplay();
}

// Função para atualizar a exibição semanal
function updateWeeklyDisplay() {
    const today = new Date();
    const startOfWeek = new Date(today);
    startOfWeek.setDate(today.getDate() - today.getDay() + (currentWeekOffset * 7));
    
    const endOfWeek = new Date(startOfWeek);
    endOfWeek.setDate(startOfWeek.getDate() + 6);
    
    // Atualizar texto da semana
    const weekText = currentWeekOffset === 0 ? 'Semana Atual' : 
                    currentWeekOffset > 0 ? `Semana +${currentWeekOffset}` : 
                    `Semana ${currentWeekOffset}`;
    document.getElementById('currentWeek').textContent = weekText;
    
    // Calcular dados da semana
    const weekData = calculateWeekData(startOfWeek, endOfWeek);
    
    // Atualizar resumo
    updateWeeklySummary(weekData);
    
    // Atualizar tabela
    updateWeeklyTable(weekData);
    
    // Atualizar gráfico
    updateWeeklyChart(weekData);
}

// Função para calcular dados da semana
function calculateWeekData(startDate, endDate) {
    const weekData = [];
    let totalConsumed = 0;
    let totalGoal = 0;
    
    for (let i = 0; i < 7; i++) {
        const currentDate = new Date(startDate);
        currentDate.setDate(startDate.getDate() + i);
        const dateStr = currentDate.toISOString().split('T')[0];
        
        // Buscar dados do dia
        const dayData = weeklyData.find(day => day.date === dateStr);
        const consumed = dayData ? dayData.total_kcal : 0;
        const goal = dailyCalorieGoal;
        
        const percentage = goal > 0 ? (consumed / goal) * 100 : 0;
        const difference = consumed - goal;
        
        let status = 'critical';
        if (percentage >= 100) status = 'excellent';
        else if (percentage >= 90) status = 'good';
        else if (percentage >= 70) status = 'fair';
        else if (percentage >= 50) status = 'poor';
        
        weekData.push({
            date: currentDate,
            dateStr: dateStr,
            dayName: currentDate.toLocaleDateString('pt-BR', { weekday: 'long' }),
            consumed: consumed,
            goal: goal,
            difference: difference,
            percentage: percentage,
            status: status
        });
        
        totalConsumed += consumed;
        totalGoal += goal;
    }
    
    return {
        days: weekData,
        totalConsumed: totalConsumed,
        totalGoal: totalGoal,
        averageConsumed: totalConsumed / 7,
        weeklyPercentage: totalGoal > 0 ? (totalConsumed / totalGoal) * 100 : 0
    };
}

// Função para atualizar resumo semanal
function updateWeeklySummary(data) {
    document.getElementById('weeklyGoal').textContent = `${data.totalGoal} kcal`;
    document.getElementById('weeklyConsumed').textContent = `${data.totalConsumed} kcal`;
    document.getElementById('weeklyDiff').textContent = `${data.totalConsumed - data.totalGoal} kcal`;
    
    document.getElementById('totalConsumed').textContent = `${data.totalConsumed} kcal`;
    document.getElementById('dailyAverage').textContent = `${Math.round(data.averageConsumed)} kcal`;
    document.getElementById('weeklyPercentage').textContent = `${Math.round(data.weeklyPercentage)}%`;
}

// Função para atualizar tabela semanal
function updateWeeklyTable(data) {
    const tbody = document.getElementById('weeklyTableBody');
    tbody.innerHTML = '';
    
    data.days.forEach(day => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${day.dayName}</td>
            <td>${day.date.toLocaleDateString('pt-BR')}</td>
            <td>${day.goal} kcal</td>
            <td>${day.consumed} kcal</td>
            <td class="${day.difference >= 0 ? 'positive' : 'negative'}">${day.difference >= 0 ? '+' : ''}${day.difference} kcal</td>
            <td>${Math.round(day.percentage)}%</td>
            <td><span class="status-badge ${day.status}">${day.status}</span></td>
        `;
        tbody.appendChild(row);
    });
}

// Função para atualizar gráfico semanal
function updateWeeklyChart(data) {
    const ctx = document.getElementById('weeklyChart');
    if (!ctx) return;
    
    if (weeklyChart) {
        weeklyChart.destroy();
    }
    
    const labels = data.days.map(day => day.dayName);
    const consumedData = data.days.map(day => day.consumed);
    const goalData = data.days.map(day => day.goal);
    
    weeklyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Meta Diária',
                    data: goalData,
                    backgroundColor: 'rgba(255, 107, 0, 0.3)',
                    borderColor: '#ff6b00',
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false,
                },
                {
                    label: 'Consumido',
                    data: consumedData,
                    backgroundColor: 'rgba(76, 175, 80, 0.3)',
                    borderColor: '#4caf50',
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#b0b0b0'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#b0b0b0'
                    }
                }
            }
        }
    });
}

// Inicializar rastreio semanal quando a aba for ativada
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar listener para mudança de abas
    document.querySelectorAll('.tab-link').forEach(tab => {
        tab.addEventListener('click', function() {
            if (this.dataset.tab === 'weekly-tracking') {
                setTimeout(() => {
                    updateWeeklyDisplay();
                }, 100);
            } else if (this.dataset.tab === 'feedback-analysis') {
                setTimeout(() => {
                    updateFeedbackAnalysis();
                }, 100);
            } else if (this.dataset.tab === 'routine') {
                setTimeout(() => {
                    updateRoutineData();
                }, 100);
            }
        });
    });
});

// --- FUNCIONALIDADES DA ABA ROTINA ---

// Dados simulados para rotinas (em produção, viriam do banco)
const routineData = {
    today: { exercise: 1, nutrition: 1, hydration: 0, sleep: 1 },
    week: [
        { date: '2024-10-01', exercise: 1, nutrition: 1, hydration: 1, sleep: 0 },
        { date: '2024-09-30', exercise: 0, nutrition: 1, hydration: 1, sleep: 1 },
        { date: '2024-09-29', exercise: 1, nutrition: 1, hydration: 0, sleep: 1 },
        { date: '2024-09-28', exercise: 1, nutrition: 0, hydration: 1, sleep: 1 },
        { date: '2024-09-27', exercise: 0, nutrition: 1, hydration: 1, sleep: 0 },
        { date: '2024-09-26', exercise: 1, nutrition: 1, hydration: 1, sleep: 1 },
        { date: '2024-09-25', exercise: 1, nutrition: 0, hydration: 0, sleep: 1 }
    ]
};

// Função para atualizar dados da rotina
function updateRoutineData() {
    // Esta função é chamada pelo código existente, mas não é necessária para a nova implementação
    // Mantida para compatibilidade
    console.log('updateRoutineData chamada');
    
    // Verificar se os elementos existem antes de tentar acessá-los
    const todayRoutines = document.getElementById('todayRoutines');
    const weekRoutines = document.getElementById('weekRoutines');
    const adherenceRate = document.getElementById('adherenceRate');
    
    if (todayRoutines) todayRoutines.textContent = '0/0';
    if (weekRoutines) weekRoutines.textContent = '0/0';
    if (adherenceRate) adherenceRate.textContent = '0%';
    
    // Atualizar tabela
    updateRoutineTable();
}

// Função para atualizar gráfico de rotinas
function updateRoutineChart() {
    const ctx = document.getElementById('routineChart');
    if (!ctx) return;
    
    const labels = routineData.week.map(day => 
        new Date(day.date).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
    );
    
    const exerciseData = routineData.week.map(day => day.exercise);
    const nutritionData = routineData.week.map(day => day.nutrition);
    const hydrationData = routineData.week.map(day => day.hydration);
    const sleepData = routineData.week.map(day => day.sleep);
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Exercício',
                    data: exerciseData,
                    backgroundColor: 'rgba(76, 175, 80, 0.8)',
                    borderColor: 'rgba(76, 175, 80, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Alimentação',
                    data: nutritionData,
                    backgroundColor: 'rgba(255, 152, 0, 0.8)',
                    borderColor: 'rgba(255, 152, 0, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Hidratação',
                    data: hydrationData,
                    backgroundColor: 'rgba(33, 150, 243, 0.8)',
                    borderColor: 'rgba(33, 150, 243, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Sono',
                    data: sleepData,
                    backgroundColor: 'rgba(156, 39, 176, 0.8)',
                    borderColor: 'rgba(156, 39, 176, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: '#b0b0b0'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 1,
                    ticks: {
                        stepSize: 1,
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            }
        }
    });
}

// Função para atualizar tabela de rotinas
function updateRoutineTable() {
    const tbody = document.getElementById('routineTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = routineData.week.map(day => {
        const total = Object.values(day).slice(1).reduce((sum, val) => sum + val, 0);
        const date = new Date(day.date).toLocaleDateString('pt-BR');
        
        return `
            <tr>
                <td>${date}</td>
                <td><i class="fas ${day.exercise ? 'fa-check text-success' : 'fa-times text-danger'}"></i></td>
                <td><i class="fas ${day.nutrition ? 'fa-check text-success' : 'fa-times text-danger'}"></i></td>
                <td><i class="fas ${day.hydration ? 'fa-check text-success' : 'fa-times text-danger'}"></i></td>
                <td><i class="fas ${day.sleep ? 'fa-check text-success' : 'fa-times text-danger'}"></i></td>
                <td><span class="badge ${total >= 3 ? 'badge-success' : total >= 2 ? 'badge-warning' : 'badge-danger'}">${total}/4</span></td>
            </tr>
        `;
    }).join('');
}

// --- FUNCIONALIDADES DA ANÁLISE DE FEEDBACK ---

let currentAnalysisPeriod = 7;
let adherenceChart = null;
let satisfactionChart = null;
let routineCategoryChart = null;

// Dados simulados para análise de feedback (em produção, viriam do banco)
const feedbackData = {
    checkins: [
        { date: '2024-10-01', satisfaction: 4.5, notes: 'Dia produtivo' },
        { date: '2024-09-30', satisfaction: 3.8, notes: 'Cansado' },
        { date: '2024-09-29', satisfaction: 4.2, notes: 'Bom progresso' },
        { date: '2024-09-28', satisfaction: 3.5, notes: 'Dificuldades' },
        { date: '2024-09-27', satisfaction: 4.0, notes: 'Estável' },
        { date: '2024-09-26', satisfaction: 4.3, notes: 'Motivado' },
        { date: '2024-09-25', satisfaction: 3.9, notes: 'Regular' }
    ],
    routines: [
        { date: '2024-10-01', completed: 3, total: 4, categories: { exercise: 1, nutrition: 1, hydration: 1, sleep: 0 } },
        { date: '2024-09-30', completed: 2, total: 4, categories: { exercise: 0, nutrition: 1, hydration: 1, sleep: 0 } },
        { date: '2024-09-29', completed: 4, total: 4, categories: { exercise: 1, nutrition: 1, hydration: 1, sleep: 1 } },
        { date: '2024-09-28', completed: 1, total: 4, categories: { exercise: 0, nutrition: 1, hydration: 0, sleep: 0 } },
        { date: '2024-09-27', completed: 3, total: 4, categories: { exercise: 1, nutrition: 1, hydration: 1, sleep: 0 } },
        { date: '2024-09-26', completed: 4, total: 4, categories: { exercise: 1, nutrition: 1, hydration: 1, sleep: 1 } },
        { date: '2024-09-25', completed: 2, total: 4, categories: { exercise: 0, nutrition: 1, hydration: 1, sleep: 0 } }
    ]
};

// Função para atualizar análise de feedback
function updateFeedbackAnalysis() {
    // Adicionar listeners para filtros
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentAnalysisPeriod = parseInt(this.dataset.period);
            updateFeedbackCharts();
        });
    });
    
    updateFeedbackCharts();
}

// Função para atualizar gráficos de feedback
function updateFeedbackCharts() {
    updateAdherenceChart();
    updateSatisfactionChart();
    updateRoutineCategoryChart();
}

// Função para atualizar gráfico de aderência
function updateAdherenceChart() {
    const ctx = document.getElementById('adherenceChart');
    if (!ctx) return;
    
    if (adherenceChart) {
        adherenceChart.destroy();
    }
    
    const filteredData = feedbackData.routines.slice(0, currentAnalysisPeriod);
    const labels = filteredData.map(day => 
        new Date(day.date).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
    );
    
    const adherenceData = filteredData.map(day => (day.completed / day.total) * 100);
    
    adherenceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Aderência (%)',
                data: adherenceData,
                borderColor: 'rgba(76, 175, 80, 1)',
                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            }
        }
    });
}

// Função para atualizar gráfico de satisfação
function updateSatisfactionChart() {
    const ctx = document.getElementById('satisfactionChart');
    if (!ctx) return;
    
    if (satisfactionChart) {
        satisfactionChart.destroy();
    }
    
    const filteredData = feedbackData.checkins.slice(0, currentAnalysisPeriod);
    const labels = filteredData.map(day => 
        new Date(day.date).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
    );
    
    const satisfactionData = filteredData.map(day => day.satisfaction);
    
    satisfactionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Satisfação',
                data: satisfactionData,
                borderColor: 'rgba(255, 152, 0, 1)',
                backgroundColor: 'rgba(255, 152, 0, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 5,
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: '#b0b0b0'
                    },
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    }
                }
            }
        }
    });
}

// Função para atualizar gráfico de categorias de rotina
function updateRoutineCategoryChart() {
    const ctx = document.getElementById('routineCategoryChart');
    if (!ctx) return;
    
    if (routineCategoryChart) {
        routineCategoryChart.destroy();
    }
    
    const filteredData = feedbackData.routines.slice(0, currentAnalysisPeriod);
    
    // Calcular totais por categoria
    const categories = {
        exercise: 0,
        nutrition: 0,
        hydration: 0,
        sleep: 0
    };
    
    filteredData.forEach(day => {
        Object.keys(categories).forEach(category => {
            categories[category] += day.categories[category];
        });
    });
    
    const labels = ['Exercício', 'Alimentação', 'Hidratação', 'Sono'];
    const data = Object.values(categories);
    const colors = [
        'rgba(76, 175, 80, 0.8)',
        'rgba(255, 152, 0, 0.8)',
        'rgba(33, 150, 243, 0.8)',
        'rgba(156, 39, 176, 0.8)'
    ];
    
    routineCategoryChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderColor: colors.map(color => color.replace('0.8', '1')),
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#b0b0b0',
                        padding: 20
                    }
                }
            }
        }
    });
}

// --- FUNCIONALIDADES DE MISSÕES ---

// Função para carregar lista de missões
function loadMissionsAdminList() {
    fetch(`api/routine_crud.php?action=list_missions&patient_id=${routineUserId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderMissionsGrid(data.data);
            } else {
                showEmptyMissions('Erro ao carregar missões: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            showEmptyMissions('Erro ao carregar missões');
        });
}

// Função para renderizar grid de missões
function renderMissionsGrid(missions) {
    const container = document.getElementById('missions-container');
    
    // Filtrar missão de sono (não é editável pelo admin)
    const editableMissions = missions.filter(mission => {
        const titleLower = (mission.title || '').toLowerCase();
        const exerciseType = mission.exercise_type || '';
        return !(titleLower.includes('sono') || exerciseType === 'sleep');
    });
    
    if (editableMissions.length === 0) {
        showEmptyMissions('Nenhuma missão editável encontrada');
        return;
    }
    
    const missionsHtml = editableMissions.map(mission => {
        const isExercise = mission.is_exercise == 1 || mission.is_exercise === '1' || mission.is_exercise === true;
        const isPersonal = mission.is_personal == 1 || mission.is_personal === '1' || mission.is_personal === true;
        const isDynamic = String(mission.id).startsWith('onboarding_');
        
        return `
        <div class="mission-card" data-id="${mission.id}" data-is-personal="${isPersonal ? 1 : 0}">
            <div class="mission-header">
                <div class="mission-icon">
                    <i class="fas ${mission.icon_class}"></i>
                </div>
                <div class="mission-actions">
                    ${!isDynamic ? `
                    <button class="btn-edit" onclick="editMission('${mission.id}', ${isPersonal ? 1 : 0})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-delete" onclick="deleteMission(${mission.id}, '${mission.title}', ${isPersonal ? 1 : 0})" title="Excluir">
                        <i class="fas fa-trash"></i>
                    </button>
                    ` : ''}
                </div>
            </div>
            <div class="mission-content">
                <h4>${mission.title}</h4>
                ${mission.description ? `<p class="mission-description">${mission.description}</p>` : ''}
                ${isDynamic ? '<p class="mission-description" style="color: var(--text-secondary, #888); font-size: 0.85rem;">Definido no perfil do usuário</p>' : ''}
            </div>
        </div>
        `;
    }).join('');
    
    container.innerHTML = missionsHtml;
}

// Função para mostrar estado vazio
function showEmptyMissions(message) {
    const container = document.getElementById('missions-container');
    container.innerHTML = `
        <div class="empty-missions">
            <i class="fas fa-tasks"></i>
            <p>${message}</p>
        </div>
    `;
}

// Função para inicializar seletor de ícones
function initIconPicker(selectedIconClass = null) {
    const iconPicker = document.getElementById('iconPicker');
    if (!iconPicker) return;
    
    const icons = [
        'fa-dumbbell', 'fa-running', 'fa-bicycle', 'fa-swimmer',
        'fa-heart', 'fa-apple-alt', 'fa-carrot', 'fa-fish',
        'fa-tint', 'fa-coffee', 'fa-bed', 'fa-moon',
        'fa-sun', 'fa-clock', 'fa-book', 'fa-pen',
        'fa-pencil-alt', 'fa-graduation-cap', 'fa-spa', 'fa-leaf',
        'fa-fire', 'fa-bolt', 'fa-star', 'fa-trophy',
        'fa-water', 'fa-utensils', 'fa-walking', 'fa-calendar-check',
        'fa-weight', 'fa-smile', 'fa-check-circle'
    ];
    
    iconPicker.innerHTML = icons.map(icon => `
        <div class="icon-option" data-icon="${icon}">
            <i class="fas ${icon}"></i>
        </div>
    `).join('');
    
    // Adicionar listeners
    iconPicker.querySelectorAll('.icon-option').forEach(option => {
        option.addEventListener('click', function() {
            iconPicker.querySelectorAll('.icon-option').forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            
            // Atualizar campo hidden corretamente
            const selectedIconField = document.getElementById('selectedIcon');
            if (selectedIconField) {
                selectedIconField.value = this.dataset.icon;
                console.log('[initIconPicker] Ícone selecionado:', this.dataset.icon);
            }
        });
    });
    
    // Selecionar ícone se fornecido (sem triggerar click para evitar piscar)
    if (selectedIconClass) {
        const iconOption = iconPicker.querySelector(`.icon-option[data-icon="${selectedIconClass}"]`);
        if (iconOption) {
            // Remover seleção anterior
            iconPicker.querySelectorAll('.icon-option').forEach(opt => opt.classList.remove('selected'));
            // Selecionar visualmente sem triggerar click
            iconOption.classList.add('selected');
            // Atualizar campo hidden diretamente
            const selectedIconField = document.getElementById('selectedIcon');
            if (selectedIconField) {
                selectedIconField.value = selectedIconClass;
            }
        } else {
            // Se não encontrar, usar o primeiro ícone como padrão
            const firstOption = iconPicker.querySelector('.icon-option');
            if (firstOption) {
                firstOption.classList.add('selected');
                const selectedIconField = document.getElementById('selectedIcon');
                if (selectedIconField) {
                    selectedIconField.value = firstOption.dataset.icon;
                }
            }
        }
    }
}

// Funções globais para o modal
window.openMissionModal = function(missionId = null, skipReset = false) {
    console.log('[openMissionModal] Chamado com missionId:', missionId, 'skipReset:', skipReset);
    
    const modal = document.getElementById('missionModal');
    console.log('[openMissionModal] Modal encontrado:', modal);
    
    if (!modal) {
        console.error('[openMissionModal] Modal não encontrado!');
        return;
    }
    
    const form = document.getElementById('missionForm');
    const title = document.getElementById('missionModalTitle');
    const saveButton = document.getElementById('saveButtonText');
    
    console.log('[openMissionModal] Form encontrado:', form, 'Title:', title, 'SaveButton:', saveButton);
    
    // Resetar formulário apenas se não for edição
    if (!skipReset) {
        if (form) {
            form.reset();
            const missionIdField = document.getElementById('missionId');
            const selectedIconField = document.getElementById('selectedIcon');
            if (missionIdField) missionIdField.value = '';
            if (selectedIconField) selectedIconField.value = '';
        }
        // Para nova missão, sempre personalizada (isPersonal = 1)
        sessionStorage.setItem('current_mission_is_personal', '1');
    }
    
    if (missionId) {
        if (title) title.textContent = 'Editar Missão';
        if (saveButton) saveButton.textContent = 'Atualizar Missão';
    } else {
        if (title) title.textContent = 'Adicionar Nova Missão';
        if (saveButton) saveButton.textContent = 'Salvar Missão';
    }
    
    // Adicionar classe active primeiro para evitar flicker
    modal.classList.add('active');
    // Usar requestAnimationFrame para garantir transição suave
    requestAnimationFrame(() => {
        modal.style.display = 'flex';
        // Inicializar seletor de ícones SEM delay para evitar piscar
        // O ícone já será selecionado pela função editMission se necessário
        const selectedIconField = document.getElementById('selectedIcon');
        const iconToSelect = selectedIconField ? selectedIconField.value : null;
        initIconPicker(iconToSelect || null);
    });
};

window.closeMissionModal = function() {
    const modal = document.getElementById('missionModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
    }
};

window.editMission = function(missionId, isPersonal = 0) {
    console.log('editMission chamado com ID:', missionId, 'isPersonal:', isPersonal);
    
    // Salvar isPersonal no sessionStorage
    sessionStorage.setItem('current_mission_is_personal', isPersonal);
    
    // Buscar dados da missão
    fetch(`api/routine_crud.php?action=get_mission&id=${encodeURIComponent(missionId)}&patient_id=${routineUserId}&is_personal=${isPersonal}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                const mission = data.data;
                console.log('Dados da missão carregados:', mission);
                
                // Verificar se é uma missão de sono (não pode ser editada)
                const titleLower = (mission.title || '').toLowerCase();
                const exerciseType = mission.exercise_type || '';
                if (titleLower.includes('sono') || exerciseType === 'sleep') {
                    alert('A missão de sono não pode ser editada. Ela é gerenciada automaticamente pelo sistema.');
                    return;
                }
                
                // Preencher formulário
                const missionIdField = document.getElementById('missionId');
                const missionTitleField = document.getElementById('missionTitle');
                const missionDescriptionField = document.getElementById('missionDescription');
                const selectedIconField = document.getElementById('selectedIcon');
                
                if (missionIdField) missionIdField.value = mission.id;
                if (missionTitleField) missionTitleField.value = mission.title || '';
                if (missionDescriptionField) missionDescriptionField.value = mission.description || '';
                
                // Definir ícone selecionado ANTES de abrir o modal
                if (selectedIconField && mission.icon_class) {
                    selectedIconField.value = mission.icon_class;
                    console.log('[editMission] Ícone definido no campo hidden:', mission.icon_class);
                }
                
                // Mapear tipo de missão do backend para o radio button
                let missionType = 'yes_no';
                
                // Se é exercício, verificar o tipo
                if (mission.is_exercise == 1) {
                    if (mission.exercise_type === 'duration') {
                        missionType = 'duration';
                    } else {
                        missionType = 'duration'; // fallback
                    }
                }
                
                // Marcar o radio button correto
                const radioButton = document.querySelector(`input[name="mission_type"][value="${missionType}"]`);
                if (radioButton) {
                    radioButton.checked = true;
                }
                
                // Abrir modal (não resetar formulário pois já preenchemos os dados)
                // O ícone já está definido no campo hidden, então o initIconPicker no openMissionModal vai selecioná-lo
                openMissionModal(missionId, true);
            } else {
                console.error('Erro ao carregar missão:', data.message);
                alert('Erro ao carregar dados da missão');
            }
        })
        .catch(error => {
            console.error('Erro ao buscar missão:', error);
            alert('Erro ao carregar dados da missão');
        });
};

window.deleteMission = function(missionId, missionName, isPersonal = 0) {
    if (confirm(`Tem certeza que deseja excluir a missão "${missionName}"?`)) {
        fetch('api/routine_crud.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete_mission',
                id: missionId,
                patient_id: routineUserId,
                is_personal: isPersonal
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadMissionsAdminList();
            } else {
                alert('Erro ao excluir missão: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao excluir missão');
        });
    }
};

// Listener para o formulário
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('missionForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            const missionId = formData.get('mission_id');
            const action = missionId ? 'update_mission' : 'create_mission';
            
            // Verificar se é missão personalizada ou padrão
            const isPersonal = sessionStorage.getItem('current_mission_is_personal') == '1';
            console.log('[SUBMIT] isPersonal:', isPersonal);
            console.log('[SUBMIT] missionId:', missionId);
            
            // Mapear tipo de missão para campos do backend
            const missionType = formData.get('mission_type');
            let is_exercise = 0;
            let exercise_type = '';
            
            if (missionType === 'duration') {
                is_exercise = 1;
                exercise_type = 'duration';
            } else if (missionType === 'sleep') {
                is_exercise = 1;
                exercise_type = 'sleep';
            } else {
                // yes_no
                is_exercise = 0;
                exercise_type = '';
            }
            
            // Garantir que o icon_class seja obtido corretamente
            let iconClass = formData.get('icon_class');
            if (!iconClass || iconClass.trim() === '') {
                // Se não tiver, tentar pegar do campo hidden
                const selectedIconField = document.getElementById('selectedIcon');
                if (selectedIconField && selectedIconField.value) {
                    iconClass = selectedIconField.value;
                } else {
                    iconClass = 'fa-check-circle'; // Fallback
                }
            }
            
            console.log('[SUBMIT] Icon class obtido:', iconClass);
            console.log('[SUBMIT] isPersonal:', isPersonal);
            console.log('[SUBMIT] is_exercise:', is_exercise);
            console.log('[SUBMIT] missionId:', missionId);
            
            // Criar objeto com dados
            const data = {
                action: action,
                title: formData.get('title'),
                description: formData.get('description'),
                icon_class: iconClass,
                is_exercise: is_exercise,
                exercise_type: exercise_type,
                patient_id: routineUserId,
                is_personal: isPersonal ? 1 : 0
            };
            
            if (missionId) {
                // Preservar IDs string (como onboarding_NomeExercicio)
                data.id = isNaN(missionId) ? missionId : parseInt(missionId);
            }
            
            fetch('api/routine_crud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                console.log('[FETCH] Response status:', response.status);
                console.log('[FETCH] Response ok:', response.ok);
                const contentType = response.headers.get('content-type');
                console.log('[FETCH] Content-Type:', contentType);
                
                return response.text().then(text => {
                    console.log('[FETCH] Raw response:', text);
                    console.log('[FETCH] Response length:', text.length);
                    
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('Resposta do servidor não é JSON: ' + text.substring(0, 200));
                    }
                    
                    if (!text || text.trim() === '') {
                        throw new Error('Resposta vazia do servidor');
                    }
                    
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('[FETCH] JSON parse error:', e);
                        throw new Error('Resposta inválida do servidor: ' + text.substring(0, 200));
                    }
                });
            })
            .then(data => {
                console.log('[FETCH] Success data:', data);
                if (data.success) {
                    closeMissionModal();
                    loadMissionsAdminList();
                } else {
                    alert('Erro ao salvar missão: ' + data.message);
                }
            })
            .catch(error => {
                console.error('[FETCH] Erro completo:', error);
                console.error('[FETCH] Stack:', error.stack);
                alert('Erro ao salvar missão: ' + error.message);
            });
        });
    }
    
    // Carregar missões quando a aba for ativada
    loadMissionsAdminList();
});

// ============ CALENDÁRIO DA ROTINA ============
let currentRoutineCalendarDate = new Date();
let routineDaysWithData = new Set();

// Buscar dados de dias com registros via PHP
<?php
// Buscar todos os dias que têm missões concluídas
$stmt_all_dates = $conn->prepare("
    SELECT DISTINCT DATE(date) as date 
    FROM sf_user_routine_log 
    WHERE user_id = ? AND is_completed = 1
    ORDER BY date DESC
");
$stmt_all_dates->bind_param("i", $user_id);
$stmt_all_dates->execute();
$all_dates_result = $stmt_all_dates->get_result();
$all_routine_dates_with_data = [];
while ($row = $all_dates_result->fetch_assoc()) {
    $all_routine_dates_with_data[] = $row['date'];
}
$stmt_all_dates->close();
echo "const allRoutineDatesWithData = " . json_encode($all_routine_dates_with_data) . ";\n";
?>
allRoutineDatesWithData.forEach(date => routineDaysWithData.add(date));

function openRoutineCalendar() {
    currentRoutineCalendarDate = new Date();
    renderRoutineCalendar();
    document.body.style.overflow = 'hidden';
    document.getElementById('routineCalendarModal').classList.add('active');
}

function closeRoutineCalendar() {
    document.getElementById('routineCalendarModal').classList.remove('active');
    document.body.style.overflow = '';
}

function changeRoutineCalendarMonth(direction) {
    const newDate = new Date(currentRoutineCalendarDate);
    newDate.setMonth(newDate.getMonth() + direction);
    
    const now = new Date();
    if (newDate.getFullYear() > now.getFullYear() || 
        (newDate.getFullYear() === now.getFullYear() && newDate.getMonth() > now.getMonth())) {
        return;
    }
    
    currentRoutineCalendarDate = newDate;
    renderRoutineCalendar();
}

function renderRoutineCalendar() {
    const year = currentRoutineCalendarDate.getFullYear();
    const month = currentRoutineCalendarDate.getMonth();
    
    document.getElementById('routineCalendarYear').textContent = year;
    document.getElementById('routineCalendarMonth').textContent = routineMonthNamesShort[month];
    
    const nextBtn = document.getElementById('routineNextMonthBtn');
    const now = new Date();
    if (year === now.getFullYear() && month === now.getMonth()) {
        nextBtn.style.opacity = '0.5';
        nextBtn.disabled = true;
    } else {
        nextBtn.style.opacity = '1';
        nextBtn.disabled = false;
    }
    
    const grid = document.getElementById('routineCalendarDaysGrid');
    grid.innerHTML = '';
    
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const prevMonth = new Date(year, month, 0);
    const daysInPrevMonth = prevMonth.getDate();
    const startDay = firstDay.getDay();
    
    for (let i = startDay - 1; i >= 0; i--) {
        const dayEl = document.createElement('div');
        dayEl.className = 'calendar-day other-month';
        dayEl.textContent = daysInPrevMonth - i;
        grid.appendChild(dayEl);
    }
    
    for (let day = 1; day <= lastDay.getDate(); day++) {
        const dayEl = document.createElement('div');
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        
        dayEl.className = 'calendar-day';
        dayEl.textContent = day;
        dayEl.setAttribute('data-date', dateStr);
        
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const targetDate = new Date(dateStr + 'T00:00:00');
        
        // Bloquear dias futuros
        if (targetDate > today) {
            dayEl.classList.add('calendar-day-disabled');
            dayEl.style.opacity = '0.3';
            dayEl.style.pointerEvents = 'none';
            dayEl.style.cursor = 'not-allowed';
        } else {
            if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
                dayEl.classList.add('today');
            }
            
            if (routineDaysWithData.has(dateStr)) {
                dayEl.classList.add('has-data');
            }
            
            dayEl.addEventListener('click', () => goToRoutineDate(dateStr));
        }
        
        grid.appendChild(dayEl);
    }
    
    const totalCells = grid.children.length;
    const remainingCells = 42 - totalCells;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (remainingCells > 0) {
        for (let day = 1; day <= remainingCells; day++) {
            const dayEl = document.createElement('div');
            dayEl.className = 'calendar-day other-month';
            dayEl.textContent = day;
            
            // Verificar se é do próximo mês e se é futuro
            if (year === today.getFullYear() && month === today.getMonth()) {
                dayEl.style.opacity = '0.3';
                dayEl.style.pointerEvents = 'none';
                dayEl.style.cursor = 'not-allowed';
            }
            
            grid.appendChild(dayEl);
        }
    }
}

function goToRoutineDate(dateStr) {
    closeRoutineCalendar();
    
    const targetDate = new Date(dateStr + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (targetDate > today) {
        console.log('[calendar] BLOQUEADO: Tentativa de navegar para data futura');
        return;
    }
    
    const direction = targetDate > currentRoutineDate ? 1 : -1;
    currentRoutineDate = targetDate;
    loadRoutineForDate(currentRoutineDate, direction);
}

// Expor funções
window.openRoutineCalendar = openRoutineCalendar;
window.closeRoutineCalendar = closeRoutineCalendar;
window.changeRoutineCalendarMonth = changeRoutineCalendarMonth;

// ============ ACOMPANHAMENTO DE EXERCÍCIO E SONO ============
const userIdExerciseSleep = <?php echo $user_id; ?>;
let currentExerciseStartDate = null;
let currentExerciseEndDate = null;
let currentSleepStartDate = null;
let currentSleepEndDate = null;
const exerciseGoalDailyMinutes = <?php echo isset($exercise_goal_daily_minutes) ? $exercise_goal_daily_minutes : 0; ?>;
const sleepGoalHours = <?php echo isset($sleep_goal_hours) ? $sleep_goal_hours : 7.5; ?>;

// Carregar últimos 7 dias por padrão
async function loadLast7DaysExercise() {
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 6);
    
    const startStr = startDate.toISOString().split('T')[0];
    const endStr = endDate.toISOString().split('T')[0];
    
    await loadExerciseData(startStr, endStr, 'Últimos 7 dias');
}

async function loadLast7DaysSleep() {
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 6);
    
    const startStr = startDate.toISOString().split('T')[0];
    const endStr = endDate.toISOString().split('T')[0];
    
    await loadSleepData(startStr, endStr, 'Últimos 7 dias');
}

// Mostrar calendário de exercício
function showExerciseCalendar() {
    if (typeof window.openChartCalendar === 'function') {
        window.openChartCalendar('exercise');
    } else if (typeof openChartCalendar === 'function') {
        openChartCalendar('exercise');
    } else {
        console.error('openChartCalendar não está definida');
    }
}
window.showExerciseCalendar = showExerciseCalendar;

// Mostrar calendário de sono
function showSleepCalendar() {
    if (typeof window.openChartCalendar === 'function') {
        window.openChartCalendar('sleep');
    } else if (typeof openChartCalendar === 'function') {
        openChartCalendar('sleep');
    } else {
        console.error('openChartCalendar não está definida');
    }
}
window.showSleepCalendar = showSleepCalendar;

// Carregar dados de exercício por período
async function loadExerciseData(startDate, endDate, periodLabel) {
    const chartSection = document.getElementById('exercise-chart');
    if (!chartSection) return;
    
    const chartContainer = document.getElementById('exercise-bars');
    if (chartContainer) {
        chartContainer.innerHTML = `
            <div class="empty-chart">
                <div class="loading-spinner"></div>
                <p>Carregando dados...</p>
            </div>
        `;
    }
    
    try {
        const response = await fetch(`ajax_get_chart_data.php?user_id=${userIdExerciseSleep}&type=exercise&start_date=${startDate}&end_date=${endDate}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            renderExerciseChart(result.data);
            updateExercisePeriodButton(periodLabel);
            updateExerciseStats(result.data);
            
            currentExerciseStartDate = startDate;
            currentExerciseEndDate = endDate;
        } else {
            if (chartContainer) {
                chartContainer.innerHTML = `
                    <div class="empty-chart">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Erro ao carregar dados</p>
                    </div>
                `;
            }
        }
    } catch (error) {
        console.error('Erro ao carregar dados de exercício:', error);
        if (chartContainer) {
            chartContainer.innerHTML = `
                <div class="empty-chart">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Erro ao carregar dados</p>
                </div>
            `;
        }
    }
}

// Carregar dados de sono por período
async function loadSleepData(startDate, endDate, periodLabel) {
    const chartSection = document.getElementById('sleep-chart');
    if (!chartSection) return;
    
    const chartContainer = document.getElementById('sleep-bars');
    if (chartContainer) {
        chartContainer.innerHTML = `
            <div class="empty-chart">
                <div class="loading-spinner"></div>
                <p>Carregando dados...</p>
            </div>
        `;
    }
    
    try {
        const response = await fetch(`ajax_get_chart_data.php?user_id=${userIdExerciseSleep}&type=sleep&start_date=${startDate}&end_date=${endDate}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            renderSleepChart(result.data);
            updateSleepPeriodButton(periodLabel);
            updateSleepStats(result.data);
            
            currentSleepStartDate = startDate;
            currentSleepEndDate = endDate;
        } else {
            if (chartContainer) {
                chartContainer.innerHTML = `
                    <div class="empty-chart">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p>Erro ao carregar dados</p>
                    </div>
                `;
            }
        }
    } catch (error) {
        console.error('Erro ao carregar dados de sono:', error);
        if (chartContainer) {
            chartContainer.innerHTML = `
                <div class="empty-chart">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Erro ao carregar dados</p>
                </div>
            `;
        }
    }
}

// Renderizar gráfico de exercício (igual hidratação)
function renderExerciseChart(data) {
    const chartContainer = document.getElementById('exercise-bars');
    if (!chartContainer || !data || data.length === 0) {
        chartContainer.innerHTML = `
            <div class="empty-chart">
                <i class="fas fa-dumbbell"></i>
                <p>Nenhum registro encontrado</p>
            </div>
        `;
        return;
    }
    
    let chartHTML = '';
    data.forEach(day => {
        const minutes = parseFloat(day.minutes || 0);
        const percentage = day.percentage || 0;
        const limitedPercentage = Math.min(percentage, 150);
        let barHeight = 0;
        if (limitedPercentage === 0) {
            barHeight = 0;
        } else if (limitedPercentage >= 100) {
            barHeight = 160;
        } else {
            barHeight = (limitedPercentage / 100) * 160;
        }
        
        const status = day.status || 'empty';
        
        chartHTML += `
            <div class="improved-bar-container">
                <div class="improved-bar-wrapper">
                    <div class="improved-bar ${status}" style="height: ${barHeight}px"></div>
                    ${minutes > 0 ? `<div class="bar-percentage-text">${Math.round(limitedPercentage)}%</div>` : ''}
                    ${exerciseGoalDailyMinutes > 0 ? '<div class="improved-goal-line"></div>' : ''}
                </div>
                <div class="improved-bar-info">
                    <span class="improved-date">${new Date(day.date + 'T00:00:00').toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit'})}</span>
                    ${minutes > 0 ? `<span class="improved-ml">${minutes} min</span>` : ''}
                </div>
            </div>
        `;
    });
    
    chartContainer.innerHTML = chartHTML;
    
    // Adicionar classe 'many-bars' quando houver mais de 7 barras
    if (data.length > 7) {
        chartContainer.classList.add('many-bars');
    } else {
        chartContainer.classList.remove('many-bars');
    }
}

// Renderizar gráfico de sono (igual hidratação)
function renderSleepChart(data) {
    const chartContainer = document.getElementById('sleep-bars');
    if (!chartContainer || !data || data.length === 0) {
        chartContainer.innerHTML = `
            <div class="empty-chart">
                <i class="fas fa-bed"></i>
                <p>Nenhum registro encontrado</p>
            </div>
        `;
        return;
    }
    
    let chartHTML = '';
    data.forEach(day => {
        const hours = parseFloat(day.hours || 0);
        const percentage = day.percentage || 0;
        const limitedPercentage = Math.min(percentage, 120);
        let barHeight = 0;
        if (limitedPercentage === 0) {
            barHeight = 0;
        } else if (limitedPercentage >= 100) {
            barHeight = 160;
        } else {
            barHeight = (limitedPercentage / 100) * 160;
        }
        
        const status = day.status || 'empty';
        
        chartHTML += `
            <div class="improved-bar-container">
                <div class="improved-bar-wrapper">
                    <div class="improved-bar ${status}" style="height: ${barHeight}px"></div>
                    ${hours > 0 ? `<div class="bar-percentage-text">${Math.round(limitedPercentage)}%</div>` : ''}
                    <div class="improved-goal-line"></div>
                </div>
                <div class="improved-bar-info">
                    <span class="improved-date">${new Date(day.date + 'T00:00:00').toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit'})}</span>
                    ${hours > 0 ? `<span class="improved-ml">${hours.toFixed(1)}h</span>` : ''}
                </div>
            </div>
        `;
    });
    
    chartContainer.innerHTML = chartHTML;
    
    // Adicionar classe 'many-bars' quando houver mais de 7 barras
    if (data.length > 7) {
        chartContainer.classList.add('many-bars');
    } else {
        chartContainer.classList.remove('many-bars');
    }
}

// Atualizar estatísticas de exercício
function updateExerciseStats(data) {
    if (!data || data.length === 0) return;
    
    const totalMinutes = data.reduce((sum, item) => sum + (parseFloat(item.minutes || 0)), 0);
    const daysWithData = data.filter(item => parseFloat(item.minutes || 0) > 0).length;
    const totalDays = data.length;
    const avgDaily = totalDays > 0 ? Math.round((totalMinutes / totalDays) * 10) / 10 : 0;
    const goalReached = exerciseGoalDailyMinutes > 0 ? Math.round((avgDaily / exerciseGoalDailyMinutes) * 100) : 0;
    
    const avgDailyEl = document.getElementById('exerciseAvgDaily');
    const periodDescEl = document.getElementById('exercisePeriodDesc');
    const daysWithDataEl = document.getElementById('exerciseDaysWithData');
    const goalReachedEl = document.getElementById('exerciseGoalReached');
    
    if (avgDailyEl) avgDailyEl.textContent = avgDaily + ' min';
    if (periodDescEl) periodDescEl.textContent = totalDays + ' dias';
    if (daysWithDataEl) {
        daysWithDataEl.textContent = daysWithData + '/' + totalDays;
        daysWithDataEl.className = 'stat-value';
        if (daysWithData >= Math.ceil(totalDays * 0.4)) {
            daysWithDataEl.classList.add('text-success');
        } else if (daysWithData > 0) {
            daysWithDataEl.classList.add('text-warning');
        } else {
            daysWithDataEl.classList.add('text-danger');
        }
    }
    if (goalReachedEl) {
        goalReachedEl.textContent = exerciseGoalDailyMinutes > 0 ? goalReached + '% da meta' : 'Sem meta definida';
    }
}

// Atualizar estatísticas de sono
function updateSleepStats(data) {
    if (!data || data.length === 0) return;
    
    const totalHours = data.reduce((sum, item) => sum + (parseFloat(item.hours || 0)), 0);
    const daysWithData = data.filter(item => parseFloat(item.hours || 0) > 0).length;
    const totalDays = data.length;
    const avgDaily = totalDays > 0 ? Math.round((totalHours / totalDays) * 10) / 10 : 0;
    const goalReached = sleepGoalHours > 0 ? Math.round((avgDaily / sleepGoalHours) * 100) : 0;
    
    let status = 'poor';
    let statusText = 'Abaixo da meta';
    if (avgDaily >= 7 && avgDaily <= 8) {
        status = 'excellent';
        statusText = 'Ideal';
    } else if (avgDaily >= 6.5 && avgDaily < 7) {
        status = 'good';
        statusText = 'Bom';
    } else if (avgDaily >= 6 && avgDaily < 6.5) {
        status = 'fair';
        statusText = 'Regular';
    } else if (avgDaily >= 5 && avgDaily < 6) {
        status = 'poor';
        statusText = 'Abaixo da meta';
    } else if (avgDaily > 0) {
        status = 'critical';
        statusText = 'Crítico';
    }
    
    const avgDailyEl = document.getElementById('sleepAvgDaily');
    const periodDescEl = document.getElementById('sleepPeriodDesc');
    const daysWithDataEl = document.getElementById('sleepDaysWithData');
    const statusDescEl = document.getElementById('sleepStatusDesc');
    
    if (avgDailyEl) avgDailyEl.textContent = avgDaily + 'h';
    if (periodDescEl) periodDescEl.textContent = totalDays + ' dias';
    if (daysWithDataEl) {
        daysWithDataEl.textContent = daysWithData + '/' + totalDays;
        daysWithDataEl.className = 'stat-value status-' + status;
    }
    if (statusDescEl) statusDescEl.textContent = statusText + ' - ' + goalReached + '% da meta';
}

// Atualizar texto do botão de período
function updateExercisePeriodButton(label) {
    const btn = document.getElementById('exercise-period-btn');
    if (btn) {
        btn.innerHTML = `<i class="fas fa-calendar-alt"></i> ${label}`;
    }
}

function updateSleepPeriodButton(label) {
    const btn = document.getElementById('sleep-period-btn');
    if (btn) {
        btn.innerHTML = `<i class="fas fa-calendar-alt"></i> ${label}`;
    }
}

// Inicializar gráficos quando a aba for ativada
document.addEventListener('DOMContentLoaded', function() {
    // Adicionar listener para mudança de abas
    document.querySelectorAll('.tab-link').forEach(tab => {
        tab.addEventListener('click', function() {
            if (this.dataset.tab === 'routine') {
                setTimeout(() => {
                    loadLast7DaysExercise();
                    loadLast7DaysSleep();
                }, 100);
            }
        });
    });
    
    // Inicializar se já estiver na aba routine
    if (document.getElementById('tab-routine') && document.getElementById('tab-routine').classList.contains('active')) {
        setTimeout(() => {
            loadLast7DaysExercise();
            loadLast7DaysSleep();
        }, 300);
    }
});

// Expor funções
window.loadExerciseData = loadExerciseData;
window.loadSleepData = loadSleepData;
</script>

<style>
/* === Rotina: espaçamento entre cards === */
#tab-routine #routineContentWrapper {
  display: flex !important;
  flex-direction: column !important;
  gap: 12px !important;
  align-items: stretch;
  padding-top: 8px !important;
}

#tab-routine .routine-day-missions {
  display: flex !important;
  flex-direction: column !important;
  gap: 12px !important;
}

#tab-routine .diary-meal-card + .diary-meal-card {
  margin-top: 12px !important;
}

#tab-routine .diary-meal-card {
  margin-bottom: 0 !important;
  display: block !important;
}

/* === Modal Customizado === */
.custom-modal {
  position: fixed !important;
  top: 0 !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  display: none !important;
  align-items: center !important;
  justify-content: center !important;
  z-index: 9999 !important;
  background: rgba(0, 0, 0, 0.7) !important;
}

.custom-modal.active,
.custom-modal[style*="flex"] {
  display: flex !important;
}

.custom-modal-overlay {
  position: absolute !important;
  top: 0 !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  background: transparent !important;
  cursor: pointer !important;
}

#missionModal .diary-calendar-wrapper {
  position: relative !important;
  background: #1E1E1E !important;
  border-radius: 16px !important;
  padding: 1.5rem !important;
  max-width: 700px !important;
  width: 90% !important;
  max-height: 95vh !important;
  overflow-y: auto !important;
  z-index: 10000 !important;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5) !important;
}

/* === Modal Header === */
#missionModal .modal-header {
  margin-bottom: 1.25rem !important;
  padding-bottom: 1rem !important;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
}

#missionModal .modal-title {
  display: flex !important;
  align-items: center !important;
  gap: 0.75rem !important;
}

#missionModal .title-icon {
  width: 40px !important;
  height: 40px !important;
  border-radius: 10px !important;
  background: rgba(255, 107, 0, 0.1) !important;
  border: 1px solid rgba(255, 107, 0, 0.2) !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  flex-shrink: 0 !important;
}

#missionModal .title-icon i {
  font-size: 1.25rem !important;
  color: var(--accent-orange) !important;
}

#missionModal .title-content h3 {
  margin: 0 0 0.15rem 0 !important;
  font-size: 1.25rem !important;
  font-weight: 700 !important;
  color: var(--text-primary) !important;
}

#missionModal .title-content p {
  margin: 0 !important;
  font-size: 0.85rem !important;
  color: var(--text-secondary) !important;
}

/* === Calendar Close Button === */
.calendar-btn-close {
  position: absolute !important;
  top: 0.75rem !important;
  right: 0.75rem !important;
  width: 32px !important;
  height: 32px !important;
  border-radius: 50% !important;
  background: rgba(255, 255, 255, 0.05) !important;
  border: 1px solid rgba(255, 255, 255, 0.1) !important;
  color: var(--text-secondary) !important;
  cursor: pointer !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  transition: all 0.3s ease !important;
  z-index: 10 !important;
}

.calendar-btn-close:hover {
  background: rgba(244, 67, 54, 0.2) !important;
  border-color: rgba(244, 67, 54, 0.4) !important;
  color: #F44336 !important;
  transform: scale(1.1) !important;
}

/* === Modal Body === */
#missionModal .modal-body {
  padding: 0 !important;
}

/* === Form Groups === */
#missionModal .form-group {
  margin-bottom: 1rem !important;
}

#missionModal .form-group label {
  display: block !important;
  font-size: 0.875rem !important;
  font-weight: 600 !important;
  color: var(--text-primary) !important;
  margin-bottom: 0.5rem !important;
}

/* === Form Inputs === */
#missionModal input[type="text"],
#missionModal select {
  width: 100% !important;
  padding: 0.625rem 0.875rem !important;
  background: rgba(255, 255, 255, 0.05) !important;
  border: 1px solid rgba(255, 255, 255, 0.1) !important;
  border-radius: 8px !important;
  color: var(--text-primary) !important;
  font-size: 0.875rem !important;
  font-family: 'Poppins', sans-serif !important;
  transition: all 0.3s ease !important;
  box-sizing: border-box !important;
}

#missionModal input[type="text"]:focus,
#missionModal select:focus {
  outline: none !important;
  background: rgba(255, 255, 255, 0.08) !important;
  border-color: var(--accent-orange) !important;
  box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1) !important;
}

#missionModal input[type="text"]::placeholder {
  color: var(--text-secondary) !important;
  opacity: 0.7 !important;
}

/* === Mission Type Selector === */
#missionModal .mission-type-selector {
  display: grid !important;
  grid-template-columns: repeat(2, 1fr) !important;
  gap: 0.75rem !important;
  margin-top: 0 !important;
}

#missionModal .mission-type-option {
  position: relative !important;
  cursor: pointer !important;
}

#missionModal .mission-type-option input[type="radio"] {
  position: absolute !important;
  opacity: 0 !important;
  pointer-events: none !important;
}

#missionModal .mission-type-option .option-content {
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 0.5rem !important;
  padding: 1rem !important;
  background: rgba(255, 255, 255, 0.05) !important;
  border: 2px solid rgba(255, 255, 255, 0.1) !important;
  border-radius: 12px !important;
  transition: all 0.3s ease !important;
  text-align: center !important;
}

#missionModal .mission-type-option .option-content i {
  font-size: 1.5rem !important;
  color: var(--text-secondary) !important;
  transition: color 0.3s ease !important;
}

#missionModal .mission-type-option .option-content span {
  font-size: 0.875rem !important;
  font-weight: 600 !important;
  color: var(--text-secondary) !important;
  transition: color 0.3s ease !important;
}

#missionModal .mission-type-option:hover .option-content {
  background: rgba(255, 255, 255, 0.08) !important;
  border-color: rgba(255, 107, 0, 0.3) !important;
  transform: translateY(-2px) !important;
}

#missionModal .mission-type-option input[type="radio"]:checked + .option-content {
  background: rgba(255, 107, 0, 0.15) !important;
  border-color: var(--accent-orange) !important;
}

#missionModal .mission-type-option input[type="radio"]:checked + .option-content i,
#missionModal .mission-type-option input[type="radio"]:checked + .option-content span {
  color: var(--accent-orange) !important;
}

/* === Icon Picker === */
#missionModal .icon-picker {
  display: grid !important;
  grid-template-columns: repeat(auto-fit, minmax(48px, 1fr)) !important;
  gap: 10px !important;
  justify-content: center !important;
  justify-items: center !important;
  align-items: center !important;
  padding: 8px 0 !important;
  margin-top: 0 !important;
}

#missionModal .icon-option {
  width: 48px !important;
  height: 48px !important;
  border-radius: 12px !important;
  border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1)) !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  background: var(--card-bg, rgba(255, 255, 255, 0.05)) !important;
  cursor: pointer !important;
  transition: all 0.2s ease !important;
}

#missionModal .icon-option:hover {
  transform: translateY(-1px) !important;
  border-color: var(--accent-orange, #FF6B00) !important;
}

#missionModal .icon-option.active,
#missionModal .icon-option[aria-checked="true"],
#missionModal .icon-option.selected {
  outline: 2px solid var(--accent-orange, #FF6B00) !important;
  outline-offset: -2px !important;
  background: rgba(255, 111, 0, 0.08) !important;
  border-color: var(--accent-orange, #FF6B00) !important;
}

#missionModal .icon-option i {
  font-size: 1.1rem !important;
  color: var(--text-primary, #ffffff) !important;
  transition: color 0.2s ease !important;
}

#missionModal .icon-option.active i,
#missionModal .icon-option[aria-checked="true"] i,
#missionModal .icon-option.selected i {
  color: var(--accent-orange, #FF6B00) !important;
}

/* === Form Actions === */
#missionModal .form-actions {
  display: flex !important;
  justify-content: flex-end !important;
  gap: 0.75rem !important;
  margin-top: 1.5rem !important;
  padding-top: 1.25rem !important;
  border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
}

#missionModal .btn-save {
  padding: 0.875rem 1.5rem !important;
  border-radius: 12px !important;
  font-size: 0.95rem !important;
  font-weight: 600 !important;
  cursor: pointer !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  gap: 0.5rem !important;
  transition: all 0.3s ease !important;
  border: none !important;
  font-family: 'Poppins', sans-serif !important;
  background: var(--accent-orange) !important;
  color: white !important;
}

#missionModal .btn-save:hover {
  background: #FF8C00 !important;
  transform: translateY(-2px) !important;
  box-shadow: 0 4px 12px rgba(255, 107, 0, 0.4) !important;
}

#missionModal .btn-save i {
  font-size: 1rem !important;
}
</style>

        <!-- Modal do Calendário da Rotina (idêntico ao da aba Diário) -->
        <div id="routineCalendarModal" class="custom-modal">
            <div class="custom-modal-overlay" onclick="closeRoutineCalendar()"></div>
            <div class="diary-calendar-wrapper">
                <button class="calendar-btn-close" onclick="closeRoutineCalendar()" type="button">
                    <i class="fas fa-times"></i>
                </button>
                
                <div class="calendar-header-title">
                    <div class="calendar-year" id="routineCalendarYear">2025</div>
                </div>
                
                <div class="calendar-nav-buttons">
                    <button class="calendar-btn-nav" onclick="changeRoutineCalendarMonth(-1)" type="button">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div class="calendar-month" id="routineCalendarMonth">OUT</div>
                    <button class="calendar-btn-nav" id="routineNextMonthBtn" onclick="changeRoutineCalendarMonth(1)" type="button">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                
                <div class="calendar-weekdays-row">
                    <span>DOM</span>
                    <span>SEG</span>
                    <span>TER</span>
                    <span>QUA</span>
                    <span>QUI</span>
                    <span>SEX</span>
                    <span>SÁB</span>
                </div>
                
                <div class="calendar-days-grid" id="routineCalendarDaysGrid"></div>
                
                <div class="calendar-separator">
                    <div class="separator-line"></div>
                    <div class="separator-dots">
                        <div class="dot"></div>
                        <div class="dot"></div>
                        <div class="dot"></div>
                    </div>
                    <div class="separator-line"></div>
                </div>
                
                <div class="calendar-footer-legend">
                    <div class="legend-row">
                        <span class="legend-marker today-marker"></span>
                        <span class="legend-text">Hoje</span>
                    </div>
                    <div class="legend-row">
                        <span class="legend-marker has-data-marker"></span>
                        <span class="legend-text">Com missões</span>
                    </div>
                    <div class="legend-row">
                        <span class="legend-marker no-data-marker"></span>
                        <span class="legend-text">Sem registros</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de Gerenciamento de Missões -->
        <div id="missionModal" class="custom-modal" style="display: none;">
            <div class="custom-modal-overlay" onclick="closeMissionModal()"></div>
            <div class="diary-calendar-wrapper">
                <button class="calendar-btn-close" onclick="closeMissionModal()" type="button">
                    <i class="fas fa-times"></i>
                </button>
                <div class="modal-header">
                    <div class="modal-title">
                        <div class="title-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="title-content">
                            <h3 id="missionModalTitle">Adicionar Nova Missão</h3>
                            <p>Configure uma missão personalizada para este paciente</p>
                        </div>
                    </div>
                </div>
                <div class="modal-body">
                    <form id="missionForm">
                        <input type="hidden" id="missionId" name="mission_id" value="">
                        <input type="hidden" id="selectedIcon" name="icon_class" value="">
                        <div class="form-group">
                            <label for="missionTitle">Título da Missão</label>
                            <input type="text" id="missionTitle" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="missionDescription">Descrição</label>
                            <input type="text" id="missionDescription" name="description">
                        </div>
                        <div class="form-group">
                            <label>Tipo de Missão</label>
                            <div class="mission-type-selector">
                                <label class="mission-type-option">
                                    <input type="radio" name="mission_type" value="yes_no" checked>
                                    <div class="option-content">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Sim/Não</span>
                                    </div>
                                </label>
                                <label class="mission-type-option">
                                    <input type="radio" name="mission_type" value="duration">
                                    <div class="option-content">
                                        <i class="fas fa-clock"></i>
                                        <span>Duração</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Ícone da Missão</label>
                            <div class="icon-picker" id="iconPicker">
                                <!-- Ícones serão inseridos via JavaScript -->
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-save">
                                <i class="fas fa-save"></i>
                                <span id="saveButtonText">Salvar Missão</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>