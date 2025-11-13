// Página Diary
import offlineDB from '/assets/js/db.js';

export const title = 'Diário Alimentar';

export default async function renderDiary(user, params) {
    const date = params.date || new Date().toISOString().split('T')[0];
    const diaryData = await loadDiaryData(date);
    
    return `
        <div class="diary-page">
            <div class="diary-header">
                <h1 class="page-title">Diário</h1>
                <div class="date-selector">
                    <button class="date-nav-arrow" onclick="changeDate(-1)">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span id="current-diary-date">${formatDate(date)}</span>
                    <button class="date-nav-arrow" onclick="changeDate(1)">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>

            <div class="nutrition-summary">
                <div class="nutrition-card">
                    <h3>Calorias</h3>
                    <div class="nutrition-value">${Math.round(diaryData.caloriesConsumed)}</div>
                    <div class="nutrition-goal">/ ${diaryData.caloriesGoal} kcal</div>
                </div>
                <div class="nutrition-card">
                    <h3>Proteínas</h3>
                    <div class="nutrition-value">${Math.round(diaryData.proteinConsumed)}g</div>
                    <div class="nutrition-goal">/ ${diaryData.proteinGoal}g</div>
                </div>
                <div class="nutrition-card">
                    <h3>Carboidratos</h3>
                    <div class="nutrition-value">${Math.round(diaryData.carbsConsumed)}g</div>
                    <div class="nutrition-goal">/ ${diaryData.carbsGoal}g</div>
                </div>
                <div class="nutrition-card">
                    <h3>Gorduras</h3>
                    <div class="nutrition-value">${Math.round(diaryData.fatConsumed)}g</div>
                    <div class="nutrition-goal">/ ${diaryData.fatGoal}g</div>
                </div>
            </div>

            <div class="meals-list" id="meals-list">
                ${renderMeals(diaryData.meals)}
            </div>

            <div class="add-meal-section">
                <button class="add-meal-btn-integrated" onclick="addMeal()">
                    <i class="fas fa-plus"></i>
                    <span>Adicionar Refeição</span>
                </button>
            </div>
        </div>
    `;
}

export async function onLoad(user, params) {
    window.currentDiaryDate = params.date || new Date().toISOString().split('T')[0];
    
    window.changeDate = function(delta) {
        const current = new Date(window.currentDiaryDate);
        current.setDate(current.getDate() + delta);
        const newDate = current.toISOString().split('T')[0];
        window.currentDiaryDate = newDate;
        window.app.navigateTo('diary', { date: newDate });
    };
    
    window.addMeal = function() {
        window.app.navigateTo('add-meal', { date: window.currentDiaryDate });
    };
}

async function loadDiaryData(date) {
    // Tentar carregar do IndexedDB
    const meals = await offlineDB.getMeals(date);
    
    // Calcular totais
    const totals = meals.reduce((acc, meal) => ({
        calories: acc.calories + (meal.calories || 0),
        protein: acc.protein + (meal.protein || 0),
        carbs: acc.carbs + (meal.carbs || 0),
        fat: acc.fat + (meal.fat || 0)
    }), { calories: 0, protein: 0, carbs: 0, fat: 0 });
    
    // Tentar buscar da API também
    try {
        const token = localStorage.getItem('auth_token');
        const response = await fetch(`${window.APP_CONFIG.API_BASE_URL}/diary/meals?date=${date}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        if (response.ok) {
            const apiData = await response.json();
            // Sincronizar com IndexedDB
            for (const meal of apiData.meals || []) {
                await offlineDB.saveMeal(meal);
            }
            return {
                meals: apiData.meals || [],
                caloriesConsumed: apiData.totals?.calories || totals.calories,
                proteinConsumed: apiData.totals?.protein || totals.protein,
                carbsConsumed: apiData.totals?.carbs || totals.carbs,
                fatConsumed: apiData.totals?.fat || totals.fat,
                caloriesGoal: apiData.goals?.calories || 2000,
                proteinGoal: apiData.goals?.protein || 150,
                carbsGoal: apiData.goals?.carbs || 200,
                fatGoal: apiData.goals?.fat || 60
            };
        }
    } catch (error) {
        console.error('Erro ao carregar dados do diário:', error);
    }
    
    // Retornar dados do IndexedDB
    return {
        meals: meals,
        caloriesConsumed: totals.calories,
        proteinConsumed: totals.protein,
        carbsConsumed: totals.carbs,
        fatConsumed: totals.fat,
        caloriesGoal: 2000,
        proteinGoal: 150,
        carbsGoal: 200,
        fatGoal: 60
    };
}

function renderMeals(meals) {
    if (meals.length === 0) {
        return `
            <div class="empty-state">
                <i class="fas fa-utensils"></i>
                <h3>Nenhuma refeição registrada</h3>
                <p>Adicione sua primeira refeição do dia</p>
            </div>
        `;
    }
    
    const mealTypes = {
        breakfast: 'Café da Manhã',
        morning_snack: 'Lanche da Manhã',
        lunch: 'Almoço',
        afternoon_snack: 'Lanche da Tarde',
        dinner: 'Jantar',
        supper: 'Ceia'
    };
    
    // Agrupar por tipo
    const grouped = {};
    meals.forEach(meal => {
        const type = meal.meal_type || 'breakfast';
        if (!grouped[type]) grouped[type] = [];
        grouped[type].push(meal);
    });
    
    return Object.keys(grouped).map(type => {
        const typeMeals = grouped[type];
        const totalKcal = typeMeals.reduce((sum, m) => sum + (m.calories || 0), 0);
        
        return `
            <div class="meal-group">
                <div class="meal-group-header">
                    <h3 class="meal-group-title">${mealTypes[type] || type}</h3>
                    <div class="meal-group-total">${Math.round(totalKcal)} kcal</div>
                </div>
                <div class="meal-items">
                    ${typeMeals.map(meal => `
                        <div class="meal-item">
                            <div class="meal-item-info">
                                <div class="meal-item-name">${meal.name || meal.recipe_name || 'Refeição'}</div>
                                <div class="meal-item-details">
                                    P: ${Math.round(meal.protein || 0)}g | 
                                    C: ${Math.round(meal.carbs || 0)}g | 
                                    G: ${Math.round(meal.fat || 0)}g
                                </div>
                            </div>
                            <div class="meal-item-actions">
                                <div class="meal-item-kcal">${Math.round(meal.calories || 0)} kcal</div>
                                <button class="meal-edit-btn" onclick="editMeal(${meal.id})" title="Editar refeição">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }).join('');
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    
    if (dateStr === today.toISOString().split('T')[0]) {
        return 'Hoje';
    } else if (dateStr === yesterday.toISOString().split('T')[0]) {
        return 'Ontem';
    } else {
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }
}

window.editMeal = function(mealId) {
    window.app.navigateTo('edit-meal', { id: mealId });
};

