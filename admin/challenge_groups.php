<?php
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Grupos de Desafio';
$page_slug = 'challenge_groups';

// Buscar grupos de desafio
$conn = require __DIR__ . '/../includes/db.php';
$groups_query = "SELECT c.*, COUNT(cp.user_id) as member_count 
                 FROM sf_challenges c 
                 LEFT JOIN sf_challenge_participants cp ON c.id = cp.challenge_id 
                 GROUP BY c.id 
                 ORDER BY c.created_at DESC";
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
                <h1><i class="fas fa-trophy"></i> Grupos de Desafio</h1>
                <p class="header-subtitle">Gerencie grupos de desafio para motivar e engajar seus pacientes</p>
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
                        <option value="completed">Concluídos</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Buscar</label>
                    <input type="text" id="searchFilter" class="form-control" placeholder="Nome do grupo ou membro..." onkeyup="filterGroups()">
                </div>
            </div>
        </div>

        <!-- Grid de Grupos Premium -->
        <div class="groups-grid" id="groupsGrid">
            <?php if (empty($groups)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3>Nenhum grupo de desafio encontrado</h3>
                    <p>Crie seu primeiro grupo para começar a motivar seus pacientes</p>
                    <button class="btn btn-primary" onclick="openCreateGroupModal()">
                        <i class="fas fa-plus"></i> Criar Primeiro Grupo
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($groups as $group): ?>
                    <div class="content-card group-card" data-status="<?php echo $group['status']; ?>">
                        <div class="card-header">
                            <div class="group-title">
                                <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                                <span class="group-status <?php echo $group['status']; ?>">
                                    <i class="fas fa-circle"></i>
                                    <?php echo ucfirst($group['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="group-description">
                                <p><?php echo htmlspecialchars($group['description']); ?></p>
                            </div>
                            <div class="group-stats">
                                <div class="stat-item">
                                    <i class="fas fa-users"></i>
                                    <div class="stat-content">
                                        <span class="stat-label">Membros</span>
                                        <span class="stat-value"><?php echo $group['member_count']; ?></span>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-calendar"></i>
                                    <div class="stat-content">
                                        <span class="stat-label">Início</span>
                                        <span class="stat-value"><?php echo date('d/m/Y', strtotime($group['start_date'])); ?></span>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-flag-checkered"></i>
                                    <div class="stat-content">
                                        <span class="stat-label">Fim</span>
                                        <span class="stat-value"><?php echo date('d/m/Y', strtotime($group['end_date'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-secondary btn-sm" onclick="viewGroup(<?php echo $group['id']; ?>)">
                                <i class="fas fa-eye"></i> Ver Detalhes
                            </button>
                            <button class="btn btn-primary btn-sm" onclick="editGroup(<?php echo $group['id']; ?>)">
                                <i class="fas fa-edit"></i> Editar
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
            <h2 id="modalTitle">Criar Grupo de Desafio</h2>
            <span class="close" onclick="closeGroupModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="groupForm">
                <input type="hidden" id="groupId" name="group_id">
                
                <div class="form-group">
                    <label for="groupName">Nome do Grupo *</label>
                    <input type="text" id="groupName" name="name" class="form-control" required placeholder="Ex: Desafio de Verão 2024">
                </div>
                
                <div class="form-group">
                    <label for="groupDescription">Descrição</label>
                    <textarea id="groupDescription" name="description" class="form-control" rows="3" placeholder="Descreva o objetivo e regras do desafio"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="startDate">Data de Início *</label>
                        <input type="date" id="startDate" name="start_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="endDate">Data de Fim *</label>
                        <input type="date" id="endDate" name="end_date" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="groupStatus">Status</label>
                    <select id="groupStatus" name="status" class="form-control">
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                        <option value="completed">Concluído</option>
                    </select>
                </div>
                
                <div class="challenge-goals">
                    <h4><i class="fas fa-target"></i> Metas do Desafio</h4>
                    <div class="goals-list">
                        <div class="goal-item">
                            <input type="checkbox" name="goals[]" value="steps" id="goal_steps">
                            <label for="goal_steps"><i class="fas fa-walking"></i> Meta de Passos Diários</label>
                        </div>
                        <div class="goal-item">
                            <input type="checkbox" name="goals[]" value="exercise" id="goal_exercise">
                            <label for="goal_exercise"><i class="fas fa-dumbbell"></i> Meta de Exercícios</label>
                        </div>
                        <div class="goal-item">
                            <input type="checkbox" name="goals[]" value="hydration" id="goal_hydration">
                            <label for="goal_hydration"><i class="fas fa-tint"></i> Meta de Hidratação</label>
                        </div>
                        <div class="goal-item">
                            <input type="checkbox" name="goals[]" value="nutrition" id="goal_nutrition">
                            <label for="goal_nutrition"><i class="fas fa-apple-alt"></i> Meta Nutricional</label>
                        </div>
                    </div>
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

<!-- Modal de Detalhes do Grupo -->
<div id="groupDetailsModal" class="modal">
    <div class="modal-content extra-large">
        <div class="modal-header">
            <h2 id="detailsTitle">Detalhes do Grupo</h2>
            <span class="close" onclick="closeGroupDetailsModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="groupDetailsContent">
                <!-- Conteúdo será carregado via JavaScript -->
            </div>
        </div>
    </div>
</div>

<script>
// Função para abrir modal de criar grupo
function openCreateGroupModal() {
    document.getElementById('modalTitle').textContent = 'Criar Grupo de Desafio';
    document.getElementById('groupForm').reset();
    document.getElementById('groupId').value = '';
    document.getElementById('groupModal').style.display = 'block';
}

// Função para editar grupo
function editGroup(groupId) {
    // Implementar busca de dados do grupo e preenchimento do modal
    alert('Editar grupo: ' + groupId);
}

// Função para visualizar grupo
function viewGroup(groupId) {
    // Implementar carregamento de detalhes do grupo
    alert('Ver detalhes do grupo: ' + groupId);
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

// Função para fechar modal de detalhes
function closeGroupDetailsModal() {
    document.getElementById('groupDetailsModal').style.display = 'none';
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
    const detailsModal = document.getElementById('groupDetailsModal');
    
    if (event.target === groupModal) {
        closeGroupModal();
    }
    if (event.target === detailsModal) {
        closeGroupDetailsModal();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
