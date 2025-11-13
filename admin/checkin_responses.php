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

// Buscar dados do admin que criou o check-in (para foto do bot)
$admin_creator_id = (int)($checkin['admin_id'] ?? 0);
$admin_data = null;
if ($admin_creator_id > 0) {
    $stmt_admin = $conn->prepare("SELECT id, full_name, profile_image_filename FROM sf_admins WHERE id = ?");
    $stmt_admin->bind_param("i", $admin_creator_id);
    $stmt_admin->execute();
    $admin_result = $stmt_admin->get_result();
    if ($admin_result->num_rows > 0) {
        $admin_data = $admin_result->fetch_assoc();
    }
    $stmt_admin->close();
}

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

// Processar filtro de datas
$date_filter = $_GET['date_filter'] ?? 'all';
$date_condition = "";

switch ($date_filter) {
    case 'last_7_days':
        $date_condition = "AND DATE(cr.submitted_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'this_week':
        $date_condition = "AND YEARWEEK(cr.submitted_at, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'last_week':
        $date_condition = "AND YEARWEEK(cr.submitted_at, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 7 DAY), 1)";
        break;
    case 'this_month':
        $date_condition = "AND YEAR(cr.submitted_at) = YEAR(CURDATE()) AND MONTH(cr.submitted_at) = MONTH(CURDATE())";
        break;
    case 'last_month':
        $date_condition = "AND YEAR(cr.submitted_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(cr.submitted_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
        break;
    default:
        $date_condition = "";
}

// Buscar usuários que responderam
// IMPORTANTE: Buscar respostas diretamente da tabela sf_checkin_responses
// sem depender de is_completed, para manter histórico completo mesmo após reset
$responses_query = "
    SELECT DISTINCT 
        u.id as user_id,
        u.name as user_name,
        u.email,
        up.profile_image_filename,
        DATE(cr.submitted_at) as response_date,
        MAX(cr.submitted_at) as completed_at
    FROM sf_checkin_responses cr
    INNER JOIN sf_users u ON cr.user_id = u.id
    LEFT JOIN sf_user_profiles up ON u.id = up.user_id
    WHERE cr.config_id = ?
    $date_condition
    GROUP BY u.id, DATE(cr.submitted_at)
    ORDER BY completed_at DESC
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
        
        // Não buscar primeira resposta para preview (removido conforme solicitado)
    }
    
    // Buscar todas as respostas deste usuário para esta data
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

// Contar total de respostas
$total_count = count($users);

require_once __DIR__ . '/includes/header.php';
?>

<style>
.checkin-responses-page {
    padding: 1.5rem 2rem;
    min-height: 100vh;
}

.checkin-responses-page * {
    box-shadow: none !important;
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

.header-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.header-title h2 {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

.header-title p {
    color: var(--text-secondary);
    font-size: 0.95rem;
    margin: 0.5rem 0 0 0;
}

/* Filtros */
.filters-section {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 16px !important;
    padding: 1.25rem !important;
    margin-bottom: 1.5rem !important;
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-group label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-secondary);
    white-space: nowrap;
}

.custom-select-wrapper {
    position: relative;
    min-width: 180px;
}

.custom-select {
    position: relative;
}

.custom-select-trigger {
    width: 100%;
    padding: 0.625rem 0.875rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.875rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
}

.custom-select-trigger:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.custom-select-trigger.active {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.custom-select-options {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: rgba(30, 30, 30, 0.98);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    overflow: hidden;
    z-index: 1000;
    display: none;
    max-height: 300px;
    overflow-y: auto;
}

.custom-select-options.active {
    display: block;
}

.custom-select-option {
    padding: 0.75rem 0.875rem;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.custom-select-option:last-child {
    border-bottom: none;
}

.custom-select-option:hover {
    background: rgba(255, 107, 0, 0.1);
    color: var(--accent-orange);
}

.custom-select-option.selected {
    background: rgba(255, 107, 0, 0.15);
    color: var(--accent-orange);
    font-weight: 600;
}

.submissions-count {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.2);
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--accent-orange);
    line-height: 1.5;
    box-sizing: border-box;
    height: 40px;
    min-height: 40px;
    max-height: 40px;
}

.submissions-count .badge {
    background: var(--accent-orange);
    color: white;
    padding: 6px;
    border-radius: 50%;
    font-size: 0.75rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    box-sizing: border-box;
    flex-shrink: 0;
    aspect-ratio: 1;
    min-width: 24px;
    min-height: 24px;
}

/* Tabela com scroll horizontal */
.table-container {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid var(--glass-border) !important;
    border-radius: 16px !important;
    overflow: hidden;
    margin-bottom: 2rem;
}

.table-wrapper {
    overflow-x: auto;
    overflow-y: visible;
    width: 100%;
}

.responses-table {
    width: 100%;
    border-collapse: collapse;
    border-spacing: 0;
    min-width: 800px;
}

.responses-table th:first-child {
    width: auto;
    min-width: 180px;
}

.responses-table th:nth-child(2) {
    width: auto;
    min-width: 0;
}

/* FIX — Diminuir espaçamento entre as colunas */
.responses-table {
    min-width: 0 !important;
}

.responses-table th:nth-child(3),
.responses-table td:nth-child(3) {
    width: 60px !important;
    min-width: 60px !important;
    text-align: center !important;
}

/* Coluna da data e nome mais próximas */
.responses-table th:first-child,
.responses-table td:first-child {
    min-width: 120px !important;
}

.responses-table th:nth-child(2),
.responses-table td:nth-child(2) {
    min-width: 160px !important;
}

.responses-table thead {
    background: rgba(255, 255, 255, 0.05);
    position: sticky;
    top: 0;
    z-index: 10;
}

.responses-table th {
    padding: 1rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-secondary);
    border-bottom: 1px solid var(--glass-border);
    white-space: nowrap;
}

