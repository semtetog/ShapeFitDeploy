<?php
// admin/users.php (VERSÃO FINAL COM LÓGICA DE AVATAR CORRIGIDA)

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
$conn = require __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'users';
$page_title = 'Pacientes';

// --- LÓGICA DE BUSCA E PAGINAÇÃO ---
$search_term = $_GET['search'] ?? '';
$group_filter = isset($_GET['group']) ? (int)$_GET['group'] : 0;
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Buscar nome do grupo se filtro por grupo estiver ativo
$group_name = '';
if ($group_filter > 0) {
    $stmt_group = $conn->prepare("SELECT name, group_name FROM sf_user_groups WHERE id = ?");
    $stmt_group->bind_param("i", $group_filter);
    $stmt_group->execute();
    $group_result = $stmt_group->get_result();
    if ($group_result->num_rows > 0) {
        $group_data = $group_result->fetch_assoc();
        $group_name = $group_data['name'] ?? $group_data['group_name'] ?? 'Grupo';
    }
    $stmt_group->close();
}

// Verificar se a coluna status existe, se não, criar
$check_status = $conn->query("SHOW COLUMNS FROM sf_users LIKE 'status'");
$has_status_column = $check_status && $check_status->num_rows > 0;
if ($check_status) $check_status->free();

if (!$has_status_column) {
    // Adicionar coluna status se não existir
    $conn->query("ALTER TABLE sf_users ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' AFTER points");
    $has_status_column = true;
}

// --- Contagem total para paginação ---
$count_sql = "SELECT COUNT(DISTINCT u.id) as total FROM sf_users u";
$count_params = []; $count_types = "";
$count_conditions = [];

if (!empty($search_term)) {
    $count_conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $like_term = "%" . $search_term . "%";
    $count_params[] = $like_term;
    $count_params[] = $like_term;
    $count_types .= "ss";
}

if ($group_filter > 0) {
    // Verificar se a tabela existe antes de usar
    $table_check = $conn->query("SHOW TABLES LIKE 'sf_user_group_members'");
    if ($table_check && $table_check->num_rows > 0) {
        $count_sql .= " INNER JOIN sf_user_group_members ugm ON u.id = ugm.user_id";
        $count_conditions[] = "ugm.group_id = ?";
        $count_params[] = $group_filter;
        $count_types .= "i";
    } else {
        // Se a tabela não existir, remover o filtro
        $group_filter = 0;
        $group_name = '';
    }
    if ($table_check) $table_check->free();
}

if (!empty($count_conditions)) {
    $count_sql .= " WHERE " . implode(" AND ", $count_conditions);
}

try {
    $stmt_count = $conn->prepare($count_sql);
    if ($stmt_count) {
        if (!empty($count_params)) { 
            $stmt_count->bind_param($count_types, ...$count_params); 
        }
        $stmt_count->execute();
        $result = $stmt_count->get_result();
        $total_users = $result->fetch_assoc()['total'] ?? 0;
        $total_pages = ceil($total_users / $limit);
        $stmt_count->close();
    } else {
        error_log("Erro ao preparar consulta de contagem: " . $conn->error);
        $total_users = 0;
        $total_pages = 1;
    }
} catch (Exception $e) {
    error_log("Erro na consulta de contagem: " . $e->getMessage());
    $total_users = 0;
    $total_pages = 1;
}

// --- Busca dos usuários da página atual ---
$status_field = $has_status_column ? "COALESCE(u.status, 'active') as status" : "'active' as status";
$sql = "SELECT DISTINCT u.id, u.name, u.email, up.profile_image_filename, u.created_at, $status_field FROM sf_users u LEFT JOIN sf_user_profiles up ON u.id = up.user_id";
$params = []; $types = "";
$conditions = [];

if (!empty($search_term)) {
    $conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%" . $search_term . "%";
    $params[] = "%" . $search_term . "%";
    $types .= "ss";
}

