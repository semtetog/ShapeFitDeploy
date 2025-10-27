<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Política de Privacidade - Shape FIT</title>
    
    <!-- Importação da Fonte Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* =================================== */
        /*    ESTILOS DA PÁGINA DE PRIVACIDADE   */
        /* (Baseado no seu style.css)        */
        /* =================================== */

        :root {
            --bg-color: #121212;
            --surface-color: #1E1E1E;
            --primary-text-color: #E0E0E0;
            --secondary-text-color: #A0A0A0;
            --accent-orange: #FF6B00;
            --accent-red: #D9534F;
            --border-color: #333333;
            --success-color: #4CAF50;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            color: var(--primary-text-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            font-size: 16px;
            line-height: 1.7;
        }

        .container {
            width: 100%;
            max-width: 800px; /* Um pouco mais largo para melhor leitura em desktop */
            background-color: var(--surface-color);
            padding: 30px 40px;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        }

        /* --- Cabeçalho --- */
        .page-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .page-header h1 {
            font-size: 2.8em;
            font-weight: 700;
            color: var(--primary-text-color);
            line-height: 1.2;
        }

        .page-header h1 span {
            background: linear-gradient(90deg, var(--accent-orange), var(--accent-red));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-fill-color: transparent;
        }

        .page-header p {
            font-size: 0.9em;
            color: var(--secondary-text-color);
            margin-top: 5px;
        }

        /* --- Conteúdo Principal --- */
        .content-section {
            margin-bottom: 35px;
        }
        
        .content-section h2 {
            font-size: 1.6em;
            font-weight: 600;
            color: var(--accent-orange);
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .content-section h3 {
            font-size: 1.1em;
            font-weight: 600;
            color: var(--primary-text-color);
            margin-top: 25px;
            margin-bottom: 10px;
        }
        
        .content-section p, .content-section li {
            font-size: 1em;
            text-align: justify;
            color: var(--secondary-text-color);
        }
        
        .content-section strong {
            font-weight: 500;
            color: var(--primary-text-color);
        }

        .content-section ul {
            list-style: none;
            padding-left: 0;
            margin-top: 15px;
        }
        
        .content-section ul li {
            position: relative;
            padding-left: 30px;
            margin-bottom: 12px;
        }

        .content-section ul li::before {
            content: '✓';
            position: absolute;
            left: 0;
            top: 1px;
            color: var(--success-color);
            font-weight: 700;
            font-size: 1.1em;
        }

        a {
            color: var(--accent-orange);
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        a:hover {
            color: var(--accent-red);
            text-decoration: underline;
        }

        /* --- Rodapé --- */
        .page-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            font-size: 0.9em;
            color: var(--secondary-text-color);
        }

        /* --- Media Queries para Responsividade --- */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            .container {
                padding: 25px;
            }
            .page-header h1 {
                font-size: 2.2em;
            }
            .content-section h2 {
                font-size: 1.4em;
            }
            .content-section p, .content-section li {
                font-size: 0.95em;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            .container {
                padding: 20px 15px;
            }
            .page-header h1 {
                font-size: 1.8em;
            }
             .page-header {
                margin-bottom: 30px;
            }
            .content-section h2 {
                font-size: 1.25em;
            }
            .content-section p, .content-section li {
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <header class="page-header">
            <h1>Política de Privacidade <span>Shape FIT</span></h1>
            <p>Última atualização: 14 de agosto de 2025</p>
        </header>

        <main>
            <section class="content-section">
                <p>A sua privacidade e a confiança no nosso trabalho são fundamentais. Esta Política de Privacidade descreve como o aplicativo <strong>Shape FIT</strong> coleta, utiliza, compartilha e protege as suas informações. Ao utilizar nossos serviços, você concorda com as práticas descritas neste documento.</p>
            </section>

            <section class="content-section">
                <h2>1. Informações que Coletamos</h2>
                <p>Para oferecer uma experiência nutricional completa, personalizada e gamificada, coletamos os seguintes tipos de informações:</p>
                
                <h3>a. Informações Fornecidas Diretamente por Você:</h3>
                <ul>
                    <li><strong>Dados de Cadastro e Perfil:</strong> Nome, e-mail, data de nascimento, gênero, peso, altura e foto de perfil (opcional).</li>
                    <li><strong>Dados de Saúde e Metas:</strong> Seus objetivos de calorias e macronutrientes (proteínas, carboidratos, gorduras), meta de hidratação e nível de atividade física.</li>
                    <li><strong>Registros Diários:</strong> Informações sobre as refeições que você consome (alimentos, quantidades e horários), seu consumo de água e a conclusão de tarefas e missões da rotina saudável (ex: meta de passos, treinos realizados).</li>
                </ul>

                <h3>b. Informações Coletadas Automaticamente:</h3>
                <ul>
                    <li><strong>Dados de Uso e Interação:</strong> Informações sobre como você interage com o aplicativo, como as funcionalidades que acessa, receitas que visualiza, sua pontuação, e seu progresso no ranking de missões.</li>
                    <li><strong>Informações Técnicas:</strong> Modelo do dispositivo, versão do sistema operacional e identificadores únicos para garantir a funcionalidade, segurança e compatibilidade do app.</li>
                </ul>
            </section>

            <section class="content-section">
                <h2>2. Como Usamos Suas Informações</h2>
                <p>As informações coletadas são essenciais para o funcionamento do <strong>Shape FIT</strong> e são utilizadas para:</p>
                <ul>
                    <li><strong>Personalizar Sua Experiência:</strong> Fornecer planos de calorias, sugestões de receitas e desafios que se alinhem aos seus objetivos nutricionais e progresso individual.</li>
                    <li><strong>Monitorar e Exibir Seu Progresso:</strong> Apresentar de forma clara seu consumo diário, seu histórico de peso, e o cumprimento das metas, permitindo que você e seu nutricionista acompanhem sua evolução.</li>
                    <li><strong>Operar as Funcionalidades do App:</strong> Gerenciar o sistema de pontos e o ranking de missões, enviar lembretes (como beber água), e garantir que todas as ferramentas funcionem corretamente.</li>
                    <li><strong>Comunicação com Você:</strong> Enviar notificações importantes sobre sua conta, atualizações do serviço e lembretes para manter sua rotina em dia.</li>
                    <li><strong>Melhoria e Segurança:</strong> Analisar dados de forma anônima para aprimorar a usabilidade do aplicativo, desenvolver novos recursos e proteger nossa plataforma contra atividades fraudulentas.</li>
                </ul>
            </section>

            <section class="content-section">
                <h2>3. Compartilhamento de Informações</h2>
                <p>A sua privacidade é uma prioridade. <strong>Nós não vendemos suas informações pessoais para terceiros.</strong> O compartilhamento é limitado e ocorre apenas nas seguintes circunstâncias:</p>
                <ul>
                    <li><strong>Com Seu Nutricionista:</strong> O profissional de nutrição que te acompanha terá acesso aos seus dados de progresso e registros para fornecer orientação profissional precisa e personalizada. Este compartilhamento é um pilar central do serviço Shape FIT.</li>
                    <li><strong>No Ranking de Missões:</strong> Para fomentar a gamificação e a motivação, seu nome de usuário (ou apelido) e sua pontuação serão visíveis para outros usuários no ranking. Nenhuma outra informação pessoal sensível é compartilhada publicamente.</li>
                    <li><strong>Para Cumprir a Lei:</strong> Podemos divulgar informações se formos obrigados por lei, intimação ou outro processo legal, ou para proteger a segurança e os direitos do Shape FIT e de seus usuários.</li>
                </ul>
            </section>

            <section class="content-section">
                <h2>4. Segurança dos Seus Dados</h2>
                <p>Implementamos medidas de segurança técnicas e administrativas robustas, projetadas para proteger suas informações pessoais contra perda, roubo, uso indevido e acesso, divulgação, alteração e destruição não autorizados. No entanto, nenhum sistema é 100% impenetrável, e não podemos garantir segurança absoluta.</p>
            </section>
            
            <section class="content-section">
                <h2>5. Seus Direitos e Controle</h2>
                <p>Você tem total controle sobre suas informações. A qualquer momento, você pode acessar, revisar e editar seus dados de perfil e registros diretamente nas configurações do aplicativo. Você também tem o direito de solicitar a exclusão da sua conta e dos seus dados associados, entrando em contato conosco.</p>
            </section>
            
            <section class="content-section">
                <h2>6. Alterações nesta Política</h2>
                <p>Podemos atualizar esta Política de Privacidade de tempos em tempos para refletir mudanças em nossas práticas ou por outras razões operacionais, legais ou regulatórias. Notificaremos você sobre quaisquer alterações significativas através do aplicativo ou por e-mail, e atualizaremos a data de "Última atualização" no topo desta página.</p>
            </section>

            <section class="content-section">
                <h2>7. Contato</h2>
                <p>Se você tiver qualquer dúvida, preocupação ou sugestão sobre nossa Política de Privacidade, entre em contato conosco. Estamos aqui para ajudar. Envie um e-mail para: <a href="mailto:privacidade@shapefit.com.br">privacidade@shapefit.com.br</a>.</p>
            </section>
        </main>

        <footer class="page-footer">
            <p>&copy; <?php echo date("Y"); ?> Shape FIT. Todos os direitos reservados.</p>
        </footer>
    </div>

</body>
</html>