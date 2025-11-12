<?php
// admin/_admin_checkin_preview.php - Preview do Check-in (estilo WhatsApp)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
$conn = require __DIR__ . '/../includes/db.php';

$checkin_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$checkin_id) {
    die('ID do check-in não fornecido');
}

// Buscar configuração do check-in
$stmt = $conn->prepare("SELECT * FROM sf_checkin_configs WHERE id = ?");
$stmt->bind_param("i", $checkin_id);
$stmt->execute();
$result = $stmt->get_result();
$checkin = $result->fetch_assoc();
$stmt->close();

if (!$checkin) {
    die('Check-in não encontrado');
}

// Buscar questões do check-in
$stmt = $conn->prepare("SELECT * FROM sf_checkin_questions WHERE config_id = ? ORDER BY order_index ASC");
$stmt->bind_param("i", $checkin_id);
$stmt->execute();
$questions_result = $stmt->get_result();
$questions = [];
while ($q = $questions_result->fetch_assoc()) {
    $q['options'] = !empty($q['options']) ? json_decode($q['options'], true) : [];
    $questions[] = $q;
}
$stmt->close();

// Preparar dados para JavaScript
$checkin_data = [
    'id' => $checkin['id'],
    'name' => $checkin['name'],
    'questions' => $questions
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Check-in</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 50%, #0f0f0f 100%);
            color: #F5F5F5;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            margin: 0;
            padding: 0;
        }

        .checkin-chat-header {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid transparent;
            border-image: linear-gradient(135deg, #FF6B00 0%, #FF8533 50%, #FF6B00 100%);
            border-image-slice: 1;
            position: relative;
        }

        .checkin-chat-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, #FF6B00 0%, #FF8533 50%, #FF6B00 100%);
        }

        .checkin-chat-header h3 {
            margin: 0;
            background: linear-gradient(135deg, #FF6B00 0%, #FF8533 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .checkin-messages {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 50%, #0f0f0f 100%);
            min-height: 0;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }

        .checkin-messages::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        .checkin-message {
            max-width: 75%;
            padding: 12px 16px;
            border-radius: 8px;
            word-wrap: break-word;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .checkin-message.bot {
            align-self: flex-start;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: #F5F5F5;
            border: 1px solid transparent;
            border-bottom-left-radius: 4px;
            position: relative;
            overflow: hidden;
        }

        .checkin-message.bot::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 1px solid transparent;
            border-radius: 8px;
            border-bottom-left-radius: 4px;
            background: linear-gradient(135deg, rgba(255, 107, 0, 0.2) 0%, rgba(255, 133, 51, 0.1) 100%) padding-box,
                        linear-gradient(135deg, rgba(255, 107, 0, 0.3) 0%, rgba(255, 133, 51, 0.2) 100%) border-box;
            pointer-events: none;
            z-index: -1;
        }

        .checkin-message.user {
            align-self: flex-end;
            background: linear-gradient(135deg, #FF6B00 0%, #FF8533 100%);
            color: #000000;
            font-weight: 600;
            border-bottom-right-radius: 4px;
            box-shadow: 0 4px 12px rgba(255, 107, 0, 0.3);
        }

        .checkin-options {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 8px;
            max-width: 75%;
            align-self: flex-start;
        }

        .checkin-option-btn {
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid transparent;
            border-radius: 8px;
            color: #FF6B00;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
            font-size: 0.95rem;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .checkin-option-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border: 1px solid transparent;
            border-radius: 8px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.03) 100%) padding-box,
                        linear-gradient(135deg, #FF6B00 0%, #FF8533 100%) border-box;
            pointer-events: none;
            z-index: -1;
        }

        .checkin-option-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #FF6B00 0%, #FF8533 100%);
            border-color: transparent;
            color: #000000;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(255, 107, 0, 0.4);
            transform: translateY(-1px);
        }

        .checkin-option-btn:hover:not(:disabled)::before {
            display: none;
        }

        .checkin-option-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            border-color: rgba(255, 107, 0, 0.2);
        }

        .checkin-option-btn:disabled::before {
            opacity: 0.5;
        }

        .checkin-input-container {
            padding: 16px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-top: 2px solid transparent;
            border-image: linear-gradient(90deg, #FF6B00 0%, #FF8533 50%, #FF6B00 100%);
            border-image-slice: 1;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-shrink: 0;
            position: relative;
        }

        .checkin-input-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, #FF6B00 0%, #FF8533 50%, #FF6B00 100%);
        }

        .checkin-text-input {
            flex: 1;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 107, 0, 0.3);
            border-radius: 24px;
            color: #F5F5F5;
            font-size: 0.95rem;
            outline: none;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .checkin-text-input:focus {
            border-color: #FF6B00;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
        }

        .checkin-text-input:disabled {
            background: rgba(255, 255, 255, 0.02);
            color: rgba(255, 107, 0, 0.4);
            cursor: not-allowed;
            opacity: 0.6;
            border-color: rgba(255, 107, 0, 0.15);
        }

        .checkin-text-input::placeholder {
            color: rgba(255, 107, 0, 0.5);
        }

        .checkin-send-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FF6B00 0%, #FF8533 100%);
            border: none;
            color: #000000;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(255, 107, 0, 0.3);
        }

        .checkin-send-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #e55a00 0%, #FF6B00 100%);
            color: #FFFFFF;
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(255, 107, 0, 0.5);
        }

        .checkin-send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: 0 2px 8px rgba(255, 107, 0, 0.2);
        }
    </style>