if ($group_filter > 0) {
    // Verificar se a tabela existe antes de usar
    $table_check = $conn->query("SHOW TABLES LIKE 'sf_user_group_members'");
    if ($table_check && $table_check->num_rows > 0) {
        $sql .= " INNER JOIN sf_user_group_members ugm ON u.id = ugm.user_id";
        $conditions[] = "ugm.group_id = ?";
        $params[] = $group_filter;
        $types .= "i";
    } else {
        // Se a tabela não existir, remover o filtro
        $group_filter = 0;
        $group_name = '';
    }
    if ($table_check) $table_check->free();
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

try {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $users = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        error_log("Erro ao preparar a consulta de usuários: " . $conn->error);
        $users = [];
    }
} catch (Exception $e) {
    error_log("Erro na consulta de usuários: " . $e->getMessage());
    $users = [];
    // Se houver erro com filtro de grupo, remover o filtro e recarregar
    if ($group_filter > 0) {
        header("Location: users.php");
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<h2>Pacientes Cadastrados<?php if ($group_filter > 0): ?> - <?php echo htmlspecialchars($group_name); ?><?php endif; ?></h2>
<div class="toolbar">
    <form method="GET" action="users.php" class="search-form">
        <?php if ($group_filter > 0): ?>
            <input type="hidden" name="group" value="<?php echo $group_filter; ?>">
        <?php endif; ?>
        <input type="text" name="search" placeholder="Buscar por nome ou e-mail..." value="<?php echo htmlspecialchars($search_term); ?>">
        <button type="submit"><i class="fas fa-search"></i></button>
        <?php if ($group_filter > 0): ?>
            <a href="users.php" class="btn btn-secondary" style="margin-left: 10px;">
                <i class="fas fa-times"></i> Remover Filtro
            </a>
        <?php endif; ?>
    </form>
    <!-- <a href="create_user.php" class="btn btn-primary">Novo Paciente</a> -->
</div>

<div class="user-cards-grid">
    <?php if (empty($users)): ?>
        <p class="empty-state">Nenhum paciente encontrado.</p>
    <?php else: ?>
        <?php foreach ($users as $user): ?>
            <div class="user-card-wrapper">
                <div class="user-card">
                    <a href="view_user.php?id=<?php echo $user['id']; ?>" class="user-card-link">
                        <div class="user-card-header">
                            <?php
                            $has_photo = false;
                            $avatar_url = '';

                            if (!empty($user['profile_image_filename'])) {
                                // Verificar primeiro a imagem original (prioridade)
                                $original_path_on_server = APP_ROOT_PATH . '/assets/images/users/' . $user['profile_image_filename'];
                                if (file_exists($original_path_on_server)) {
                                    $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($user['profile_image_filename']);
                                    $has_photo = true;
                                } else {
                                    // Fallback: verificar thumbnail
                                    $thumb_filename = 'thumb_' . $user['profile_image_filename'];
                                    $thumb_path_on_server = APP_ROOT_PATH . '/assets/images/users/' . $thumb_filename;
                                    if (file_exists($thumb_path_on_server)) {
                                        $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($thumb_filename);
                                        $has_photo = true;
                                    }
                                }
                            }

                            if ($has_photo):
                            ?>
                                <img src="<?php echo $avatar_url; ?>" 
                                     alt="Foto de <?php echo htmlspecialchars($user['name']); ?>" 
                                     class="user-card-avatar">
                            <?php else:
                                // SE NÃO TEM FOTO, GERA AS INICIAIS
                                $name_parts = explode(' ', trim($user['name']));
                                $initials = '';
                                if (count($name_parts) > 1) {
                                    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1));
                                } elseif (!empty($name_parts[0])) {
                                    $initials = strtoupper(substr($name_parts[0], 0, 2));
                                } else {
                                    $initials = '??';
                                }
                                // Gerar cor escura para bom contraste com texto branco
                                $hash = md5($user['name']);
                                $r = hexdec(substr($hash, 0, 2)) % 156 + 50;  // 50-205
                                $g = hexdec(substr($hash, 2, 2)) % 156 + 50;  // 50-205
                                $b = hexdec(substr($hash, 4, 2)) % 156 + 50;  // 50-205
                                // Garantir que pelo menos um canal seja escuro
                                $max = max($r, $g, $b);
                                if ($max > 180) {
                                    $r = (int)($r * 0.7);
                                    $g = (int)($g * 0.7);
                                    $b = (int)($b * 0.7);
                                }
                                $bgColor = sprintf('#%02x%02x%02x', $r, $g, $b);
                            ?>
                                <div class="initials-avatar" style="background-color: <?php echo $bgColor; ?>;">
                                    <?php echo $initials; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="user-card-body">
                            <h3 class="user-card-name"><?php echo htmlspecialchars($user['name']); ?></h3>
                            <p class="user-card-email"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        <div class="user-card-footer">
                            <span class="user-card-date">
                                <i class="fas fa-calendar-alt"></i>
                                Cadastro: <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                            </span>
                        </div>
                    </a>
                    <div class="user-card-actions" onclick="event.stopPropagation()">
                        <div class="toggle-switch-wrapper">
                            <?php
                            $is_active = ($user['status'] ?? 'active') === 'active';
                            ?>
                            <label class="toggle-switch">
                                <input type="checkbox" 
                                       class="toggle-switch-input" 
                                       <?php echo $is_active ? 'checked' : ''; ?>
                                       onchange="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['status'] ?? 'active', ENT_QUOTES); ?>', this)"
                                       data-user-id="<?php echo $user['id']; ?>"
                                       data-current-status="<?php echo htmlspecialchars($user['status'] ?? 'active', ENT_QUOTES); ?>">
                                <span class="toggle-switch-slider"></span>
                            </label>
                            <span class="toggle-switch-label" style="color: <?php echo $is_active ? '#22C55E' : '#EF4444'; ?>; font-weight: <?php echo $is_active ? '700' : '600'; ?>;"><?php echo $is_active ? 'Ativo' : 'Inativo'; ?></span>
                        </div>
                        <button type="button" 
                                class="btn-delete-user-card" 
                                onclick="showDeleteUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>')" 
                                title="Excluir usuário permanentemente">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Seção de Paginação Completa -->
