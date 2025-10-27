<?php
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Grupos de Usuários';
$page_slug = 'user_groups';

// Buscar grupos de usuários
$conn = require __DIR__ . '/../includes/db.php';
$groups_query = "SELECT ug.*, COUNT(ugm.user_id) as member_count,
                        a.full_name as created_by_name
                 FROM sf_user_groups ug
                 LEFT JOIN sf_user_group_members ugm ON ug.id = ugm.group_id
                 LEFT JOIN sf_admins a ON ug.admin_id = a.id
                 GROUP BY ug.id
                 ORDER BY ug.created_at DESC";
$groups_result = $conn->query($groups_query);
$groups = $groups_result->fetch_all(MYSQLI_ASSOC);

// Buscar usuários para adicionar aos grupos
$users_query = "SELECT id, name, email FROM sf_users ORDER BY name";
$users_result = $conn->query($users_query);
$users = $users_result->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-wrapper">
    <div class="main-content">
        <!-- Header Premium -->
        <div class="main-header">
            <div class="header-content">
                <h1><i class="fas fa-layer-group"></i> Grupos de Usuários</h1>
                <p class="header-subtitle">Organize seus pacientes em grupos para distribuição personalizada de conteúdo</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openCreateGroupModal()">
                    <i class="fas fa-plus"></i> Criar Grupo
                </button>
            </div>
        </div>

        <!-- Filtros Premium -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filtros e Busca</h3>
            </div>
            <div class="filters-grid">
                <div class="filter-group">
                    <label><i class="fas fa-toggle-on"></i> Status do Grupo</label>
                    <select id="statusFilter" class="form-control" onchange="filterGroups()">
                        <option value="">Todos os status</option>
                        <option value="active">Ativos</option>
                        <option value="inactive">Inativos</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Buscar</label>
                    <input type="text" id="searchFilter" class="form-control" placeholder="Nome do grupo..." onkeyup="filterGroups()">
                </div>
            </div>
        </div>

        <!-- Grid de Grupos Premium -->
        <div class="groups-grid" id="groupsGrid">
            <?php if (empty($groups)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <h3>Nenhum grupo de usuários encontrado</h3>
                    <p>Crie seu primeiro grupo para organizar seus pacientes</p>
                    <button class="btn btn-primary" onclick="openCreateGroupModal()">
                        <i class="fas fa-plus"></i> Criar Primeiro Grupo
                    </button>
                </div>
            <?php else: ?>
            <?php foreach ($groups as $group): ?>
                <div class="group-card" data-status="<?php echo $group['status']; ?>">
                    <div class="group-header">
                        <div class="group-info">
                            <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                            <p><?php echo htmlspecialchars($group['description']); ?></p>
                        </div>
                        <div class="group-status">
                            <span class="status-badge <?php echo $group['status']; ?>">
                                <?php echo ucfirst($group['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="group-stats">
                        <div class="stat-item">
                            <i class="fas fa-users"></i>
                            <span><?php echo $group['member_count']; ?> membros</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-user"></i>
                            <span>Criado por: <?php echo htmlspecialchars($group['created_by_name']); ?></span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo date('d/m/Y', strtotime($group['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="group-actions">
                        <button class="btn btn-sm btn-secondary" onclick="viewGroupMembers(<?php echo $group['id']; ?>)">
                            <i class="fas fa-eye"></i> Ver Membros
                        </button>
                        <button class="btn btn-sm btn-primary" onclick="editGroup(<?php echo $group['id']; ?>)">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteGroup(<?php echo $group['id']; ?>)">
                            <i class="fas fa-trash"></i> Excluir
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Criar/Editar Grupo -->
<div id="groupModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Criar Grupo de Usuários</h2>
            <span class="close" onclick="closeGroupModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="groupForm">
                <input type="hidden" id="groupId" name="group_id">
                
                <div class="form-group">
                    <label for="groupName">Nome do Grupo *</label>
                    <input type="text" id="groupName" name="name" class="form-control" required placeholder="Ex: Grupo Premium">
                </div>
                
                <div class="form-group">
                    <label for="groupDescription">Descrição</label>
                    <textarea id="groupDescription" name="description" class="form-control" rows="3" placeholder="Descreva o propósito do grupo"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="groupStatus">Status</label>
                    <select id="groupStatus" name="status" class="form-control">
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                    </select>
                </div>
                
                <div class="members-section">
                    <label><i class="fas fa-users"></i> Membros do Grupo</label>
                    <div class="members-search">
                        <input type="text" id="memberSearch" placeholder="Buscar membros por nome ou email..." onkeyup="filterMembers()">
                    </div>
                    <div class="members-list">
                        <?php foreach ($users as $user): ?>
                            <div class="member-item" data-name="<?php echo strtolower(htmlspecialchars($user['name'])); ?>" data-email="<?php echo strtolower(htmlspecialchars($user['email'])); ?>">
                                <div class="member-avatar">
                                    <?php echo getUserAvatarHtml($user, 'large'); ?>
                                </div>
                                <div class="member-info">
                                    <div class="member-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                    <div class="member-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                                <div class="member-checkbox">
                                    <input type="checkbox" name="members[]" value="<?php echo $user['id']; ?>" id="member_<?php echo $user['id']; ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeGroupModal()">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="saveGroup()">Salvar Grupo</button>
        </div>
    </div>
</div>

<!-- Modal de Membros do Grupo -->
<div id="membersModal" class="modal">
    <div class="modal-content extra-large">
        <div class="modal-header">
            <h2 id="membersTitle">Membros do Grupo</h2>
            <span class="close" onclick="closeMembersModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="membersContent">
                <!-- Conteúdo será carregado via JavaScript -->
            </div>
        </div>
    </div>
</div>

<script>
// Função para abrir modal de criar grupo
function openCreateGroupModal() {
    document.getElementById('modalTitle').textContent = 'Criar Grupo de Usuários';
    document.getElementById('groupForm').reset();
    document.getElementById('groupId').value = '';
    document.getElementById('groupModal').style.display = 'block';
}

// Função para editar grupo
function editGroup(groupId) {
    // Implementar busca de dados do grupo e preenchimento do modal
    alert('Editar grupo: ' + groupId);
}

// Função para visualizar membros do grupo
function viewGroupMembers(groupId) {
    // Implementar carregamento de membros do grupo
    alert('Ver membros do grupo: ' + groupId);
}

// Função para excluir grupo
function deleteGroup(groupId) {
    if (confirm('Tem certeza que deseja excluir este grupo?')) {
        // Implementar exclusão do grupo
        alert('Excluir grupo: ' + groupId);
    }
}

// Função para fechar modal
function closeGroupModal() {
    document.getElementById('groupModal').style.display = 'none';
}

// Função para filtrar membros
function filterMembers() {
    const searchTerm = document.getElementById('memberSearch').value.toLowerCase();
    const memberItems = document.querySelectorAll('.member-item');
    
    memberItems.forEach(item => {
        const name = item.getAttribute('data-name');
        const email = item.getAttribute('data-email');
        
        if (name.includes(searchTerm) || email.includes(searchTerm)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

// Função para fechar modal de membros
function closeMembersModal() {
    document.getElementById('membersModal').style.display = 'none';
}

// Função para salvar grupo
function saveGroup() {
    const form = document.getElementById('groupForm');
    const formData = new FormData(form);
    
    // Implementar salvamento do grupo
    alert('Salvar grupo');
}

// Função para filtrar grupos
function filterGroups() {
    const statusFilter = document.getElementById('statusFilter').value;
    const searchFilter = document.getElementById('searchFilter').value.toLowerCase();
    const groups = document.querySelectorAll('.group-card');
    
    groups.forEach(group => {
        const status = group.dataset.status;
        const name = group.querySelector('h3').textContent.toLowerCase();
        const description = group.querySelector('p').textContent.toLowerCase();
        
        const statusMatch = !statusFilter || status === statusFilter;
        const searchMatch = !searchFilter || name.includes(searchFilter) || description.includes(searchFilter);
        
        if (statusMatch && searchMatch) {
            group.style.display = 'block';
        } else {
            group.style.display = 'none';
        }
    });
}

// Fechar modais ao clicar fora
window.onclick = function(event) {
    const groupModal = document.getElementById('groupModal');
    const membersModal = document.getElementById('membersModal');
    
    if (event.target === groupModal) {
        closeGroupModal();
    }
    if (event.target === membersModal) {
        closeMembersModal();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
