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
                        $bgColor = '#' . substr(md5($user['name']), 0, 6);
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

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>