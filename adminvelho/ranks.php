<?php
// admin/ranks.php (Página de Ranking com Paginação e Busca)

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
$conn = require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminLogin();

$page_slug = 'ranks';
$page_title = 'Ranking de Usuários';
$extra_css = ['ranking_page.css'];

// --- LÓGICA DE BUSCA ---
$search_term = trim($_GET['search'] ?? '');
$search_param = "%" . $search_term . "%";

// --- LÓGICA DE PAGINAÇÃO ---
$users_per_page = 20;

// 1. Obter o número total de usuários (filtrado se houver busca)
$count_sql = "SELECT COUNT(id) as total FROM sf_users WHERE name LIKE ?";
$stmt_count = $conn->prepare($count_sql);
$stmt_count->bind_param("s", $search_param);
$stmt_count->execute();
$total_users_result = $stmt_count->get_result();
$total_users = (int)($total_users_result->fetch_assoc()['total'] ?? 0);
$stmt_count->close();
$total_pages = $total_users > 0 ? ceil($total_users / $users_per_page) : 1;

// 2. Obter a página atual da URL
$current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
    'options' => ['default' => 1, 'min_range' => 1]
]);
if ($current_page > $total_pages) {
    $current_page = $total_pages;
}

// 3. Calcular o OFFSET
$offset = ($current_page - 1) * $users_per_page;

// --- FUNÇÕES DE HELPER ---
$levels = [ 1 => 0, 2 => 150, 3 => 350, 4 => 600, 5 => 1000, 6 => 1500, 7 => 2200, 8 => 3000, 9 => 4000, 10 => 5500 ];
function getUserLevel($points, $levels) {
    $current_level = 1;
    foreach ($levels as $level => $required_points) {
        if ($points >= $required_points) {
            $current_level = $level;
        } else {
            break;
        }
    }
    return $current_level;
}

function getAdminUserProfileImageUrl($player_data) {
    if (!empty($player_data['profile_image_filename']) && file_exists(APP_ROOT_PATH . '/assets/images/users/' . $player_data['profile_image_filename'])) {
        return BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($player_data['profile_image_filename']);
    }
    $gender = strtolower($player_data['gender'] ?? 'male');
    return ($gender === 'female') ? 'https://i.ibb.co/XkpfDjbj/FEMININO.webp' : 'https://i.ibb.co/gLcMfWyn/MASCULINO.webp';
}

// --- LÓGICA DE RANKING (com busca e paginação) ---
$rankings = [];
$sql = "
    SELECT r.* FROM (
        SELECT u.id, u.name, u.points, up.profile_image_filename, up.gender, 
               RANK() OVER (ORDER BY u.points DESC, u.name ASC) as user_rank 
        FROM sf_users u 
        LEFT JOIN sf_user_profiles up ON u.id = up.user_id
    ) as r
    WHERE r.name LIKE ?
    ORDER BY r.user_rank ASC 
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $search_param, $users_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['level'] = getUserLevel($row['points'], $levels);
    $rankings[] = $row;
}
$stmt->close();

require_once __DIR__ . '/includes/header.php';
?>

