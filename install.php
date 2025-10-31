<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="theme-color" content="#10B981">
    <title>📲 Baixar App ShapeFIT - Instale no seu Celular</title>
    <meta name="description" content="Baixe o aplicativo ShapeFIT. Instale no seu celular iOS e tenha acesso rápido ao seu acompanhamento nutricional e fitness.">
    <meta name="keywords" content="baixar app ShapeFIT, instalar aplicativo fitness, nutricionista app, app iOS">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary-bg: #1f2420;
            --surface-bg: #2a2f2b;
            --accent-green: #10B981;
            --accent-green-hover: #059669;
            --text-primary: #ffffff;
            --text-secondary: #d1d5db;
            --border-color: rgba(255, 255, 255, 0.1);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(180deg, #0f1410 0%, #1f2420 100%);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .header {
            text-align: center;
            margin-bottom: 4rem;
            padding-top: 3rem;
        }
        
        .header h1 {
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #10B981, #34D399);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header p {
            font-size: 1.2rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .steps-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
            margin-bottom: 4rem;
        }
        
        .step-card {
            background: var(--surface-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
        }
        
        .step-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-green), #34D399);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .step-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(16, 185, 129, 0.2);
        }
        
        .step-card:hover::before {
            opacity: 1;
        }
        
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--accent-green), #34D399);
            color: white;
            border-radius: 12px;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .step-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }
        
        .step-description {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 2rem;
        }
        
        .phone-container {
            position: relative;
            margin: 2rem 0;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .phone-frame {
            width: 100%;
            max-width: 300px;
            height: 600px;
            background: linear-gradient(135deg, #1a1a1a, #2d2d2d);
            border: 8px solid #0a0a0a;
            border-radius: 40px;
            position: relative;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }
        
        .phone-frame::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 6px;
            background: #333;
            border-radius: 3px;
            z-index: 10;
        }
        
        .phone-frame::after {
            content: '';
            position: absolute;
            bottom: 12px;
            left: 50%;
            transform: translateX(-50%);
            width: 140px;
            height: 4px;
            background: #333;
            border-radius: 2px;
            z-index: 10;
        }
        
        .phone-screen {
            width: 100%;
            height: 100%;
            background: #0a0a0a;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px 80px;
            overflow: hidden;
        }
        
        .phone-content {
            width: 100%;
            height: 100%;
            background: var(--primary-bg);
            border-radius: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
            color: var(--text-secondary);
            text-align: center;
            padding: 2rem;
            position: relative;
        }
        
        .phone-content::after {
            content: '📱';
            font-size: 4rem;
            opacity: 0.3;
        }
        
        .phone-content span {
            font-size: 0.9rem;
            opacity: 0.7;
        }
        
        .modal-preview {
            background: var(--surface-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            margin-top: 2rem;
            text-align: center;
        }
        
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .modal-header i {
            color: var(--accent-green);
            font-size: 1.5rem;
        }
        
        .modal-instructions {
            text-align: left;
            margin: 1.5rem 0;
        }
        
        .modal-instructions ul {
            list-style: none;
            padding: 0;
        }
        
        .modal-instructions li {
            padding: 1rem 0;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-instructions li:last-child {
            border-bottom: none;
        }
        
        .modal-instructions li i {
            color: var(--accent-green);
            font-size: 1.2rem;
            margin-top: 0.2rem;
            flex-shrink: 0;
        }
        
        .modal-phone-preview {
            margin-top: 2rem;
        }
        
        .modal-phone-preview .phone-frame {
            max-width: 240px;
            height: 480px;
        }
        
        .arrow-connector {
            position: absolute;
            top: 50%;
            right: -1.5rem;
            transform: translateY(-50%);
            width: 3rem;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-green), transparent);
        }
        
        .arrow-connector::after {
            content: '';
            position: absolute;
            right: -8px;
            top: 50%;
            transform: translateY(-50%);
            width: 0;
            height: 0;
            border-left: 10px solid var(--accent-green);
            border-top: 6px solid transparent;
            border-bottom: 6px solid transparent;
        }
        
        @media (max-width: 1024px) {
            .steps-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .step-card {
                max-width: 600px;
                margin: 0 auto;
            }
            
            .arrow-connector {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .header {
                padding-top: 2rem;
                margin-bottom: 3rem;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .header p {
                font-size: 1rem;
            }
            
            .step-card {
                padding: 1.5rem;
            }
            
            .phone-frame {
                max-width: 240px;
                height: 480px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📲 Etapas de Instalação no iOS</h1>
            <p>Siga os passos abaixo para instalar o aplicativo ShapeFIT no seu dispositivo iOS</p>
        </div>

        <div class="steps-container">
            <!-- Etapa 1 -->
            <div class="step-card">
                <div class="step-number">1</div>
                
                <h2 class="step-title">1ª Etapa</h2>
                <p class="step-description">
                    Abra ShapeFIT usando seu navegador Safari no dispositivo iOS.
                </p>
                
                <div class="phone-container">
                    <div class="phone-frame">
                        <div class="phone-screen">
                            <div class="phone-content">
                                <span>Placeholder - Imagem do App</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Etapa 2 -->
            <div class="step-card">
                <div class="arrow-connector"></div>
                <div class="step-number">2</div>
                
                <h2 class="step-title">2ª Etapa</h2>
                <p class="step-description">
                    Adicione este site à tela inicial para acesso rápido da próxima vez!
                </p>
                
                <div class="modal-preview">
                    <div class="modal-header">
                        <i class="fas fa-plus-circle"></i>
                        <span>Adicionar à Tela de Início</span>
                    </div>
                    
                    <div class="modal-instructions">
                        <ul>
                            <li>
                                <i class="fas fa-share"></i>
                                <span>Toque no botão de compartilhar na barra inferior do Safari</span>
                            </li>
                            <li>
                                <i class="fas fa-home"></i>
                                <span>Selecione "Adicionar à Tela de Início" no menu</span>
                            </li>
                            <li>
                                <i class="fas fa-check"></i>
                                <span>Confirme adicionando o app à sua tela inicial</span>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="modal-phone-preview">
                        <div class="phone-frame">
                            <div class="phone-screen">
                                <div class="phone-content">
                                    <span>Placeholder - Modal Safari</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Etapa 3 -->
            <div class="step-card">
                <div class="arrow-connector"></div>
                <div class="step-number">3</div>
                
                <h2 class="step-title">3ª Etapa</h2>
                <p class="step-description">
                    Abra o app e faça login com sua conta. Pronto! Você já pode usar o ShapeFIT como um app nativo.
                </p>
                
                <div class="phone-container">
                    <div class="phone-frame">
                        <div class="phone-screen">
                            <div class="phone-content">
                                <span>Placeholder - App Instalado</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