.responses-table th:first-child {
    padding: 1rem 0.75rem 1rem 1rem;
    position: relative;
    border-left: none !important;
    margin-left: 0 !important;
    padding-left: 1rem !important;
}

.responses-table th:first-child::after {
    content: '';
    position: absolute;
    right: 0.375rem;
    top: 15%;
    bottom: 15%;
    width: 1px;
    background: linear-gradient(to bottom, 
        transparent 0%, 
        rgba(255, 255, 255, 0.15) 20%, 
        rgba(255, 255, 255, 0.3) 50%, 
        rgba(255, 255, 255, 0.15) 80%, 
        transparent 100%);
}

.responses-table th:nth-child(2) {
    padding: 1rem 0.5rem 1rem 0.75rem;
}


.responses-table th:nth-child(2) {
    width: auto;
    min-width: 0;
}

.responses-table tbody tr {
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    border-left: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.responses-table tbody tr:hover {
    background: rgba(255, 107, 0, 0.05);
}

.responses-table td {
    padding: 1rem 0;
    font-size: 0.875rem;
    color: var(--text-primary);
    vertical-align: middle;
}

.responses-table td:first-child {
    padding: 1rem 0.2rem 1rem 1rem;
    position: relative;
}

.responses-table td:first-child::after {
    content: '';
    position: absolute;
    right: 0.1rem;
    top: 15%;
    bottom: 15%;
    width: 1px;
    background: linear-gradient(to bottom, 
        transparent 0%, 
        rgba(255, 255, 255, 0.15) 20%, 
        rgba(255, 255, 255, 0.3) 50%, 
        rgba(255, 255, 255, 0.15) 80%, 
        transparent 100%);
}

.responses-table td:nth-child(2) {
    padding: 1rem 0.5rem 1rem 0.2rem;
}

.responses-table td:first-child .table-date {
    margin: 0;
    padding: 0;
    gap: 0.5rem;
    width: 100%;
    justify-content: flex-start;
}

.responses-table td:nth-child(2) .table-user {
    margin: 0;
    padding: 0;
    gap: 0.75rem;
    width: 100%;
    justify-content: flex-start;
}

.table-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--accent-orange);
}

.table-date {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.table-date i {
    font-size: 0.75rem;
    opacity: 0.6;
}

.table-user {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.table-user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-orange);
    font-weight: 700;
    font-size: 0.75rem;
    flex-shrink: 0;
}

.table-user-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.table-user-name {
    font-weight: 600;
    color: var(--text-primary);
    white-space: nowrap;
    overflow: visible;
    text-overflow: clip;
}

.btn-delete-response {
    background: rgba(239, 68, 68, 0.1);
    color: #EF4444;
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 8px;
    padding: 0;
    font-size: 0.875rem;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    width: 36px;
    height: 36px;
    line-height: 1;
}

.btn-delete-response i {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
}

.btn-delete-response:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: #EF4444;
    color: #EF4444;
    transform: translateY(-1px);
}

.btn-delete-response:active {
    transform: translateY(0);
}

.table-preview {
    color: var(--text-secondary);
    font-size: 0.875rem;
    line-height: 1.5;
    max-width: 400px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
}

/* Modais Customizados (estilo admin) */
.custom-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    z-index: 999999;
    align-items: center;
    justify-content: center;
}

.custom-modal.active {
    display: flex !important;
}

.custom-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
}

.custom-modal-content {
    position: relative;
    background: linear-gradient(135deg, rgba(30, 30, 30, 0.98) 0%, rgba(20, 20, 20, 0.98) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 0;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
    transform: scale(0.9);
    transition: transform 0.3s ease;
    z-index: 2;
}

.custom-modal.active .custom-modal-content {
    transform: scale(1);
}

.custom-modal-content.custom-modal-small {
    max-width: 400px;
}

.custom-modal-header {
    padding: 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    align-items: center;
    gap: 1rem;
    color: var(--accent-orange);
}

.custom-modal-header i {
    font-size: 1.75rem;
}

.custom-modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
}

.custom-modal-body {
    padding: 2rem;
}

.custom-modal-body p {
    margin: 0 0 1rem 0;
    color: var(--text-secondary);
    line-height: 1.6;
}

.custom-modal-body p:last-child {
    margin-bottom: 0;
}

.custom-modal-body p strong {
    color: var(--text-primary);
    font-weight: 600;
}

.custom-modal-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.btn-modal-cancel,
.btn-modal-primary,
.btn-modal-danger {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border: none;
}

.btn-modal-cancel {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-modal-cancel:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.2);
}

.btn-modal-primary {
    background: var(--accent-orange);
    color: white;
}

.btn-modal-primary:hover {
    background: var(--accent-orange-hover);
    transform: translateY(-1px);
}

.btn-modal-danger {
    background: rgba(244, 67, 54, 0.15);
    color: var(--danger-red);
    border: 1px solid rgba(244, 67, 54, 0.4);
}

.btn-modal-danger:hover {
    background: rgba(244, 67, 54, 0.25);
    border-color: var(--danger-red);
    color: var(--danger-red);
    transform: translateY(-1px);
}

/* FORÇA A TABELA A NÃO TER LARGURA MÍNIMA */
.responses-table {
    min-width: unset !important;
    width: 100% !important;
    table-layout: auto !important;
}

/* COLUNA DATA - AJUSTE PERFEITO */
.responses-table th:first-child,
.responses-table td:first-child {
    width: 165px !important;
    min-width: 165px !important;
    max-width: 165px !important;
    white-space: nowrap !important;
    padding-right: 0.75rem !important;
    position: relative;
}

/* IMPEDIR QUE O FLEX APERTE A DATA - PACOTE GARANTIDO */
.responses-table td:first-child .table-date {
    flex-shrink: 0 !important;
}

