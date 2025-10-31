<?php
// admin/app_installation.php - Página de Instalação do App

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
$conn = require __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'app_installation';
$page_title = 'Instalação do App';

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* Estilos específicos da página de instalação */
.installation-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}

.installation-header {
    text-align: center;
    margin-bottom: 3rem;
}

.installation-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary-text-color);
    margin-bottom: 1rem;
}

.installation-header p {
    font-size: 1.1rem;
    color: var(--secondary-text-color);
}

.steps-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
    margin-bottom: 3rem;
}

.step-card {
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 2rem;
    text-align: center;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
}

.step-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}

.step-number {
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, var(--accent-orange), #ff6b35);
    color: white;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(255, 107, 0, 0.3);
}

.step-title {
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--primary-text-color);
    margin: 1.5rem 0 1rem 0;
}

.step-description {
    font-size: 0.95rem;
    color: var(--secondary-text-color);
    line-height: 1.6;
    margin-bottom: 1.5rem;
}

.phone-placeholder {
    width: 100%;
    max-width: 280px;
    height: 560px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
    border: 3px solid var(--border-color);
    border-radius: 24px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

.phone-placeholder::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 4px;
    background: #333;
    border-radius: 2px;
}

.phone-placeholder::after {
    content: '';
    position: absolute;
    bottom: 15px;
    left: 50%;
    transform: translateX(-50%);
    width: 120px;
    height: 4px;
    background: #333;
    border-radius: 2px;
}

.phone-screen {
    width: 100%;
    height: 100%;
    background: #0a0a0a;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px 60px;
}

.phone-screen-content {
    width: 100%;
    height: 100%;
    background: var(--bg-color);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    gap: 1rem;
    color: var(--secondary-text-color);
    font-size: 0.9rem;
    text-align: center;
    padding: 2rem;
}

.phone-screen-content i {
    font-size: 3rem;
    color: var(--accent-orange);
    opacity: 0.5;
}

.arrow-connector {
    position: absolute;
    top: 50%;
    right: -1.5rem;
    transform: translateY(-50%);
    width: 3rem;
    height: 2px;
    background: var(--accent-orange);
    opacity: 0.5;
}

.arrow-connector::after {
    content: '';
    position: absolute;
    right: -8px;
    top: 50%;
    transform: translateY(-50%);
    width: 0;
    height: 0;
    border-left: 10px solid var(--accent-orange);
    border-top: 6px solid transparent;
    border-bottom: 6px solid transparent;
}

.step-card:last-child .arrow-connector {
    display: none;
}

.modal-preview {
    background: var(--surface-color);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 2rem;
    margin-top: 1.5rem;
    text-align: center;
}

.modal-preview-header {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--primary-text-color);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.modal-preview-header i {
    color: var(--accent-orange);
}

.modal-preview-content {
    color: var(--secondary-text-color);
    line-height: 1.8;
    margin-bottom: 1.5rem;
}

.modal-preview-steps {
    text-align: left;
    display: inline-block;
    margin: 1rem 0;
}

.modal-preview-steps li {
    margin: 0.8rem 0;
    color: var(--secondary-text-color);
    display: flex;
    align-items: flex-start;
    gap: 0.8rem;
}

.modal-preview-steps li i {
    color: var(--accent-orange);
    margin-top: 0.2rem;
    flex-shrink: 0;
}

/* Responsive */
@media (max-width: 1200px) {
    .steps-container {
        grid-template-columns: 1fr;
        gap: 3rem;
    }
    
    .arrow-connector {
        display: none;
    }
    
    .step-card {
        max-width: 600px;
        margin: 0 auto;
    }
}

@media (max-width: 768px) {
    .installation-wrapper {
        padding: 1rem;
    }
    
    .installation-header h1 {
        font-size: 2rem;
    }
    
    .step-card {
        padding: 1.5rem;
    }
    
    .phone-placeholder {
        max-width: 240px;
        height: 480px;
    }
}
</style>

<div class="installation-wrapper">
    <div class="installation-header">
        <h1>Etapas de Instalação no iOS</h1>
        <p>Siga os passos abaixo para instalar o aplicativo ShapeFIT no seu dispositivo iOS</p>
    </div>

    <div class="steps-container">
        <!-- Etapa 1 -->
        <div class="step-card">
            <div class="arrow-connector"></div>
            <div class="step-number">1</div>
            
            <div class="phone-placeholder">
                <div class="phone-screen">
                    <div class="phone-screen-content">
                        <i class="fas fa-mobile-alt"></i>
                        <span>Placeholder - Imagem do App</span>
                    </div>
                </div>
            </div>
            
            <h3 class="step-title">1ª Etapa</h3>
            <p class="step-description">
                Abra ShapeFIT usando seu navegador Safari no dispositivo iOS.
            </p>
        </div>

        <!-- Etapa 2 -->
        <div class="step-card">
            <div class="arrow-connector"></div>
            <div class="step-number">2</div>
            
            <div class="modal-preview">
                <div class="modal-preview-header">
                    <i class="fas fa-plus-circle"></i>
                    <span>Adicionar à Tela de Início</span>
                </div>
                <div class="modal-preview-content">
                    <p>Siga os passos abaixo para adicionar o app à sua tela inicial:</p>
                    <ul class="modal-preview-steps">
                        <li>
                            <i class="fas fa-share"></i>
                            <span>Toque no botão de compartilhar na barra inferior</span>
                        </li>
                        <li>
                            <i class="fas fa-home"></i>
                            <span>Selecione "Adicionar à Tela de Início"</span>
                        </li>
                        <li>
                            <i class="fas fa-check"></i>
                            <span>Confirme adicionando o app</span>
                        </li>
                    </ul>
                </div>
                <div class="phone-placeholder" style="max-width: 200px; height: 400px; margin: 0 auto;">
                    <div class="phone-screen">
                        <div class="phone-screen-content">
                            <i class="fas fa-image"></i>
                            <span>Placeholder - Modal Safari</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <h3 class="step-title">2ª Etapa</h3>
            <p class="step-description">
                Adicione este site à tela inicial para acesso rápido da próxima vez!
            </p>
        </div>

        <!-- Etapa 3 -->
        <div class="step-card">
            <div class="step-number">3</div>
            
            <div class="phone-placeholder">
                <div class="phone-screen">
                    <div class="phone-screen-content">
                        <i class="fas fa-check-circle"></i>
                        <span>Placeholder - App Instalado</span>
                    </div>
                </div>
            </div>
            
            <h3 class="step-title">3ª Etapa</h3>
            <p class="step-description">
                Abra o app e faça login com sua conta. Pronto! Você já pode usar o ShapeFIT como um app nativo.
            </p>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>