<div class="ranking-page-container">
    <div class="toolbar">
        <h2><?php echo $page_title; ?></h2>
        <!-- Formulário de Busca -->
        <form method="GET" action="ranks.php" class="search-form">
            <input type="text" name="search" placeholder="Buscar por nome..." value="<?php echo htmlspecialchars($search_term); ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <?php if (empty($rankings)): ?>
        <div class="content-card">
            <p class="empty-state">Nenhum usuário encontrado<?php echo !empty($search_term) ? ' com o termo "' . htmlspecialchars($search_term) . '"' : ''; ?>.</p>
        </div>
    <?php else: ?>
        
        <?php if ($current_page === 1 && empty($search_term) && count($rankings) >= 3): // Mostra pódio apenas na pág 1 e sem busca ?>
        <div class="podium">
            <div class="podium-place second">
                <a href="view_user.php?id=<?php echo $rankings[1]['id']; ?>" class="podium-link">
                    <div class="podium-picture-wrapper"><img src="<?php echo getAdminUserProfileImageUrl($rankings[1]); ?>" alt="2º lugar" class="podium-picture"><div class="podium-rank-badge">2</div></div>
                    <span class="podium-name"><?php echo htmlspecialchars(explode(' ', $rankings[1]['name'])[0]); ?></span>
                    <span class="podium-level">Nível <?php echo $rankings[1]['level']; ?></span>
                    <span class="podium-points"><?php echo number_format($rankings[1]['points'], 0, ',', '.'); ?> pts</span>
                </a>
            </div>
            <div class="podium-place first">
                <a href="view_user.php?id=<?php echo $rankings[0]['id']; ?>" class="podium-link">
                    <div class="podium-picture-wrapper"><img src="<?php echo getAdminUserProfileImageUrl($rankings[0]); ?>" alt="1º lugar" class="podium-picture"><div class="podium-rank-badge"><i class="fas fa-crown"></i></div></div>
                    <span class="podium-name"><?php echo htmlspecialchars(explode(' ', $rankings[0]['name'])[0]); ?></span>
                    <span class="podium-level">Nível <?php echo $rankings[0]['level']; ?></span>
                    <span class="podium-points"><?php echo number_format($rankings[0]['points'], 0, ',', '.'); ?> pts</span>
                </a>
            </div>
            <div class="podium-place third">
                <a href="view_user.php?id=<?php echo $rankings[2]['id']; ?>" class="podium-link">
                    <div class="podium-picture-wrapper"><img src="<?php echo getAdminUserProfileImageUrl($rankings[2]); ?>" alt="3º lugar" class="podium-picture"><div class="podium-rank-badge">3</div></div>
                    <span class="podium-name"><?php echo htmlspecialchars(explode(' ', $rankings[2]['name'])[0]); ?></span>
                    <span class="podium-level">Nível <?php echo $rankings[2]['level']; ?></span>
                    <span class="podium-points"><?php echo number_format($rankings[2]['points'], 0, ',', '.'); ?> pts</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="ranking-list-section">
            <ul class="ranking-list">
                <?php 
                $start_index = ($current_page === 1 && empty($search_term) && count($rankings) >= 3) ? 3 : 0;
                ?>
                <?php foreach (array_slice($rankings, $start_index) as $player): ?>
                    <li class="ranking-item">
                        <a href="view_user.php?id=<?php echo $player['id']; ?>" class="ranking-item-link">
                            <span class="item-rank"><?php echo $player['user_rank']; ?>º</span>
                            <img src="<?php echo getAdminUserProfileImageUrl($player); ?>" alt="Foto de <?php echo htmlspecialchars($player['name']); ?>" class="item-picture">
                            <div class="item-info-container">
                                <span class="item-name"><?php echo htmlspecialchars($player['name']); ?></span>
                                <span class="item-level">Nível <?php echo $player['level']; ?></span>
                            </div>
                            <span class="item-points"><?php echo number_format($player['points'], 0, ',', '.'); ?> pts</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="pagination-footer">
            <div class="pagination-info">
                Página <?php echo $current_page; ?> de <?php echo $total_pages; ?> (<?php echo $total_users; ?> resultados)
            </div>
            <div class="pagination-container">
                <?php if ($current_page > 1): ?>
                    <a href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search_term); ?>" class="pagination-link">« Anterior</a>
                <?php endif; ?>

                <?php 
                for ($i = 1; $i <= $total_pages; $i++): 
                    if ($i == $current_page || $i <= 2 || $i >= $total_pages - 1 || abs($i - $current_page) <= 2):
                ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>" class="pagination-link <?php if ($i == $current_page) echo 'active'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php 
                    elseif ($i == 3 || $i == $total_pages - 2):
                ?>
                    <span class="pagination-link-dots">...</span>
                <?php 
                    endif;
                endfor; 
                ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search_term); ?>" class="pagination-link">Próxima »</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>