<div class="pagination-footer">
    <div class="pagination-info">
        Mostrando <strong><?php echo count($users); ?></strong> de <strong><?php echo $total_users; ?></strong> pacientes.
    </div>
    <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search_term); ?>" class="pagination-link">«</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>" class="pagination-link <?php if ($i == $page) echo 'active'; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search_term); ?>" class="pagination-link">»</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
/* Grid de cards - ajustado para evitar espaços vazios no zoom */
.user-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* Wrapper para card de usuário */
.user-card-wrapper {
    position: relative;
}

/* Card principal */
.user-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 320px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.user-card:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 107, 0, 0.3);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

/* Link clicável que cobre a maior parte do card */
.user-card-link {
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
}

/* Header com avatar */
.user-card-header {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 1rem;
    flex-shrink: 0;
}

.user-card-avatar,
.initials-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255, 107, 0, 0.3);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.initials-avatar {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    font-weight: 700;
    color: white;
    text-transform: uppercase;
}

/* Body com nome e email */
.user-card-body {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    margin-bottom: 1rem;
    min-height: 0;
    text-align: center;
}

.user-card-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.4;
    word-wrap: break-word;
}

.user-card-email {
    font-size: 0.875rem;
    color: var(--text-secondary);
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.4;
    word-wrap: break-word;
}

/* Footer com data */
.user-card-footer {
    flex-shrink: 0;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
}

.user-card-date {
    font-size: 0.8rem;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.user-card-date i {
    font-size: 0.75rem;
}

/* Ações do card (toggle + botão delete) - agora no final do card */
.user-card-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-top: 1rem;
    padding-top: 0.75rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    flex-shrink: 0;
}

/* Toggle switch styles - idêntico ao challenge_groups.php */
.toggle-switch-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
}

.toggle-switch-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-secondary);
    transition: color 0.3s ease;
    white-space: nowrap;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
    cursor: pointer;
    flex-shrink: 0;
}

