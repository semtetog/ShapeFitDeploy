document.addEventListener('DOMContentLoaded', function() {
    const card = document.querySelector('.card-routine-circular');
    if (!card) return;

    // --- Elementos da UI ---
    const progressCircle = card.querySelector('.progress-ring__progress');
    const missionIcon = card.querySelector('#mission-icon');
    const missionTitle = card.querySelector('#mission-title');
    const completeButton = card.querySelector('.complete-mission-btn-circular');
    const completedCountSpan = card.querySelector('#completed-count');
    const csrfToken = card.dataset.csrfToken;

    // --- Dados & Cálculos ---
    let totalMissions = parseInt(card.dataset.totalMissions, 10);
    let completedMissions = parseInt(card.dataset.completedMissions, 10);
    const radius = progressCircle.r.baseVal.value;
    const circumference = 2 * Math.PI * radius;

    // Fila de missões (lida do atributo data-* e convertida de JSON para um objeto JS)
    // --- LINHA CORRIGIDA ---
    let missionQueue = JSON.parse(card.dataset.missionsQueue);

    /**
     * Define o progresso do círculo SVG.
     * @param {number} percent - A porcentagem de progresso (0 a 100).
     */
    function setProgress(percent) {
        if (isNaN(percent) || percent < 0) percent = 0;
        if (percent > 100) percent = 100;
        const offset = circumference - (percent / 100) * circumference;
        progressCircle.style.strokeDasharray = `${circumference} ${circumference}`;
        progressCircle.style.strokeDashoffset = offset;
    }

    /**
     * Atualiza todo o estado visual do card.
     */
    function updateUI() {
        // Atualiza o contador de texto
        completedCountSpan.textContent = completedMissions;
        
        // Calcula e aplica a porcentagem no círculo
        const percentage = totalMissions > 0 ? (completedMissions / totalMissions) * 100 : 0;
        setProgress(percentage);

        // Verifica se ainda há missões na fila
        if (missionQueue && missionQueue.length > 0) {
            const nextMission = missionQueue[0];
            missionIcon.className = `fas ${nextMission.icon_class || 'fa-tasks'}`;
            missionTitle.textContent = nextMission.title;
            if (completeButton) {
                completeButton.dataset.routineId = nextMission.id;
                completeButton.style.display = ''; // Garante que o botão está visível
            }
        } else {
            // Todas as missões foram concluídas
            missionIcon.className = 'fas fa-trophy';
            missionTitle.textContent = 'Você completou tudo!';
            if (completeButton) {
                completeButton.style.display = 'none'; // Esconde o botão
            }
        }
    }

    // --- Lógica de Eventos ---
    if (completeButton) {
        completeButton.addEventListener('click', function() {
            const routineId = this.dataset.routineId;
            if (!routineId) return;

            // Feedback visual otimista
            completedMissions++;
            missionQueue.shift(); // Remove a missão concluída da fila
            updateUI();

            // Preparar dados para a API
            const formData = new FormData();
            formData.append('routine_id', routineId);
            formData.append('status', 1);
            formData.append('csrf_token', csrfToken);

            // Chamar a API
            fetch('api/update_routine_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    // Atualiza o total de pontos na tela (se a função existir)
                    if (typeof window.updateUserPointsDisplay === 'function') {
                        window.updateUserPointsDisplay(data.new_total_points);
                    }
                    // Mostra o popup de pontos ganhos
                    if (typeof window.showSinglePopup === 'function' && data.points_awarded > 0) {
                        window.showSinglePopup(data.points_awarded);
                    }
                } else {
                    // Reverte a UI em caso de erro
                    console.error("Falha ao atualizar missão:", data.message);
                    completedMissions--; 
                    // (Opcional: Adicionar a missão de volta à fila se a API falhar)
                    updateUI();
                }
            }).catch(error => {
                console.error("Erro na requisição fetch:", error);
                completedMissions--;
                updateUI();
            });
        });
    }

    // --- Inicialização ---
    updateUI();
});