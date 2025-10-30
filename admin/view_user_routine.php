<!-- view_user_routine.php -->
<!-- Conteúdo completo da aba Rotina: HTML, CSS e JS -->
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

        <!-- 2. CALENDÁRIO COM LÓGICA AJAX (IGUAL AO DIÁRIO) -->
        <div class="diary-slider-container">
            <div class="diary-header-redesign">
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
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const html = await response.text();
        
        if (html.trim()) {
            // Parse HTML e extrair dados
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            const dayContent = tempDiv.querySelector('.routine-content-day');
            
            if (dayContent) {
                // Extrair data-attributes do resumo
                const missions = parseInt(dayContent.dataset.missions || '0', 10);
                
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
                    
                    updateRoutineSummary(missions);
                    
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
                    updateRoutineSummary(missions);
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
                    updateRoutineSummary(0);
                } else {
                    wrapper.innerHTML = '<div class="diary-empty-state"><i class="fas fa-calendar-day"></i><p>Nenhum registro neste dia</p></div>';
                    wrapper.style.opacity = '1';
                    updateRoutineSummary(0);
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
                updateRoutineSummary(0);
            } else {
                wrapper.innerHTML = '<div class="diary-empty-state"><i class="fas fa-calendar-day"></i><p>Nenhum registro neste dia</p></div>';
                wrapper.style.opacity = '1';
                updateRoutineSummary(0);
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
function updateRoutineSummary(missions) {
    document.getElementById('routineSummaryMissions').innerHTML = `<i class="fas fa-check-circle"></i><span>${missions} missões</span>`;
    document.getElementById('routineSummaryProgress').textContent = `Progresso: ${missions > 0 ? '100' : '0'}%`;
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
    fetch('api/routine_crud.php?action=list&patient_id=<?php echo $user_id; ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderMissionsGrid(data.missions);
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
    
    if (missions.length === 0) {
        showEmptyMissions('Nenhuma missão encontrada');
        return;
    }
    
    container.innerHTML = missions.map(mission => `
        <div class="mission-card" data-id="${mission.id}">
            <div class="mission-header">
                <div class="mission-icon">
                    <i class="fas ${mission.icon_class}"></i>
                </div>
                <div class="mission-actions">
                    <button class="btn-edit" onclick="editMission(${mission.id})" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-delete" onclick="deleteMission(${mission.id}, '${mission.title}')" title="Excluir">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="mission-content">
                <h4>${mission.title}</h4>
                <p class="mission-type">${mission.is_exercise ? 'Exercício' : 'Missão Simples'}</p>
                ${mission.is_exercise ? `<p class="mission-duration">Duração: ${mission.duration_minutes || 0} min</p>` : ''}
            </div>
        </div>
    `).join('');
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
function initIconPicker() {
    const iconPicker = document.getElementById('iconPicker');
    if (!iconPicker) return;
    
    const icons = [
        'fa-dumbbell', 'fa-running', 'fa-bicycle', 'fa-swimmer',
        'fa-heart', 'fa-apple-alt', 'fa-carrot', 'fa-fish',
        'fa-tint', 'fa-coffee', 'fa-glass-whiskey', 'fa-wine-bottle',
        'fa-bed', 'fa-moon', 'fa-sun', 'fa-clock',
        'fa-book', 'fa-pen', 'fa-pencil-alt', 'fa-graduation-cap',
        'fa-meditation', 'fa-yoga', 'fa-spa', 'fa-leaf',
        'fa-fire', 'fa-bolt', 'fa-star', 'fa-trophy'
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
            document.getElementById('selectedIcon').value = this.dataset.icon;
        });
    });
}

// Funções globais para o modal
window.openMissionModal = function(missionId = null) {
    const modal = document.getElementById('missionModal');
    const form = document.getElementById('missionForm');
    const title = document.getElementById('missionModalTitle');
    const saveButton = document.getElementById('saveButtonText');
    
    // Resetar formulário
    form.reset();
    document.getElementById('missionId').value = '';
    document.getElementById('selectedIcon').value = '';
    
    if (missionId) {
        title.textContent = 'Editar Missão';
        saveButton.textContent = 'Atualizar Missão';
        // Aqui você carregaria os dados da missão
    } else {
        title.textContent = 'Adicionar Nova Missão';
        saveButton.textContent = 'Salvar Missão';
    }
    
    modal.style.display = 'flex';
    
    // Inicializar seletor de ícones após o modal estar visível
    setTimeout(() => {
        initIconPicker();
    }, 100);
};

window.closeMissionModal = function() {
    document.getElementById('missionModal').style.display = 'none';
};

window.editMission = function(missionId) {
    // Aqui você carregaria os dados da missão e abriria o modal
    openMissionModal(missionId);
};

window.deleteMission = function(missionId, missionName) {
    if (confirm(`Tem certeza que deseja excluir a missão "${missionName}"?`)) {
        fetch('api/routine_crud.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete&id=${missionId}&patient_id=<?php echo $user_id; ?>`
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
            formData.append('action', formData.get('mission_id') ? 'update' : 'create');
            formData.append('patient_id', '<?php echo $user_id; ?>');
            
            fetch('api/routine_crud.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeMissionModal();
                    loadMissionsAdminList();
                } else {
                    alert('Erro ao salvar missão: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao salvar missão');
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
                        <div class="form-group">
                            <label for="missionTitle">Título da Missão</label>
                            <input type="text" id="missionTitle" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="missionDescription">Descrição</label>
                            <input type="text" id="missionDescription" name="description">
                        </div>
                        <div class="form-group">
                            <label for="missionType">Tipo de Missão</label>
                            <select id="missionType" name="is_exercise" required>
                                <option value="0">Missão Normal</option>
                                <option value="1">Exercício</option>
                            </select>
                        </div>
                        <div class="form-group" id="exerciseTypeGroup" style="display: none;">
                            <label for="exerciseType">Tipo de Exercício</label>
                            <select id="exerciseType" name="exercise_type">
                                <option value="duration">Duração (minutos)</option>
                                <option value="reps">Repetições</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Ícone da Missão</label>
                            <div class="icon-picker" id="iconPicker">
                                <!-- Ícones serão inseridos via JavaScript -->
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