.toggle-switch-input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-switch-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #EF4444; /* Vermelho quando desativado */
    transition: all 0.3s ease;
    border-radius: 26px;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-switch-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: all 0.3s ease;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-switch-input:checked + .toggle-switch-slider {
    background-color: #22C55E; /* Verde quando ativado */
    box-shadow: 0 0 8px rgba(34, 197, 94, 0.4);
}

.toggle-switch-input:checked + .toggle-switch-slider:before {
    transform: translateX(24px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
}

.toggle-switch:hover .toggle-switch-slider {
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2), 0 0 12px rgba(255, 255, 255, 0.1);
}

.toggle-switch-input:checked:hover + .toggle-switch-slider {
    box-shadow: 0 0 12px rgba(34, 197, 94, 0.6);
}

.toggle-switch-input:not(:checked):hover + .toggle-switch-slider {
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2), 0 0 12px rgba(239, 68, 68, 0.3);
}

/* Botão de exclusão no card - estilo igual ao painel admin */
.btn-delete-user-card {
    background: rgba(244, 67, 54, 0.1);
    color: var(--danger-red);
    border: 1px solid rgba(244, 67, 54, 0.3);
    border-radius: 8px;
    width: 36px;
    height: 36px;
    padding: 0;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.btn-delete-user-card:hover {
    background: rgba(244, 67, 54, 0.2);
    border-color: var(--danger-red);
    color: var(--danger-red);
    transform: translateY(-2px);
}

.btn-delete-user-card:active {
    transform: translateY(0);
}

.btn-delete-user-card i {
    font-size: 0.9rem;
}

/* Responsividade */
@media (max-width: 768px) {
    .user-cards-grid {
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
    }
    
    .user-card {
        min-height: 300px;
        padding: 1rem;
    }
    
    .user-card-avatar,
    .initials-avatar {
        width: 70px;
        height: 70px;
    }
    
    .user-card-name {
        font-size: 1rem;
    }
    
    .user-card-email {
        font-size: 0.8rem;
    }
}

@media (max-width: 480px) {
    .user-cards-grid {
        grid-template-columns: 1fr;
    }
    
    .user-card {
        min-height: 280px;
    }
}
</style>

<!-- Modal de Exclusão de Usuário -->
<div id="deleteUserModal" class="custom-modal">
    <div class="custom-modal-overlay" onclick="closeDeleteUserModal()"></div>
    <div class="custom-modal-content">
        <div class="custom-modal-header" style="color: var(--danger-red);">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Excluir Usuário Permanentemente</h3>
        </div>
        <div class="custom-modal-body">
            <p><strong>ATENÇÃO: Esta ação não pode ser desfeita!</strong></p>
            <p>Tem certeza que deseja excluir permanentemente o usuário <strong id="delete-user-name"></strong>?</p>
            <p class="modal-warning">Todos os dados relacionados serão excluídos permanentemente, incluindo:</p>
            <ul style="text-align: left; margin: 15px 0; padding-left: 30px;">
                <li>Dados pessoais e perfil</li>
                <li>Histórico de refeições e diário alimentar</li>
                <li>Histórico de peso e medidas</li>
                <li>Fotos e imagens</li>
                <li>Metas e objetivos</li>
                <li>Rotinas e exercícios</li>
                <li>Todos os dados relacionados</li>
            </ul>
            <p style="color: var(--danger-red); font-weight: 600;">Esta ação é IRREVERSÍVEL!</p>
        </div>
        <div class="custom-modal-footer">
            <button class="btn-modal-cancel" onclick="closeDeleteUserModal()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button class="btn-modal-danger" onclick="confirmDeleteUser()">
                <i class="fas fa-trash-alt"></i> Excluir Permanentemente
            </button>
        </div>
    </div>
</div>

<!-- Modal de Sucesso/Erro -->
<div id="alertModal" class="custom-modal" style="display: none;">
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

<style>
/* Estilos para modais - idênticos ao view_user.php */
.custom-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.1s ease;
}

.custom-modal.active {
    opacity: 1;
    pointer-events: all;
}

