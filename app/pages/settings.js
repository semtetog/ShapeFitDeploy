// Página Settings
export const title = 'Configurações';

export default async function renderSettings(user, params) {
    return `
        <div class="settings-page">
            <h1 class="page-title">Configurações</h1>
            
            <div class="settings-list">
                <a href="#" class="settings-item" data-page="profile">
                    <i class="fas fa-user"></i>
                    <span>Perfil</span>
                    <i class="fas fa-chevron-right"></i>
                </a>
                <a href="#" class="settings-item" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Sair</span>
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
    `;
}

export async function onLoad(user, params) {
    const { default: offlineDB } = await import('/assets/js/db.js');
    
    window.logout = function() {
        localStorage.removeItem('auth_token');
        offlineDB.clearAll();
        window.location.href = '/pages/login.html';
    };
}