</head>
<body>
    <div class="checkin-chat-header">
        <h3 id="checkinName"><?php echo htmlspecialchars($checkin['name']); ?></h3>
    </div>
    <div class="checkin-messages" id="checkinMessages"></div>
    <div class="checkin-input-container" id="checkinInputContainer">
        <input type="text" class="checkin-text-input" id="checkinTextInput" placeholder="Digite sua resposta..." onkeypress="if(event.key === 'Enter') sendCheckinResponse()" disabled>
        <button class="checkin-send-btn" onclick="sendCheckinResponse()" id="checkinSendBtn" disabled>
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>

    <script>
        const checkinData = <?php echo json_encode($checkin_data); ?>;
        let currentQuestionIndex = 0;
        let messageDelay = 500; // Delay padrão em ms
        let previewMode = true; // Modo preview (não salva respostas)

        // Escutar mensagens do iframe pai para atualizar preview
        window.addEventListener('message', function(event) {
            if (event.data.type === 'updateCheckin') {
                // Atualizar dados do check-in
                if (event.data.checkinName) {
                    document.getElementById('checkinName').textContent = event.data.checkinName;
                }
                
                // Reiniciar preview
                currentQuestionIndex = 0;
                document.getElementById('checkinMessages').innerHTML = '';
                const inputContainer = document.getElementById('checkinInputContainer');
                const textInput = document.getElementById('checkinTextInput');
                const sendBtn = document.getElementById('checkinSendBtn');
                textInput.disabled = true;
                sendBtn.disabled = true;
                textInput.value = '';
                
                // Atualizar questões
                if (event.data.questions) {
                    checkinData.questions = event.data.questions;
                }
                
                // Iniciar preview
                setTimeout(() => renderNextQuestion(), 500);
            } else if (event.data.type === 'updateDelay') {
                messageDelay = event.data.delay || 500;
            } else if (event.data.type === 'restartPreview') {
                currentQuestionIndex = 0;
                document.getElementById('checkinMessages').innerHTML = '';
                const inputContainer = document.getElementById('checkinInputContainer');
                const textInput = document.getElementById('checkinTextInput');
                const sendBtn = document.getElementById('checkinSendBtn');
                textInput.disabled = true;
                sendBtn.disabled = true;
                textInput.value = '';
                setTimeout(() => renderNextQuestion(), 500);
            }
        });

        // Iniciar preview quando carregar
        window.addEventListener('load', function() {
            setTimeout(() => renderNextQuestion(), 500);
        });

        function renderNextQuestion() {
            const messagesDiv = document.getElementById('checkinMessages');
            const inputContainer = document.getElementById('checkinInputContainer');
            const textInput = document.getElementById('checkinTextInput');
            const sendBtn = document.getElementById('checkinSendBtn');
            
            if (currentQuestionIndex >= checkinData.questions.length) {
                // Todas as perguntas foram respondidas
                addMessage('Obrigado pelo seu feedback! Seu check-in foi salvo com sucesso.', 'bot');
                textInput.disabled = true;
                sendBtn.disabled = true;
                textInput.value = '';
                textInput.placeholder = 'Check-in finalizado';
                return;
            }
            
            const question = checkinData.questions[currentQuestionIndex];
            
            // Adicionar mensagem da pergunta com delay
            setTimeout(() => {
                addMessage(question.question_text, 'bot');
                
                // Habilitar ou desabilitar input baseado no tipo
                if (question.question_type === 'text') {
                    textInput.disabled = false;
                    sendBtn.disabled = false;
                    textInput.value = '';
                    textInput.placeholder = 'Digite sua resposta...';
                    textInput.focus();
                } else {
                    // Múltipla escolha ou escala - desabilitar input
                    textInput.disabled = true;
                    sendBtn.disabled = true;
                    textInput.value = '';
                    textInput.placeholder = 'Selecione uma opção acima...';
                    setTimeout(() => showQuestionOptions(question), messageDelay);
                }
            }, messageDelay);
        }

        function showQuestionOptions(question) {
            const messagesDiv = document.getElementById('checkinMessages');
            const optionsDiv = document.createElement('div');
            optionsDiv.className = 'checkin-options';
            
            if ((question.question_type === 'scale' || question.question_type === 'multiple_choice') && question.options && question.options.length > 0) {
                question.options.forEach(option => {
                    const btn = document.createElement('button');
                    btn.className = 'checkin-option-btn';
                    btn.type = 'button';
                    btn.textContent = option;
                    btn.onclick = () => selectOption(option);
                    optionsDiv.appendChild(btn);
                });
                
                messagesDiv.appendChild(optionsDiv);
                // Scroll suave para o final
                setTimeout(() => {
                    messagesDiv.scrollTop = messagesDiv.scrollHeight;
                }, 100);
            }
        }

        function selectOption(option) {
            // Desabilitar todos os botões de opção para evitar múltiplos cliques
            const optionButtons = document.querySelectorAll('.checkin-option-btn');
            optionButtons.forEach(btn => {
                btn.disabled = true;
                btn.style.opacity = '0.6';
                btn.style.cursor = 'not-allowed';
            });
            
            addMessage(option, 'user');
            currentQuestionIndex++;
            setTimeout(() => renderNextQuestion(), messageDelay);
        }

        function sendCheckinResponse() {
            const input = document.getElementById('checkinTextInput');
            const sendBtn = document.getElementById('checkinSendBtn');
            
            // Verificar se está desabilitado
            if (input.disabled) return;
            
            const response = input.value.trim();
            if (!response) return;
            
            addMessage(response, 'user');
            input.value = '';
            input.disabled = true;
            sendBtn.disabled = true;
            currentQuestionIndex++;
            
            setTimeout(() => renderNextQuestion(), messageDelay);
        }

        function addMessage(text, type) {
            const messagesDiv = document.getElementById('checkinMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `checkin-message ${type}`;
            messageDiv.textContent = text;
            messagesDiv.appendChild(messageDiv);
            // Scroll suave para o final
            setTimeout(() => {
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            }, 50);
        }
    </script>
</body>
</html>