.responses-table td:first-child .table-date span {
    white-space: nowrap !important;
    flex-shrink: 0 !important;
}

/* ZERO RISCOS - Qualquer span na primeira coluna */
.responses-table td:first-child span {
    white-space: nowrap !important;
    flex-shrink: 0 !important;
}

.responses-table td:first-child::after {
    content: '';
    position: absolute;
    right: 0.375rem;
    top: 15%;
    bottom: 15%;
    width: 1px;
    background: linear-gradient(to bottom, 
        transparent 0%, 
        rgba(255, 255, 255, 0.15) 20%, 
        rgba(255, 255, 255, 0.3) 50%, 
        rgba(255, 255, 255, 0.15) 80%, 
        transparent 100%);
}

/* COLUNA NOME - FLEX */
.responses-table th:nth-child(2),
.responses-table td:nth-child(2) {
    width: auto !important;
    padding-left: 0.75rem !important;
}

/* COLUNA AÇÕES - FIXA */
.responses-table th:nth-child(3),
.responses-table td:nth-child(3) {
    width: 60px !important;
    min-width: 60px !important;
    max-width: 60px !important;
}

/* ZERA QUALQUER ESTICAMENTO */
.table-wrapper {
    width: 100% !important;
    overflow-x: hidden !important;
}

/* Modo de Seleção - Copiado do .submissions-count */
.btn-select-mode {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    color: #3B82F6;
    cursor: pointer;
    transition: all 0.3s ease;
    line-height: 1.5;
    box-sizing: border-box;
    height: 40px;
    min-height: 40px;
    max-height: 40px;
}

.btn-select-mode:hover {
    background: rgba(59, 130, 246, 0.15);
    border-color: rgba(59, 130, 246, 0.3);
    transform: translateY(-1px);
}

.btn-select-mode.active {
    background: rgba(59, 130, 246, 0.15);
    color: #3B82F6;
    border-color: rgba(59, 130, 246, 0.4);
    transform: scale(1.05);
    box-shadow: 0 0 20px rgba(59, 130, 246, 0.8), 0 0 40px rgba(59, 130, 246, 0.6), 0 0 60px rgba(59, 130, 246, 0.4), 0 0 80px rgba(59, 130, 246, 0.2), 0 4px 12px rgba(59, 130, 246, 0.2);
}

.btn-select-mode.active:hover {
    background: rgba(59, 130, 246, 0.2);
    border-color: rgba(59, 130, 246, 0.5);
    transform: scale(1.08);
    box-shadow: 0 0 25px rgba(59, 130, 246, 0.9), 0 0 50px rgba(59, 130, 246, 0.7), 0 0 70px rgba(59, 130, 246, 0.5), 0 0 100px rgba(59, 130, 246, 0.3), 0 6px 16px rgba(59, 130, 246, 0.3);
}

/* Linha selecionada */
.responses-table tbody tr.response-row.selected {
    background: rgba(255, 107, 0, 0.1) !important;
    border-left: 3px solid var(--accent-orange);
    margin-left: 0;
}

.responses-table tbody tr.response-row.selected td:first-child {
    padding-left: calc(1rem - 3px) !important;
}

/* Garantir que o thead não receba estilos de seleção */
.responses-table thead tr,
.responses-table thead th {
    border-left: none !important;
    border-right: none !important;
}

/* Garantir que tbody tr não tenha border-left por padrão */
.responses-table tbody tr {
    border-left: none !important;
}

.response-row.select-mode {
    cursor: pointer;
}

.response-row.select-mode:hover {
    background: rgba(255, 107, 0, 0.05);
}

/* Barra de ações flutuante */
.selection-actions-bar {
    position: fixed;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, rgba(30, 30, 30, 0.98) 0%, rgba(20, 20, 20, 0.98) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 1rem 1.5rem;
    display: none;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
    z-index: 1000;
    backdrop-filter: blur(10px);
}

.selection-actions-bar.active {
    display: flex;
}

.selection-actions-bar .selected-count {
    font-weight: 600;
    color: var(--text-primary);
    margin-right: 0.5rem;
}

.selection-actions-bar .btn-action {
    padding: 0.625rem 1.25rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    border: none;
}

.selection-actions-bar .btn-delete-selected {
    background: rgba(239, 68, 68, 0.15);
    color: #EF4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.selection-actions-bar .btn-delete-selected:hover {
    background: rgba(239, 68, 68, 0.25);
    border-color: #EF4444;
}

.selection-actions-bar .btn-cancel-selection {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.selection-actions-bar .btn-cancel-selection:hover {
    background: rgba(255, 255, 255, 0.1);
}

/* Modal de Chat */
.chat-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(10px);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.chat-modal.active {
    display: flex;
}

.chat-modal-content {
    background: rgba(30, 30, 30, 0.98);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.chat-modal-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--glass-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    position: relative;
}

.chat-modal-tabs {
    display: flex;
    gap: 0.5rem;
    border-bottom: 1px solid var(--glass-border);
    padding: 0 1.5rem;
    flex-shrink: 0;
}

.chat-modal-tab {
    padding: 0.75rem 1rem;
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.chat-modal-tab:hover {
    color: var(--text-primary);
}

.chat-modal-tab.active {
    color: var(--accent-orange);
    border-bottom-color: var(--accent-orange);
}

.chat-modal-tab-content {
    display: none;
}

.chat-modal-tab-content.active {
    display: block;
}

.chat-summary-content {
    padding: 1.5rem;
    line-height: 1.8;
    color: var(--text-primary);
}

.chat-summary-content.loading {
    text-align: center;
    padding: 3rem 1.5rem;
}

.chat-summary-content.loading i {
    font-size: 2rem;
    color: var(--accent-orange);
    animation: spin 1s linear infinite;
}

.chat-summary-content h4 {
    color: var(--accent-orange);
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    font-size: 1rem;
}

.chat-summary-content h4:first-child {
    margin-top: 0;
}

.chat-summary-content p {
    margin-bottom: 1rem;
    color: var(--text-secondary);
}

.chat-summary-content ul {
    margin-left: 1.5rem;
    margin-bottom: 1rem;
    color: var(--text-secondary);
}

.chat-summary-content li {
    margin-bottom: 0.5rem;
}

.chat-modal-header h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.chat-modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
}

.chat-modal-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--accent-orange);
}

