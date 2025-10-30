<?php
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions_admin.php';
$conn = require __DIR__ . '/../../includes/db.php';

// Verificar se admin está logado (simplificado para AJAX)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die('Acesso não autorizado');
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$requestedDate = $_GET['date'] ?? date('Y-m-d');

// Buscar dados do perfil do usuário
$stmt_profile = $conn->prepare("SELECT * FROM sf_user_profiles WHERE user_id = ?");
$stmt_profile->bind_param("i", $user_id);
$stmt_profile->execute();
$user_profile = $stmt_profile->get_result()->fetch_assoc();
$stmt_profile->close();

// Buscar missões concluídas do dia
require_once __DIR__ . '/../../../includes/functions.php';
$day_missions = getRoutineItemsForUser($conn, $user_id, $requestedDate, $user_profile);
$completed_missions = array_filter($day_missions, function($mission) {
    return $mission['completion_status'] == 1;
});
$total_missions = count($day_missions);
?>

<div class="routine-content-day" data-date="<?php echo $requestedDate; ?>" data-missions="<?php echo count($completed_missions); ?>">
    <div class="routine-day-missions">
        <?php if (empty($completed_missions)): ?>
            <div class="diary-empty-state">
                <i class="fas fa-calendar-day"></i>
                <p>Nenhum registro neste dia</p>
            </div>
        <?php else: ?>
            <?php foreach ($completed_missions as $mission): ?>
                <div class="diary-meal-card">
                    <div class="diary-meal-header">
                        <div class="diary-meal-icon">
                            <i class="fas <?php echo htmlspecialchars($mission['icon_class'] ?? 'fa-check'); ?>"></i>
                        </div>
                        <div class="diary-meal-info">
                            <h5 style="margin: 0;"><?php echo htmlspecialchars($mission['title'] ?? 'Missão'); ?></h5>
                            <span class="diary-meal-totals">
                                <strong><?php echo isset($mission['duration_minutes']) && $mission['duration_minutes'] ? $mission['duration_minutes'] . 'min' : 'Concluída'; ?></strong>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php 
$conn->close();
?>

