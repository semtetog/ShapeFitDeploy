// Arquivo: assets/js/diary_logic.js

document.addEventListener('DOMContentLoaded', function() {
    const addMealBtn = document.getElementById('add-meal-diary-btn');
    const baseAppUrlElement = document.getElementById('base_app_url_for_js');
    const baseAppUrl = baseAppUrlElement ? baseAppUrlElement.value : '';
    const dateSpan = document.getElementById('current-diary-date');
    
    if (addMealBtn) {
        addMealBtn.addEventListener('click', () => {
            const currentDate = dateSpan ? dateSpan.dataset.date : '';
            // Redireciona para a página de adicionar refeição, passando a data atual
            window.location.href = `${baseAppUrl}/add_food_to_diary.php?date=${currentDate}`;
        });
    }

    // Lógica para o menu inferior (se for usado em mais páginas, pode ir para um global.js)
    const addMealShortcut = document.getElementById('add-meal-shortcut-bottom-nav');
    if(addMealShortcut) {
        addMealShortcut.addEventListener('click', (e) => {
            e.preventDefault();
            const today = new Date().toISOString().slice(0, 10);
            window.location.href = `${baseAppUrl}/add_food_to_diary.php?date=${today}`;
        });
    }
});