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
                    <button class="diary-nav-side diary-nav-left" onclick="window.routineView.navigateRoutineDate(-1)" type="button">
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
                    
                    <button class="diary-nav-side diary-nav-right" onclick="window.routineView.navigateRoutineDate(1)" type="button" style="visibility: hidden;">
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
                <button class="diary-calendar-icon-btn" onclick="window.routineView.openRoutineCalendar()" type="button" title="Ver calendário">
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
                <button class="btn-add-mission-circular" onclick="window.routineView.openMissionModal()" title="Adicionar Missão">
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
</style>

<script>
(function initRoutine() {
    // Namespace global para funções da rotina, garantindo que sejam acessíveis
    window.routineView = {};

    const routineUserId = <?php echo json_encode($user_id); ?>;
    if (!routineUserId) {
        console.error("[Routine] ID do usuário não encontrado.");
        return;
    }

    // ============ LÓGICA DO SLIDER DIÁRIO ============
    const routineMonthNamesShort = ['JAN','FEV','MAR','ABR','MAI','JUN','JUL','AGO','SET','OUT','NOV','DEZ'];
    const routineMonthNamesLower = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
    const routineWeekdayNames = ['DOMINGO','SEGUNDA','TERÇA','QUARTA','QUINTA','SEXTA','SÁBADO'];
    let currentRoutineDate = new Date();

    // Funções do slider (navigateRoutineDate, updateRoutineDateDisplay, loadRoutineForDate)
    window.routineView.navigateRoutineDate = function(direction) {
        const newDate = new Date(currentRoutineDate);
        newDate.setDate(newDate.getDate() + direction);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (newDate > today) return; // Bloquear navegação para o futuro

        currentRoutineDate.setDate(currentRoutineDate.getDate() + direction);
        updateRoutineDateDisplay();
        loadRoutineForDate(currentRoutineDate, direction);
    };

    function updateRoutineDateDisplay() {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        currentRoutineDate.setHours(0, 0, 0, 0);

        const day = currentRoutineDate.getDate();
        const month = currentRoutineDate.getMonth();
        const year = currentRoutineDate.getFullYear();
        const weekday = currentRoutineDate.getDay();

        document.getElementById('routineDayMonth').textContent = `${day} ${routineMonthNamesShort[month]}`;
        document.getElementById('routineWeekday').textContent = routineWeekdayNames[weekday];
        document.getElementById('routineYear').textContent = year;

        const prevDate = new Date(currentRoutineDate);
        prevDate.setDate(day - 1);
        document.getElementById('routinePrevDate').textContent = `${prevDate.getDate()} ${routineMonthNamesLower[prevDate.getMonth()]}`;

        const nextDate = new Date(currentRoutineDate);
        nextDate.setDate(day + 1);
        const nextDateBtn = document.querySelector('.diary-nav-right');

        if (currentRoutineDate >= today) {
            nextDateBtn.style.visibility = 'hidden';
        } else {
            nextDateBtn.style.visibility = 'visible';
            document.getElementById('routineNextDate').textContent = `${nextDate.getDate()} ${routineMonthNamesLower[nextDate.getMonth()]}`;
        }
    }

    async function loadRoutineForDate(date, direction = 0) {
        const dateString = date.toISOString().split('T')[0];
        console.log(`[routine] Carregando data: ${dateString}`);
        const wrapper = document.getElementById('routineContentWrapper');
        
        // Animação de saída
        if (direction !== 0 && wrapper.children.length > 0 && !wrapper.querySelector('.diary-loading-state')) {
            wrapper.style.transition = 'opacity 0.15s ease-out, transform 0.15s ease-out';
            wrapper.style.opacity = '0';
            wrapper.style.transform = `translateX(${direction > 0 ? '-20px' : '20px'})`;
            await new Promise(resolve => setTimeout(resolve, 150));
        } else {
            const loadingState = document.getElementById('routineLoadingState');
            if (loadingState) {
                loadingState.style.display = 'flex';
                wrapper.innerHTML = '';
                wrapper.appendChild(loadingState);
            }
        }

        try {
            const response = await fetch(`actions/load_routine_days.php?user_id=${routineUserId}&date=${dateString}`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const html = await response.text();

            // Preparar para animação de entrada
            wrapper.style.transition = 'none';
             if (direction !== 0) {
                wrapper.style.opacity = '0';
                wrapper.style.transform = `translateX(${direction > 0 ? '20px' : '-20px'})`;
            }
            
            // Inserir conteúdo
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            const summaryData = tempDiv.querySelector('[data-missions-completed]');
            let completed = 0;
            let total = 0;
            if (summaryData) {
                completed = summaryData.getAttribute('data-missions-completed') || 0;
                total = summaryData.getAttribute('data-missions-total') || 0;
            }
            document.getElementById('routineSummaryMissions').textContent = `${completed}/${total} missões`;
            wrapper.innerHTML = html;

            // Forçar reflow e animar entrada
            void wrapper.offsetHeight;
            wrapper.style.transition = 'opacity 0.15s ease-in, transform 0.15s ease-in';
            wrapper.style.opacity = '1';
            wrapper.style.transform = 'translateX(0px)';

        } catch (error) {
            console.error('Erro ao carregar rotina:', error);
            wrapper.innerHTML = '<div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>Não foi possível carregar a rotina. Tente novamente.</p></div>';
        }
    }

    // ============ LÓGICA DE MISSÕES ============
    let missionToEdit = null;

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
                showEmptyMissions('Erro de conexão ao carregar missões.');
            });
    }

    function renderMissionsGrid(missions) {
        const container = document.getElementById('missions-container');
        if (!container) return;
        container.innerHTML = '';

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
             const isPersonal = mission.is_personal == 1;
             const isDynamic = String(mission.id).startsWith('onboarding_');
             return `
                <div class="mission-card" data-id="${mission.id}" data-is-personal="${isPersonal ? 1 : 0}">
                    <div class="mission-header">
                        <div class="mission-icon"><i class="fas ${mission.icon_class}"></i></div>
                        <div class="mission-actions">
                            <button class="btn-edit" onclick="window.routineView.editMission('${mission.id}', ${isDynamic ? 0 : (isPersonal ? 1 : 0)})" title="Editar"><i class="fas fa-edit"></i></button>
                            ${!isDynamic ? `<button class="btn-delete" onclick="window.routineView.deleteMission('${mission.id}', '${mission.title.replace(/'/g, "\\'")}', ${isPersonal ? 1 : 0})" title="Excluir"><i class="fas fa-trash"></i></button>` : ''}
                        </div>
                    </div>
                    <div class="mission-body">
                        <h4>${mission.title}</h4>
                        <p>${mission.description || 'Sem descrição'}</p>
                    </div>
                </div>`;
        }).join('');
        container.innerHTML = missionsHtml;
    }
    
    function showEmptyMissions(message) {
        const container = document.getElementById('missions-container');
        if (!container) return;
        container.innerHTML = `<div class="empty-missions"><i class="fas fa-info-circle"></i><span>${message}</span></div>`;
    }

    window.routineView.openMissionModal = function(mission = null) {
        missionToEdit = mission;
        const modal = document.getElementById('missionModal');
        const form = document.getElementById('missionForm');
        form.reset();

        document.getElementById('missionModalTitle').textContent = mission ? 'Editar Missão' : 'Adicionar Missão';
        document.getElementById('missionId').value = mission ? mission.id : '';

        if (mission) {
            document.getElementById('missionTitle').value = mission.title;
            document.getElementById('missionDescription').value = mission.description;
            
            const selectedIconField = document.getElementById('selectedIcon');
            selectedIconField.value = mission.icon_class || 'fa-check-circle';
            
            const iconOption = document.querySelector(`.icon-option[data-icon="${selectedIconField.value}"]`);
            if(iconOption){
                document.querySelectorAll('.icon-option').forEach(opt => opt.classList.remove('selected'));
                iconOption.classList.add('selected');
            }

            let missionType = 'yes_no';
            if (mission.is_exercise == 1) {
                if (mission.exercise_type === 'duration') {
                    missionType = 'duration';
                }
            }
            const radio = form.querySelector(`input[name="mission_type"][value="${missionType}"]`);
            if (radio) radio.checked = true;

        } else {
             document.getElementById('selectedIcon').value = 'fa-check-circle';
             const defaultIcon = document.querySelector(`.icon-option[data-icon="fa-check-circle"]`);
             if(defaultIcon){
                document.querySelectorAll('.icon-option').forEach(opt => opt.classList.remove('selected'));
                defaultIcon.classList.add('selected');
             }
        }
        modal.style.display = 'flex';
    };

    window.routineView.closeMissionModal = function() {
        document.getElementById('missionModal').style.display = 'none';
    };

    window.routineView.editMission = function(missionId, isPersonal) {
        fetch(`api/routine_crud.php?action=get_mission&id=${encodeURIComponent(missionId)}&patient_id=${routineUserId}&is_personal=${isPersonal}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    sessionStorage.setItem('current_mission_is_personal', isPersonal);
                    window.routineView.openMissionModal(data.data);
                } else {
                    alert('Erro ao carregar dados da missão: ' + data.message);
                }
            }).catch(error => alert('Erro de conexão ao buscar missão.'));
    };

    window.routineView.deleteMission = function(missionId, missionTitle, isPersonal) {
        if (confirm(`Tem certeza que deseja excluir a missão "${missionTitle}"?`)) {
            fetch('api/routine_crud.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'delete_mission', id: missionId, patient_id: routineUserId, is_personal: isPersonal })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadMissionsAdminList();
                } else {
                    alert('Erro ao excluir missão: ' + data.message);
                }
            })
            .catch(error => alert('Erro de conexão ao excluir missão.'));
        }
    };
    
    function initIconPicker() {
        const iconPicker = document.getElementById('iconPicker');
        if (!iconPicker) return;
        
        const icons = ['fa-dumbbell', 'fa-running', 'fa-bicycle', 'fa-swimmer', 'fa-heart', 'fa-apple-alt', 'fa-carrot', 'fa-fish', 'fa-tint', 'fa-coffee', 'fa-bed', 'fa-moon', 'fa-sun', 'fa-clock', 'fa-book', 'fa-pen', 'fa-pencil-alt', 'fa-graduation-cap', 'fa-spa', 'fa-leaf', 'fa-fire', 'fa-bolt', 'fa-star', 'fa-trophy', 'fa-water', 'fa-utensils', 'fa-walking', 'fa-calendar-check', 'fa-weight', 'fa-smile', 'fa-check-circle'];
        
        iconPicker.innerHTML = icons.map(icon => `<div class="icon-option" data-icon="${icon}"><i class="fas ${icon}"></i></div>`).join('');
        
        iconPicker.querySelectorAll('.icon-option').forEach(option => {
            option.addEventListener('click', function() {
                iconPicker.querySelectorAll('.icon-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('selectedIcon').value = this.dataset.icon;
            });
        });
    }

    // ============ CALENDÁRIO DA ROTINA ============
    let currentRoutineCalendarDate = new Date();
    let routineDaysWithData = new Set(<?php echo json_encode($routine_days_with_data); ?>);

    window.routineView.openRoutineCalendar = function() {
        document.getElementById('routineCalendarModal').style.display = 'flex';
        renderRoutineCalendar();
    };

    window.routineView.closeRoutineCalendar = function() {
        document.getElementById('routineCalendarModal').style.display = 'none';
    };

    function renderRoutineCalendar() {
        const year = currentRoutineCalendarDate.getFullYear();
        const month = currentRoutineCalendarDate.getMonth();
        const monthNames = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
        document.getElementById('routineCalendarMonthYear').textContent = `${monthNames[month]} ${year}`;

        const calendarBody = document.getElementById('routineCalendarDaysGrid');
        calendarBody.innerHTML = '';
        const firstDayOfMonth = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        let date = 1;
        for (let i = 0; i < 6; i++) {
            let row = document.createElement('div');
            row.classList.add('calendar-row');
            for (let j = 0; j < 7; j++) {
                if (i === 0 && j < firstDayOfMonth) {
                    let cell = document.createElement('div');
                    cell.classList.add('calendar-day', 'other-month');
                    row.appendChild(cell);
                } else if (date > daysInMonth) {
                    break;
                } else {
                    let cell = document.createElement('div');
                    cell.classList.add('calendar-day');
                    cell.textContent = date;
                    const cellDate = new Date(year, month, date);
                    const dateString = cellDate.toISOString().split('T')[0];
                    
                    if (routineDaysWithData.has(dateString)) {
                        cell.classList.add('has-data');
                    }
                    if (cellDate.setHours(0,0,0,0) === new Date().setHours(0,0,0,0)) {
                        cell.classList.add('today');
                    }
                    
                    const today = new Date();
                    today.setHours(0,0,0,0);
                    if (cellDate > today) {
                        cell.classList.add('disabled');
                    } else {
                        cell.onclick = () => selectRoutineDate(cellDate);
                    }
                    
                    row.appendChild(cell);
                    date++;
                }
            }
            calendarBody.appendChild(row);
            if (date > daysInMonth) break;
        }
    }

    window.routineView.navigateRoutineCalendar = function(direction) {
        currentRoutineCalendarDate.setMonth(currentRoutineCalendarDate.getMonth() + direction);
        renderRoutineCalendar();
    };

    function selectRoutineDate(date) {
        currentRoutineDate = date;
        updateRoutineDateDisplay();
        loadRoutineForDate(date);
        window.routineView.closeRoutineCalendar();
    }

    // ============ INICIALIZAÇÃO E EVENTOS ============
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('missionForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                const missionId = formData.get('mission_id');
                const action = missionId ? 'update_mission' : 'create_mission';
                const isPersonal = sessionStorage.getItem('current_mission_is_personal') == '1';
                
                const missionType = formData.get('mission_type');
                let is_exercise = 0;
                let exercise_type = '';
                if (missionType === 'duration') {
                    is_exercise = 1;
                    exercise_type = 'duration';
                }
                
                const data = {
                    action: action,
                    title: formData.get('title'),
                    description: formData.get('description'),
                    icon_class: formData.get('icon_class') || 'fa-check-circle',
                    is_exercise: is_exercise,
                    exercise_type: exercise_type,
                    patient_id: routineUserId,
                    is_personal: isPersonal ? 1 : 0
                };
                if (missionId) {
                    data.id = isNaN(missionId) ? missionId : parseInt(missionId);
                }

                fetch('api/routine_crud.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.routineView.closeMissionModal();
                        loadMissionsAdminList();
                    } else {
                        alert('Erro ao salvar missão: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro ao salvar missão:', error);
                    alert('Erro de conexão ao salvar missão.');
                });
            });
        }

        function initializeRoutineTab() {
            const routineTab = document.getElementById('tab-routine');
            if (routineTab && routineTab.classList.contains('active')) {
                loadRoutineForDate(currentRoutineDate, 0);
                loadMissionsAdminList();
            }
        }

        document.querySelectorAll('.tab-link').forEach(link => {
            link.addEventListener('click', function() {
                if (this.getAttribute('data-tab') === 'routine') {
                    setTimeout(() => {
                        loadRoutineForDate(currentRoutineDate, 0);
                        loadMissionsAdminList();
                    }, 150); 
                }
            });
        });
        
        initIconPicker();
        initializeRoutineTab();
    });

})();
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
/* Estilo removido - agora usa o estilo padrão do view_user_addon.css (sleep-modal-close) */

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
            <div class="custom-modal-overlay" onclick="window.routineView.closeRoutineCalendar()"></div>
            <div class="diary-calendar-wrapper">
                <button class="calendar-btn-close" onclick="window.routineView.closeRoutineCalendar()" type="button">
                    <i class="fas fa-times"></i>
                </button>
                
                <div class="calendar-header-title">
                    <div class="calendar-year" id="routineCalendarYear">2025</div>
                </div>
                
                <div class="calendar-nav-buttons">
                    <button class="calendar-btn-nav" onclick="window.routineView.navigateRoutineCalendar(-1)" type="button">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div class="calendar-month" id="routineCalendarMonth">OUT</div>
                    <button class="calendar-btn-nav" id="routineNextMonthBtn" onclick="window.routineView.navigateRoutineCalendar(1)" type="button">
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
            <div class="custom-modal-overlay" onclick="window.routineView.closeMissionModal()"></div>
            <div class="diary-calendar-wrapper">
                <button class="calendar-btn-close" onclick="window.routineView.closeMissionModal()" type="button">
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