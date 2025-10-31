<?php
// public_html/routine.php (VERSÃO FINAL COM CORREÇÃO DE LAYOUT)

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$current_date = date('Y-m-d');

// 1. Busca todos os itens do dia
$all_routine_items = getRoutineItemsForUser($conn, $user_id, $current_date, getUserProfileData($conn, $user_id));

// 2. Separa os itens
$routine_todos = [];
$routine_completed = [];
foreach ($all_routine_items as $item) {
    if ($item['completion_status'] == 1) {
        $routine_completed[] = $item;
    } else {
        $routine_todos[] = $item;
    }
}

// 3. Calcula o progresso
$total_items = count($all_routine_items);
$completed_count = count($routine_completed);
$progress_percentage = ($total_items > 0) ? round(($completed_count / $total_items) * 100) : 0;

// 4. Prepara para o layout
$page_title = "Sua Rotina";
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* Estilos unificados com main_app.php */
body { background-color: var(--bg-color); color: var(--text-primary); }
.app-container { max-width: 600px; margin: 0 auto; }


/* Botão de sono - IDÊNTICO ao duration-btn */
.action-btn.sleep-btn {
    background-color: rgba(255, 193, 7, 0.2);
    color: #ffc107;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.action-btn.sleep-btn:hover {
    background-color: rgba(255, 193, 7, 0.3);
}

.action-btn.sleep-btn:focus {
    outline: none;
    box-shadow: none;
}

