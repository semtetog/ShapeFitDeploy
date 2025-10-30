<!-- view_user_routine.php -->
<!-- Conteúdo completo da aba Rotina: HTML, CSS e JS -->

<div id="tab-routine" class="tab-content">
    <div class="routine-container">
        
        <!-- 1. CARD DE RESUMO DA ROTINA -->
        <div class="nutrients-summary-card">
            <div class="summary-main">
                <div class="summary-icon routine-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 11L12 14L22 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M21 12V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="summary-info">
                    <h3>Resumo da Rotina Semanal</h3>
                    <div class="summary-meta">Acompanhamento de missões, treinos e sono dos últimos 7 dias</div>
                    <div class="summary-description">Dados baseados nos registros diários do paciente no aplicativo</div>
                </div>
            </div>
            <div class="summary-stats">
                <div class="summary-stat">
                    <div class="stat-value" id="routine-missions-completed">0/0</div>
                    <div class="stat-label">Missões Concluídas</div>
                    <div class="stat-description">Última semana</div>
                </div>
                <div class="summary-stat">
                    <div class="stat-value" id="routine-sleep-avg"><?php echo number_format($avg_sleep_7 ?? 0, 1); ?>h</div>
                    <div class="stat-label">Sono Médio</div>
                    <div class="stat-description">Últimos 7 dias</div>
                </div>
                <div class="summary-stat">
                    <div class="stat-value" id="routine-workouts-days"><?php echo count(array_slice($routine_exercise_data, 0, 7)); ?></div>
                    <div class="stat-label">Dias com Treino</div>
                    <div class="stat-description">Última semana</div>
                </div>
            </div>
        </div>

        <!-- 2. CALENDÁRIO EXATAMENTE IGUAL AO DIÁRIO (MAS COM MISSÕES) -->
        <div class="diary-slider-container">
            <div class="diary-header-redesign">
                <!-- Ano no topo -->
                <div class="diary-year" id="routineYear">2025</div>
                
                <!-- Navegação e data principal -->
                <div class="diary-nav-row">
                    <button class="diary-nav-side diary-nav-left" onclick="navigateRoutine(-1)" type="button">
                        <i class="fas fa-chevron-left"></i>
                        <span id="routinePrevDate">26 out</span>
                    </button>
                    
                    <div class="diary-main-date">
                        <div class="diary-day-month" id="routineDayMonth">27 OUT</div>
                        <div class="diary-weekday" id="routineWeekday">SEGUNDA</div>
                    </div>
                    
                    <button class="diary-nav-side diary-nav-right" onclick="navigateRoutine(1)" type="button">
                        <span id="routineNextDate">28 out</span>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                
                <!-- Resumo de missões -->
                <div class="diary-summary-row">
                    <div class="diary-kcal" id="routineSummaryMissions">
                        <i class="fas fa-check-circle"></i>
                        <span>0 missões</span>
                    </div>
                    <div class="diary-macros" id="routineSummaryProgress">
                        Progresso: 0%
                    </div>
                </div>
                
                <!-- Botão de calendário -->
                <button class="diary-calendar-icon-btn" onclick="openRoutineCalendar()" type="button" title="Ver calendário">
                    <i class="fas fa-calendar-alt"></i>
                </button>
            </div>
            
            <div class="diary-slider-wrapper" id="routineSliderWrapper">
                <div class="diary-slider-track" id="routineSliderTrack">
                    <?php 
                    // Gerar array com TODOS os dias, mesmo se não houver dados
                    $all_dates = [];
                    for ($i = 0; $i < $daysToShow; $i++) {
                        $current_date = date('Y-m-d', strtotime($endDate . " -$i days"));
                        $all_dates[] = $current_date;
                    }
                    
                    // Inverter ordem: mais antigo à esquerda, mais recente à direita
                    $all_dates = array_reverse($all_dates);
                    
                    foreach ($all_dates as $date): 
                        // Buscar missões do dia usando a mesma lógica do routine.php
                        $day_missions = getRoutineItemsForUser($conn, $user_id, $date, $user_profile);
                        $completed_missions = array_filter($day_missions, function($mission) {
                            return $mission['completion_status'] == 1;
                        });
                        
                        // Formatar data por extenso
                        $timestamp = strtotime($date);
                        $day_of_week = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'][date('w', $timestamp)];
                        $day_number = date('d', $timestamp);
                        $month_name_abbr = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'][date('n', $timestamp) - 1];
                        $year = date('Y', $timestamp);
                    ?>
                    <div class="diary-day-card" data-date="<?php echo $date; ?>">
                        <!-- Dados escondidos para o JavaScript buscar -->
                        <div class="diary-day-summary" style="display: none;">
                            <div class="diary-summary-item">
                                <i class="fas fa-check-circle"></i>
                                <span><?php echo count($completed_missions); ?> missões</span>
                            </div>
                            <div class="diary-summary-macros">
                                <?php echo count($completed_missions); ?> concluídas
                            </div>
                        </div>
                        
                        <div class="diary-day-meals">
                            <?php if (empty($completed_missions)): ?>
                                <div class="diary-empty-state">
                                    <i class="fas fa-calendar-day"></i>
                                    <p>Nenhum registro neste dia</p>
                    </div>
                            <?php else: ?>
                                <?php foreach ($completed_missions as $mission): ?>
                                    <div class="diary-meal-card">
                                        <div class="diary-meal-header">
                                            <div class="diary-meal-icon">
                                                <i class="fas <?php echo htmlspecialchars($mission['icon_class']); ?>"></i>
                    </div>
                                            <div class="diary-meal-info">
                                                <h5><?php echo htmlspecialchars($mission['title']); ?></h5>
                                                <span class="diary-meal-totals">
                                                    <strong><?php echo isset($mission['duration_minutes']) && $mission['duration_minutes'] ? $mission['duration_minutes'] . 'min' : 'Concluída'; ?></strong>
                                                </span>
                </div>
                        </div>
                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                </div>
                    </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>

                    </div>
                    </div>