.chat-modal-body {
    padding: 1.5rem;
    overflow-y: auto;
    flex: 1;
}

.chat-message {
    margin-bottom: 1.5rem;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.chat-message.bot {
    display: flex;
    gap: 0.75rem;
}

.chat-message.bot .message-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-orange);
    font-size: 0.875rem;
    font-weight: 700;
    flex-shrink: 0;
    overflow: hidden;
}

.chat-message.bot .message-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.chat-message.bot .message-content {
    flex: 1;
}

.chat-message.bot .message-bubble {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 1rem;
    color: var(--text-primary);
    font-size: 0.875rem;
    line-height: 1.6;
}

.chat-message.user {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.chat-message.user .message-content {
    max-width: 80%;
}

.chat-message.user .message-bubble {
    background: rgba(255, 107, 0, 0.15);
    border: 1px solid rgba(255, 107, 0, 0.3);
    border-radius: 12px;
    padding: 1rem;
    color: var(--text-primary);
    font-size: 0.875rem;
    line-height: 1.6;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.chat-message.user .message-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-orange);
    font-size: 0.75rem;
    font-weight: 700;
    flex-shrink: 0;
}

.chat-message.user .message-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.chat-message-time {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-top: 0.5rem;
    padding-left: 0.5rem;
}

.chat-message.user .chat-message-time {
    text-align: right;
    padding-right: 0.5rem;
    padding-left: 0;
}
</style>

<div class="checkin-responses-page">
    <a href="checkin.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Voltar para Check-ins
    </a>

    <div class="header-card">
        <div class="header-title">
            <div>
        <h2><?php echo htmlspecialchars($checkin['name']); ?></h2>
                <p><?php echo htmlspecialchars($checkin['description'] ?? ''); ?></p>
            </div>
        </div>
    </div>

    <div class="filters-section">
        <div class="filter-group">
            <label for="dateFilter">Filtrar por:</label>
            <div class="custom-select-wrapper">
                <div class="custom-select">
                    <div class="custom-select-trigger" id="dateFilterTrigger">
                        <?php
                        $filter_labels = [
                            'all' => 'Todas as datas',
                            'last_7_days' => 'Últimos 7 dias',
                            'this_week' => 'Esta semana',
                            'last_week' => 'Semana passada',
                            'this_month' => 'Este mês',
                            'last_month' => 'Mês passado'
                        ];
                        echo htmlspecialchars($filter_labels[$date_filter] ?? 'Todas as datas');
                        ?>
                        <i class="fas fa-chevron-down" style="font-size: 0.75rem; margin-left: 0.5rem;"></i>
                    </div>
                    <div class="custom-select-options" id="dateFilterOptions">
                        <div class="custom-select-option <?php echo $date_filter === 'all' ? 'selected' : ''; ?>" data-value="all">Todas as datas</div>
                        <div class="custom-select-option <?php echo $date_filter === 'last_7_days' ? 'selected' : ''; ?>" data-value="last_7_days">Últimos 7 dias</div>
                        <div class="custom-select-option <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>" data-value="this_week">Esta semana</div>
                        <div class="custom-select-option <?php echo $date_filter === 'last_week' ? 'selected' : ''; ?>" data-value="last_week">Semana passada</div>
                        <div class="custom-select-option <?php echo $date_filter === 'this_month' ? 'selected' : ''; ?>" data-value="this_month">Este mês</div>
                        <div class="custom-select-option <?php echo $date_filter === 'last_month' ? 'selected' : ''; ?>" data-value="last_month">Mês passado</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="submissions-count">
            <span>Respostas</span>
            <span class="badge"><?php echo $total_count; ?></span>
        </div>
        <button class="btn-select-mode" id="selectModeBtn" onclick="toggleSelectMode()" title="Modo de seleção">
            <i class="fas fa-mouse-pointer"></i>
            <span>Selecionar</span>
        </button>
    </div>

    <?php if (empty($users)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>Nenhuma resposta ainda</h3>
            <p>Os pacientes ainda não responderam este check-in.</p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <div class="table-wrapper">
                <table class="responses-table">
                    <thead>
                        <tr>
                            <th>
                                <i class="fas fa-clock"></i> Enviado em
                            </th>
                            <th>
                                <i class="fas fa-user"></i> Nome
                            </th>
                            <th style="width: 80px; text-align: center;">
                                <i class="fas fa-cog"></i>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $key => $user): ?>
                            <?php
                            $date = new DateTime($user['completed_at']);
                            $formatted_date = $date->format('d/m/Y');
                            $formatted_time = $date->format('H:i');
                            
                            $name_parts = explode(' ', trim($user['user_name']));
                            $initials = count($name_parts) > 1 
                                ? strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1)) 
                                : (!empty($name_parts[0]) ? strtoupper(substr($name_parts[0], 0, 2)) : 'U');
                            ?>
                            <tr data-user-key="<?php echo htmlspecialchars($key); ?>" data-user-id="<?php echo $user['user_id']; ?>" data-response-date="<?php echo $response_date; ?>" class="response-row">
                                <td onclick="handleRowClick('<?php echo htmlspecialchars($key); ?>', event)" style="cursor: pointer;">
                                    <div class="table-date">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo $formatted_date . ',&nbsp;' . $formatted_time; ?></span>
                                    </div>
                                </td>
                                <td onclick="handleRowClick('<?php echo htmlspecialchars($key); ?>', event)" style="cursor: pointer;">
                                    <div class="table-user">
                                        <div class="table-user-avatar">
                                            <?php if (!empty($user['profile_image_filename']) && file_exists(APP_ROOT_PATH . '/assets/images/users/' . $user['profile_image_filename'])): ?>
                                                <img src="<?php echo BASE_APP_URL . '/assets/images/users/' . htmlspecialchars($user['profile_image_filename']); ?>" alt="<?php echo htmlspecialchars($user['user_name']); ?>">
                                            <?php else: ?>
                                                <?php echo $initials; ?>
                        <?php endif; ?>
                    </div>
                                        <span class="table-user-name"><?php echo htmlspecialchars($user['user_name']); ?></span>
                    </div>
                                </td>
                                <td onclick="event.stopPropagation();" style="text-align: center;">
                                    <button class="btn-delete-response" onclick="showDeleteResponseModal('<?php echo htmlspecialchars($key); ?>', '<?php echo htmlspecialchars($user['user_name']); ?>', '<?php echo $formatted_date; ?>')" title="Excluir resposta">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
                </div>

