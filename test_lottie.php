<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Animação Lottie</title>
    <style>
        body {
            background-color: #222; /* Fundo escuro para contraste */
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            font-family: sans-serif;
            color: white;
        }
        #lottie-container {
            width: 80%;
            max-width: 500px;
            height: auto;
            border: 2px dashed #555; /* Borda para vermos o container */
            background-color: #333;
        }
    </style>
</head>
<body>

    <div>
        <h1>Página de Teste Lottie</h1>
        <p>A animação deve aparecer dentro da caixa abaixo:</p>
        
        <!-- Este é o container onde a animação será renderizada -->
        <div id="lottie-container"></div>
    </div>

    <!-- 1. Carregamos a biblioteca lottie-web diretamente -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>

    <!-- 2. Nosso script para iniciar a animação -->
    <script>
        console.log("Página de teste carregada. Tentando iniciar Lottie...");

        // Seleciona o container onde a animação vai entrar
        const container = document.getElementById('lottie-container');

        // Configura e carrega a animação
        const animation = lottie.loadAnimation({
            container: container,       // O elemento DOM para usar
            renderer: 'svg',            // O tipo de renderização
            loop: true,                 // Repetir a animação?
            autoplay: true,             // Começar a tocar automaticamente?
            path: '/banner_receitas.json' // O caminho para o seu arquivo de animação
        });

        console.log("Comando lottie.loadAnimation foi executado.");
        
        // Adiciona um "ouvinte" para sabermos se a animação carregou com sucesso
        animation.addEventListener('DOMLoaded', function() {
            console.log("SUCESSO! O evento DOMLoaded da animação foi disparado.");
        });

    </script>

</body>
</html>