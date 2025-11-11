<?php
// admin/checkin_responses.php - Visualizar respostas dos check-ins

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'checkin';
$page_title = 'Respostas do Check-in';

$admin_id = $_SESSION['admin_id'] ?? 1;
$checkin_id = (int)($_GET['id'] ?? 0);

if ($checkin_id <= 0) {
    header("Location: checkin.php");
    exit;
}

// Buscar check-in
$stmt = $conn->prepare("SELECT * FROM sf_checkin_configs WHERE id = ? AND admin_id = ?");
$stmt->bind_param("ii", $checkin_id, $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: checkin.php");
    exit;
}

$checkin = $result->fetch_assoc();
$stmt->close();

// Buscar perguntas
$stmt = $conn->prepare("SELECT * FROM sf_checkin_questions WHERE config_id = ? ORDER BY order_index ASC");
$stmt->bind_param("i", $checkin_id);
$stmt->execute();
$questions_result = $stmt->get_result();
$questions = [];
while ($row = $questions_result->fetch_assoc()) {
    $row['options'] = !empty($row['options']) ? json_decode($row['options'], true) : null;
    $questions[$row['id']] = $row;
}
$stmt->close();

// Buscar usuários que responderam
$responses_query = "
    SELECT DISTINCT 
        u.id as user_id,
        u.name as user_name,
        u.email,
        up.profile_image_filename,
        ca.completed_at,
        DATE(ca.completed_at) as response_date
    FROM sf_checkin_responses cr
    INNER JOIN sf_users u ON cr.user_id = u.id
    LEFT JOIN sf_user_profiles up ON u.id = up.user_id
    INNER JOIN sf_checkin_availability ca ON ca.config_id = cr.config_id AND ca.user_id = cr.user_id
    WHERE cr.config_id = ?
    AND ca.is_completed = 1
    GROUP BY u.id, DATE(ca.completed_at)
    ORDER BY ca.completed_at DESC
";

$stmt = $conn->prepare($responses_query);
$stmt->bind_param("i", $checkin_id);
$stmt->execute();
$users_result = $stmt->get_result();
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $user_id = $row['user_id'];
    $response_date = $row['response_date'];
    $key = $user_id . '_' . $response_date;
    
    if (!isset($users[$key])) {
        $users[$key] = $row;
        $users[$key]['responses'] = [];
    }
    
    // Buscar respostas deste usuário para esta data
    $resp_stmt = $conn->prepare("
        SELECT question_id, response_text, response_value, submitted_at
        FROM sf_checkin_responses
        WHERE config_id = ? AND user_id = ? AND DATE(submitted_at) = ?
        ORDER BY submitted_at ASC
    ");
    $resp_stmt->bind_param("iis", $checkin_id, $user_id, $response_date);
    $resp_stmt->execute();
    $resp_result = $resp_stmt->get_result();
    while ($resp = $resp_result->fetch_assoc()) {
        $users[$key]['responses'][$resp['question_id']] = $resp;
    }
    $resp_stmt->close();
}
$stmt->close();

require_once __DIR__ . '/includes/header.php';
?>

<style>
.checkin-responses-page {
    padding: 1.5rem 2rem;
}

.header-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 20px !important;
    padding: 1.5rem !important;
    margin-bottom: 2rem !important;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--accent-orange);
    text-decoration: none;
    margin-bottom: 1rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.back-link:hover {
    color: #e55a00;
    transform: translateX(-4px);
}

.user-response-card {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 16px !important;
    padding: 1.5rem !important;
    margin-bottom: 1.5rem !important;
}

.user-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--glass-border);
}

.user-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-orange);
    font-weight: 700;
    font-size: 1.1rem;
    flex-shrink: 0;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.user-info h3 {
    margin: 0 0 4px 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
}

.user-info p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--text-secondary);
}

.response-item {
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.response-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.response-question {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

.response-answer {
    background: rgba(255, 107, 0, 0.1);
    border-left: 3px solid var(--accent-orange);
    padding: 1rem;
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.95rem;
    line-height: 1.6;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}
</style>

<div class="checkin-responses-page">
    <a href="checkin.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Voltar para Check-ins
    </a>

    <div class="header-card">
        <h2><?php echo htmlspecialchars($checkin['name']); ?></h2>
        <p style="color: var(--text-secondary); margin-top: 0.5rem;">
            <?php echo htmlspecialchars($checkin['description'] ?? ''); ?>
        </p>
    </div>

    <?php if (empty($users)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>Nenhuma resposta ainda</h3>
            <p>Os pacientes ainda não responderam este check-in.</p>
        </div>
    <?php else: ?>
        <?php foreach ($users as $user): ?>
            <div class="user-response-card">
                <div class="user-header">
                    <div class="user-avatar">
                        <?php if (!empty($user['profile_image_filename']) && file_exists(APP_ROOT_PATH . '/assets/images/users/' . $user['profile_image_filename'])): ?>
                            <img src="<?php echo BASE_APP_URL . '/assets/images/users/' . htmlspecialchars($user['profile_image_filename']); ?>" alt="<?php echo htmlspecialchars($user['user_name']); ?>">
                        <?php else: ?>
                            <?php
                            $name_parts = explode(' ', trim($user['user_name']));
                            $initials = count($name_parts) > 1 
                                ? strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1)) 
                                : (!empty($name_parts[0]) ? strtoupper(substr($name_parts[0], 0, 2)) : 'U');
                            echo $initials;
                            ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <h3><?php echo htmlspecialchars($user['user_name']); ?></h3>
                        <p>
                            <i class="fas fa-calendar"></i> 
                            <?php 
                            $date = new DateTime($user['completed_at']);
                            echo $date->format('d/m/Y H:i');
                            ?>
                        </p>
                    </div>
                </div>

                <div class="responses-list">
                    <?php foreach ($questions as $question_id => $question): ?>
                        <?php if (isset($user['responses'][$question_id])): ?>
                            <?php $response = $user['responses'][$question_id]; ?>
                            <div class="response-item">
                                <div class="response-question">
                                    <?php echo htmlspecialchars($question['question_text']); ?>
                                </div>
                                <div class="response-answer">
                                    <?php 
                                    if (!empty($response['response_text'])) {
                                        echo nl2br(htmlspecialchars($response['response_text']));
                                    } elseif (!empty($response['response_value'])) {
                                        echo htmlspecialchars($response['response_value']);
                                    } else {
                                        echo '<em>Sem resposta</em>';
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

