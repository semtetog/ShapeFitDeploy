// public_html/shapefit/assets/js/points_history_logic.js
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.feed-item.expandable').forEach(item => {
        item.addEventListener('click', function() {
            this.classList.toggle('expanded');
            
            const content = this.nextElementSibling;
            if (content && content.classList.contains('expandable-content')) {
                // A mágica da animação está aqui
                if (content.classList.contains('show')) {
                    content.classList.remove('show');
                } else {
                    // Adiciona a classe que ativa a transição no CSS
                    content.classList.add('show');
                }
            }
        });
    });
});