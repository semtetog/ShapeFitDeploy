<?php
// Define um limite de tempo maior, pois o script pode demorar
set_time_limit(600); 

// --- CARREGAMENTO E CONFIGURAÇÃO ---
// __DIR__ se refere à pasta 'cron', então precisamos subir um nível
require_once __DIR__ . '/../includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';
// Inclui o script de envio de notificações que já criamos e testamos
require_once APP_ROOT_PATH . '/admin/send_notification.php'; // Ajuste o caminho se necessário

// Define o fuso horário para garantir que a hora esteja correta
date_default_timezone_set('America/Sao_Paulo');
$current_hour = (int)date('G'); // Pega a hora atual no formato 24h (0-23)

echo "CRON SCRIPT INICIADO. HORA ATUAL: " . $current_hour . "h\n";

// --- BUSCA USUÁRIOS APTOS A RECEBER NOTIFICAÇÕES ---
// Pega todos os usuários que têm um push_token válido
$sql = "SELECT id, name, push_token FROM sf_users WHERE push_token IS NOT NULL AND push_token != ''";
$result = $conn->query($sql);
$users_to_notify = $result->fetch_all(MYSQLI_ASSOC);

if (empty($users_to_notify)) {
    echo "Nenhum usuário com token encontrado. Encerrando.\n";
    exit;
}

echo "Encontrados " . count($users_to_notify) . " usuários para notificar.\n";

// --- LÓGICA DE ENVIO BASEADA NO HORÁRIO ---
$notifications_sent = 0;

foreach ($users_to_notify as $user) {
    $title = '';
    $body = '';
    $first_name = explode(' ', $user['name'])[0];

    // Lógica para os horários das refeições
    if ($current_hour == 8) { // 8h
        $title = "☀️ Bom dia, {$first_name}!";
        $body = "Que tal registrar seu café da manhã para começar o dia bem?";
    } elseif ($current_hour == 12) { // 12h
        $title = "🍴 Hora do Almoço!";
        $body = "Não se esqueça de adicionar seu almoço no diário.";
    } elseif ($current_hour == 19) { // 19h
        $title = "🌙 Boa noite, {$first_name}!";
        $body = "Já registrou seu jantar? Complete seu diário de hoje!";
    }

    // Lógica para lembretes genéricos (enviados em horários específicos)
    if ($current_hour == 10) { // 10h
        $title = "💧 Lembrete de Hidratação";
        $body = "Vamos beber um copo d'água para bater a meta e ganhar pontos!";
    } elseif ($current_hour == 15) { // 15h
        $title = "🚀 Missão do Dia!";
        $body = "Ainda dá tempo de completar suas missões e subir no ranking!";
    } elseif ($current_hour == 17) { // 17h
        $title = "🍲 Dica de Receita";
        $body = "Procurando inspiração? Confira nossas receitas deliciosas e saudáveis!";
    }

    // Se uma mensagem foi definida para a hora atual, envie-a
    if (!empty($title) && !empty($body)) {
        echo "Enviando '{$title}' para o usuário #{$user['id']}...\n";
        
        // A função sendPushNotificationV1 vem do arquivo que incluímos
        sendPushNotificationV1(
            $user['push_token'], 
            $title, 
            $body, 
            ['openUrl' => BASE_APP_URL . '/main_app.php'] // Dados extras
        );
        $notifications_sent++;
        sleep(1); // Pausa de 1 segundo para não sobrecarregar o servidor
    }
}

echo "CRON SCRIPT FINALIZADO. {$notifications_sent} notificações foram enviadas.\n";
?>