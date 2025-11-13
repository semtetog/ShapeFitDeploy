// Página Dashboard
import offlineDB from '/assets/js/db.js';

export const title = 'Dashboard';

export default async function renderDashboard(user, params) {
    // Buscar dados do dashboard
    const dashboardData = await loadDashboardData(user);
    
    return `
        <div class="dashboard-page">
            <!-- Card de Peso -->
            <div class="dashboard-card card-weight">
                <span>Peso Atual</span>
                <strong>${dashboardData.currentWeight || '--'}kg</strong>
                ${dashboardData.daysUntilNextWeight > 0 ? 
                    `<div class="countdown">${dashboardData.daysUntilNextWeight} dias</div>` : 
                    '<button class="edit-button" onclick="editWeight()"><i class="fas fa-edit"></i></button>'
                }
            </div>

            <!-- Card de Hidratação -->
            <div class="dashboard-card card-hydration">
                <div class="hydration-content">
                    <div class="hydration-info">
                        <h3>Hidratação</h3>
                        <div class="water-status">
                            ${dashboardData.waterConsumed}ml <span>/ ${dashboardData.waterGoal}ml</span>
                        </div>
                        <div class="water-controls">
                            <div class="water-input-row">
                                <input type="number" class="water-number-input" id="water-amount" placeholder="250" min="0" max="2000" step="50">
                                <select class="water-select" id="water-unit">
                                    <option value="ml">ml</option>
                                    <option value="cups">copos</option>
                                </select>
                            </div>
                            <div class="quick-add-row">
                                <button class="quick-add" onclick="addWater(250)">+250ml</button>
                                <button class="quick-add" onclick="addWater(500)">+500ml</button>
                                <button class="quick-add" onclick="addWater(750)">+750ml</button>
                            </div>
                        </div>
                    </div>
                    <div class="water-drop-container-svg">
                        <svg id="animated-water-drop" width="160" height="160" viewBox="0 0 160 160">
                            <!-- SVG da gota d'água animada -->
                            <defs>
                                <linearGradient id="waterGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" style="stop-color:#4FC3F7;stop-opacity:0.8" />
                                    <stop offset="100%" style="stop-color:#29B6F6;stop-opacity:1" />
                                </linearGradient>
                            </defs>
                            <g id="water-level-group" transform="translate(0, ${160 - (dashboardData.waterPercentage * 1.6)})">
                                <path d="M80 20 Q100 40 100 80 Q100 120 80 140 Q60 120 60 80 Q60 40 80 20 Z" 
                                      fill="url(#waterGradient)" opacity="0.9"/>
                            </g>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Card de Consumo -->
            <div class="dashboard-card card-consumption">
                <h3>Consumo de Hoje</h3>
                <div class="consumption-grid">
                    <div class="consumption-item">
                        <div class="progress-circle">
                            <svg class="circular-chart" viewBox="0 0 36 36">
                                <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                <path class="circle" stroke-dasharray="${dashboardData.caloriesPercentage}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                <text x="18" y="20.35" class="percentage-text">${Math.round(dashboardData.caloriesPercentage)}%</text>
                            </svg>
                        </div>
                        <p>Calorias</p>
                        <strong>${dashboardData.caloriesConsumed}/${dashboardData.caloriesGoal}</strong>
                    </div>
                    <div class="consumption-item">
                        <div class="progress-circle">
                            <svg class="circular-chart" viewBox="0 0 36 36">
                                <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                <path class="circle" stroke-dasharray="${dashboardData.proteinPercentage}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                <text x="18" y="20.35" class="percentage-text">${Math.round(dashboardData.proteinPercentage)}%</text>
                            </svg>
                        </div>
                        <p>Proteína</p>
                        <strong>${Math.round(dashboardData.proteinConsumed)}g</strong>
                    </div>
                    <div class="consumption-item">
                        <div class="progress-circle">
                            <svg class="circular-chart" viewBox="0 0 36 36">
                                <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                <path class="circle" stroke-dasharray="${dashboardData.carbsPercentage}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                <text x="18" y="20.35" class="percentage-text">${Math.round(dashboardData.carbsPercentage)}%</text>
                            </svg>
                        </div>
                        <p>Carboidratos</p>
                        <strong>${Math.round(dashboardData.carbsConsumed)}g</strong>
                    </div>
                    <div class="consumption-item">
                        <div class="progress-circle">
                            <svg class="circular-chart" viewBox="0 0 36 36">
                                <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                <path class="circle" stroke-dasharray="${dashboardData.fatPercentage}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                <text x="18" y="20.35" class="percentage-text">${Math.round(dashboardData.fatPercentage)}%</text>
                            </svg>
                        </div>
                        <p>Gorduras</p>
                        <strong>${Math.round(dashboardData.fatConsumed)}g</strong>
                    </div>
                </div>
            </div>

            <!-- Card de Missões -->
            <div class="dashboard-card card-missions">
                <div class="card-header">
                    <h3>Missões de Hoje</h3>
                    <a href="#" class="view-all-link" data-page="routine">Ver todas</a>
                </div>
                <div class="missions-progress">
                    <div class="missions-progress-info">
                        <span>${dashboardData.completedMissions}/${dashboardData.totalMissions}</span>
                        <span>Completas</span>
                    </div>
                    <div class="progress-bar-missions">
                        <div class="progress-bar-missions-fill" style="width: ${dashboardData.missionsPercentage}%"></div>
                    </div>
                </div>
                <div class="missions-carousel-container" id="missions-carousel">
                    ${renderMissionsCarousel(dashboardData.missions)}
                </div>
            </div>

            <!-- Card de Ações Rápidas -->
            <a href="#" class="dashboard-card card-action-item" data-page="diary">
                <div class="action-icon premium">
                    <i class="fas fa-utensils"></i>
                </div>
                <div class="action-content">
                    <h3>Adicionar Refeição</h3>
                    <p>Registre suas refeições do dia</p>
                </div>
                <div class="action-button">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </a>

            <a href="#" class="dashboard-card card-action-item" data-page="recipes">
                <div class="action-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="action-content">
                    <h3>Explorar Receitas</h3>
                    <p>Descubra receitas saudáveis</p>
                </div>
                <div class="action-button">
                    <i class="fas fa-arrow-right"></i>
                </div>
            </a>
        </div>
    `;
}

