document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.getElementById('csrf_token_main_app').value;
    const editProfileForm = document.getElementById('edit-profile-form');
    
    // --- LÓGICA DO FORMULÁRIO PRINCIPAL ---
    if (editProfileForm) {
        // --- Lógica de Upload de Foto ---
        const profilePictureInput = document.getElementById('profile-picture-input');
        const profilePictureDisplay = document.getElementById('profile-picture-display');
        const editPictureOverlay = document.querySelector('.edit-picture-overlay');
        const uploadStatus = document.getElementById('upload-status');

        if (profilePictureInput && profilePictureDisplay && editPictureOverlay) {
            profilePictureInput.addEventListener('change', async function(event) {
                const file = event.target.files[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = e => profilePictureDisplay.src = e.target.result;
                reader.readAsDataURL(file);

                editPictureOverlay.classList.add('is-loading');
                uploadStatus.textContent = '';
                
                const formData = new FormData();
                formData.append('action', 'update_profile_picture');
                formData.append('profile_picture', file);
                formData.append('csrf_token', csrfToken);

                try {
                    const response = await fetch('includes/ajax_handler.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    uploadStatus.textContent = result.message;
                    uploadStatus.className = result.success ? 'upload-status success' : 'upload-status error';
                    if (result.success) { profilePictureDisplay.src = result.new_image_url; }
                } catch (error) {
                    uploadStatus.textContent = "Falha de comunicação.";
                    uploadStatus.className = 'upload-status error';
                } finally {
                    editPictureOverlay.classList.remove('is-loading');
                    setTimeout(() => uploadStatus.textContent = '', 3000);
                }
            });
        }
        
        // --- Lógica de Salvar os Dados do Formulário ---
        editProfileForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const saveButton = this.querySelector('button[type="submit"]');
            const originalButtonText = saveButton.textContent;
            saveButton.textContent = 'Salvando...';
            saveButton.disabled = true;

            const formData = new FormData(this);
            formData.append('action', 'update_profile_details');
            formData.append('csrf_token', csrfToken);

            try {
                const response = await fetch('includes/ajax_handler.php', { method: 'POST', body: formData });
                const result = await response.json();
                uploadStatus.textContent = result.message;
                uploadStatus.className = result.success ? 'upload-status success' : 'upload-status error';
                setTimeout(() => uploadStatus.textContent = '', 3000);
            } catch (error) {
                uploadStatus.textContent = 'Falha ao conectar com o servidor.';
                uploadStatus.className = 'upload-status error';
                setTimeout(() => uploadStatus.textContent = '', 3000);
            } finally {
                saveButton.textContent = originalButtonText;
                saveButton.disabled = false;
            }
        });

        // --- LÓGICA DO MODAL DE RESTRIÇÕES ---
        const restrictionsModal = document.getElementById('restrictions-modal');
        const openModalBtn = document.getElementById('open-restrictions-modal');
        
        if (openModalBtn && restrictionsModal) {
            const closeModalBtns = restrictionsModal.querySelectorAll('.modal-close-btn, .modal-overlay, .modal-confirm-btn');

            const openModal = () => restrictionsModal.classList.add('active');
            const closeModal = () => restrictionsModal.classList.remove('active');

            openModalBtn.addEventListener('click', (e) => {
                e.preventDefault();
                openModal();
            });

            closeModalBtns.forEach(btn => btn.addEventListener('click', closeModal));
        }
    }
});