<!-- Modal do Calendário da Rotina (idêntico ao da aba Diário) -->
<div id="routineCalendarModal" class="custom-modal">
    <div class="custom-modal-overlay" onclick="closeRoutineCalendar()"></div>
    <div class="diary-calendar-wrapper">
        <button class="calendar-btn-close" onclick="closeRoutineCalendar()" type="button">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="calendar-header-title">
            <div class="calendar-year">2025</div>
                </div>

        <div class="calendar-nav-buttons">
            <button class="calendar-btn-nav" onclick="changeRoutineCalendarMonth(-1)" type="button">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="calendar-month">OUT</div>
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
                <span class="legend-text">Com registros</span>
            </div>
            <div class="legend-row">
                <span class="legend-marker no-data-marker"></span>
                <span class="legend-text">Sem registros</span>
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
                <button class="btn-add-mission" onclick="openMissionModal()">
                    <i class="fas fa-plus"></i>
                    <span>Adicionar Missão</span>
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
                <input type="hidden" id="missionId" name="mission_id">
                
                <div class="form-group">
                    <label for="missionName">Nome da Missão</label>
                    <input type="text" id="missionName" name="mission_name" placeholder="Ex: Beber 2L de água por dia" required>
                </div>
                
                <div class="form-group">
                    <label for="missionType">Tipo de Missão</label>
                    <select id="missionType" name="mission_type" required>
                        <option value="binary">Sim/Não (Binária)</option>
                        <option value="duration">Com Duração (Exercício)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Escolha um Ícone</label>
                    <div class="icon-picker" id="iconPicker">
                        <!-- Ícones serão carregados via JavaScript -->
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeMissionModal()">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i>
                        <span id="saveButtonText">Salvar Missão</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ======== ROTINA: JAVASCRIPT ESPECÍFICO ========

