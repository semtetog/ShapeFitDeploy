document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('measurements-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const button = this.querySelector('button[type="submit"]');
            const originalButtonText = button.textContent;
            button.disabled = true;
            button.textContent = 'Salvando...';

            fetch('includes/ajax_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message || 'Ocorreu um erro.');
                if (data.success) {
                    window.location.reload(); // Recarrega a página para mostrar o novo registro no histórico
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ocorreu um erro de conexão. Verifique sua internet.');
            })
            .finally(() => {
                button.disabled = false;
                button.textContent = originalButtonText;
            });
        });
    }
    
      // ===================================================================
    //      NOVA LÓGICA PARA O MODAL DE FOTOS
    // ===================================================================
    const modal = document.getElementById('photo-modal');
    const modalImg = document.getElementById('modal-image');
    const photoLinks = document.querySelectorAll('.history-photos a');
    const closeBtn = document.querySelector('.photo-modal-close');

    photoLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault(); // Impede que o link abra uma nova aba
            modal.style.display = 'flex';
            modalImg.src = this.href; // Pega a URL do link e coloca na imagem do modal
        });
    });

    // Função para fechar o modal
    function closeModal() {
        modal.style.display = 'none';
    }

    // Fecha ao clicar no 'X'
    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    // Fecha ao clicar no fundo (fora da imagem)
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) { // Verifica se o clique foi no fundo e não na imagem
                closeModal();
            }
        });
    }
    // ===================================================================
    

    // Lógica para pré-visualização das imagens
    document.querySelectorAll('.photo-input').forEach(input => {
        input.addEventListener('change', function() {
            const label = this.nextElementSibling;
            const span = label.querySelector('span');
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    label.style.backgroundImage = `url('${e.target.result}')`;
                    label.classList.add('has-image');
                    if (span) span.textContent = 'Alterar'; // Muda o texto para "Alterar"
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
});