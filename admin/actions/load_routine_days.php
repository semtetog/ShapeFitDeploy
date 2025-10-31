<?php
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/functions_admin.php';
require_once __DIR__ . '/../../includes/functions.php';
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

try {
    // Buscar total de missões do dia para calcular progresso
    $stmt_profile = $conn->prepare("SELECT * FROM sf_user_profiles WHERE user_id = ?");
    $stmt_profile->bind_param("i", $user_id);
    $stmt_profile->execute();
    $user_profile = $stmt_profile->get_result()->fetch_assoc();
    $stmt_profile->close();
    
    $all_missions = getRoutineItemsForUser($conn, $user_id, $requestedDate, $user_profile);
    $total_missions = count($all_missions);
    
    // Buscar missões fixas concluídas do dia
    $completed_missions = [];
    
    $stmt_routine_log = $conn->prepare("
        SELECT 
            uri.id,
            uri.title,
            uri.icon_class,
            pl.timestamp as completion_time,
            uri.exercise_type,
            CASE 
                WHEN uri.exercise_type = 'sleep' THEN udt.sleep_hours
                WHEN uri.exercise_type = 'duration' THEN ued.duration_minutes
                ELSE NULL
            END as duration_minutes
        FROM sf_user_routine_log url
        JOIN sf_user_routine_items uri ON url.routine_item_id = uri.id
        LEFT JOIN sf_user_points_log pl ON pl.user_id = url.user_id 
            AND pl.action_key = 'ROUTINE_COMPLETE' 
            AND CAST(pl.action_context_id AS UNSIGNED) = url.routine_item_id
            AND DATE(pl.timestamp) = url.date
        LEFT JOIN sf_user_daily_tracking udt ON udt.user_id = url.user_id 
            AND udt.date = url.date 
            AND uri.exercise_type = 'sleep'
        LEFT JOIN sf_user_exercise_durations ued ON ued.user_id = url.user_id 
            AND uri.exercise_type = 'duration'
            AND ued.exercise_name COLLATE utf8mb4_unicode_ci = uri.title COLLATE utf8mb4_unicode_ci
        WHERE url.user_id = ? 
            AND url.date = ? 
            AND url.is_completed = 1
        ORDER BY pl.timestamp ASC
    ");
    
    if ($stmt_routine_log) {
        $stmt_routine_log->bind_param("is", $user_id, $requestedDate);
        if ($stmt_routine_log->execute()) {
            $result_routine_log = $stmt_routine_log->get_result();
            while ($row = $result_routine_log->fetch_assoc()) {
                $completed_missions[] = $row;
            }
        } else {
            error_log('[load_routine_days] Erro ao executar query routine_log: ' . $stmt_routine_log->error);
        }
        $stmt_routine_log->close();
    }
    
    // Buscar missões de onboarding concluídas (exercícios)
    // Nota: sf_user_onboarding_completion não tem timestamp de conclusão, então usamos o timestamp do points_log
    $stmt_onboarding = $conn->prepare("
        SELECT 
            uoc.activity_name as title,
            'fa-dumbbell' as icon_class,
            COALESCE(pl.timestamp, ued.updated_at) as completion_time,
            ued.duration_minutes
        FROM sf_user_onboarding_completion uoc
        LEFT JOIN sf_user_exercise_durations ued ON uoc.user_id = ued.user_id 
            AND uoc.activity_name COLLATE utf8mb4_unicode_ci = ued.exercise_name COLLATE utf8mb4_unicode_ci
        LEFT JOIN sf_user_points_log pl ON pl.user_id = uoc.user_id 
            AND pl.action_key = 'ROUTINE_COMPLETE' 
            AND pl.action_context_id COLLATE utf8mb4_unicode_ci = uoc.activity_name COLLATE utf8mb4_unicode_ci
            AND DATE(pl.timestamp) = uoc.completion_date
        WHERE uoc.user_id = ? 
            AND uoc.completion_date = ?
        ORDER BY completion_time ASC
    ");
    
    if ($stmt_onboarding) {
        $stmt_onboarding->bind_param("is", $user_id, $requestedDate);
        if ($stmt_onboarding->execute()) {
            $result_onboarding = $stmt_onboarding->get_result();
            while ($row = $result_onboarding->fetch_assoc()) {
                $completed_missions[] = $row;
            }
        } else {
            error_log('[load_routine_days] Erro ao executar query onboarding: ' . $stmt_onboarding->error);
        }
        $stmt_onboarding->close();
    }
    
    // Ordenar por horário de conclusão
    usort($completed_missions, function($a, $b) {
        $time_a = strtotime($a['completion_time'] ?? '9999-12-31 23:59:59');
        $time_b = strtotime($b['completion_time'] ?? '9999-12-31 23:59:59');
        return $time_a <=> $time_b;
    });
    
} catch (Exception $e) {
    error_log('[load_routine_days] Erro: ' . $e->getMessage());
    http_response_code(500);
    die('Erro ao carregar rotina: ' . $e->getMessage());
}
?>

<div class="routine-content-day" data-date="<?php echo $requestedDate; ?>" data-missions="<?php echo count($completed_missions); ?>" data-total="<?php echo $total_missions; ?>">
    <div class="routine-day-missions">
        <?php if (empty($completed_missions)): ?>
            <div class="diary-empty-state">
                <i class="fas fa-calendar-day"></i>
                <p>Nenhum registro neste dia</p>
            </div>
        <?php else: ?>
            <?php foreach ($completed_missions as $mission): 
                $completion_time = $mission['completion_time'] ?? null;
                $time_display = '';
                if ($completion_time) {
                    $time_display = date('H:i', strtotime($completion_time));
                }
            ?>
                <div class="diary-meal-card">
                    <div class="diary-meal-header">
                        <div class="diary-meal-icon">
                            <i class="fas <?php echo htmlspecialchars($mission['icon_class'] ?? 'fa-check'); ?>"></i>
                        </div>
                        <div class="diary-meal-info">
                            <h5 style="margin: 0;"><?php echo htmlspecialchars($mission['title'] ?? 'Missão'); ?></h5>
                            <span class="diary-meal-totals">
                                <?php if ($time_display): ?>
                                    <span style="font-size: 0.85rem; color: var(--accent-orange); font-weight: 500; white-space: nowrap;">
                                        <i class="fas fa-clock" style="margin-right: 4px;"></i><?php echo $time_display; ?>
                                    </span>
                                    <?php if (isset($mission['duration_minutes']) && $mission['duration_minutes']): ?>
                                        <span style="font-size: 0.85rem; color: var(--text-secondary); font-weight: 400; margin-left: 8px;">
                                            <?php if (isset($mission['exercise_type']) && $mission['exercise_type'] === 'sleep'): ?>
                                                Duração: <?php echo round($mission['duration_minutes'], 1); ?>h de sono
                                            <?php else: ?>
                                                Duração: <?php echo $mission['duration_minutes']; ?>min
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>


