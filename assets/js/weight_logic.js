// A verificação de 'DOMContentLoaded' foi REMOVIDA.
// O atributo 'defer' no <script> já garante que o HTML foi carregado.

const editWeightModal = document.getElementById('edit-weight-modal');

// Se o modal não existe na página, o script não precisa fazer mais nada.
// Esta verificação no início previne erros caso o script seja carregado em uma página sem o modal.
if (editWeightModal) {

    // --- ELEMENTOS ---
    const openBtn = document.querySelector('[data-action="open-weight-modal"]');
    const saveBtn = document.getElementById('save-weight-btn');
    const weightInput = document.getElementById('new-weight-input');
    const closeModalBtn = document.getElementById('close-weight-modal');
    const modalOverlay = editWeightModal.querySelector('.modal-overlay');

    // --- FUNÇÕES DO MODAL ---
    const openModal = () => { 
        editWeightModal.classList.add('is-visible'); 
    };
    const closeModal = () => { 
        editWeightModal.classList.remove('is-visible'); 
    };

    // --- OUVINTES DE EVENTO ---
    
    // Anexa o evento de clique DIRETAMENTE ao botão de abrir, se ele existir na página.
    if (openBtn) {
        openBtn.addEventListener('click', openModal);
    }
    
    // Anexa os eventos para fechar o modal.
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeModal);
    }
    if (modalOverlay) {
        modalOverlay.addEventListener('click', closeModal);
    }

    // Lógica para salvar o peso.
    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            // A variável BASE_APP_URL é definida no layout_header.php e já está disponível aqui.
            const apiUrl = `${BASE_APP_URL}/api/update_weight.php`; 
            const csrfToken = document.getElementById('csrf_token_main_app')?.value;
            const newWeight = weightInput.value.trim().replace(',', '.');

            if (newWeight === '' || isNaN(parseFloat(newWeight)) || newWeight <= 20 || newWeight >= 300) {
                if (typeof window.showAppNotification === 'function') {
                    window.showAppNotification('Por favor, insira um peso válido.', 'error');
                }
                return;
            }

            saveBtn.textContent = 'Salvando...';
            saveBtn.disabled = true;

            const formData = new FormData();
            formData.append('weight', newWeight);
            formData.append('csrf_token', csrfToken);

            fetch(apiUrl, { method: 'POST', body: formData })
            .then(response => response.json().then(data => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (!ok) {
                    throw new Error(data.message || 'Ocorreu um erro.');
                }
                if (typeof window.showAppNotification === 'function') {
                    window.showAppNotification(data.message, 'success');
                }
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            })
            .catch(err => {
                console.error('Erro ao salvar peso:', err);
                if (typeof window.showAppNotification === 'function') {
                    window.showAppNotification(err.message, 'error');
                }
                closeModal();
            })
            .finally(() => {
                saveBtn.textContent = 'Salvar';
                saveBtn.disabled = false;
            });
        });
    }
}