// Dados para JavaScript
const userViewData = {
    weightHistory: <?php echo json_encode($weight_chart_data); ?>,
    routineData: {
        steps: <?php echo json_encode($routine_steps_data); ?>,
        sleep: <?php echo json_encode($routine_sleep_data); ?>,
        exercise: <?php echo json_encode($routine_exercise_data); ?>
    }
};

// Variáveis globais da rotina
let routineCards = [];
let currentRoutineIndex = 0;
let routineTrack = null;

// Função para atualizar dados da rotina
function updateRoutineData(period = 7) {
    // Atualizar passos
    const stepsStats = calculateStepsStats(userViewData.routineData.steps, period);
    const stepsToday = userViewData.routineData.steps[0] ? parseInt(userViewData.routineData.steps[0].steps_daily) : 0;
    const stepsGoal = 10000;
    const stepsProgress = Math.min((stepsToday / stepsGoal) * 100, 100);
    
    // Atualizar sono
    const sleepStats = calculateSleepStats(userViewData.routineData.sleep, period);
    const sleepToday = userViewData.routineData.sleep[0] ? parseFloat(userViewData.routineData.sleep[0].sleep_hours) : 0;
    
    // Atualizar exercícios
    const exerciseStats = calculateExerciseStats(userViewData.routineData.exercise, period);
}

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

// Função para navegar na rotina
function navigateRoutine(direction) {
    if (!Array.isArray(routineCards) || routineCards.length === 0) return;

    const nextIdx = currentRoutineIndex + direction;

    // Bloqueia avançar para futuro
    if (direction > 0) {
        if (nextIdx >= routineCards.length) return;
        const nd = new Date(routineCards[nextIdx].getAttribute('data-date') + 'T00:00:00');
        const today = new Date(); today.setHours(0,0,0,0);
        if (nd > today) return;
    }

    // Válida voltar
    if (nextIdx < 0) return;

    currentRoutineIndex = nextIdx;
    updateRoutineDisplay();
}

// Função para atualizar display da rotina
function updateRoutineDisplay() {
    routineTrack = document.getElementById('routineSliderTrack');
    if (!routineTrack) return;
    
    const currentCard = routineCards[currentRoutineIndex];
    if (!currentCard) return;
    
    // Adicionar transição suave para o slider
    routineTrack.style.transition = 'transform 0.3s ease-in-out';
    
    const offset = -currentRoutineIndex * 100;
    routineTrack.style.transform = `translateX(${offset}%)`;
    
    // Atualizar dados do card
    const date = currentCard.getAttribute('data-date');
    const dateObj = new Date(date + 'T00:00:00');
    
    // Nomes dos meses e dias da semana
    const monthNamesShort = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
    const monthNamesLower = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    const weekdayNames = ['DOMINGO', 'SEGUNDA', 'TERÇA', 'QUARTA', 'QUINTA', 'SEXTA', 'SÁBADO'];
    
    // Atualizar ano
    document.getElementById('routineYear').textContent = dateObj.getFullYear();
    
    // Atualizar dia e mês principal
    const day = dateObj.getDate();
    const month = monthNamesShort[dateObj.getMonth()];
    document.getElementById('routineDayMonth').textContent = `${day} ${month}`;
    
    // Atualizar dia da semana
    document.getElementById('routineWeekday').textContent = weekdayNames[dateObj.getDay()];
    
    // Atualizar resumo de missões
    const summaryDiv = currentCard.querySelector('.diary-day-summary');
    if (summaryDiv) {
        const missionsText = summaryDiv.querySelector('.diary-summary-item span');
        const progressText = summaryDiv.querySelector('.diary-summary-macros');
        
        if (missionsText) {
            document.getElementById('routineSummaryMissions').innerHTML = 
                `<i class="fas fa-check-circle"></i><span>${missionsText.textContent}</span>`;
        }
        
        if (progressText) {
            document.getElementById('routineSummaryProgress').textContent = progressText.textContent;
        }
    } else {
        // Sem dados
        document.getElementById('routineSummaryMissions').innerHTML = 
            `<i class="fas fa-check-circle"></i><span>0 missões</span>`;
        document.getElementById('routineSummaryProgress').textContent = 'Progresso: 0%';
    }
}

