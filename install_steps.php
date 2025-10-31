<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
    <meta name="theme-color" content="#FF6B35">
    <title>📲 Instalação do App ShapeFIT - Passo a Passo</title>
    <meta name="description" content="Guia completo de instalação do aplicativo ShapeFIT no seu dispositivo iOS">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --accent-orange: #FF6B35;
            --accent-orange-dark: #E55A2B;
            --accent-orange-light: #FF8A5C;
            --bg-black: #0A0A0A;
            --bg-dark: #1A1A1A;
            --bg-gray: #2A2A2A;
            --bg-gray-light: #3A3A3A;
            --text-primary: #FFFFFF;
            --text-secondary: #CCCCCC;
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--bg-black) 0%, var(--bg-dark) 50%, var(--bg-gray) 100%);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.4;
            padding: 1rem;
            overflow-x: hidden;
        }
        
        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 0;
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding-top: 1rem;
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--accent-orange), var(--accent-orange-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header p {
            font-size: 1rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.5rem;
            margin-bottom: 0;
            max-width: 100%;
        }
        
        .step-item {
            position: relative;
        }
        
        .step-number {
            position: absolute;
            top: -15px;
            left: 15px;
            z-index: 10;
            background: linear-gradient(135deg, var(--accent-orange), var(--accent-orange-dark));
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 800;
            box-shadow: 0 6px 20px rgba(255, 107, 53, 0.5);
            border: 3px solid rgba(255, 255, 255, 0.2);
        }
        
        .step-content {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 1.5rem 1rem 1rem 1rem;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.4);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .step-content:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(255, 107, 53, 0.2);
            border-color: rgba(255, 107, 53, 0.3);
        }
        
        .step-image-wrapper {
            width: 100%;
            height: auto;
            min-height: 300px;
            margin: 0 auto 0.75rem;
            background: transparent;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .step-image {
            width: 100%;
            height: auto;
            max-height: 400px;
            object-fit: contain;
            display: block;
        }
        
        .step-text {
            text-align: center;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .step-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--accent-orange);
            margin-bottom: 0.75rem;
        }
        
        .step-description {
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.5;
            min-height: 40px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 8px;
            padding: 0.75rem;
            border: 1px dashed rgba(255, 107, 53, 0.2);
            flex: 1;
        }
        
        .step-description::before {
            content: 'Digite o texto descritivo do passo aqui...';
            color: rgba(255, 255, 255, 0.3);
            font-style: italic;
        }
        
        .step-description:not(:empty)::before {
            display: none;
        }
        
        @media (max-width: 1400px) {
            .steps-grid {
                grid-template-columns: repeat(5, 1fr);
                gap: 1rem;
            }
            
            .step-image {
                max-height: 350px;
            }
        }
        
        @media (max-width: 1200px) {
            .steps-grid {
                grid-template-columns: repeat(5, 1fr);
                gap: 1rem;
            }
            
            .step-image {
                max-height: 300px;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 0.5rem;
            }
            
            .header {
                margin-bottom: 1.5rem;
                padding-top: 0.5rem;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .header p {
                font-size: 0.85rem;
            }
            
            .steps-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .step-image {
                max-height: 350px;
            }
            
            .step-content {
                padding: 1.25rem 0.75rem 0.75rem 0.75rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📲 Guia de Instalação</h1>
            <p>Siga os passos abaixo para instalar o ShapeFIT no seu dispositivo iOS</p>
        </div>

        <div class="steps-grid">
            <!-- Passo 1 -->
            <div class="step-item">
                <div class="step-number">1</div>
                <div class="step-content">
                    <div class="step-image-wrapper">
                        <img src="assets/images/install/1.png" alt="Passo 1" class="step-image">
                    </div>
                    <div class="step-text">
                        <h3 class="step-title">Passo 1</h3>
                        <div class="step-description" contenteditable="true"></div>
                    </div>
                </div>
            </div>

            <!-- Passo 2 -->
            <div class="step-item">
                <div class="step-number">2</div>
                <div class="step-content">
                    <div class="step-image-wrapper">
                        <img src="assets/images/install/2.png" alt="Passo 2" class="step-image">
                    </div>
                    <div class="step-text">
                        <h3 class="step-title">Passo 2</h3>
                        <div class="step-description" contenteditable="true"></div>
                    </div>
                </div>
            </div>

            <!-- Passo 3 -->
            <div class="step-item">
                <div class="step-number">3</div>
                <div class="step-content">
                    <div class="step-image-wrapper">
                        <img src="assets/images/install/3.png" alt="Passo 3" class="step-image">
                    </div>
                    <div class="step-text">
                        <h3 class="step-title">Passo 3</h3>
                        <div class="step-description" contenteditable="true"></div>
                    </div>
                </div>
            </div>

            <!-- Passo 4 -->
            <div class="step-item">
                <div class="step-number">4</div>
                <div class="step-content">
                    <div class="step-image-wrapper">
                        <img src="assets/images/install/4.png" alt="Passo 4" class="step-image">
                    </div>
                    <div class="step-text">
                        <h3 class="step-title">Passo 4</h3>
                        <div class="step-description" contenteditable="true"></div>
                    </div>
                </div>
            </div>

            <!-- Passo 5 -->
            <div class="step-item">
                <div class="step-number">5</div>
                <div class="step-content">
                    <div class="step-image-wrapper">
                        <img src="assets/images/install/5.png" alt="Passo 5" class="step-image">
                    </div>
                    <div class="step-text">
                        <h3 class="step-title">Passo 5</h3>
                        <div class="step-description" contenteditable="true"></div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>

