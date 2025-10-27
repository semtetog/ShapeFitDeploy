<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/functions.php';
$conn = require __DIR__ . '/../includes/db.php';

$page_title = 'Planos Alimentares';

// Buscar usuários para o select
$users_query = "SELECT id, name, email FROM sf_users ORDER BY name";
$users_result = $conn->query($users_query);
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

// Buscar planos existentes
$plans_query = "
    SELECT 
        dp.*,
        u.name as user_name,
        u.email as user_email,
        a.full_name as admin_name
    FROM sf_diet_plans dp
    JOIN sf_users u ON dp.user_id = u.id
    JOIN sf_admins a ON dp.admin_id = a.id
    ORDER BY dp.created_at DESC
";
$plans_result = $conn->query($plans_query);
$plans = [];
while ($row = $plans_result->fetch_assoc()) {
    $plans[] = $row;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-wrapper">
    <div class="main-content">
        <!-- Header Premium -->
        <div class="main-header">
            <div class="header-content">
                <h1><i class="fas fa-utensils"></i> Planos Alimentares</h1>
                <p class="header-subtitle">Gerencie planos alimentares personalizados para seus pacientes</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openCreatePlanModal()">
                    <i class="fas fa-plus"></i> Novo Plano
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
                    <label><i class="fas fa-user"></i> Filtrar por Paciente</label>
                    <div class="custom-dropdown" id="userDropdown">
                        <div class="custom-dropdown-toggle" onclick="toggleUserDropdown()">
                            <div class="custom-dropdown-selected">
                                <div id="selectedUserAvatar" class="custom-dropdown-initials" style="background: var(--accent-orange);">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="custom-dropdown-info">
                                    <div class="custom-dropdown-name" id="selectedUserName">Todos os pacientes</div>
                                    <div class="custom-dropdown-email" id="selectedUserEmail"></div>
                                </div>
                            </div>
                            <i class="fas fa-chevron-down custom-dropdown-arrow"></i>
                        </div>
                        <div class="custom-dropdown-menu" id="userDropdownMenu">
                            <div class="custom-dropdown-item" 
                                 data-id=""
                                 data-name="Todos os pacientes"
                                 data-email=""
                                 data-avatar="<i class='fas fa-users'></i>"
                                 onclick="selectUser(this)">
                                <div class="custom-dropdown-initials" style="background: var(--accent-orange);">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="custom-dropdown-info">
                                    <div class="custom-dropdown-name">Todos os pacientes</div>
                                    <div class="custom-dropdown-email"></div>
                                </div>
                            </div>
                            <?php foreach ($users as $user): ?>
                                <div class="custom-dropdown-item" 
                                     data-id="<?php echo htmlspecialchars($user['id']); ?>"
                                     data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                     data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                     data-avatar="<?php echo htmlspecialchars(getUserAvatarHtml($user, 'small')); ?>"
                                     onclick="selectUser(this)">
                                    <?php echo getUserAvatarHtml($user, 'small'); ?>
                                    <div class="custom-dropdown-info">
                                        <div class="custom-dropdown-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                        <div class="custom-dropdown-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-toggle-on"></i> Status do Plano</label>
                    <select id="status_filter" class="form-control" onchange="filterPlans()">
                        <option value="">Todos os status</option>
                        <option value="active">Ativos</option>
                        <option value="inactive">Inativos</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Buscar</label>
                    <input type="text" id="searchFilter" class="form-control" placeholder="Nome do plano ou paciente..." onkeyup="filterPlans()">
                </div>
            </div>
        </div>

        <!-- Grid de Planos Premium -->
        <div class="plans-grid" id="plansGrid">
            <?php if (empty($plans)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <h3>Nenhum plano alimentar encontrado</h3>
                    <p>Crie seu primeiro plano para começar a personalizar a alimentação dos seus pacientes</p>
                    <button class="btn btn-primary" onclick="openCreatePlanModal()">
                        <i class="fas fa-plus"></i> Criar Primeiro Plano
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($plans as $plan): ?>
                    <div class="content-card plan-card" data-user-id="<?php echo $plan['user_id']; ?>" data-status="<?php echo $plan['is_active'] ? 'active' : 'inactive'; ?>">
                        <div class="card-header">
                            <div class="plan-title">
                                <h3><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                                <span class="plan-status <?php echo $plan['is_active'] ? 'active' : 'inactive'; ?>">
                                    <i class="fas fa-circle"></i>
                                    <?php echo $plan['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="plan-info">
                                <div class="info-item">
                                    <i class="fas fa-user"></i>
                                    <div class="info-content">
                                        <span class="info-label">Paciente</span>
                                        <span class="info-value"><?php echo htmlspecialchars($plan['user_name']); ?></span>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-envelope"></i>
                                    <div class="info-content">
                                        <span class="info-label">Email</span>
                                        <span class="info-value"><?php echo htmlspecialchars($plan['user_email']); ?></span>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-user-md"></i>
                                    <div class="info-content">
                                        <span class="info-label">Criado por</span>
                                        <span class="info-value"><?php echo htmlspecialchars($plan['admin_name']); ?></span>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-calendar"></i>
                                    <div class="info-content">
                                        <span class="info-label">Data de criação</span>
                                        <span class="info-value"><?php echo date('d/m/Y', strtotime($plan['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-primary btn-sm">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button class="btn btn-secondary btn-sm">
                                <i class="fas fa-eye"></i> Visualizar
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<!-- Modal de Criação/Edição de Plano -->
<div id="planModal" class="modal">
    <div class="modal-content extra-large">
        <div class="modal-header">
            <h3><i class="fas fa-utensils"></i> <span id="modalTitle">Novo Plano Alimentar</span></h3>
            <span class="close" onclick="closePlanModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="planForm">
                <input type="hidden" id="plan_id" name="plan_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="user_id"><i class="fas fa-user"></i> Paciente *</label>
                        <div class="custom-dropdown" id="modalUserDropdown">
                            <div class="custom-dropdown-toggle" onclick="toggleModalUserDropdown()">
                                <div class="custom-dropdown-selected">
                                        <div id="modalSelectedUserAvatar" class="custom-dropdown-initials" style="background: var(--accent-orange);">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="custom-dropdown-info">
                                        <div class="custom-dropdown-name" id="modalSelectedUserName">Selecione um paciente</div>
                                        <div class="custom-dropdown-email" id="modalSelectedUserEmail"></div>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-down custom-dropdown-arrow"></i>
                            </div>
                            <div class="custom-dropdown-menu" id="modalUserDropdownMenu">
                                <?php foreach ($users as $user): ?>
                                    <div class="custom-dropdown-item" 
                                         data-id="<?php echo htmlspecialchars($user['id']); ?>"
                                         data-name="<?php echo htmlspecialchars($user['name']); ?>"
                                         data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                         data-avatar="<?php echo htmlspecialchars(getUserAvatarHtml($user, 'small')); ?>"
                                         onclick="selectModalUser(this)">
                                        <?php echo getUserAvatarHtml($user, 'small'); ?>
                                        <div class="custom-dropdown-info">
                                            <div class="custom-dropdown-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                            <div class="custom-dropdown-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <input type="hidden" id="user_id" name="user_id" required>
                    </div>
                    <div class="form-group">
                        <label for="plan_name"><i class="fas fa-utensils"></i> Nome do Plano *</label>
                        <input type="text" id="plan_name" name="plan_name" class="form-control" required placeholder="Ex: Plano de Emagrecimento">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description"><i class="fas fa-align-left"></i> Descrição</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="Descrição do plano alimentar"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="total_calories"><i class="fas fa-fire"></i> Calorias Totais (kcal) *</label>
                        <input type="number" id="total_calories" name="total_calories" class="form-control" required min="800" max="5000">
                    </div>
                    <div class="form-group">
                        <label for="water_ml"><i class="fas fa-tint"></i> Hidratação (ml) *</label>
                        <input type="number" id="water_ml" name="water_ml" class="form-control" required min="1000" max="5000">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="protein_g"><i class="fas fa-drumstick-bite"></i> Proteínas (g) *</label>
                        <input type="number" id="protein_g" name="protein_g" class="form-control" required min="20" max="300" step="0.1">
                    </div>
                    <div class="form-group">
                        <label for="carbs_g"><i class="fas fa-bread-slice"></i> Carboidratos (g) *</label>
                        <input type="number" id="carbs_g" name="carbs_g" class="form-control" required min="20" max="500" step="0.1">
                    </div>
                    <div class="form-group">
                        <label for="fat_g"><i class="fas fa-oil-can"></i> Gorduras (g) *</label>
                        <input type="number" id="fat_g" name="fat_g" class="form-control" required min="10" max="200" step="0.1">
                    </div>
                </div>
                
                <!-- Seção de Refeições -->
                <div class="meals-section">
                    <div class="section-header">
                        <h4><i class="fas fa-utensils"></i> Refeições do Plano</h4>
                        <button type="button" class="btn btn-primary" onclick="addMeal()">
                            <i class="fas fa-plus"></i> Adicionar Refeição
                        </button>
                    </div>
                    
                    <div id="mealsContainer">
                        <!-- Refeições serão adicionadas dinamicamente aqui -->
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closePlanModal()">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="savePlan()">Salvar Plano</button>
        </div>
    </div>
</div>

<script>
// Funções do Modal
function openCreatePlanModal() {
    document.getElementById('modalTitle').textContent = 'Novo Plano Alimentar';
    document.getElementById('planForm').reset();
    document.getElementById('plan_id').value = '';
    document.getElementById('mealsContainer').innerHTML = '';
    addMeal(); // Adicionar uma refeição padrão
    document.getElementById('planModal').style.display = 'block';
}

function closePlanModal() {
    document.getElementById('planModal').style.display = 'none';
}

function editPlan(planId) {
    // Implementar busca e edição do plano
    console.log('Editando plano:', planId);
}

function viewPlan(planId) {
    // Implementar visualização do plano
    console.log('Visualizando plano:', planId);
}

function deletePlan(planId) {
    if (confirm('Tem certeza que deseja excluir este plano?')) {
        // Implementar exclusão do plano
        console.log('Excluindo plano:', planId);
    }
}

function addMeal() {
    const container = document.getElementById('mealsContainer');
    const mealIndex = container.children.length;
    
    const mealDiv = document.createElement('div');
    mealDiv.className = 'meal-item';
    mealDiv.innerHTML = `
        <div class="meal-header">
            <h5>Refeição ${mealIndex + 1}</h5>
            <button type="button" class="btn-icon" onclick="removeMeal(this)">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Nome da Refeição</label>
                <input type="text" name="meals[${mealIndex}][name]" class="form-control" placeholder="Ex: Café da Manhã" required>
            </div>
            <div class="form-group">
                <label>Horário</label>
                <input type="time" name="meals[${mealIndex}][time]" class="form-control">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Calorias</label>
                <input type="number" name="meals[${mealIndex}][calories]" class="form-control" min="0" step="1">
            </div>
            <div class="form-group">
                <label>Proteínas (g)</label>
                <input type="number" name="meals[${mealIndex}][protein]" class="form-control" min="0" step="0.1">
            </div>
            <div class="form-group">
                <label>Carboidratos (g)</label>
                <input type="number" name="meals[${mealIndex}][carbs]" class="form-control" min="0" step="0.1">
            </div>
            <div class="form-group">
                <label>Gorduras (g)</label>
                <input type="number" name="meals[${mealIndex}][fat]" class="form-control" min="0" step="0.1">
            </div>
        </div>
        <div class="form-group">
            <label>Descrição</label>
            <textarea name="meals[${mealIndex}][description]" class="form-control" rows="2" placeholder="Descrição da refeição"></textarea>
        </div>
    `;
    
    container.appendChild(mealDiv);
}

function removeMeal(button) {
    button.closest('.meal-item').remove();
}

function savePlan() {
    const form = document.getElementById('planForm');
    const formData = new FormData(form);
    formData.append('action', 'save_plan');
    
    // Implementar salvamento do plano
    console.log('Salvando plano...');
}

// Variável global para armazenar o usuário selecionado
let selectedUserId = '';

// Funções do modal
function openCreatePlanModal() {
    console.log('Abrindo modal...');
    const modal = document.getElementById('planModal');
    if (modal) {
        modal.style.display = 'block';
        console.log('Modal aberto!');
    } else {
        console.error('Modal não encontrado!');
    }
    // Resetar formulário
    document.getElementById('planForm').reset();
    modalSelectedUserId = '';
    document.getElementById('modalSelectedUserName').textContent = 'Selecione um paciente';
    document.getElementById('modalSelectedUserEmail').textContent = '';
    document.getElementById('modalSelectedUserAvatar').innerHTML = '<i class="fas fa-users"></i>';
}

function closePlanModal() {
    console.log('Fechando modal...');
    const modal = document.getElementById('planModal');
    if (modal) {
        modal.style.display = 'none';
        console.log('Modal fechado!');
    } else {
        console.error('Modal não encontrado!');
    }
}

function toggleUserDropdown() {
    const dropdown = document.getElementById('userDropdownMenu');
    const toggle = document.querySelector('#userDropdown .custom-dropdown-toggle');
    
    dropdown.classList.toggle('show');
    toggle.classList.toggle('active');
}

function selectUser(element) {
    const userId = element.dataset.id;
    const userName = element.dataset.name;
    const userEmail = element.dataset.email;
    const avatarHtml = element.dataset.avatar;
    
    selectedUserId = userId;
    
    // Atualizar o display selecionado
    document.getElementById('selectedUserName').textContent = userName;
    document.getElementById('selectedUserEmail').textContent = userEmail;
    document.getElementById('selectedUserAvatar').innerHTML = avatarHtml;
    
    // Fechar dropdown
    toggleUserDropdown();
    
    // Filtrar planos
    filterPlans();
}

function filterPlans() {
    const statusFilter = document.getElementById('status_filter').value;
    const searchFilter = document.getElementById('searchFilter').value.toLowerCase();
    const plans = document.querySelectorAll('.plan-card');
    
    plans.forEach(plan => {
        const userId = plan.dataset.userId;
        const status = plan.dataset.status;
        const planName = plan.querySelector('h3').textContent.toLowerCase();
        const patientName = plan.querySelector('.info-value').textContent.toLowerCase();
        
        let show = true;
        
        // Filtro por usuário
        if (selectedUserId && userId !== selectedUserId) {
            show = false;
        }
        
        // Filtro por status
        if (statusFilter && status !== statusFilter) {
            show = false;
        }
        
        // Filtro por busca
        if (searchFilter && !planName.includes(searchFilter) && !patientName.includes(searchFilter)) {
            show = false;
        }
        
        plan.style.display = show ? 'block' : 'none';
    });
}

// Funções para o modal
let modalSelectedUserId = '';

function toggleModalUserDropdown() {
    const dropdown = document.getElementById('modalUserDropdownMenu');
    const toggle = document.querySelector('#modalUserDropdown .custom-dropdown-toggle');
    
    dropdown.classList.toggle('show');
    toggle.classList.toggle('active');
}

function selectModalUser(element) {
    const userId = element.dataset.id;
    const userName = element.dataset.name;
    const userEmail = element.dataset.email;
    const avatarHtml = element.dataset.avatar;
    
    modalSelectedUserId = userId;
    
    // Atualizar o display selecionado
    document.getElementById('modalSelectedUserName').textContent = userName;
    document.getElementById('modalSelectedUserEmail').textContent = userEmail;
    document.getElementById('modalSelectedUserAvatar').innerHTML = avatarHtml;
    
    // Atualizar o input hidden
    document.getElementById('user_id').value = userId;
    
    // Fechar dropdown
    toggleModalUserDropdown();
}

// Fechar dropdown quando clicar fora
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('userDropdown');
    const modalDropdown = document.getElementById('modalUserDropdown');
    
    if (!dropdown.contains(event.target)) {
        document.getElementById('userDropdownMenu').classList.remove('show');
        document.querySelector('#userDropdown .custom-dropdown-toggle').classList.remove('active');
    }
    
    if (!modalDropdown.contains(event.target)) {
        document.getElementById('modalUserDropdownMenu').classList.remove('show');
        document.querySelector('#modalUserDropdown .custom-dropdown-toggle').classList.remove('active');
    }
});

// Fechar modal ao clicar fora
window.onclick = function(event) {
    const modal = document.getElementById('planModal');
    if (event.target == modal) {
        closePlanModal();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
