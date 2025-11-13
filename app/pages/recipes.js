// Página Recipes
export const title = 'Receitas';

export default async function renderRecipes(user, params) {
    const recipes = await loadRecipes(params.search);
    
    return `
        <div class="recipes-page">
            <div class="recipes-header">
                <h1 class="page-title">Receitas</h1>
                <div class="search-box">
                    <input type="text" id="recipe-search" placeholder="Buscar receitas..." value="${params.search || ''}">
                    <i class="fas fa-search"></i>
                </div>
            </div>

            <div class="recipes-grid" id="recipes-grid">
                ${renderRecipesGrid(recipes)}
            </div>
        </div>
    `;
}

export async function onLoad(user, params) {
    const searchInput = document.getElementById('recipe-search');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                window.app.navigateTo('recipes', { search: e.target.value });
            }, 500);
        });
    }
}

async function loadRecipes(search = '') {
    // Importar offlineDB dinamicamente
    const { default: offlineDB } = await import('/assets/js/db.js');
    
    // Tentar cache primeiro
    const cacheKey = `/recipes${search ? `?search=${search}` : ''}`;
    const cached = await offlineDB.getCachedAPIResponse(cacheKey);
    if (cached) {
        return cached;
    }
    
    // Buscar da API
    try {
        const token = localStorage.getItem('auth_token');
        const url = `${window.APP_CONFIG.API_BASE_URL}/recipes${search ? `?search=${encodeURIComponent(search)}` : ''}`;
        const response = await fetch(url, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            await offlineDB.cacheAPIResponse(cacheKey, data);
            return data;
        }
    } catch (error) {
        console.error('Erro ao carregar receitas:', error);
    }
    
    return [];
}

function renderRecipesGrid(recipes) {
    if (recipes.length === 0) {
        return `
            <div class="empty-state">
                <i class="fas fa-utensils"></i>
                <h3>Nenhuma receita encontrada</h3>
                <p>Tente buscar com outros termos</p>
            </div>
        `;
    }
    
    return recipes.map(recipe => `
        <div class="recipe-card" onclick="viewRecipe(${recipe.id})">
            <div class="recipe-image">
                ${recipe.image_filename ? 
                    `<img src="/assets/images/recipes/${recipe.image_filename}" alt="${recipe.name}">` :
                    `<div class="recipe-placeholder"><i class="fas fa-utensils"></i></div>`
                }
            </div>
            <div class="recipe-info">
                <h3>${recipe.name}</h3>
                <div class="recipe-meta">
                    <span><i class="fas fa-fire"></i> ${recipe.calories || 0} kcal</span>
                    <span><i class="fas fa-clock"></i> ${recipe.prep_time || 0} min</span>
                </div>
            </div>
        </div>
    `).join('');
}

window.viewRecipe = function(recipeId) {
    window.app.navigateTo('recipe-detail', { id: recipeId });
};

