/**
 * Routine Logic com Suporte a Registro de Tempo de Exercício
 * Versão melhorada que pergunta a duração quando completar exercícios
 */

document.addEventListener('DOMContentLoaded', function() {
    const todoList = document.getElementById('routine-list-todo');
    const completedList = document.getElementById('routine-list-completed');
    const csrfToken = document.getElementById('csrf_token_routine_page')?.value;
    
    if (!csrfToken) {
        console.error('CSRF token não encontrado!');
        return;
    }

    // =============================================
    // MODAL DE DURAÇÃO DE EXERCÍCIO
    // =============================================
    
    function createExerciseDurationModal() {
        const modalHTML = `
            <div id="exerciseDurationModal" class="exercise-modal-overlay" style="display: none;">
                <div class="exercise-modal-content">
                    <div class="exercise-modal-header">
                        <h3 id="exerciseModalTitle">Quanto tempo durou?</h3>
                        <button class="exercise-modal-close" onclick="closeExerciseModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <div class="exercise-modal-body">
                        <p id="exerciseModalDescription">Informe a duração do seu treino</p>
                        
                        <div class="duration-input-container">
                            <label for="exerciseDuration">Duração (minutos)</label>
                            <input 
                                type="number" 
                                id="exerciseDuration" 
                                min="1" 
                                max="600" 
                                placeholder="Ex: 45"
                                class="duration-input"
                            />
                        </div>
                        
                        <div class="quick-duration-buttons">
                            <button class="quick-duration-btn" data-minutes="15">15 min</button>
                            <button class="quick-duration-btn" data-minutes="30">30 min</button>
                            <button class="quick-duration-btn" data-minutes="45">45 min</button>
                            <button class="quick-duration-btn" data-minutes="60">1h</button>
                            <button class="quick-duration-btn" data-minutes="90">1h30</button>
                            <button class="quick-duration-btn" data-minutes="120">2h</button>
                        </div>
                    </div>
                    
                    <div class="exercise-modal-footer">
                        <button class="btn-cancel" onclick="closeExerciseModal()">Cancelar</button>
                        <button class="btn-confirm" id="confirmExerciseDuration">Confirmar</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Event listeners dos botões rápidos
        document.querySelectorAll('.quick-duration-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('exerciseDuration').value = this.dataset.minutes;
            });
        });
    }
    
    // Criar o modal ao carregar a página
    createExerciseDurationModal();
    
    // Função global para fechar modal
    window.closeExerciseModal = function() {
        document.getElementById('exerciseDurationModal').style.display = 'none';
        document.getElementById('exerciseDuration').value = '';
    };
    
    // Função para abrir o modal
    function openExerciseModal(routineId, routineTitle, exerciseType) {
        const modal = document.getElementById('exerciseDurationModal');
        const titleElement = document.getElementById('exerciseModalTitle');
        const descElement = document.getElementById('exerciseModalDescription');
        
        titleElement.textContent = routineTitle;
        
        if (exerciseType === 'cardio') {
            descElement.textContent = '🏃 Informe quanto tempo durou o cardio';
        } else {
            descElement.textContent = '💪 Informe quanto tempo durou o treino';
        }
        
        modal.style.display = 'flex';
        
        // Focar no input
        setTimeout(() => {
            document.getElementById('exerciseDuration').focus();
        }, 300);
        
        // Listener do botão confirmar
        document.getElementById('confirmExerciseDuration').onclick = function() {
            const duration = parseInt(document.getElementById('exerciseDuration').value);
            
            if (!duration || duration < 1) {
                alert('Por favor, informe uma duração válida (mínimo 1 minuto)');
                return;
            }
            
            if (duration > 600) {
                alert('Duração máxima: 600 minutos (10 horas)');
                return;
            }
            
            // Completar a rotina com a duração
            completeRoutineWithDuration(routineId, duration);
            closeExerciseModal();
        };
    }
    
    // =============================================
    // LÓGICA DE COMPLETAR ROTINA
    // =============================================
    
    function completeRoutine(routineId, listItem) {
        const formData = new FormData();
        formData.append('routine_id', routineId);
        formData.append('csrf_token', csrfToken);
        
        fetch(BASE_URL + '/actions/complete_routine_item_v2.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.needs_duration) {
                // É um exercício! Abrir modal para perguntar duração
                openExerciseModal(
                    routineId,
                    data.routine_title,
                    data.exercise_type
                );
            } else if (data.success) {
                // Completou com sucesso
                moveToCompleted(listItem);
                showSuccessMessage(data.message);
                
                // Atualizar pontos se estiver na tela
                updatePointsDisplay(data.new_total_points);
            } else {
                alert(data.message || 'Erro ao completar rotina.');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao processar a rotina.');
        });
    }
    
    function completeRoutineWithDuration(routineId, durationMinutes) {
        const formData = new FormData();
        formData.append('routine_id', routineId);
        formData.append('exercise_duration_minutes', durationMinutes);
        formData.append('csrf_token', csrfToken);
        
        const listItem = document.querySelector(`[data-routine-id="${routineId}"]`);
        
        fetch(BASE_URL + '/actions/complete_routine_item_v2.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                moveToCompleted(listItem);
                showSuccessMessage(data.message);
                updatePointsDisplay(data.new_total_points);
            } else {
                alert(data.message || 'Erro ao completar rotina.');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao processar a rotina.');
        });
    }
    
    // =============================================
    // LÓGICA DE SKIP E UNCOMPLETE
    // =============================================
    
    function skipRoutine(listItem) {
        listItem.classList.add('fading-out');
        setTimeout(() => {
            listItem.remove();
            updateProgress();
            checkPlaceholders();
        }, 400);
    }
    
    function uncompleteRoutine(routineId, listItem) {
        const formData = new FormData();
        formData.append('routine_id', routineId);
        formData.append('csrf_token', csrfToken);
        
        fetch(BASE_URL + '/actions/uncomplete_routine_item.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                moveToTodo(listItem);
                updatePointsDisplay(data.new_total_points);
            } else {
                alert(data.message || 'Erro ao desmarcar rotina.');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao processar a rotina.');
        });
    }
    
    // =============================================
    // FUNÇÕES AUXILIARES
    // =============================================
    
    function moveToCompleted(listItem) {
        listItem.classList.add('fading-out');
        setTimeout(() => {
            listItem.remove();
            
            listItem.classList.remove('fading-out');
            listItem.classList.add('is-completed');
            
            const para = listItem.querySelector('p');
            if (para) para.style.textDecoration = 'line-through';
            
            const actionsDiv = listItem.querySelector('.routine-actions');
            if (actionsDiv) {
                actionsDiv.innerHTML = '<button class="action-btn uncomplete-btn" aria-label="Desfazer"><i class="fas fa-times"></i></button>';
            }
            
            completedList.appendChild(listItem);
            updateProgress();
            checkPlaceholders();
        }, 400);
    }
    
    function moveToTodo(listItem) {
        listItem.classList.add('fading-out');
        setTimeout(() => {
            listItem.remove();
            
            listItem.classList.remove('fading-out', 'is-completed');
            
            const para = listItem.querySelector('p');
            if (para) para.style.textDecoration = 'none';
            
            const actionsDiv = listItem.querySelector('.routine-actions');
            if (actionsDiv) {
                actionsDiv.innerHTML = `
                    <button class="action-btn skip-btn" aria-label="Ignorar"><i class="fas fa-times"></i></button>
                    <button class="action-btn complete-btn" aria-label="Concluir"><i class="fas fa-check"></i></button>
                `;
            }
            
            todoList.appendChild(listItem);
            updateProgress();
            checkPlaceholders();
        }, 400);
    }
    
    function updateProgress() {
        const totalItems = todoList.querySelectorAll('.routine-list-item:not(.placeholder-card)').length + 
                          completedList.querySelectorAll('.routine-list-item:not(.placeholder-card)').length;
        const completedItems = completedList.querySelectorAll('.routine-list-item:not(.placeholder-card)').length;
        const percentage = totalItems > 0 ? Math.round((completedItems / totalItems) * 100) : 0;
        
        document.getElementById('progress-text').textContent = `${completedItems}/${totalItems} concluídas`;
        document.getElementById('progress-percentage').textContent = `${percentage}%`;
        document.getElementById('progress-bar').style.width = `${percentage}%`;
    }
    
    function checkPlaceholders() {
        const todoItems = todoList.querySelectorAll('.routine-list-item:not(.placeholder-card)');
        const completedItems = completedList.querySelectorAll('.routine-list-item:not(.placeholder-card)');
        
        const allDonePlaceholder = document.getElementById('all-done-placeholder');
        const noneCompletedPlaceholder = document.getElementById('none-completed-placeholder');
        
        if (allDonePlaceholder) {
            allDonePlaceholder.style.display = todoItems.length === 0 ? '' : 'none';
        }
        
        if (noneCompletedPlaceholder) {
            noneCompletedPlaceholder.style.display = completedItems.length === 0 ? '' : 'none';
        }
    }
    
    function showSuccessMessage(message) {
        // Criar toast notification
        const toast = document.createElement('div');
        toast.className = 'success-toast';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    function updatePointsDisplay(newPoints) {
        const pointsElement = document.querySelector('.points-counter-badge span');
        if (pointsElement) {
            pointsElement.textContent = newPoints;
        }
    }
    
    // =============================================
    // EVENT LISTENERS
    // =============================================
    
    // Botões da lista "A Fazer"
    todoList.addEventListener('click', function(event) {
        const completeButton = event.target.closest('.complete-btn');
        const skipButton = event.target.closest('.skip-btn');
        const listItem = event.target.closest('.routine-list-item');

        if (!listItem || listItem.classList.contains('placeholder-card')) return;

        const routineId = listItem.dataset.routineId;

        if (skipButton) {
            skipRoutine(listItem);
        } else if (completeButton) {
            completeRoutine(routineId, listItem);
        }
    });

    // Botões da lista "Concluídas"
    completedList.addEventListener('click', function(event) {
        const uncompleteButton = event.target.closest('.uncomplete-btn');
        const listItem = event.target.closest('.routine-list-item');

        if (!listItem || listItem.classList.contains('placeholder-card')) return;

        const routineId = listItem.dataset.routineId;

        if (uncompleteButton) {
            uncompleteRoutine(routineId, listItem);
        }
    });
    
    // Enter no input de duração
    document.getElementById('exerciseDuration').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            document.getElementById('confirmExerciseDuration').click();
        }
    });
});





