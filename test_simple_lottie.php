<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste Simples Lottie</title>
    <style>
        body {
            background-color: #222;
            padding: 20px;
            font-family: sans-serif;
            color: white;
        }
        #lottie-container {
            width: 100%;
            max-width: 500px;
            height: 300px;
            border: 2px dashed #555;
            background-color: #333;
            margin: 20px 0;
        }
        .debug {
            background: #333;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <h1>Teste Simples Lottie</h1>
    
    <div class="debug">
        <strong>Status:</strong> <span id="status">Carregando...</span>
    </div>

    <div id="lottie-container"></div>
    
    <div class="debug">
        <strong>Console Logs:</strong>
        <div id="logs"></div>
    </div>

    <!-- Scripts na mesma ordem do main_app -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>
    
    <script>
        function log(message) {
            const logs = document.getElementById('logs');
            logs.innerHTML += '<div>' + new Date().toLocaleTimeString() + ': ' + message + '</div>';
            console.log(message);
        }
        
        log('Script iniciado');
        
        // Verifica se lottie está disponível
        if (typeof lottie === 'undefined') {
            log('ERRO: lottie não está disponível');
            document.getElementById('status').textContent = 'ERRO: lottie não disponível';
        } else {
            log('SUCESSO: lottie está disponível');
            document.getElementById('status').textContent = 'lottie disponível, carregando animação...';
            
            // Tenta carregar a animação
            const animation = lottie.loadAnimation({
                container: document.getElementById('lottie-container'),
                renderer: 'svg',
                loop: true,
                autoplay: true,
                path: '/banner_receitas.json'
            });
            
            animation.addEventListener('DOMLoaded', function() {
                log('SUCESSO: Animação carregada!');
                document.getElementById('status').textContent = 'Animação carregada com sucesso!';
            });
            
            animation.addEventListener('data_failed', function() {
                log('ERRO: Falha ao carregar dados');
                document.getElementById('status').textContent = 'ERRO: Falha ao carregar dados';
            });
        }
    </script>
</body>
</html>
