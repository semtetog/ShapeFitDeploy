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
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// --- Contagem total para paginação ---
$count_sql = "SELECT COUNT(u.id) as total FROM sf_users u";
$count_params = []; $count_types = "";
if (!empty($search_term)) {
    $count_sql .= " WHERE u.name LIKE ? OR u.email LIKE ?";
    $like_term = "%" . $search_term . "%";
    $count_params = [$like_term, $like_term];
    $count_types = "ss";
}
$stmt_count = $conn->prepare($count_sql);
if ($stmt_count) {
    if (!empty($count_params)) { $stmt_count->bind_param($count_types, ...$count_params); }
    $stmt_count->execute();
    $total_users = $stmt_count->get_result()->fetch_assoc()['total'] ?? 0;
    $total_pages = ceil($total_users / $limit);
    $stmt_count->close();
} else {
    $total_users = 0;
    $total_pages = 1;
}

// --- Busca dos usuários da página atual ---
$sql = "SELECT u.id, u.name, u.email, up.profile_image_filename, u.created_at FROM sf_users u LEFT JOIN sf_user_profiles up ON u.id = up.user_id";
$params = []; $types = "";
if (!empty($search_term)) {
    $sql .= " WHERE u.name LIKE ? OR u.email LIKE ?";
    $params[] = "%" . $search_term . "%";
    $params[] = "%" . $search_term . "%";
    $types .= "ss";
}
$sql .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    error_log("Erro ao preparar a consulta de usuários: " . $conn->error);
    $users = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<h2>Pacientes Cadastrados</h2>
<div class="toolbar">
    <form method="GET" action="users.php" class="search-form">
        <input type="text" name="search" placeholder="Buscar por nome ou e-mail..." value="<?php echo htmlspecialchars($search_term); ?>">
        <button type="submit"><i class="fas fa-search"></i></button>
    </form>
    <!-- <a href="create_user.php" class="btn btn-primary">Novo Paciente</a> -->
</div>

<div class="user-cards-grid">
    <?php if (empty($users)): ?>
        <p class="empty-state">Nenhum paciente encontrado.</p>
    <?php else: ?>
        <?php foreach ($users as $user): ?>
            <div class="user-card-wrapper">
                <a href="view_user.php?id=<?php echo $user['id']; ?>" class="user-card">
                    <div class="user-card-header">
                        <?php
                        $has_photo = false;
                        $avatar_url = '';

                        if (!empty($user['profile_image_filename'])) {
                            $thumb_filename = 'thumb_' . $user['profile_image_filename'];
                            $thumb_path_on_server = APP_ROOT_PATH . '/assets/images/users/' . $thumb_filename;
                            
                            // Prioridade 1: A thumbnail existe?
                            if (file_exists($thumb_path_on_server)) {
                                $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($thumb_filename);
                                $has_photo = true;
                            } else {
                                // Prioridade 2: Se a thumb não existe, a imagem original existe?
                                $original_path_on_server = APP_ROOT_PATH . '/assets/images/users/' . $user['profile_image_filename'];
                                if (file_exists($original_path_on_server)) {
                                    $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($user['profile_image_filename']);
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
                <button type="button" 
                        class="btn-delete-user-card" 
                        onclick="event.stopPropagation(); showDeleteUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name'], ENT_QUOTES); ?>')" 
                        title="Excluir usuário permanentemente">
                    <i class="fas fa-trash-alt"></i>
                </button>
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
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); /* auto-fit remove colunas vazias, minmax menor para mais cards caberem */
    gap: 1.5rem;
    margin-bottom: 2rem;
}

/* Wrapper para card de usuário com botão de exclusão */
.user-card-wrapper {
    position: relative;
}

.user-card-wrapper .user-card {
    text-decoration: none;
    color: inherit;
    min-height: 280px; /* Altura mínima para todos os cards */
    max-height: 280px; /* Altura máxima para todos os cards */
    display: flex;
    flex-direction: column;
}

/* Garantir que o body tenha altura fixa */
.user-card-wrapper .user-card .user-card-body {
    flex: 1 1 auto;
    min-height: 0; /* Permite que o conteúdo se ajuste */
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
}

/* Nome do usuário - truncar com ellipsis após 2 linhas */
.user-card-wrapper .user-card .user-card-name {
    display: -webkit-box;
    -webkit-line-clamp: 2; /* Limitar a 2 linhas */
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.4;
    max-height: 2.8em; /* Aproximadamente 2 linhas (1.4 * 2) */
    word-wrap: break-word;
    hyphens: auto;
}

/* Email - truncar com ellipsis após 2 linhas */
.user-card-wrapper .user-card .user-card-email {
    display: -webkit-box;
    -webkit-line-clamp: 2; /* Limitar a 2 linhas */
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.4;
    max-height: 2.8em; /* Aproximadamente 2 linhas */
    word-wrap: break-word;
}

/* Botão de exclusão no card */
.btn-delete-user-card {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(220, 38, 38, 0.9);
    color: white;
    border: none;
    border-radius: 6px;
    padding: 8px 10px;
    font-size: 0.85rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    z-index: 10;
    opacity: 0;
    transform: scale(0.8);
}

.user-card-wrapper:hover .btn-delete-user-card {
    opacity: 1;
    transform: scale(1);
}

.btn-delete-user-card:hover {
    background: #dc2626;
    transform: scale(1.1);
}

.btn-delete-user-card:active {
    transform: scale(0.95);
}
</style>

<!-- Modal de Exclusão de Usuário -->
<div id="deleteUserModal" class="custom-modal" style="display: none;">
    <div class="custom-modal-overlay" onclick="closeDeleteUserModal()"></div>
    <div class="custom-modal-content">
        <div class="custom-modal-header" style="color: #dc2626;">
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
            <p style="color: #dc2626; font-weight: 600;">Esta ação é IRREVERSÍVEL!</p>
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
/* Estilos para modais */
.custom-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
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
    background: rgba(0, 0, 0, 0.6);
    cursor: pointer;
}

.custom-modal-content {
    background: var(--surface-color);
    border-radius: 12px;
    padding: 24px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    z-index: 10000;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.custom-modal-small {
    max-width: 400px;
}

.custom-modal-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    font-size: 1.3rem;
}

.custom-modal-header i {
    font-size: 1.5rem;
}

.custom-modal-header h3 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
}

.custom-modal-body {
    margin-bottom: 24px;
    color: var(--primary-text-color);
    line-height: 1.6;
}

.custom-modal-body p {
    margin: 12px 0;
}

.modal-warning {
    color: var(--secondary-text-color);
    font-size: 0.95rem;
}

.custom-modal-footer {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.btn-modal-cancel,
.btn-modal-primary,
.btn-modal-danger {
    padding: 10px 20px;
    border-radius: 8px;
    border: none;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-modal-cancel {
    background: var(--surface-color);
    color: var(--primary-text-color);
    border: 1px solid var(--border-color);
}

.btn-modal-cancel:hover {
    background: var(--bg-color);
}

.btn-modal-primary {
    background: var(--accent-orange);
    color: white;
}

.btn-modal-primary:hover {
    background: var(--accent-orange-hover);
}

.btn-modal-danger {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
}

.btn-modal-danger:hover {
    background: linear-gradient(135deg, #b91c1c, #991b1b);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
}
</style>

<script>
// Sistema de exclusão de usuário
let currentUserIdToDelete = null;
let currentUserNameToDelete = null;

function showDeleteUserModal(userId, userName) {
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