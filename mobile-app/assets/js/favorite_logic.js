// assets/js/favorite_logic.js
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.favorite-toggle-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();

            const recipeId = this.dataset.recipeId;
            const csrfToken = this.dataset.csrfToken;
            const icon = this.querySelector('i');
            const isFavorited = this.classList.contains('is-favorited');

            const formData = new FormData();
            formData.append('action', 'toggle_favorite');
            formData.append('recipe_id', recipeId);
            formData.append('csrf_token', csrfToken);
            
            // Otimismo na UI: muda o ícone imediatamente
            this.classList.toggle('is-favorited');
            icon.classList.toggle('fas');
            icon.classList.toggle('far');
            
            fetch('ajax_toggle_favorite.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    // Se deu erro, reverte a mudança na UI
                    this.classList.toggle('is-favorited');
                    icon.classList.toggle('fas');
                    icon.classList.toggle('far');
                    alert('Erro ao favoritar a receita. Tente novamente.');
                }
                // Se deu certo, a UI já foi atualizada
            })
            .catch(error => {
                // Reverte a mudança se a rede falhar
                this.classList.toggle('is-favorited');
                icon.classList.toggle('fas');
                icon.classList.toggle('far');
                alert('Erro de conexão. Tente novamente.');
            });
        });
    });
});