<!-- Modal de Chat -->
<div class="chat-modal" id="chatModal">
    <div class="chat-modal-content">
        <div class="chat-modal-header">
            <h3 id="chatModalUserName"></h3>
            <button class="chat-modal-close" onclick="closeChatModal()" type="button">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="chat-modal-tabs">
            <button class="chat-modal-tab active" data-tab="chat" onclick="switchTab('chat')">
                <i class="fas fa-comments"></i>
                Chat
            </button>
            <button class="chat-modal-tab" data-tab="summary" onclick="switchTab('summary')">
                <i class="fas fa-file-alt"></i>
                Resumo
            </button>
        </div>
        <div class="chat-modal-body">
            <div class="chat-modal-tab-content active" id="chatTabContent">
                <div id="chatModalBody">
                    <!-- Conteúdo do chat será inserido aqui via JavaScript -->
                </div>
            </div>
            <div class="chat-modal-tab-content" id="summaryTabContent">
                <div class="chat-summary-content loading" id="summaryContent">
                    <i class="fas fa-spinner"></i>
                    <p style="margin-top: 1rem;">Gerando resumo...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação de Exclusão -->
<div id="deleteResponseModal" class="custom-modal">
    <div class="custom-modal-overlay" onclick="closeDeleteResponseModal()"></div>
    <div class="custom-modal-content">
        <div class="custom-modal-header" style="color: var(--danger-red);">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Excluir Resposta</h3>
        </div>
        <div class="custom-modal-body">
            <p><strong>ATENÇÃO: Esta ação não pode ser desfeita!</strong></p>
            <p>Tem certeza que deseja excluir permanentemente a resposta de <strong id="delete-response-user-name"></strong> do dia <strong id="delete-response-date"></strong>?</p>
            <p style="color: var(--danger-red); font-weight: 600;">Esta ação é IRREVERSÍVEL!</p>
        </div>
        <div class="custom-modal-footer">
            <button class="btn-modal-cancel" onclick="closeDeleteResponseModal()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button class="btn-modal-danger" onclick="confirmDeleteResponse()">
                <i class="fas fa-trash-alt"></i> Excluir Permanentemente
            </button>
        </div>
    </div>
</div>

<!-- Barra de Ações de Seleção -->
<div id="selectionActionsBar" class="selection-actions-bar">
    <span class="selected-count" id="selectedCount">0 selecionadas</span>
    <button class="btn-action btn-delete-selected" onclick="deleteSelectedResponses()">
        <i class="fas fa-trash-alt"></i>
        Excluir Selecionadas
    </button>
    <button class="btn-action btn-cancel-selection" onclick="toggleSelectMode()">
        <i class="fas fa-times"></i>
        Cancelar
    </button>
</div>

<!-- Modal de Alerta (Sucesso/Erro) -->
<div id="alertModal" class="custom-modal">
    <div class="custom-modal-overlay" onclick="closeAlertModal()"></div>
    <div class="custom-modal-content custom-modal-small">
        <div class="custom-modal-header" id="alertModalHeader">
            <i id="alertModalIcon"></i>
            <h3 id="alertModalTitle"></h3>
        </div>
        <div class="custom-modal-body">
            <p id="alertModalMessage"></p>
        </div>
        <div class="custom-modal-footer">
            <button class="btn-modal-primary" onclick="closeAlertModal()">
                OK
            </button>
        </div>
    </div>
</div>

<script>
// Dados dos usuários para o JavaScript
const usersData = <?php echo json_encode($users); ?>;
const questionsData = <?php echo json_encode($questions); ?>;

// Custom Select
document.addEventListener('DOMContentLoaded', function() {
    const trigger = document.getElementById('dateFilterTrigger');
    const options = document.getElementById('dateFilterOptions');
    const optionItems = options.querySelectorAll('.custom-select-option');
    
    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        options.classList.toggle('active');
        trigger.classList.toggle('active');
    });
    
    optionItems.forEach(option => {
        option.addEventListener('click', function() {
            const value = this.getAttribute('data-value');
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('date_filter', value);
            window.location.href = currentUrl.toString();
        });
    });
    
    document.addEventListener('click', function(e) {
        if (!trigger.contains(e.target) && !options.contains(e.target)) {
            options.classList.remove('active');
            trigger.classList.remove('active');
        }
    });
    
    // Checkboxes removidos conforme solicitado
});

let currentUserKey = null;