.custom-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    transition: none !important;
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

.modal-warning {
    color: var(--text-secondary) !important;
    font-size: 0.9rem;
    padding: 1rem;
    background: rgba(255, 107, 0, 0.08);
    border-left: 3px solid var(--accent-orange);
    border-radius: 8px;
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

/* Botão de perigo no modal - estilo igual ao view_user.php */
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

/* Forçar exibição de modais quando .active */
.custom-modal { display: none; }
.custom-modal.active { display: flex !important; }
</style>

<script>
// Toggle status do usuário - idêntico ao challenge_groups.php
function toggleUserStatus(userId, currentStatus, toggleElement) {
    if (!userId) {
        alert('Erro: ID do usuário não fornecido');
        // Reverter o toggle
        if (toggleElement) {
            toggleElement.checked = currentStatus === 'active';
            updateToggleLabel(toggleElement);
        }
        return;
    }
    
    // Usar o elemento passado ou encontrar
    const toggle = toggleElement || document.querySelector(`.toggle-switch-input[data-user-id="${userId}"]`);
    if (!toggle) return;
    
    // IMPORTANTE: O checkbox já foi alterado pelo evento onchange
    // Então toggle.checked já reflete o NOVO estado (não o antigo)
    const isChecked = toggle.checked;
    const newStatus = isChecked ? 'active' : 'inactive';
    const wrapper = toggle.closest('.toggle-switch-wrapper');
    const label = wrapper ? wrapper.querySelector('.toggle-switch-label') : null;
    
    // Atualizar label IMEDIATAMENTE baseado no estado atual do checkbox
    if (label) {
        const newText = isChecked ? 'Ativo' : 'Inativo';
        const newColor = isChecked ? '#22C55E' : '#EF4444';
        const newWeight = isChecked ? '700' : '600';
        
        // Atualizar diretamente
        label.textContent = newText;
        label.style.color = newColor;
        label.style.fontWeight = newWeight;
        
        // Forçar reflow para garantir que a atualização seja visível
        label.offsetHeight;
    }
    
    // Atualizar status via AJAX (sem recarregar a página)
    fetch('ajax_users.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'toggle_status',
            user_id: userId,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Atualizar o atributo data-current-status para próximas mudanças
            toggle.setAttribute('data-current-status', newStatus);
        } else {
            // Reverter o toggle em caso de erro
            toggle.checked = !isChecked;
            updateToggleLabel(toggle);
            alert('Erro ao atualizar status: ' + (result.message || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        // Reverter o toggle em caso de erro
        toggle.checked = !isChecked;
        updateToggleLabel(toggle);
        alert('Erro ao atualizar status. Tente novamente.');
    });
}

function updateToggleLabel(toggle) {
    const wrapper = toggle.closest('.toggle-switch-wrapper');
    const label = wrapper ? wrapper.querySelector('.toggle-switch-label') : null;
    if (label) {
        const isActive = toggle.checked;
        label.textContent = isActive ? 'Ativo' : 'Inativo';
        label.style.color = isActive ? '#22C55E' : '#EF4444';
        label.style.fontWeight = isActive ? '700' : '600';
    }
}

// Sistema de exclusão de usuário - idêntico ao view_user.php
let currentUserIdToDelete = null;
let currentUserNameToDelete = null;

function showDeleteUserModal(userId, userName) {
    console.log('[showDeleteUserModal] userId=', userId, 'userName=', userName);
    currentUserIdToDelete = userId;
    currentUserNameToDelete = userName;
    document.getElementById('delete-user-name').textContent = userName;
    document.body.style.overflow = 'hidden';
    const modal = document.getElementById('deleteUserModal');
    if (modal) {
        modal.classList.add('active');
        modal.style.display = 'flex';
    }
}

function closeDeleteUserModal() {
    const modal = document.getElementById('deleteUserModal');
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }
    document.body.style.overflow = '';
    currentUserIdToDelete = null;
    currentUserNameToDelete = null;
}

async function confirmDeleteUser() {
    if (!currentUserIdToDelete) {
        alert('Erro: ID do usuário não encontrado. Recarregue a página e tente novamente.');
        return;
    }
    
    const userIdToDelete = currentUserIdToDelete;
    const userNameToDelete = currentUserNameToDelete;
    
    closeDeleteUserModal();
    
    try {
        const formData = new FormData();
        formData.append('user_id', String(userIdToDelete));
        
        const response = await fetch('<?php echo BASE_ADMIN_URL; ?>/actions/delete_user.php', {
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
            const alertModal = document.getElementById('alertModal');
            if (alertModal) {
                alertModal.dataset.redirectOnClose = 'true';
                alertModal.dataset.redirectUrl = '<?php echo BASE_ADMIN_URL; ?>/users.php';
            }
            showAlertModal('Usuário Excluído', data.message, true);
        } else {
            showAlertModal('Erro', data.message, false);
        }
    } catch (error) {
        console.error('Erro ao excluir usuário:', error);
        showAlertModal('Erro', 'Erro ao excluir usuário. Verifique o console para mais detalhes.', false);
    }
}

// Expor funções globalmente
window.showDeleteUserModal = showDeleteUserModal;
window.closeDeleteUserModal = closeDeleteUserModal;
window.confirmDeleteUser = confirmDeleteUser;

function showAlertModal(title, message, isSuccess = true) {
    const modal = document.getElementById('alertModal');
    const header = document.getElementById('alertModalHeader');
    const icon = document.getElementById('alertModalIcon');
    const titleEl = document.getElementById('alertModalTitle');
    const messageEl = document.getElementById('alertModalMessage');
    
    if (isSuccess) {
        header.style.color = '#10B981';
        icon.className = 'fas fa-check-circle';
    } else {
        header.style.color = '#dc2626';
        icon.className = 'fas fa-times-circle';
    }
    
    titleEl.textContent = title;
    messageEl.textContent = message;
    modal.classList.add('active');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeAlertModal() {
    const modal = document.getElementById('alertModal');
    modal.classList.remove('active');
    modal.style.display = 'none';
    document.body.style.overflow = '';
    if (modal.dataset.reloadOnClose === 'true') {
        location.reload();
    }
    if (modal.dataset.redirectOnClose === 'true') {
        const redirectUrl = modal.dataset.redirectUrl || '<?php echo BASE_ADMIN_URL; ?>/users.php';
        window.location.href = redirectUrl;
    }
}

async function confirmDeleteUser() {
    if (!currentUserIdToDelete) {
        alert('Erro: ID do usuário não encontrado. Recarregue a página e tente novamente.');
        return;
    }
    
    const userIdToDelete = currentUserIdToDelete;
    const userNameToDelete = currentUserNameToDelete;
    
    closeDeleteUserModal();
    
    // Confirmar novamente com prompt nativo para segurança extra
    const confirmMessage = `Tem CERTEZA ABSOLUTA que deseja excluir PERMANENTEMENTE o usuário "${userNameToDelete}"?\n\nEsta ação NÃO PODE SER DESFEITA!`;
    if (!confirm(confirmMessage)) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('user_id', String(userIdToDelete));
        
        const response = await fetch('<?php echo BASE_ADMIN_URL; ?>/actions/delete_user.php', {
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
            const alertModal = document.getElementById('alertModal');
            if (alertModal) {
                alertModal.dataset.reloadOnClose = 'true';
            }
            showAlertModal('Usuário Excluído', data.message, true);
        } else {
            showAlertModal('Erro', data.message, false);
        }
    } catch (error) {
        console.error('Erro ao excluir usuário:', error);
        showAlertModal('Erro', 'Erro ao excluir usuário. Verifique o console para mais detalhes.', false);
    }
}

// Expor funções globalmente
window.showDeleteUserModal = showDeleteUserModal;
window.closeDeleteUserModal = closeDeleteUserModal;
window.confirmDeleteUser = confirmDeleteUser;
window.showAlertModal = showAlertModal;
window.closeAlertModal = closeAlertModal;
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>