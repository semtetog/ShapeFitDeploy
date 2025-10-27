document.addEventListener('DOMContentLoaded', function() {
    const routineSection = document.querySelector('.routine-section-gamified');
    if (!routineSection) return;

    // --- Elementos da UI ---
    const progressFill = document.getElementById('routine-progress-fill');
    const character = document.getElementById('progress-character');
    const progressText = document.getElementById('routine-progress-text');
    const missionCards = document.querySelectorAll('.mission-card-interactive:not(.celebration)');
    const celebrationCard = document.getElementById('routine-celebration-card');
    
    // --- Dados Iniciais (lidos do HTML) ---
    const csrfToken = routineSection.dataset.csrfToken;
    let totalMissions = parseInt(routineSection.dataset.totalMissions, 10);
    let completedMissions = parseInt(routineSection.dataset.completedMissions, 10);
    let currentCardIndex = 0;

    // Função para atualizar a barra de progresso e o personagem
    function updateProgressUI() {
        const percentage = totalMissions > 0 ? (completedMissions / totalMissions) * 100 : 0;
        
        progressFill.style.width = percentage + '%';
        character.style.left = percentage + '%';
        
        progressText.innerHTML = `Você completou <strong>${Math.round(percentage)}%</strong> da sua rotina hoje!`;
    }

    // Função para mostrar o próximo card de missão
    function showNextCard() {
        if (missionCards[currentCardIndex]) {
            missionCards[currentCardIndex].classList.add('hidden');
        }
        currentCardIndex++;
        if (missionCards[currentCardIndex]) {
            missionCards[currentCardIndex].classList.remove('hidden');
        } else {
            celebrationCard.classList.remove('hidden');
            const confettiContainer = celebrationCard.querySelector('.confetti-container');
            if (confettiContainer) {
                confettiContainer.innerHTML = confettiContainer.innerHTML;
            }
        }
    }
    
    // Função local para atualizar o contador de pontos (mantida para clareza)
    function updateUserPointsDisplay(newTotal) {
        const pointsDisplay = document.getElementById('user-points-display');
        if (pointsDisplay) {
            const formattedPoints = Number(newTotal).toLocaleString('pt-BR', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 1
            });
            pointsDisplay.textContent = formattedPoints;
        }
    }

    // --- Adiciona os ouvintes de clique nos botões ---
    missionCards.forEach(card => {
        const routineId = card.dataset.routineId;
        const yesButton = card.querySelector('[data-action="yes"]');
        const noButton = card.querySelector('[data-action="no"]');

        // Clique no "Sim"
        yesButton.addEventListener('click', () => {
            // 1. Atualiza a UI imediatamente (feedback otimista)
            completedMissions++;
            updateProgressUI();
            
            // 2. Prepara os dados para a API
            const formData = new FormData();
            formData.append('routine_id', routineId);
            formData.append('status', 1);
            formData.append('csrf_token', csrfToken);
            
            // 3. Envia a requisição para sua API
            fetch('api/update_routine_status.php', {
                method: 'POST',
                body: formData 
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log('Missão atualizada com sucesso via API.');

                    // ATUALIZA O TOTAL DE PONTOS NA TELA
                    updateUserPointsDisplay(data.new_total_points);

                    // AQUI ESTÁ A ADIÇÃO: CHAMA O POPUP DE PONTOS
                    // Verifica se a função global existe antes de chamar
                    if (typeof window.showSinglePopup === 'function' && data.points_awarded > 0) {
                        window.showSinglePopup(data.points_awarded); 
                    }

                } else {
                    console.error("Falha ao atualizar missão:", data.message);
                    // Reverte a UI em caso de falha da API
                    completedMissions--;
                    updateProgressUI();
                }
            }).catch(error => {
                console.error("Erro na requisição fetch:", error);
                completedMissions--;
                updateProgressUI();
            });
            
            // 4. Mostra o próximo card
            showNextCard();
        });

        // Clique no "Não"
        noButton.addEventListener('click', () => {
            showNextCard();
        });
    });

    // Garante que a UI inicial esteja correta ao carregar a página
    updateProgressUI();
});