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
            background: #0b141a;
            color: #e9edef;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .checkin-chat-header {
            background: #202c33;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .checkin-chat-header h3 {
            margin: 0;
            color: white;
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
            background: #0b141a;
            min-height: 0;
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
            background: #202c33;
            color: #e9edef;
            border-bottom-left-radius: 4px;
        }

        .checkin-message.user {
            align-self: flex-end;
            background: #005c4b;
            color: white;
            border-bottom-right-radius: 4px;
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
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: left;
            font-size: 0.95rem;
        }

        .checkin-option-btn:hover:not(:disabled) {
            background: rgba(255, 107, 0, 0.2);
            border-color: #FF6B00;
        }

        .checkin-option-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .checkin-input-container {
            padding: 16px;
            background: #202c33;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            gap: 12px;
            align-items: center;
            flex-shrink: 0;
        }

        .checkin-text-input {
            flex: 1;
            padding: 12px 16px;
            background: #2a3942;
            border: none;
            border-radius: 24px;
            color: white;
            font-size: 0.95rem;
            outline: none;
            font-family: inherit;
        }

        .checkin-text-input:disabled {
            background: #1e2730;
            color: rgba(255, 255, 255, 0.4);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .checkin-text-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .checkin-send-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #FF6B00;
            border: none;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .checkin-send-btn:hover {
            background: #e55a00;
            transform: scale(1.1);
        }

        .checkin-send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
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
            addMessage(option, 'user');
            currentQuestionIndex++;
            setTimeout(() => renderNextQuestion(), messageDelay);
        }

        function sendCheckinResponse() {
            const input = document.getElementById('checkinTextInput');
            const response = input.value.trim();
            
            if (!response) return;
            
            addMessage(response, 'user');
            input.value = '';
            currentQuestionIndex++;
            
            setTimeout(() => renderNextQuestion(), messageDelay);
        }

        function addMessage(text, type) {
            const messagesDiv = document.getElementById('checkinMessages');
            const messageDiv = document.createElement('div');
            messageDiv.className = `checkin-message ${type}`;
            messageDiv.textContent = text;
            messagesDiv.appendChild(messageDiv);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }
    </script>
</body>
</html>