// Função para abrir calendário da rotina
function openRoutineCalendar() {
    const modal = document.getElementById('routineCalendarModal');
    if (modal) {
        modal.style.display = 'flex';
        renderRoutineCalendar();
    }
}

// Função para fechar calendário da rotina
function closeRoutineCalendar() {
    const modal = document.getElementById('routineCalendarModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Função para renderizar calendário da rotina
function renderRoutineCalendar() {
    const today = new Date();
    const currentMonth = today.getMonth();
    const currentYear = today.getFullYear();
    
    // Atualizar ano e mês no calendário
    document.querySelector('#routineCalendarModal .calendar-year').textContent = currentYear;
    document.querySelector('#routineCalendarModal .calendar-month').textContent = 
        ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'][currentMonth];
    
    // Renderizar dias do calendário
    const daysGrid = document.getElementById('routineCalendarDaysGrid');
    if (daysGrid) {
        daysGrid.innerHTML = '';
        
        const firstDay = new Date(currentYear, currentMonth, 1);
        const lastDay = new Date(currentYear, currentMonth + 1, 0);
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - firstDay.getDay());
        
        for (let i = 0; i < 42; i++) {
            const cellDate = new Date(startDate);
            cellDate.setDate(startDate.getDate() + i);
            
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';
            dayElement.textContent = cellDate.getDate();
            
            // Marcar dia atual
            if (cellDate.toDateString() === today.toDateString()) {
                dayElement.classList.add('today');
            }
            
            // Marcar dias com dados
            const dateStr = cellDate.toISOString().split('T')[0];
            const hasData = routineCards.some(card => card.getAttribute('data-date') === dateStr);
            if (hasData) {
                dayElement.classList.add('has-data');
            }
            
            // Adicionar evento de clique
            dayElement.addEventListener('click', () => {
                selectRoutineDayFromCalendar(dateStr);
            });
            
            daysGrid.appendChild(dayElement);
        }
    }
}

// Função para selecionar dia do calendário da rotina
function selectRoutineDayFromCalendar(dateStr) {
    const targetIndex = routineCards.findIndex(card => card.getAttribute('data-date') === dateStr);
    if (targetIndex !== -1) {
        currentRoutineIndex = targetIndex;
        updateRoutineDisplay();
        closeRoutineCalendar();
    }
}

// Função para mudar mês do calendário da rotina
function changeRoutineCalendarMonth(direction) {
    // Implementar mudança de mês
    console.log('Mudando mês da rotina:', direction);
}

// Inicializar rotina quando a aba for ativada
document.addEventListener('DOMContentLoaded', function() {
    // Atualizar referência aos cards da rotina
    routineCards = Array.from(document.querySelectorAll('#routineSliderTrack .diary-day-card'));
    routineTrack = document.getElementById('routineSliderTrack');
    
    if (routineCards.length > 0) {
        // Determinar o índice do dia atual
        const todayStr = new Date().toISOString().slice(0, 10);
        const todayIdx = routineCards.findIndex(card => card.getAttribute('data-date') === todayStr);
        currentRoutineIndex = (todayIdx !== -1) ? todayIdx : (routineCards.length - 1);
        
        // Atualizar display
        updateRoutineDisplay();
    }
    
    // Adicionar listeners para navegação
    const prevBtn = document.querySelector('#tab-routine .diary-nav-left');
    const nextBtn = document.querySelector('#tab-routine .diary-nav-right');
    
    if (prevBtn) prevBtn.addEventListener('click', () => navigateRoutine(-1));
    if (nextBtn) nextBtn.addEventListener('click', () => navigateRoutine(1));
    
    // Adicionar listener para mudança de abas
    document.querySelectorAll('.tab-link').forEach(tab => {
        tab.addEventListener('click', function() {
            if (this.dataset.tab === 'routine') {
                setTimeout(() => {
                    updateRoutineData();
                }, 100);
            }
        });
    });
});
</script>