function switchTab(tabName) {
    // Atualizar abas
    document.querySelectorAll('.chat-modal-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelector(`.chat-modal-tab[data-tab="${tabName}"]`).classList.add('active');
    
    // Atualizar conteúdo
    document.querySelectorAll('.chat-modal-tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(`${tabName}TabContent`).classList.add('active');
    
    // Se for a aba de resumo e ainda não foi carregado, carregar
    if (tabName === 'summary' && currentUserKey) {
        loadSummary(currentUserKey);
    }
}

function openChatModal(userKey) {
    const user = usersData[userKey];
    if (!user) return;
    
    currentUserKey = userKey;
    
    const modal = document.getElementById('chatModal');
    const modalBody = document.getElementById('chatModalBody');
    const modalUserName = document.getElementById('chatModalUserName');
    
    // Nome do usuário
    modalUserName.textContent = user.user_name;
    
    // Limpar conteúdo anterior
    modalBody.innerHTML = '';
    
    // Resetar para aba de chat
    switchTab('chat');
    
    // Limpar resumo anterior
    const summaryContent = document.getElementById('summaryContent');
    summaryContent.className = 'chat-summary-content loading';
    summaryContent.innerHTML = '<i class="fas fa-spinner"></i><p style="margin-top: 1rem;">Gerando resumo...</p>';
    
    // Criar mensagens do chat
    const questionIds = Object.keys(questionsData).sort((a, b) => {
        return (questionsData[a].order_index || 0) - (questionsData[b].order_index || 0);
    });
    
    questionIds.forEach(questionId => {
        const question = questionsData[questionId];
        const response = user.responses[questionId];
        
        // Mensagem do bot (pergunta) - usar foto do admin
        const botMessage = document.createElement('div');
        botMessage.className = 'chat-message bot';
        
        // Avatar do admin (bot)
                                    <?php 
        $admin_avatar_html = '';
        if ($admin_data) {
            $admin_name = $admin_data['full_name'] ?? '';
            
            // Verificar se tem foto do admin
            $has_admin_photo = false;
            $admin_photo_url = '';
            if (!empty($admin_data['profile_image_filename'])) {
                $admin_photo_path = APP_ROOT_PATH . '/assets/images/users/' . $admin_data['profile_image_filename'];
                if (file_exists($admin_photo_path)) {
                    $admin_photo_url = BASE_APP_URL . '/assets/images/users/' . htmlspecialchars($admin_data['profile_image_filename']);
                    $has_admin_photo = true;
                                    } else {
                    // Tentar thumbnail
                    $admin_thumb_filename = 'thumb_' . $admin_data['profile_image_filename'];
                    $admin_thumb_path = APP_ROOT_PATH . '/assets/images/users/' . $admin_thumb_filename;
                    if (file_exists($admin_thumb_path)) {
                        $admin_photo_url = BASE_APP_URL . '/assets/images/users/' . htmlspecialchars($admin_thumb_filename);
                        $has_admin_photo = true;
                    }
                }
            }
            
            if ($has_admin_photo) {
                $admin_avatar_html = '<img src="' . $admin_photo_url . '" alt="Admin" onerror="this.onerror=null; this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';">';
            } else {
                // Gerar iniciais
                $admin_name_parts = explode(' ', trim($admin_name));
                $admin_initials = count($admin_name_parts) > 1 
                    ? strtoupper(substr($admin_name_parts[0], 0, 1) . substr(end($admin_name_parts), 0, 1)) 
                    : (!empty($admin_name_parts[0]) ? strtoupper(substr($admin_name_parts[0], 0, 2)) : 'A');
                $admin_avatar_html = $admin_initials;
            }
        } else {
            $admin_avatar_html = 'A';
        }
        ?>
        const adminAvatar = <?php echo json_encode($admin_avatar_html); ?>;
        
        botMessage.innerHTML = `
            <div class="message-avatar">${adminAvatar}</div>
            <div class="message-content">
                <div class="message-bubble">${escapeHtml(question.question_text)}</div>
                                </div>
        `;
        modalBody.appendChild(botMessage);
        
        // Mensagem do usuário (resposta)
        const userMessage = document.createElement('div');
        userMessage.className = 'chat-message user';
        
        let responseText = 'Sem resposta';
        if (response) {
            if (response.response_text) {
                responseText = response.response_text;
            } else if (response.response_value) {
                responseText = response.response_value;
            }
        }
        
        const userAvatar = user.profile_image_filename && 
            '<?php echo BASE_APP_URL; ?>/assets/images/users/' + escapeHtml(user.profile_image_filename);
        const nameParts = user.user_name.split(' ');
        const initials = nameParts.length > 1 
            ? (nameParts[0][0] + nameParts[nameParts.length - 1][0]).toUpperCase()
            : (nameParts[0]?.substring(0, 2) || 'U').toUpperCase();
        
        userMessage.innerHTML = `
            <div class="message-content">
                <div class="message-bubble">${escapeHtml(responseText)}</div>
                ${response ? `<div class="chat-message-time">${formatDateTime(response.submitted_at)}</div>` : ''}
                            </div>
            <div class="message-avatar">
                ${userAvatar ? `<img src="${userAvatar}" alt="${escapeHtml(user.user_name)}">` : initials}
                </div>
        `;
        modalBody.appendChild(userMessage);
    });
    
    // Scroll para o final
    setTimeout(() => {
        modalBody.scrollTop = modalBody.scrollHeight;
    }, 100);
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeChatModal() {
    const modal = document.getElementById('chatModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    currentUserKey = null;
}

async function loadSummary(userKey) {
    const user = usersData[userKey];
    if (!user) return;
    
    const summaryContent = document.getElementById('summaryContent');
    summaryContent.className = 'chat-summary-content loading';
    summaryContent.innerHTML = '<i class="fas fa-spinner"></i><p style="margin-top: 1rem;">Gerando resumo...</p>';
    
    try {
        // Construir texto da conversa
        const questionIds = Object.keys(questionsData).sort((a, b) => {
            return (questionsData[a].order_index || 0) - (questionsData[b].order_index || 0);
        });
        
        let conversationText = '';
        let flowInfo = [];
        
        questionIds.forEach(questionId => {
            const question = questionsData[questionId];
            const response = user.responses[questionId];
            if (response && response.response_text) {
                conversationText += `Pergunta: ${question.question_text}\n`;
                conversationText += `Resposta: ${response.response_text}\n\n`;
                
                // Adicionar informações do fluxo
                flowInfo.push({
                    question_text: question.question_text,
                    question_type: question.question_type,
                    options: question.options ? JSON.parse(question.options) : null,
                    response_text: response.response_text
                });
            }
        });
        
        // Fazer requisição para gerar resumo
        const formData = new FormData();
        formData.append('action', 'generate_summary');
        formData.append('conversation', conversationText);
        formData.append('user_name', user.user_name);
        formData.append('user_id', user.user_id);
        formData.append('flow_info', JSON.stringify(flowInfo));
        
        const response = await fetch('<?php echo BASE_ADMIN_URL; ?>/ajax_checkin.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        
        // Verificar se a resposta está vazia ou não é JSON válido
        if (!text || text.trim() === '') {
            throw new Error('Resposta vazia do servidor');
        }
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('Erro ao fazer parse do JSON:', parseError);
            console.error('Resposta recebida:', text.substring(0, 500));
            throw new Error('Resposta inválida do servidor: ' + parseError.message);
        }
        
        if (data.success && data.summary) {
            summaryContent.className = 'chat-summary-content';
            summaryContent.innerHTML = data.summary;
        } else {
            summaryContent.className = 'chat-summary-content';
            const errorMsg = data.message || 'Erro ao gerar resumo. Tente novamente.';
            summaryContent.innerHTML = '<p style="color: var(--danger-red);">' + errorMsg + '</p>';
        }
    } catch (error) {
        console.error('Erro ao carregar resumo:', error);
        summaryContent.className = 'chat-summary-content';
        summaryContent.innerHTML = '<p style="color: var(--danger-red);">Erro ao gerar resumo: ' + error.message + '</p>';
    }
}

// Modo de Seleção
let selectModeActive = false;
let selectedRows = new Set();

function toggleSelectMode() {
    selectModeActive = !selectModeActive;
    const btn = document.getElementById('selectModeBtn');
    const rows = document.querySelectorAll('.response-row');
    const actionsBar = document.getElementById('selectionActionsBar');
    
    if (selectModeActive) {
        btn.classList.add('active');
        btn.innerHTML = '<i class="fas fa-times"></i><span>Cancelar</span>';
        rows.forEach(row => {
            row.classList.add('select-mode');
        });
    } else {
        btn.classList.remove('active');
        btn.innerHTML = '<i class="fas fa-mouse-pointer"></i><span>Selecionar</span>';
        rows.forEach(row => {
            row.classList.remove('select-mode', 'selected');
        });
        selectedRows.clear();
        if (actionsBar) {
            actionsBar.classList.remove('active');
        }
        updateSelectionCount();
    }
}

function handleRowClick(userKey, event) {
    if (selectModeActive) {
        event.stopPropagation();
        const row = document.querySelector(`tr[data-user-key="${userKey}"]`);
        if (!row) return;
        
        if (selectedRows.has(userKey)) {
            selectedRows.delete(userKey);
            row.classList.remove('selected');
        } else {
            selectedRows.add(userKey);
            row.classList.add('selected');
        }
        updateSelectionCount();
    } else {
        openChatModal(userKey);
    }
}

function updateSelectionCount() {
    const count = selectedRows.size;
    const actionsBar = document.getElementById('selectionActionsBar');
    const countSpan = document.getElementById('selectedCount');
    
    if (count > 0) {
        if (actionsBar) actionsBar.classList.add('active');
        if (countSpan) countSpan.textContent = `${count} selecionada${count > 1 ? 's' : ''}`;
    } else {
        if (actionsBar) actionsBar.classList.remove('active');
    }
}

function deleteSelectedResponses() {
    if (selectedRows.size === 0) {
        showAlertModal('Aviso', 'Nenhuma resposta selecionada.', false);
        return;
    }
    
    const count = selectedRows.size;
    const userName = 'as respostas selecionadas';
    const responseDate = `${count} resposta${count > 1 ? 's' : ''}`;
    
    // Preparar modal de confirmação para múltiplas exclusões
    document.getElementById('delete-response-user-name').textContent = userName;
    document.getElementById('delete-response-date').textContent = responseDate;
    
    // Armazenar as chaves selecionadas
    window.selectedRowsToDelete = Array.from(selectedRows);
    
    showDeleteResponseModal('bulk', userName, responseDate);
}

function confirmDeleteBulkResponse() {
    if (!window.selectedRowsToDelete || window.selectedRowsToDelete.length === 0) {
        showAlertModal('Erro', 'Nenhuma resposta selecionada para exclusão.', false);
        return;
    }
    
    const rowsToDelete = window.selectedRowsToDelete;
    const checkinId = <?php echo $checkin_id; ?>;
    let deletedCount = 0;
    let errorCount = 0;
    
    closeDeleteResponseModal();
    
    // Processar exclusões uma por uma
    Promise.all(rowsToDelete.map(async (userKey) => {
        const row = document.querySelector(`tr[data-user-key="${userKey}"]`);
        if (!row) return false;
        
        const userId = row.getAttribute('data-user-id');
        const responseDate = row.getAttribute('data-response-date');
        
        try {
            const formData = new FormData();
            formData.append('action', 'delete_response');
            formData.append('user_id', userId);
            formData.append('config_id', checkinId);
            formData.append('response_date', responseDate);
            
            const response = await fetch('<?php echo BASE_ADMIN_URL; ?>/ajax_checkin.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            
            const text = await response.text();
            const data = JSON.parse(text);
            
            if (data.success) {
                if (row && row.parentNode) {
                    row.parentNode.removeChild(row);
                }
                return true;
            } else {
                return false;
            }
        } catch (error) {
            console.error('Erro ao excluir resposta:', error);
            return false;
        }
    })).then(results => {
        deletedCount = results.filter(r => r === true).length;
        errorCount = results.filter(r => r === false).length;
        
        // Atualizar contador
        const badge = document.querySelector('.submissions-count .badge');
        if (badge) {
            const currentCount = parseInt(badge.textContent) || 0;
            badge.textContent = Math.max(0, currentCount - deletedCount);
        }
        
        // Sair do modo de seleção
        if (selectModeActive) {
            toggleSelectMode();
        }
        
        if (errorCount > 0) {
            showAlertModal('Aviso', `${deletedCount} resposta(s) excluída(s) com sucesso. ${errorCount} erro(s) ocorreram.`, false);
        } else {
            showAlertModal('Sucesso', `${deletedCount} resposta(s) excluída(s) com sucesso!`, true);
        }
    });
}

// Variáveis globais para exclusão
let currentResponseToDelete = null;

function showDeleteResponseModal(userKey, userName, responseDate) {
    currentResponseToDelete = userKey;
    
    const nameEl = document.getElementById('delete-response-user-name');
    const dateEl = document.getElementById('delete-response-date');
    
    if (nameEl) nameEl.textContent = userName;
    if (dateEl) dateEl.textContent = responseDate;
    
    // Ajustar texto do modal para exclusão em massa
    if (userKey === 'bulk') {
        const modalBody = document.querySelector('#deleteResponseModal .custom-modal-body');
        if (modalBody) {
            modalBody.innerHTML = `
                <p><strong>ATENÇÃO: Esta ação não pode ser desfeita!</strong></p>
                <p>Tem certeza que deseja excluir permanentemente <strong>${responseDate}</strong>?</p>
                <p style="color: var(--danger-red); font-weight: 600;">Esta ação é IRREVERSÍVEL!</p>
            `;
        }
    }
    
    const modal = document.getElementById('deleteResponseModal');
    if (modal) {
        modal.classList.add('active');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeDeleteResponseModal() {
    const modal = document.getElementById('deleteResponseModal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }
    document.body.style.overflow = '';
    currentResponseToDelete = null;
}

async function confirmDeleteResponse() {
    if (!currentResponseToDelete) {
        showAlertModal('Erro', 'Erro: Dados da resposta não encontrados. Recarregue a página e tente novamente.', false);
        return;
    }
    
    // Se for exclusão em massa
    if (currentResponseToDelete === 'bulk') {
        confirmDeleteBulkResponse();
        return;
    }
    
    const userKey = currentResponseToDelete;
    const row = document.querySelector(`tr[data-user-key="${userKey}"]`);
    
    if (!row) {
        showAlertModal('Erro', 'Erro: Linha não encontrada. Recarregue a página e tente novamente.', false);
        closeDeleteResponseModal();
        return;
    }
    
    const userId = row.getAttribute('data-user-id');
    const responseDate = row.getAttribute('data-response-date');
    const checkinId = <?php echo $checkin_id; ?>;
    
    closeDeleteResponseModal();
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete_response');
        formData.append('user_id', userId);
        formData.append('config_id', checkinId);
        formData.append('response_date', responseDate);
        
        const response = await fetch('<?php echo BASE_ADMIN_URL; ?>/ajax_checkin.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Erro ao fazer parse do JSON:', e, text);
            showAlertModal('Erro', 'Resposta inválida do servidor: ' + text.substring(0, 100), false);
            return;
        }
        
        if (data.success) {
            showAlertModal('Sucesso', data.message || 'Resposta excluída com sucesso!', true);
            // Remover a linha da tabela
            if (row && row.parentNode) {
                row.parentNode.removeChild(row);
            }
            // Atualizar contador
            const badge = document.querySelector('.submissions-count .badge');
            if (badge) {
                const currentCount = parseInt(badge.textContent) || 0;
                badge.textContent = Math.max(0, currentCount - 1);
            }
        } else {
            showAlertModal('Erro', data.message || 'Erro ao excluir resposta.', false);
        }
    } catch (error) {
        console.error('Erro ao excluir resposta:', error);
        showAlertModal('Erro', 'Erro ao comunicar com o servidor. Tente novamente.', false);
    }
}

function showAlertModal(title, message, isSuccess) {
    const modal = document.getElementById('alertModal');
    const header = document.getElementById('alertModalHeader');
    const icon = document.getElementById('alertModalIcon');
    const titleEl = document.getElementById('alertModalTitle');
    const messageEl = document.getElementById('alertModalMessage');
    
    if (isSuccess) {
        header.style.color = '#22C55E';
        icon.className = 'fas fa-check-circle';
    } else {
        header.style.color = 'var(--danger-red)';
        icon.className = 'fas fa-exclamation-circle';
    }
    
    titleEl.textContent = title;
    messageEl.textContent = message;
    
    if (modal) {
        modal.classList.add('active');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeAlertModal() {
    const modal = document.getElementById('alertModal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }
    document.body.style.overflow = '';
    
    // Se houver redirect configurado
    if (modal && modal.dataset.redirectOnClose === 'true') {
        window.location.href = modal.dataset.redirectUrl || window.location.href;
    }
}

// Fechar modal ao clicar fora
document.getElementById('chatModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeChatModal();
    }
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeChatModal();
    }
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
