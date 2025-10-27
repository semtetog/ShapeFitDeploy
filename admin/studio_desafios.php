<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Estúdio de Desafios';
$page_slug = 'studio_desafios';

// Verificar autenticação do admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Se não estiver logado, redirecionar para login
    header("Location: index.php");
    exit;
}


// Buscar ligas de desafio do admin
$admin_id = $_SESSION['admin_id'] ?? null;

if (!$admin_id) {
    die("Erro: ID do administrador não encontrado na sessão. <a href='index.php'>Fazer login novamente</a>");
}

// Para o sistema atual, vamos usar o admin_id como created_by
// Isso significa que o admin pode criar ligas e gerenciar usuários

$ligas_query = "SELECT 
    cr.*,
    COUNT(crm.user_id) as total_participantes,
    a.full_name as admin_name
FROM sf_challenge_rooms cr
LEFT JOIN sf_challenge_room_members crm ON cr.id = crm.challenge_room_id
LEFT JOIN sf_admins a ON cr.created_by = a.id
WHERE cr.created_by = ?
GROUP BY cr.id
ORDER BY cr.created_at DESC";
$stmt_ligas = $conn->prepare($ligas_query);
$stmt_ligas->bind_param("i", $admin_id);
$stmt_ligas->execute();
$ligas = $stmt_ligas->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_ligas->close();

// Buscar usuários para adicionar às ligas
$users_query = "SELECT u.id, u.name, u.email, up.profile_image_filename 
                FROM sf_users u 
                LEFT JOIN sf_user_profiles up ON u.id = up.user_id 
                ORDER BY u.name";
$users_result = $conn->query($users_query);
$users = $users_result->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/includes/header-novo.php';
?>