.page-header {
    padding: calc(env(safe-area-inset-top, 0px) + 20px) 24px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-actions {
    display: flex;
    gap: 12px;
}

.btn {
    padding: 12px 16px;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-secondary {
    background: var(--surface-color);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.btn-secondary:hover {
    background: var(--border-color);
    transform: translateY(-2px);
}
.page-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

/* --- CORREÇÃO DE LAYOUT APLICADA AQUI --- */
.routine-content-wrapper {
    display: block; /* Garante que os itens fiquem um abaixo do outro */
    padding: 0 24px;
    padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 100px);
    display: flex;
    flex-direction: column;
    gap: 24px; /* Espaçamento entre os cards */
}

.glass-card {
    background-color: var(--surface-color);
    border-radius: 16px;
    padding: 20px;
}

/* Card de Progresso */
.progress-info { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.progress-text, .progress-percentage { font-size: 0.9rem; font-weight: 600; color: var(--text-primary); }
.progress-bar-container { width: 100%; height: 8px; background-color: var(--glass-bg); border-radius: 4px; overflow: hidden; }
.progress-bar-fill { height: 100%; background-image: var(--primary-orange-gradient); border-radius: 4px; transition: width 0.5s ease; }

/* Seções e Listas */
.section-title { font-size: 1.2rem; font-weight: 600; margin-bottom: 16px; }
.routine-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px; }

/* Item da lista */
.routine-list-item {
    background-color: var(--glass-bg);
    border-radius: 12px;
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: space-between;
    transition: all 0.4s ease;
    opacity: 1;
    transform: translateX(0);
}
.routine-info {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.routine-list-item.fading-out { opacity: 0; transform: translateX(50px); }
.routine-list-item p { margin: 0; font-size: 1rem; font-weight: 500; flex-grow: 1; text-align: left;}
.routine-duration-display {
    font-size: 0.85rem;
    color: var(--text-secondary);
    font-weight: 500;
    margin-top: 4px;
    margin-left: 0;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
}

/* Container para botões de ação */
.routine-actions { display: flex; align-items: center; gap: 10px; }

/* Estilo base para botões circulares */
.action-btn {
    border: none;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.action-btn.skip-btn { background-color: rgba(255, 255, 255, 0.1); color: var(--text-secondary); }
.action-btn.skip-btn:hover { background-color: rgba(255, 255, 255, 0.2); }
.action-btn.complete-btn { background-image: var(--primary-orange-gradient); color: var(--text-primary); }
.action-btn.complete-btn:hover { filter: brightness(1.1); }
.action-btn.duration-btn { background-color: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.3); }
.action-btn.duration-btn:hover { background-color: rgba(255, 193, 7, 0.3); }
.action-btn.duration-btn:focus { outline: none; box-shadow: none; }
.action-btn.complete-btn.disabled { opacity: 0.5; cursor: not-allowed; filter: none; }
.action-btn.complete-btn.disabled:hover { filter: none; }
.action-btn.complete-btn.disabled:focus { outline: none; box-shadow: none; }
.action-btn.uncomplete-btn { background-color: rgba(229, 57, 53, 0.2); color: #ef5350; }
.action-btn.uncomplete-btn:hover { background-color: rgba(229, 57, 53, 0.4); }


/* Estilos para itens concluídos */
.routine-list-item.is-completed { opacity: 0.7; }
.routine-list-item.is-completed p { text-decoration: line-through; }

/* Mensagem de placeholder */
.placeholder-card { text-align: center; padding: 24px; opacity: 0.7; }
.placeholder-card i { font-size: 2.5rem; color: #4CAF50; margin-bottom: 12px; }
.placeholder-card p { margin: 0; font-size: 1rem; font-weight: 600; }
</style>

<div class="app-container">
    <div class="page-header">
        <h1 class="page-title">Sua Rotina</h1>
    </div>

    <section class="routine-content-wrapper">
        <div class="glass-card">
            <div class="progress-info">
                <span class="progress-text" id="progress-text"><?php echo $completed_count; ?>/<?php echo $total_items; ?> concluídas</span>
                <span class="progress-percentage" id="progress-percentage"><?php echo $progress_percentage; ?>%</span>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" id="progress-bar" style="width: <?php echo $progress_percentage; ?>%;"></div>
            </div>
        </div>

        <div>
            <h2 class="section-title">A Fazer</h2>
            <ul class="routine-list" id="routine-list-todo">
                <?php foreach($routine_todos as $item): ?>
                    <li class="routine-list-item" data-routine-id="<?php echo $item['id']; ?>">
                        <div class="routine-info">
                            <p><?php echo htmlspecialchars($item['title']); ?></p>
                            <div class="routine-actions">
                                <button class="action-btn skip-btn" aria-label="Ignorar"><i class="fas fa-times"></i></button>
                                <?php 
                                // Verificar se é missão de duração (exercício)
                                $is_duration = false;
                                $is_sleep = false;
                                
                                if (strpos($item['id'], 'onboarding_') === 0) {
                                    // Exercício onboarding - sempre é duração
                                    $is_duration = true;
                                } elseif (isset($item['is_exercise']) && $item['is_exercise'] == 1) {
                                    // Verificar se é sono ou duração baseado no exercise_type
                                    if (isset($item['exercise_type']) && $item['exercise_type'] === 'sleep') {
                                        $is_sleep = true;
                                    } elseif (isset($item['exercise_type']) && $item['exercise_type'] === 'duration') {
                                        $is_duration = true;
                                    }
                                } elseif (strpos($item['title'], 'sono') !== false || strpos($item['title'], 'Sono') !== false) {
                                    // Fallback para verificação por título
                                    $is_sleep = true;
                                }
                                
                                if ($is_duration): ?>
                                    <!-- Exercício com duração -->
                                    <button class="action-btn duration-btn" aria-label="Definir Duração" data-routine-id="<?php echo $item['id']; ?>">
                                        <i class="fas fa-clock"></i>
                                    </button>
                                    <button class="action-btn complete-btn disabled" aria-label="Concluir">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php elseif ($is_sleep): ?>
                                    <!-- Item de sono - precisa de horários -->
                                    <button class="action-btn sleep-btn" aria-label="Registrar Sono" data-routine-id="<?php echo $item['id']; ?>">
                                        <i class="fas fa-clock"></i>
                                    </button>
                                    <button class="action-btn complete-btn disabled" aria-label="Concluir">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php else: ?>
                                    <!-- Rotina normal -->
                                    <button class="action-btn complete-btn" aria-label="Concluir"><i class="fas fa-check"></i></button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <small class="routine-duration-display" style="display: none;"></small>
                    </li>
                <?php endforeach; ?>
                 <li class="placeholder-card" id="all-done-placeholder" style="<?php echo empty($routine_todos) ? '' : 'display: none;'; ?>">
                    <i class="fas fa-trophy"></i>
                    <p>Parabéns! Você completou tudo.</p>
                </li>
            </ul>
        </div>

        <div>
            <h2 class="section-title">Concluídas</h2>
            <ul class="routine-list" id="routine-list-completed">
                <?php foreach($routine_completed as $item): ?>
                    <li class="routine-list-item is-completed" data-routine-id="<?php echo $item['id']; ?>">
                        <div class="routine-info">
                            <p><?php echo htmlspecialchars($item['title']); ?></p>
                            <div class="routine-actions">
                                <button class="action-btn uncomplete-btn" aria-label="Desfazer"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                        <?php if (!empty($item['duration_minutes'])): ?>
                            <small class="routine-duration-display" style="display: flex;">
                                <?php if (isset($item['exercise_type']) && $item['exercise_type'] === 'sleep'): ?>
                                    <i class="fas fa-moon" style="font-size: 0.8em;"></i> <?php echo round($item['duration_minutes'], 1); ?>h de sono
                                <?php else: ?>
                                    <i class="fas fa-stopwatch" style="font-size: 0.8em;"></i> <?php echo htmlspecialchars($item['duration_minutes']); ?> min
                                <?php endif; ?>
                            </small>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                 <li class="placeholder-card" id="none-completed-placeholder" style="<?php echo empty($routine_completed) ? '' : 'display: none;'; ?>">
                    <p>Nenhuma tarefa concluída ainda.</p>
                </li>
            </ul>
        </div>
    </section>
</div>

<input type="hidden" id="csrf_token_routine_page" value="<?php echo $_SESSION['csrf_token']; ?>">

<script>
document.addEventListener('DOMContentLoaded', function() {
    const todoList = document.getElementById('routine-list-todo');
    const completedList = document.getElementById('routine-list-completed');
    const csrfToken = document.getElementById('csrf_token_routine_page').value;

    // --- GERENCIADOR DE EVENTOS ÚNICO E CENTRALIZADO ---
    document.body.addEventListener('click', function(event) {
        const target = event.target;
        const listItem = target.closest('.routine-list-item');

        // --- AÇÕES DENTRO DE UM ITEM DA ROTINA ---
        if (listItem) {
            const skipButton = target.closest('.skip-btn');
            const durationButton = target.closest('.duration-btn');
            const sleepButton = target.closest('.sleep-btn');
            const completeButton = target.closest('.complete-btn');
            const uncompleteButton = target.closest('.uncomplete-btn');

            if (skipButton) {
                handleSkip(listItem);
                return;
            }
            if (durationButton) {
                showExerciseDurationModal(listItem);
                return;
            }
            if (sleepButton) {
                showSleepModal(listItem);
                return;
            }
            if (uncompleteButton) {
                handleUncomplete(listItem);
                return;
            }
            
            if (completeButton) {
                // Se o botão tem a classe .disabled, mostra o alerta e para.
                if (completeButton.classList.contains('disabled')) {
                    const missionId = listItem.dataset.routineId;
                    if (String(missionId).startsWith('onboarding_')) {
                        alert('⚠️ Para completar, primeiro defina a duração do exercício!');
                    } 
                    else if (listItem.querySelector('.sleep-btn')) {
                        alert('⚠️ Para completar, primeiro registre seus horários de sono!');
                    }
                    return; // Impede que a tarefa seja completada.
                }
                
                // Se não tiver a classe .disabled, completa a tarefa.
                handleComplete(listItem);
            }
        }

        // --- AÇÕES DE MODAIS (FECHAR E CONFIRMAR) ---
        const activeModal = target.closest('.modal-overlay');
        if (activeModal) {
            // Botão de fechar/cancelar genérico
            if (target.closest('[data-action="close-modal"]')) {
                activeModal.classList.remove('modal-visible');
                document.body.style.overflow = '';
            }
            // Botão de confirmar duração do exercício
            if (target.closest('#confirm-exercise-duration')) {
                handleConfirmExerciseDuration(activeModal);
            }
            // Botão de confirmar registro de sono
            if (target.closest('#confirm-sleep-main')) {
                handleConfirmSleep(activeModal);
            }
        }
    });

    // --- FUNÇÕES DE AÇÃO ---

    function handleSkip(listItem) {
        listItem.classList.add('fading-out');
        setTimeout(() => { listItem.remove(); updateUI(); }, 400);
    }
    
    function handleComplete(listItem) {
        const missionId = listItem.dataset.routineId;
        const button = listItem.querySelector('.complete-btn');

        if (String(missionId).startsWith('onboarding_')) {
            const duration = listItem.querySelector('.duration-btn')?.dataset.duration;
            if (duration) {
                completeExerciseWithDuration(missionId, duration, listItem, button);
            } else {
                 alert('Erro: Duração não encontrada. Tente definir novamente.');
            }
        } else if (listItem.querySelector('.sleep-btn')) {
            // Item de sono - completar com dados do sessionStorage
            completeSleepRoutine(listItem, button);
        } else {
            completeRoutineDirectly(listItem, button);
        }
    }

    function handleUncomplete(listItem) {
        const button = listItem.querySelector('.uncomplete-btn');
        button.classList.add('disabled');
        const { endpoint, routineIdToSend } = getEndpointAndId(listItem.dataset.routineId, 'uncomplete');

        fetchAction(endpoint, { routine_id: routineIdToSend, csrf_token: csrfToken })
            .then(success => {
                if (success) {
                    moveItem(listItem, completedList, todoList, false);
                }
            })
            .finally(() => { button.classList.remove('disabled'); });
    }

    // --- FUNÇÕES DE MODAL ---

    function showExerciseDurationModal(listItem) {
        const modal = document.getElementById('exercise-duration-modal');
        if (!modal) return;

        const missionId = listItem.dataset.routineId;
        const title = listItem.querySelector('p').textContent;
        const durationButton = listItem.querySelector('.duration-btn');
        const durationInput = document.getElementById('exercise-duration-input');
        
        modal.dataset.currentItemId = missionId;
        modal.querySelector('h2').textContent = `⏱️ Duração - ${title}`;
        durationInput.value = durationButton.dataset.duration || 60;
        
        modal.classList.add('modal-visible');
    }
    
    function handleConfirmExerciseDuration(modal) {
        const durationInput = document.getElementById('exercise-duration-input');
        const duration = parseInt(durationInput.value, 10);
        const missionId = modal.dataset.currentItemId;
        
        if (!missionId) return;
        const listItem = todoList.querySelector(`.routine-list-item[data-routine-id="${missionId}"]`);
        if (!listItem) return;

        if (duration >= 15 && duration <= 300) {
            const durationButton = listItem.querySelector('.duration-btn');
            const completeBtn = listItem.querySelector('.complete-btn');
            const durationDisplay = listItem.querySelector('.routine-duration-display');

            durationButton.dataset.duration = duration;
            completeBtn.classList.remove('disabled');
            durationDisplay.innerHTML = `<i class="fas fa-stopwatch" style="font-size: 0.8em;"></i> ${duration} min`;
            durationDisplay.style.display = 'flex';
            
            modal.classList.remove('modal-visible');
        } else {
            alert('Por favor, insira uma duração entre 15 e 300 minutos.');
        }
    }
    
    function showSleepModal(listItem) {
        const modal = document.getElementById('sleep-modal-main');
        if (modal) {
            modal.classList.add('modal-visible');
            document.body.style.overflow = 'hidden';
        }
    }
    
    function handleConfirmSleep(modal) {
        // Obter valores dos inputs de horário
        const sleepTime = document.getElementById('sleep-time-main').value;
        const wakeTime = document.getElementById('wake-time-main').value;

        if (!sleepTime || !wakeTime) { alert('Por favor, preencha ambos os horários.'); return; }
        if (sleepTime === wakeTime) { alert('Os horários não podem ser iguais.'); return; }

        // Salvar dados no sessionStorage
        const sleepData = {
            sleep_time: sleepTime,
            wake_time: wakeTime
        };
        sessionStorage.setItem('sleep_data', JSON.stringify(sleepData));

        // Fechar modal
        modal.classList.remove('modal-visible');
        document.body.style.overflow = '';

        // Habilitar o botão de completar (igual aos exercícios)
        // Procurar por item de sono (que tem botão de sono)
        const sleepItems = document.querySelectorAll('.routine-list-item');
        let currentItem = null;
        
        for (let item of sleepItems) {
            if (item.querySelector('.sleep-btn')) {
                currentItem = item;
                break;
            }
        }
        
        if (currentItem) {
            const completeBtn = currentItem.querySelector('.complete-btn.disabled');
            if (completeBtn) {
                completeBtn.classList.remove('disabled');
            }
            
            // Mostrar duração do sono (igual aos exercícios)
            const durationDisplay = currentItem.querySelector('.routine-duration-display');
            if (durationDisplay) {
                const sleepTime = new Date(`2000-01-01T${sleepData.sleep_time}`);
                const wakeTime = new Date(`2000-01-01T${sleepData.wake_time}`);
                
                // Calcular diferença em horas
                let diffMs = wakeTime - sleepTime;
                if (diffMs < 0) {
                    // Se acordou no dia seguinte
                    diffMs += 24 * 60 * 60 * 1000;
                }
                const diffHours = Math.round(diffMs / (60 * 60 * 1000) * 10) / 10;
                
                durationDisplay.innerHTML = `<i class="fas fa-moon" style="font-size: 0.8em;"></i> ${diffHours}h de sono`;
                durationDisplay.style.display = 'flex';
            }
        }
    }

    // --- FUNÇÕES DE MANIPULAÇÃO DA API E UI ---
    
    function completeSleepRoutine(listItem, button) {
        const sleepData = JSON.parse(sessionStorage.getItem('sleep_data'));
        button.classList.add('disabled');

        const missionId = listItem.dataset.routineId;
        const params = {
            routine_id: missionId,
            sleep_time: sleepData.sleep_time,
            wake_time: sleepData.wake_time,
            csrf_token: csrfToken
        };
        
        fetchAction('actions/complete_sleep_routine.php', params)
            .then(success => {
                if (success) { 
                    // Limpar dados do sessionStorage
                    sessionStorage.removeItem('sleep_data');
                    
                    // Mover item para concluídas
                    moveItem(listItem, todoList, completedList, true);
                }
            })
            .finally(() => {
                button.classList.remove('disabled');
            });
    }
    
    function completeRoutineDirectly(listItem, button) {
        button.classList.add('disabled');
        const { endpoint, routineIdToSend } = getEndpointAndId(listItem.dataset.routineId, 'complete');

        fetchAction(endpoint, { routine_id: routineIdToSend, csrf_token: csrfToken })
            .then(success => {
                if (success) { moveItem(listItem, todoList, completedList, true); }
            })
            .finally(() => { button.classList.remove('disabled'); });
    }

    function completeExerciseWithDuration(missionId, duration, listItem, button) {
        button.classList.add('disabled');
        const params = {
            routine_id: missionId.replace('onboarding_', ''),
            duration_minutes: duration, csrf_token: csrfToken
        };

        fetchAction('actions/complete_onboarding_routine.php', params)
            .then(success => {
                if (success) { moveItem(listItem, todoList, completedList, true); }
            })
            .finally(() => { button.classList.remove('disabled'); });
    }

    async function fetchAction(endpoint, params) {
        try {
            const formData = new URLSearchParams();
            for (const key in params) { formData.append(key, params[key]); }
            
            const response = await fetch(endpoint, { method: 'POST', body: formData });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();

            if (!data.success) {
                alert(data.message || `Ocorreu um erro.`);
                return false;
            }
            return true;
        } catch (error) {
            console.error('Erro de Fetch:', error);
            alert('Falha na comunicação com o servidor.');
            return false;
        }
    }

    function getEndpointAndId(missionId, type) {
        const isOnboarding = String(missionId).startsWith('onboarding_');
        return {
            endpoint: isOnboarding ? `actions/${type}_onboarding_routine.php` : `actions/${type}_routine_item.php`,
            routineIdToSend: isOnboarding ? missionId.replace('onboarding_', '') : missionId
        };
    }
    
    function moveItem(listItem, fromList, toList, isCompleting) {
        listItem.classList.add('fading-out');

        setTimeout(() => {
            const clonedItem = listItem.cloneNode(true);
            listItem.remove();
            clonedItem.classList.remove('fading-out');
            const actionsContainer = clonedItem.querySelector('.routine-actions');
            
            if (isCompleting) {
                clonedItem.classList.add('is-completed');
                actionsContainer.innerHTML = `<button class="action-btn uncomplete-btn" aria-label="Desfazer"><i class="fas fa-times"></i></button>`;
            } else {
                clonedItem.classList.remove('is-completed');
                const missionId = clonedItem.dataset.routineId;
                const isOnboardingExercise = String(missionId).startsWith('onboarding_');
                const isSleepItem = clonedItem.querySelector('p').textContent.includes('sono') || clonedItem.querySelector('p').textContent.includes('Sono');
                
                if (isOnboardingExercise) {
                    actionsContainer.innerHTML = `
                        <button class="action-btn skip-btn" aria-label="Ignorar"><i class="fas fa-times"></i></button>
                        <button class="action-btn duration-btn" aria-label="Definir Duração" data-routine-id="${missionId}"><i class="fas fa-clock"></i></button>
                        <button class="action-btn complete-btn disabled" aria-label="Concluir"><i class="fas fa-check"></i></button>
                    `;
                    clonedItem.querySelector('.routine-duration-display').style.display = 'none';
                } else if (isSleepItem) {
                    actionsContainer.innerHTML = `
                        <button class="action-btn skip-btn" aria-label="Ignorar"><i class="fas fa-times"></i></button>
                        <button class="action-btn sleep-btn" aria-label="Registrar Sono" data-routine-id="${missionId}"><i class="fas fa-clock"></i></button>
                        <button class="action-btn complete-btn disabled" aria-label="Concluir"><i class="fas fa-check"></i></button>
                    `;
                    clonedItem.querySelector('.routine-duration-display').style.display = 'none';
                } else {
                     actionsContainer.innerHTML = `
                        <button class="action-btn skip-btn" aria-label="Ignorar"><i class="fas fa-times"></i></button>
                        <button class="action-btn complete-btn" aria-label="Concluir"><i class="fas fa-check"></i></button>
                    `;
                }
            }
            toList.prepend(clonedItem);
            updateUI();
        }, 400);
    }

    function updateUI() {
        const todoItems = todoList.querySelectorAll('.routine-list-item');
        const completedItems = completedList.querySelectorAll('.routine-list-item');
        const totalItems = todoItems.length + completedItems.length;
        const completedCount = completedItems.length;
        const progressPercentage = totalItems > 0 ? Math.round((completedCount / totalItems) * 100) : 0;
        
        document.getElementById('progress-text').textContent = `${completedCount}/${totalItems} concluídas`;
        document.getElementById('progress-percentage').textContent = `${progressPercentage}%`;
        document.getElementById('progress-bar').style.width = `${progressPercentage}%`;
        document.getElementById('all-done-placeholder').style.display = (todoItems.length === 0 && totalItems > 0) ? 'block' : 'none';
        document.getElementById('none-completed-placeholder').style.display = (completedItems.length === 0) ? 'block' : 'none';
    }
});
</script>

<!-- Modal para duração de exercício -->
<div class="modal-overlay" id="exercise-duration-modal">
    <div class="modal-content glass-card">
        <h2>⏱️ Duração do Exercício</h2>
        <div class="modal-body">
            <div class="form-group">
                <label for="exercise-duration-input">Quanto tempo durou o exercício?</label>
                <div class="duration-input-group">
                    <input type="number" id="exercise-duration-input" class="form-input" placeholder="Ex: 45" min="15" max="300" value="60">
                    <span class="duration-unit">minutos</span>
                </div>
                <small class="form-help">Entre 15 e 300 minutos</small>
            </div>
            <div class="modal-actions">
                <button type="button" class="primary-button secondary-button" data-action="close-modal">Cancelar</button>
                <button type="button" class="primary-button" id="confirm-exercise-duration">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Sono (igual ao main_app.php) -->
<div class="modal-overlay" id="sleep-modal-main">
    <div class="modal-content glass-card">
        <h2>😴 Registrar Sono</h2>
        <div class="modal-body">
            <div class="form-group">
                <label for="sleep-time-main">Hora que deitou:</label>
                <input type="time" id="sleep-time-main" class="time-input" value="22:00">
            </div>
            <div class="form-group">
                <label for="wake-time-main">Hora que acordou:</label>
                <input type="time" id="wake-time-main" class="time-input" value="07:00">
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="primary-button secondary-button" data-action="close-modal">Cancelar</button>
            <button type="button" class="primary-button" id="confirm-sleep-main">Registrar Sono</button>
        </div>
    </div>
</div>

<style>
/* Estilos para o modal de duração de exercício */
.duration-input-group {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 8px;
}

.duration-input-group input {
    flex: 1;
    padding: 12px 16px;
    border-radius: 8px;
    border: 1px solid var(--glass-border);
    background: rgba(255,255,255,0.05);
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: 600;
    box-sizing: border-box; /* <<< CORREÇÃO MÁGICA */
}

.duration-input-group input:focus {
    outline: none;
    border-color: var(--accent-orange);
}

.duration-unit {
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
    min-width: 60px;
}

.form-help {
    color: var(--text-secondary);
    font-size: 0.8rem;
    margin-top: 4px;
    display: block;
}

/* --- ESTILOS DEFINITIVOS PARA MODAIS (VERSÃO CORRIGIDA PARA IOS) --- */

/* ETAPA 1: PERMITIR QUE O CONTEÚDO VAZADO SEJA VISÍVEL */
.modal-overlay {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background: rgba(0, 0, 0, 0.8) !important;
    display: flex !important;
    visibility: hidden;
    opacity: 0;
    transition: opacity 0.3s ease, visibility 0s linear 0.3s;
    align-items: center !important;
    justify-content: center !important;
    z-index: 99999 !important;
    padding: 20px !important; /* Adicionado padding para evitar corte */
    box-sizing: border-box !important;
    /* CORREÇÃO CRÍTICA: Remove o corte horizontal */
    overflow-x: hidden !important; 
    overflow-y: auto !important; /* Permite scroll vertical se o modal for muito alto */
}

.modal-overlay.modal-visible {
    visibility: visible;
    opacity: 1;
    transition-delay: 0s;
}

/* ETAPA 2: AJUSTAR O MODAL E OS INPUTS PARA DAR ESPAÇO */
.modal-content {
    background: var(--surface-color) !important;
    border-radius: 16px !important;
    padding: 24px !important;
    width: 100% !important;
    max-width: 400px !important; 
    max-height: calc(100vh - 40px) !important;
    overflow-y: auto !important;
    border: 1px solid var(--border-color) !important;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3) !important;
    position: relative !important;
    z-index: 100000 !important;
    box-sizing: border-box !important;
    margin: 0 !important;
    /* Permite que o conteúdo interno (o seletor de data) vaze para fora dos limites do padding */
    overflow-x: visible !important; 
}

.modal-content h2 {
    margin: 0 0 20px 0;
    color: var(--text-primary);
    font-size: 1.3rem;
    text-align: center;
}

.modal-body {
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--text-primary);
    font-weight: 600;
}

.form-input {
    width: 100%;
    padding: 12px 16px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--bg-color);
    color: var(--text-primary);
    font-size: 1rem;
    box-sizing: border-box;
    max-width: 100%; /* Garante que não ultrapasse o container */
}

.form-input:focus {
    outline: none;
    border-color: var(--accent-orange);
}

/* Input de horário customizado - LIMPO E SIMPLES */
.time-input {
    width: 100%;
    padding: 16px 20px;
    border-radius: 12px;
    border: 2px solid var(--border-color);
    background: var(--bg-color);
    color: var(--text-primary);
    font-size: 1.2rem;
    font-weight: 600;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    box-sizing: border-box;
    letter-spacing: 1px;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

.time-input:focus {
    outline: none;
    border-color: var(--accent-orange);
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
}


/* Evita zoom no input em iOS */
@media (max-width: 480px) {
    .form-input {
        font-size: 16px !important;
    }
    
    /* Ajustes específicos para mobile - modal de sono */
    .modal-overlay {
        padding: 20px !important; /* Padding maior para evitar corte */
        box-sizing: border-box !important;
    }
    
    .modal-content {
        /* Usamos uma largura relativa generosa e um padding menor */
        max-width: 95vw !important; 
        padding: 20px 16px !important; 
    }
    
    /* Ajustes para os inputs de horário em mobile */
    .time-input {
        padding: 14px 16px;
        font-size: 1.1rem;
    }
    
    .modal-actions {
        flex-direction: column !important; /* Botões empilhados em mobile */
        gap: 12px !important;
    }
    
    .primary-button {
        width: 100% !important; /* Botões ocupam toda a largura */
    }
}

/* Para telas um pouco maiores, os botões ficam lado a lado */
@media (min-width: 400px) {
    .modal-actions {
        flex-direction: row !important;
    }
}


.modal-actions {
    display: flex;
    flex-direction: column; /* Botões sempre empilhados por padrão em mobile */
    gap: 10px;
    justify-content: center;
    margin-top: 24px;
}

.primary-button {
    padding: 14px 24px;
    border-radius: 8px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 1rem;
    width: 100%;
    box-sizing: border-box; /* Garante que padding seja incluído na largura */
}

.primary-button.secondary-button {
    background: var(--surface-color);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.primary-button.secondary-button:hover {
    background: var(--border-color);
}

.primary-button:not(.secondary-button) {
    background: var(--accent-orange);
    color: white;
}

.primary-button:not(.secondary-button):hover {
    background: #e55a00;
}
</style>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>