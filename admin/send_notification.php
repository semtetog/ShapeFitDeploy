<?php
// Arquivo: admin/send_notification.php
// Versão Final de Produção (sem Composer)

// Carrega as configurações principais do app, como caminhos e conexão com o banco.
// O require_once '../includes/config.php' foi movido para dentro dos blocos que o usam
// para tornar este arquivo puramente uma biblioteca de funções.
require_once __DIR__ . '/../includes/config.php'; // Usa __DIR__ para um caminho mais robusto
require_once APP_ROOT_PATH . '/includes/db.php';


// Inclui os arquivos da biblioteca JWT que subimos para a pasta 'includes'
require_once APP_ROOT_PATH . '/includes/jwt/JWT.php';
require_once APP_ROOT_PATH . '/includes/jwt/Key.php';

// Declara o uso das classes da biblioteca JWT para evitar conflitos de nome
use Firebase\JWT\JWT;

// =========================================================================
// CAMINHO PARA O SEU ARQUIVO DE CREDENCIAIS JSON
// Confirme que este é o nome e o caminho corretos para o seu arquivo.
$credentials_path = APP_ROOT_PATH . '/includes/firebase_credentials/shapefit-2a566-76595e31e81c.json';

// ID DO SEU PROJETO FIREBASE (pegue em Configurações do Projeto > Geral)
$project_id = 'shapefit-2a566'; 
// =========================================================================


/**
 * Gera um token de acesso temporário para a API v1 do Firebase usando JWT.
 * Esta função é interna e usada por sendPushNotificationV1.
 *
 * @param string $credentials_path O caminho para o arquivo de credenciais JSON.
 * @return string O token de acesso.
 * @throws Exception Se o arquivo de credenciais não for encontrado ou for inválido.
 */
function get_firebase_access_token($credentials_path) {
    if (!file_exists($credentials_path)) {
        throw new Exception("Arquivo de credenciais do Firebase não encontrado em: {$credentials_path}");
    }

    $credentials_content = file_get_contents($credentials_path);
    $service_account = json_decode($credentials_content, true);
    if (!$service_account) {
        throw new Exception("Arquivo de credenciais do Firebase inválido ou mal formatado.");
    }

    $private_key = $service_account['private_key'];
    $client_email = $service_account['client_email'];

    $now_seconds = time();
    $payload = [
        'iss' => $client_email,
        'sub' => $client_email,
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now_seconds,
        'exp' => $now_seconds + 3599, // Válido por pouco menos de 1 hora
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging'
    ];

    $jwt = JWT::encode($payload, $private_key, 'RS256');

    // Troca o JWT por um Access Token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (isset($data['access_token'])) {
        return $data['access_token'];
    }

    throw new Exception("Falha ao obter token de acesso do Google: " . ($data['error_description'] ?? 'Erro desconhecido. Verifique as credenciais.'));
}


/**
 * Envia uma notificação push para um dispositivo específico usando a API v1 do Firebase.
 *
 * @param string $device_token O token do dispositivo alvo.
 * @param string $title O título da notificação.
 * @param string $body O corpo da mensagem da notificação.
 * @param array $data_payload Dados extras para enviar com a notificação (ex: { 'openUrl': '/diary.php' }).
 * @return array A resposta do servidor Firebase ou um array de erro.
 */
function sendPushNotificationV1($device_token, $title, $body, $data_payload = []) {
    global $credentials_path, $project_id;

    try {
        $access_token = get_firebase_access_token($credentials_path);

        $url = "https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send";
        
        $message = [
            'message' => [
                'token' => $device_token,
                'notification' => [ 'title' => $title, 'body' => $body ],
                'data' => (object)$data_payload, // O campo 'data' espera um objeto
            ],
        ];

        $headers = [ 'Authorization: Bearer ' . $access_token, 'Content-Type: application/json' ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => json_encode($message),
            CURLOPT_SSL_VERIFYPEER => true, // Mais seguro para produção
        ]);
        $result = curl_exec($ch);
        curl_close($ch);

        return json_decode($result, true);

    } catch (Exception $e) {
        // Em caso de erro, retorna um array com a mensagem de erro
        error_log("Erro ao enviar notificação Push: " . $e->getMessage()); // Loga o erro no servidor
        return ['error' => true, 'message' => $e->getMessage()];
    }
}


echo "--- MODO DE TESTE MANUAL ---<br>";

// ID do usuário que você quer testar
$target_user_id = 32;

$stmt = $conn->prepare("SELECT push_token FROM sf_users WHERE id = ?");
$stmt->bind_param("i", $target_user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($user && !empty($user['push_token'])) {
    $user_push_token = $user['push_token'];
    $notification_title = "Teste Manual";
    $notification_body = "Esta é uma notificação de teste disparada manualmente.";
    $extra_data = [ 'openUrl' => BASE_APP_URL . '/main_app.php' ];

    echo "Tentando enviar notificação v1 para o token: " . substr($user_push_token, 0, 30) . "...<br>";
    $response = sendPushNotificationV1($user_push_token, $notification_title, $notification_body, $extra_data);

    echo "Resposta do Firebase v1: <pre>";
    print_r($response);
    echo "</pre>";

} else {
    echo "Usuário #{$target_user_id} não encontrado ou não possui um token de notificação.";
}
*/
?>