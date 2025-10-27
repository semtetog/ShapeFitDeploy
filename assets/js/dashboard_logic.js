document.addEventListener('DOMContentLoaded', function() {
    const openModalBtn = document.getElementById('open-help-modal-btn');
    const modalContainer = document.getElementById('goal-help-modal');

    if (!openModalBtn || !modalContainer) {
        // Se os elementos não existirem na página, não faz nada.
        return;
    }

    // Função para abrir o modal
    const openModal = () => {
        modalContainer.style.display = 'flex';
        // Pequeno delay para a animação de opacidade funcionar
        setTimeout(() => {
            modalContainer.classList.add('is-visible');
        }, 10);
    };

    // Função para fechar o modal
    const closeModal = () => {
        modalContainer.classList.remove('is-visible');
        // Espera a animação de opacidade terminar para esconder o elemento
        setTimeout(() => {
            modalContainer.style.display = 'none';
        }, 300); // 300ms é a duração da transição no CSS
    };

    // Evento para abrir o modal
    openModalBtn.addEventListener('click', openModal);

    // Eventos para fechar o modal
    // Usamos um seletor para pegar múltiplos elementos (overlay e botões)
    modalContainer.addEventListener('click', (event) => {
        // Verifica se o elemento clicado (ou um de seus pais) tem o atributo data-action="close-modal"
        if (event.target.closest('[data-action="close-modal"]')) {
            closeModal();
        }
    });

    // Fechar o modal com a tecla 'Escape'
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modalContainer.classList.contains('is-visible')) {
            closeModal();
        }
    });
});