<div class="admin-wrapper">
    <div class="main-content">
        
        <div class="main-header">
            <div class="header-content">
                <h1><i class="fas fa-dumbbell"></i> Estúdio de Desafios</h1>
                <p class="header-subtitle">Crie e gerencie ligas de competição personalizadas para seus pacientes</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openCreateLigaModal()">
                    <i class="fas fa-plus"></i> Nova Liga
                </button>
            </div>
        </div>

        <!-- Estatísticas do Estúdio -->
        <div class="studio-stats">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo count($ligas); ?></h3>
                    <p>Ligas Criadas</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo array_sum(array_column($ligas, 'total_participantes')); ?></h3>
                    <p>Total de Participantes</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-fire"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo count(array_filter($ligas, function($liga) { return $liga['status'] === 'active'; })); ?></h3>
                    <p>Ligas Ativas</p>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filtros</h3>
            </div>
            <div class="filters-grid">
                <div class="filter-group">
                    <label>Status da Liga</label>
                    <select id="statusFilter" class="form-control" onchange="filterLigas()">
                        <option value="">Todas as ligas</option>
                        <option value="active">Ativas</option>
                        <option value="completed">Concluídas</option>
                        <option value="cancelled">Canceladas</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Buscar</label>
                    <input type="text" id="searchFilter" class="form-control" placeholder="Nome da liga..." onkeyup="filterLigas()">
                </div>
            </div>
        </div>

        <!-- Grid de Ligas -->
        <div class="ligas-grid" id="ligasGrid">
            <?php if (empty($ligas)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <h3>Nenhuma liga criada ainda</h3>
                    <p>Crie sua primeira liga para começar a motivar seus pacientes</p>
                    <button class="btn btn-primary" onclick="openCreateLigaModal()">
                        <i class="fas fa-plus"></i> Criar Primeira Liga
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($ligas as $liga): ?>
                    <div class="liga-card" data-status="<?php echo $liga['status']; ?>">
                        <div class="liga-header">
                            <div class="liga-title">
                                <h3><?php echo htmlspecialchars($liga['name']); ?></h3>
                                <span class="liga-status <?php echo $liga['status']; ?>">
                                    <i class="fas fa-circle"></i>
                                    <?php echo ucfirst($liga['status']); ?>
                                </span>
                            </div>
                            <div class="liga-actions">
                                <button class="btn-icon" onclick="viewLigaDetails(<?php echo $liga['id']; ?>)" title="Ver Detalhes">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-icon" onclick="manageParticipants(<?php echo $liga['id']; ?>)" title="Gerenciar Participantes">
                                    <i class="fas fa-users"></i>
                                </button>
                                <button class="btn-icon" onclick="editLiga(<?php echo $liga['id']; ?>)" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="liga-body">
                            <p class="liga-description"><?php echo htmlspecialchars($liga['description']); ?></p>
                            
                            <div class="liga-stats">
                                <div class="stat">
                                    <i class="fas fa-users"></i>
                                    <span><?php echo $liga['total_participantes']; ?> participantes</span>
                                </div>
                                <div class="stat">
                                    <i class="fas fa-calendar"></i>
                                    <span><?php echo date('d/m/Y', strtotime($liga['start_date'])); ?> - <?php echo date('d/m/Y', strtotime($liga['end_date'])); ?></span>
                                </div>
                                <div class="stat">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo $liga['status'] === 'active' ? 'Em andamento' : 'Finalizada'; ?></span>
                                </div>
                            </div>
                            
                            <?php if ($liga['goals']): ?>
                                <div class="liga-rules">
                                    <h4>Regras da Liga:</h4>
                                    <div class="rules-list">
                                        <?php 
                                        $goals = json_decode($liga['goals'], true);
                                        foreach ($goals as $goal_type => $goal_value): 
                                        ?>
                                            <div class="rule-item">
                                                <i class="fas fa-<?php echo getGoalIcon($goal_type); ?>"></i>
                                                <span><?php echo getGoalLabel($goal_type); ?>: <?php echo $goal_value; ?> pontos</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Criar/Editar Liga -->
<div class="modal-overlay" id="ligaModal">
    <div class="modal-content large">
        <div class="modal-header">
            <h3 id="modalTitle">Criar Nova Liga</h3>
            <button class="modal-close" onclick="closeLigaModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="ligaForm" onsubmit="saveLiga(event)">
            <div class="modal-body">
                <!-- Identidade da Liga -->
                <div class="form-section">
                    <h4><i class="fas fa-tag"></i> Identidade da Liga</h4>
                    <div class="form-group">
                        <label for="ligaName">Nome da Liga</label>
                        <input type="text" id="ligaName" name="name" class="form-control" placeholder="Ex: Foco Total - Março" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="ligaDescription">Descrição</label>
                        <textarea id="ligaDescription" name="description" class="form-control" rows="3" placeholder="Descreva o objetivo desta liga..."></textarea>
                    </div>
                </div>

                <!-- Duração -->
                <div class="form-section">
                    <h4><i class="fas fa-calendar"></i> Duração</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="startDate">Data de Início</label>
                            <input type="date" id="startDate" name="start_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="endDate">Data de Fim</label>
                            <input type="date" id="endDate" name="end_date" class="form-control" required>
                        </div>
                    </div>
                </div>

                <!-- Motor de Pontuação -->
                <div class="form-section">
                    <h4><i class="fas fa-cogs"></i> Motor de Pontuação</h4>
                    <p class="section-description">Configure como os participantes vão ganhar pontos nesta liga</p>
                    
                    <div class="scoring-modules">
                        <div class="module-card">
                            <div class="module-header">
                                <h5><i class="fas fa-utensils"></i> Diário Alimentar</h5>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="modules[diario]" value="1" onchange="toggleModule('diario')">
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="module-options" id="diario-options" style="display: none;">
                                <div class="option-item">
                                    <label>Dia com diário preenchido:</label>
                                    <input type="number" name="diario_dia_preenchido" placeholder="Ex: 10" class="form-control">
                                    <span>pontos</span>
                                </div>
                                <div class="option-item">
                                    <label>Atingir meta de proteína:</label>
                                    <input type="number" name="diario_meta_proteina" placeholder="Ex: 15" class="form-control">
                                    <span>pontos</span>
                                </div>
                                <div class="option-item">
                                    <label>Manter-se dentro da meta de kcal:</label>
                                    <input type="number" name="diario_meta_kcal" placeholder="Ex: 20" class="form-control">
                                    <span>pontos</span>
                                </div>
                            </div>
                        </div>

                        <div class="module-card">
                            <div class="module-header">
                                <h5><i class="fas fa-tint"></i> Hidratação</h5>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="modules[hidratacao]" value="1" onchange="toggleModule('hidratacao')">
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="module-options" id="hidratacao-options" style="display: none;">
                                <div class="option-item">
                                    <label>Atingir meta diária de água:</label>
                                    <input type="number" name="hidratacao_meta_agua" placeholder="Ex: 25" class="form-control">
                                    <span>pontos</span>
                                </div>
                            </div>
                        </div>

                        <div class="module-card">
                            <div class="module-header">
                                <h5><i class="fas fa-tasks"></i> Missões Diárias</h5>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="modules[missoes]" value="1" onchange="toggleModule('missoes')">
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="module-options" id="missoes-options" style="display: none;">
                                <div class="option-item">
                                    <label>Por cada missão completada:</label>
                                    <input type="number" name="missoes_por_missao" placeholder="Ex: 5" class="form-control">
                                    <span>pontos</span>
                                </div>
                            </div>
                        </div>

                        <div class="module-card">
                            <div class="module-header">
                                <h5><i class="fas fa-weight"></i> Check-in Semanal</h5>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="modules[checkin]" value="1" onchange="toggleModule('checkin')">
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="module-options" id="checkin-options" style="display: none;">
                                <div class="option-item">
                                    <label>Bônus por registrar peso na semana:</label>
                                    <input type="number" name="checkin_bonus_peso" placeholder="Ex: 30" class="form-control">
                                    <span>pontos</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Participantes -->
                <div class="form-section">
                    <h4><i class="fas fa-users"></i> Participantes</h4>
                    <div class="participants-selector">
                        <div class="search-box">
                            <input type="text" id="userSearch" placeholder="Buscar usuários..." class="form-control">
                        </div>
                        <div class="users-list" id="usersList">
                            <?php foreach ($users as $user): ?>
                                <div class="user-item" data-user-id="<?php echo $user['id']; ?>">
                                    <div class="user-avatar">
                                        <?php if (!empty($user['profile_image_filename'])): ?>
                                            <img src="<?php echo BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($user['profile_image_filename']); ?>" alt="Avatar">
                                        <?php else: ?>
                                            <i class="fas fa-user"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-info">
                                        <h5><?php echo htmlspecialchars($user['name']); ?></h5>
                                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                                    </div>
                                    <input type="checkbox" name="participants[]" value="<?php echo $user['id']; ?>" class="participant-checkbox">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Recompensas -->
                <div class="form-section">
                    <h4><i class="fas fa-trophy"></i> Recompensas</h4>
                    <div class="rewards-config">
                        <div class="reward-item">
                            <label>1º Lugar:</label>
                            <input type="text" id="reward_first" name="reward_first" placeholder="Ex: Badge 'Campeão'" class="form-control">
                        </div>
                        <div class="reward-item">
                            <label>2º Lugar:</label>
                            <input type="text" id="reward_second" name="reward_second" placeholder="Ex: Badge 'Vice-Campeão'" class="form-control">
                        </div>
                        <div class="reward-item">
                            <label>3º Lugar:</label>
                            <input type="text" id="reward_third" name="reward_third" placeholder="Ex: Badge 'Terceiro Lugar'" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeLigaModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Criar Liga</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Gerenciar Participantes -->
<div class="modal-overlay" id="participantsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Gerenciar Participantes</h3>
            <button class="modal-close" onclick="closeParticipantsModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="participants-section">
                <h4>Adicionar Participantes</h4>
                <div class="search-box">
                    <input type="text" id="addUserSearch" placeholder="Buscar usuários..." class="form-control">
                </div>
                <div class="users-list" id="addUsersList">
                    <!-- Lista de usuários será carregada aqui -->
                </div>
            </div>
            
            <div class="participants-section">
                <h4>Participantes Atuais</h4>
                <div class="current-participants" id="currentParticipants">
                    <!-- Participantes atuais serão carregados aqui -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ===== FUNÇÕES PRINCIPAIS DO ESTÚDIO DE DESAFIOS =====

// Variáveis globais
let currentLigaId = null;
let currentEditMode = false;

// ===== GERENCIAMENTO DE MODAIS =====
function openCreateLigaModal() {
    currentEditMode = false;
    currentLigaId = null;
    document.getElementById('modalTitle').textContent = 'Criar Nova Liga';
    document.getElementById('ligaForm').reset();
    resetModuleOptions();
    resetParticipants();
    document.getElementById('ligaModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function openEditLigaModal(ligaId) {
    currentEditMode = true;
    currentLigaId = ligaId;
    document.getElementById('modalTitle').textContent = 'Editar Liga';
    loadLigaData(ligaId);
    document.getElementById('ligaModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLigaModal() {
    document.getElementById('ligaModal').classList.remove('active');
    document.body.style.overflow = 'auto';
    currentEditMode = false;
    currentLigaId = null;
}

function openParticipantsModal(ligaId) {
    currentLigaId = ligaId;
    loadCurrentParticipants(ligaId);
    loadAvailableUsers();
    document.getElementById('participantsModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeParticipantsModal() {
    document.getElementById('participantsModal').classList.remove('active');
    document.body.style.overflow = 'auto';
}

// ===== FUNÇÕES DE MÓDULOS =====
function toggleModule(moduleName) {
    const checkbox = document.querySelector(`input[name="modules[${moduleName}]"]`);
    const options = document.getElementById(`${moduleName}-options`);
    
    if (!options) {
        console.error(`Elemento não encontrado: ${moduleName}-options`);
        return;
    }
    
    if (checkbox.checked) {
        options.style.display = 'block';
        options.style.animation = 'fadeIn 0.3s ease';
    } else {
        options.style.display = 'none';
        // Limpar valores quando desabilitado
        const inputs = options.querySelectorAll('input');
        inputs.forEach(input => input.value = '');
    }
}

function resetModuleOptions() {
    const modules = ['diario', 'hidratacao', 'missoes', 'checkin'];
    modules.forEach(module => {
        const checkbox = document.querySelector(`input[name="modules[${module}]"]`);
        const options = document.getElementById(`${module}-options`);
        if (checkbox) checkbox.checked = false;
        if (options) {
            options.style.display = 'none';
            const inputs = options.querySelectorAll('input');
            inputs.forEach(input => input.value = '');
        }
    });
}

// ===== SALVAMENTO DE LIGA =====
function saveLiga(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Validar dados obrigatórios
    const name = formData.get('name');
    const startDate = formData.get('start_date');
    const endDate = formData.get('end_date');
    
    if (!name || !startDate || !endDate) {
        showNotification('Por favor, preencha todos os campos obrigatórios.', 'error');
        return;
    }
    
    // Validar datas
    if (new Date(startDate) >= new Date(endDate)) {
        showNotification('A data de fim deve ser posterior à data de início.', 'error');
        return;
    }
    
    // Coletar configurações dos módulos
    const modules = {};
    const moduleCheckboxes = form.querySelectorAll('input[name^="modules["]');
    
    moduleCheckboxes.forEach(checkbox => {
        if (checkbox.checked) {
            const moduleName = checkbox.name.match(/\[(.*?)\]/)[1];
            modules[moduleName] = {};
            
            // Coletar opções específicas do módulo
            const options = document.getElementById(`${moduleName}-options`);
            if (options) {
                const inputs = options.querySelectorAll('input[type="number"]');
                inputs.forEach(input => {
                    if (input.value) {
                        modules[moduleName][input.name.replace(`${moduleName}_`, '')] = parseInt(input.value);
                    }
                });
            }
        }
    });
    
    // Coletar participantes selecionados
    const participants = Array.from(form.querySelectorAll('input[name="participants[]"]:checked'))
        .map(input => input.value);
    
    // Preparar dados para envio
    const ligaData = {
        name: name,
        description: formData.get('description') || '',
        start_date: startDate,
        end_date: endDate,
        goals: JSON.stringify(modules),
        participants: participants,
        rewards: {
            first: formData.get('reward_first') || '',
            second: formData.get('reward_second') || '',
            third: formData.get('reward_third') || ''
        }
    };
    
    // Enviar dados
    saveLigaToServer(ligaData);
}

async function saveLigaToServer(ligaData) {
    try {
        const response = await fetch('api/studio_ligas.php', {
            method: currentEditMode ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: currentEditMode ? 'update' : 'create',
                liga_id: currentLigaId,
                data: ligaData
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            closeLigaModal();
            setTimeout(() => {
                location.reload(); // Recarregar para mostrar a nova liga
            }, 1500);
        } else {
            showNotification(result.message || 'Erro ao salvar liga', 'error');
        }
    } catch (error) {
        console.error('Erro ao salvar liga:', error);
        showNotification('Erro de conexão. Tente novamente.', 'error');
    }
}

// ===== FUNÇÕES DE GESTÃO =====
function manageParticipants(ligaId) {
    openParticipantsModal(ligaId);
}

function viewLigaDetails(ligaId) {
    // Redirecionar para página de detalhes
    window.location.href = `challenge_room_details.php?id=${ligaId}`;
}

function editLiga(ligaId) {
    openEditLigaModal(ligaId);
}

// ===== FILTROS =====
function filterLigas() {
    const statusFilter = document.getElementById('statusFilter').value;
    const searchFilter = document.getElementById('searchFilter').value.toLowerCase();
    const ligas = document.querySelectorAll('.liga-card');
    
    ligas.forEach(liga => {
        const status = liga.dataset.status;
        const title = liga.querySelector('h3').textContent.toLowerCase();
        const description = liga.querySelector('.liga-description').textContent.toLowerCase();
        
        const statusMatch = !statusFilter || status === statusFilter;
        const searchMatch = !searchFilter || title.includes(searchFilter) || description.includes(searchFilter);
        
        liga.style.display = (statusMatch && searchMatch) ? 'block' : 'none';
    });
}

// ===== GESTÃO DE PARTICIPANTES =====
async function loadCurrentParticipants(ligaId) {
    try {
        const response = await fetch(`api/liga_participants.php?liga_id=${ligaId}`);
        const result = await response.json();
        
        const container = document.getElementById('currentParticipants');
        if (result.success && result.participants) {
            container.innerHTML = result.participants.map(participant => `
                <div class="participant-item">
                    <div class="participant-avatar">
                        ${participant.profile_image ? 
                            `<img src="${participant.profile_image}" alt="Avatar">` : 
                            `<i class="fas fa-user"></i>`
                        }
                    </div>
                    <div class="participant-info">
                        <h5>${participant.name}</h5>
                        <p>${participant.email}</p>
                    </div>
                    <button class="btn-remove" onclick="removeParticipant(${participant.user_id})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<p class="no-participants">Nenhum participante encontrado</p>';
        }
    } catch (error) {
        console.error('Erro ao carregar participantes:', error);
        showNotification('Erro ao carregar participantes', 'error');
    }
}

async function loadAvailableUsers() {
    try {
        const response = await fetch('api/get_users.php');
        const result = await response.json();
        
        const container = document.getElementById('addUsersList');
        if (result.success && result.users) {
            container.innerHTML = result.users.map(user => `
                <div class="user-item" data-user-id="${user.id}">
                    <div class="user-avatar">
                        ${user.profile_image ? 
                            `<img src="${user.profile_image}" alt="Avatar">` : 
                            `<i class="fas fa-user"></i>`
                        }
                    </div>
                    <div class="user-info">
                        <h5>${user.name}</h5>
                        <p>${user.email}</p>
                    </div>
                    <button class="btn-add" onclick="addParticipant(${user.id})">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Erro ao carregar usuários:', error);
    }
}

async function addParticipant(userId) {
    try {
        const response = await fetch('api/liga_participants.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'add',
                liga_id: currentLigaId,
                user_id: userId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Participante adicionado com sucesso!', 'success');
            loadCurrentParticipants(currentLigaId);
        } else {
            showNotification(result.message || 'Erro ao adicionar participante', 'error');
        }
    } catch (error) {
        console.error('Erro ao adicionar participante:', error);
        showNotification('Erro de conexão', 'error');
    }
}

async function removeParticipant(userId) {
    if (!confirm('Tem certeza que deseja remover este participante?')) {
        return;
    }
    
    try {
        const response = await fetch('api/liga_participants.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'remove',
                liga_id: currentLigaId,
                user_id: userId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Participante removido com sucesso!', 'success');
            loadCurrentParticipants(currentLigaId);
        } else {
            showNotification(result.message || 'Erro ao remover participante', 'error');
        }
    } catch (error) {
        console.error('Erro ao remover participante:', error);
        showNotification('Erro de conexão', 'error');
    }
}

// ===== FUNÇÕES AUXILIARES =====
function resetParticipants() {
    const checkboxes = document.querySelectorAll('input[name="participants[]"]');
    checkboxes.forEach(checkbox => checkbox.checked = false);
}

async function loadLigaData(ligaId) {
    try {
        const response = await fetch(`api/studio_ligas.php?id=${ligaId}`);
        const result = await response.json();
        
        if (result.success && result.liga) {
            const liga = result.liga;
            
            // Preencher campos básicos
            document.getElementById('ligaName').value = liga.name || '';
            document.getElementById('ligaDescription').value = liga.description || '';
            document.getElementById('startDate').value = liga.start_date || '';
            document.getElementById('endDate').value = liga.end_date || '';
            
            // Preencher recompensas
            if (liga.rewards) {
                const rewards = JSON.parse(liga.rewards);
                document.getElementById('reward_first').value = rewards.first || '';
                document.getElementById('reward_second').value = rewards.second || '';
                document.getElementById('reward_third').value = rewards.third || '';
            }
            
            // Preencher módulos
            if (liga.goals) {
                const goals = JSON.parse(liga.goals);
                Object.keys(goals).forEach(module => {
                    const checkbox = document.querySelector(`input[name="modules[${module}]"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                        toggleModule(module);
                        
                        // Preencher valores
                        Object.keys(goals[module]).forEach(option => {
                            const input = document.querySelector(`input[name="${module}_${option}"]`);
                            if (input) {
                                input.value = goals[module][option];
                            }
                        });
                    }
                });
            }
        }
    } catch (error) {
        console.error('Erro ao carregar dados da liga:', error);
        showNotification('Erro ao carregar dados da liga', 'error');
    }
}

// ===== SISTEMA DE NOTIFICAÇÕES =====
function showNotification(message, type = 'info') {
    // Remover notificação existente
    const existingNotification = document.querySelector('.notification');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Criar nova notificação
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remover após 5 segundos
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// ===== FUNÇÕES AUXILIARES PARA METAS =====
function getGoalIcon(goalType) {
    const icons = {
        'diario': 'utensils',
        'hidratacao': 'tint',
        'missoes': 'tasks',
        'checkin': 'weight'
    };
    return icons[goalType] || 'target';
}

function getGoalLabel(goalType) {
    const labels = {
        'diario': 'Diário Alimentar',
        'hidratacao': 'Hidratação',
        'missoes': 'Missões Diárias',
        'checkin': 'Check-in Semanal'
    };
    return labels[goalType] || goalType;
}

// ===== EVENT LISTENERS =====
document.addEventListener('DOMContentLoaded', function() {
    // Fechar modais ao clicar fora
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            closeLigaModal();
            closeParticipantsModal();
        }
    });
    
    // Fechar modais com ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeLigaModal();
            closeParticipantsModal();
        }
    });
    
    // Validação de datas em tempo real
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    
    if (startDateInput && endDateInput) {
        startDateInput.addEventListener('change', function() {
            endDateInput.min = this.value;
        });
        
        endDateInput.addEventListener('change', function() {
            if (this.value && startDateInput.value && this.value <= startDateInput.value) {
                showNotification('A data de fim deve ser posterior à data de início.', 'error');
                this.value = '';
            }
        });
    }
});

// ===== ANIMAÇÕES CSS =====
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        padding: 16px 20px;
        border-radius: 8px;
        color: white;
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 300px;
        animation: slideIn 0.3s ease;
    }
    
    .notification-success { background: #22c55e; }
    .notification-error { background: #ef4444; }
    .notification-info { background: #3b82f6; }
    
    .notification-content {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
    }
    
    .notification-close {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        padding: 4px;
        border-radius: 4px;
    }
    
    .notification-close:hover {
        background: rgba(255, 255, 255, 0.2);
    }
    
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    .participant-item, .user-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        margin-bottom: 8px;
        transition: all 0.2s ease;
    }
    
    .participant-item:hover, .user-item:hover {
        background: rgba(255, 107, 53, 0.05);
    }
    
    .btn-add, .btn-remove {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }
    
    .btn-add {
        background: var(--accent-orange);
        color: white;
    }
    
    .btn-add:hover {
        background: var(--primary-orange);
        transform: scale(1.1);
    }
    
    .btn-remove {
        background: #ef4444;
        color: white;
    }
    
    .btn-remove:hover {
        background: #dc2626;
        transform: scale(1.1);
    }
    
    .no-participants {
        text-align: center;
        color: var(--text-secondary);
        padding: 20px;
        font-style: italic;
    }
`;
document.head.appendChild(style);
</script>

<style>
/* ===== ESTILOS PARA O ESTÚDIO DE DESAFIOS ===== */

/* Estatísticas do Studio */
.studio-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.stat-card {
    background: var(--surface-color);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    border: 1px solid var(--border-color);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary-orange-gradient);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.stat-content h3 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-primary);
}

.stat-content p {
    margin: 4px 0 0 0;
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
}

/* Grid de Ligas */
.ligas-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
    gap: 28px;
}

.liga-card {
    background: var(--surface-color);
    border-radius: 16px;
    border: 1px solid var(--border-color);
    overflow: hidden;
    transition: all 0.3s ease;
}

.liga-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}

.liga-header {
    padding: 24px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.liga-title h3 {
    margin: 0 0 8px 0;
    color: var(--text-primary);
    font-size: 1.3rem;
}

.liga-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

.liga-status.active {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
}

.liga-status.completed {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.liga-status.cancelled {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.liga-actions {
    display: flex;
    gap: 8px;
}

.btn-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--surface-color);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.btn-icon:hover {
    background: var(--accent-orange);
    color: white;
    border-color: var(--accent-orange);
}

.liga-body {
    padding: 24px;
}

.liga-description {
    color: var(--text-secondary);
    margin-bottom: 24px;
    line-height: 1.6;
    font-size: 0.95rem;
}

.liga-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--text-secondary);
    font-size: 0.9rem;
    padding: 8px 12px;
    background: rgba(255, 107, 53, 0.03);
    border-radius: 8px;
    border: 1px solid rgba(255, 107, 53, 0.1);
}

.stat i {
    color: var(--accent-orange);
    width: 16px;
    font-size: 0.9rem;
}

.liga-rules h4 {
    margin: 0 0 16px 0;
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: 600;
}

.rules-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.rule-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: rgba(255, 107, 53, 0.05);
    border-radius: 8px;
    font-size: 0.9rem;
}

.rule-item i {
    color: var(--accent-orange);
    width: 16px;
    font-size: 0.9rem;
}

/* Modal Styles */
.modal-content.large {
    max-width: 800px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-overlay {
    display: none;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.active {
    display: flex;
    opacity: 1;
    visibility: visible;
}

.modal-content {
    transform: scale(0.9);
    transition: all 0.3s ease;
}

.modal-overlay.active .modal-content {
    transform: scale(1);
}

.form-section {
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border-color);
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.form-section h4 {
    margin: 0 0 16px 0;
    color: var(--text-primary);
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-description {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin: 0 0 20px 0;
}

.scoring-modules {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 16px;
}

.module-card {
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 20px;
    background: var(--surface-color);
    transition: all 0.3s ease;
}

.module-card:hover {
    border-color: rgba(255, 107, 53, 0.3);
}

.module-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.module-header h5 {
    margin: 0;
    color: var(--text-primary);
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .slider {
    background-color: var(--accent-orange);
}

input:checked + .slider:before {
    transform: translateX(26px);
}

.module-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.option-item {
    display: flex;
    align-items: center;
    gap: 12px;
}

.option-item label {
    flex: 1;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.option-item input {
    width: 80px;
}

.option-item span {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.participants-selector {
    max-height: 300px;
    overflow-y: auto;
}

.user-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    margin-bottom: 8px;
    transition: all 0.2s ease;
}

.user-item:hover {
    background: rgba(255, 107, 53, 0.05);
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--border-color);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.user-avatar i {
    color: var(--accent-orange);
    font-size: 1.2rem;
}

.user-info {
    flex: 1;
}

.user-info h5 {
    margin: 0 0 4px 0;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.user-info p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.8rem;
}

.rewards-config {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.reward-item {
    display: flex;
    align-items: center;
    gap: 12px;
}

.reward-item label {
    width: 100px;
    font-weight: 600;
    color: var(--text-primary);
}

.reward-item input {
    flex: 1;
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
}

.empty-icon {
    font-size: 4rem;
    color: var(--text-secondary);
    margin-bottom: 20px;
}

.empty-state h3 {
    margin: 0 0 12px 0;
    color: var(--text-primary);
}

.empty-state p {
    color: var(--text-secondary);
    margin-bottom: 24px;
}

/* Responsividade */
@media (max-width: 768px) {
    .ligas-grid {
        grid-template-columns: 1fr;
    }
    
    .liga-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .liga-actions {
        align-self: flex-end;
    }
    
    .scoring-modules {
        grid-template-columns: 1fr;
    }
}

</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