export async function onLoad(user, params) {
    // Inicializar eventos
    setupWaterControls();
    setupMissionsCarousel();
}

async function loadDashboardData(user) {
    const today = new Date().toISOString().split('T')[0];
    
    // Tentar carregar do IndexedDB primeiro
    const cachedData = await offlineDB.getCachedAPIResponse(`/dashboard?date=${today}`);
    if (cachedData) {
        return cachedData;
    }
    
    // Se não tem cache, buscar da API
    try {
        const token = localStorage.getItem('auth_token');
        const response = await fetch(`${window.APP_CONFIG.API_BASE_URL}/dashboard?date=${today}`, {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            await offlineDB.cacheAPIResponse(`/dashboard?date=${today}`, data);
            return data;
        }
    } catch (error) {
        console.error('Erro ao carregar dados do dashboard:', error);
    }
    
    // Retornar dados padrão se falhar
    return {
        currentWeight: user.weight_kg || '--',
        daysUntilNextWeight: 0,
        waterConsumed: 0,
        waterGoal: 2000,
        waterPercentage: 0,
        caloriesConsumed: 0,
        caloriesGoal: 2000,
        caloriesPercentage: 0,
        proteinConsumed: 0,
        proteinPercentage: 0,
        carbsConsumed: 0,
        carbsPercentage: 0,
        fatConsumed: 0,
        fatPercentage: 0,
        completedMissions: 0,
        totalMissions: 0,
        missionsPercentage: 0,
        missions: []
    };
}

function renderMissionsCarousel(missions) {
    if (missions.length === 0) {
        return `
            <div class="mission-slide active completion-message">
                <div class="mission-icon"><i class="fas fa-check-circle"></i></div>
                <div class="mission-details">
                    <h4>Parabéns!</h4>
                    <span>Todas as missões de hoje foram completadas</span>
                </div>
            </div>
        `;
    }
    
    return missions.map((mission, index) => `
        <div class="mission-slide ${index === 0 ? 'active' : ''}" data-mission-id="${mission.id}">
            <div class="mission-icon">
                <i class="${mission.icon || 'fas fa-check'}"></i>
            </div>
            <div class="mission-details">
                <h4>${mission.title}</h4>
                <span>${mission.description || ''}</span>
            </div>
            <div class="mission-actions">
                <button class="mission-action-btn skip-btn" onclick="skipMission(${mission.id})">
                    <i class="fas fa-times"></i>
                </button>
                <button class="mission-action-btn complete-btn" onclick="completeMission(${mission.id})">
                    <i class="fas fa-check"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function setupWaterControls() {
    // Implementar controles de água
    window.addWater = async function(amount) {
        const token = localStorage.getItem('auth_token');
        try {
            const response = await fetch(`${window.APP_CONFIG.API_BASE_URL}/water`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({ amount })
            });
            
            if (response.ok) {
                // Recarregar dashboard
                window.app.refreshCurrentPage();
            }
        } catch (error) {
            console.error('Erro ao adicionar água:', error);
        }
    };
}

function setupMissionsCarousel() {
    // Implementar carrossel de missões
    // Similar ao código original do main_app.php
}

window.completeMission = async function(missionId) {
    const token = localStorage.getItem('auth_token');
    try {
        const response = await fetch(`${window.APP_CONFIG.API_BASE_URL}/routine/complete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({ routine_id: missionId })
        });
        
        if (response.ok) {
            window.app.refreshCurrentPage();
        }
    } catch (error) {
        console.error('Erro ao completar missão:', error);
    }
};

