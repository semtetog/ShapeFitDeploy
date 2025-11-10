<?php
/**
 * API para gerenciar notificações de desafios
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

$action = $data['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'mark_as_read':
            $notification_id = (int)($data['notification_id'] ?? 0);
            if ($notification_id <= 0) {
                throw new Exception('ID de notificação inválido');
            }
            markNotificationAsRead($conn, $notification_id, $user_id);
            echo json_encode(['success' => true, 'message' => 'Notificação marcada como lida']);
            break;
            
        case 'get_notifications':
            $limit = (int)($data['limit'] ?? 10);
            $notifications = getChallengeNotifications($conn, $user_id, $limit);
            echo json_encode(['success' => true, 'notifications' => $notifications]);
            break;
            
        case 'mark_all_as_read':
            $stmt = $conn->prepare("
                UPDATE sf_challenge_notifications
                SET is_read = 1
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Todas as notificações marcadas como lidas']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

