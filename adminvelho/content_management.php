<?php
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$page_title = 'Gerenciamento de Conteúdo';
$page_slug = 'content_management';

// Buscar conteúdos existentes
$conn = require __DIR__ . '/../includes/db.php';
$content_query = "SELECT mc.*, a.full_name as author_name
                  FROM sf_member_content mc
                  LEFT JOIN sf_admins a ON mc.admin_id = a.id
                  ORDER BY mc.created_at DESC";
$content_result = $conn->query($content_query);
$contents = $content_result->fetch_all(MYSQLI_ASSOC);

// Buscar categorias (usando sf_categories existente)
$categories_query = "SELECT * FROM sf_categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Buscar usuários para conteúdo específico
$users_query = "SELECT id, name, email FROM sf_users ORDER BY name";
$users_result = $conn->query($users_query);
$users = $users_result->fetch_all(MYSQLI_ASSOC);

// Buscar grupos para conteúdo específico
$groups_query = "SELECT id, group_name as name FROM sf_user_groups WHERE is_active = 1 ORDER BY group_name";
$groups_result = $conn->query($groups_query);
$groups = $groups_result->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-wrapper">
    <div class="main-content">
        <!-- Header Premium -->
        <div class="main-header">
            <div class="header-content">
                <h1><i class="fas fa-edit"></i> Gerenciamento de Conteúdo</h1>
                <p class="header-subtitle">Crie e gerencie conteúdos para a área de membros dos seus pacientes</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openCreateContentModal()">
                    <i class="fas fa-plus"></i> Criar Conteúdo
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
                    <label><i class="fas fa-tag"></i> Tipo de Conteúdo</label>
                    <select id="typeFilter" class="form-control" onchange="filterContent()">
                        <option value="">Todos os tipos</option>
                        <option value="chef">Chef</option>
                        <option value="supplements">Suplementos</option>
                        <option value="videos">Vídeos</option>
                        <option value="articles">Artigos</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-toggle-on"></i> Status</label>
                    <select id="statusFilter" class="form-control" onchange="filterContent()">
                        <option value="">Todos os status</option>
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                        <option value="draft">Rascunho</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-search"></i> Buscar</label>
                    <input type="text" id="searchFilter" class="form-control" placeholder="Título ou descrição..." onkeyup="filterContent()">
                </div>
            </div>
        </div>

        <!-- Grid de Conteúdos Premium -->
        <div class="content-grid" id="contentGrid">
            <?php if (empty($contents)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <h3>Nenhum conteúdo encontrado</h3>
                    <p>Crie seu primeiro conteúdo para a área de membros</p>
                    <button class="btn btn-primary" onclick="openCreateContentModal()">
                        <i class="fas fa-plus"></i> Criar Primeiro Conteúdo
                    </button>
                </div>
            <?php else: ?>
            <?php foreach ($contents as $content): ?>
                <div class="content-card" data-type="<?php echo $content['content_type']; ?>" data-status="<?php echo $content['status']; ?>">
                    <div class="content-header">
                        <div class="content-type-icon">
                            <?php
                            $icon = 'fas fa-file';
                            switch($content['content_type']) {
                                case 'chef':
                                    $icon = 'fas fa-utensils';
                                    break;
                                case 'supplements':
                                    $icon = 'fas fa-pills';
                                    break;
                                case 'videos':
                                    $icon = 'fas fa-play';
                                    break;
                                case 'articles':
                                    $icon = 'fas fa-file-alt';
                                    break;
                            }
                            ?>
                            <i class="<?php echo $icon; ?>"></i>
                        </div>
                        <div class="content-actions">
                            <button class="btn btn-sm btn-secondary" onclick="viewContent(<?php echo $content['id']; ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-primary" onclick="editContent(<?php echo $content['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteContent(<?php echo $content['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="content-body">
                        <h3><?php echo htmlspecialchars($content['title']); ?></h3>
                        <p><?php echo htmlspecialchars(substr($content['description'], 0, 100)) . '...'; ?></p>
                        
                        <div class="content-meta">
                            <div class="meta-item">
                                <i class="fas fa-user"></i>
                                <span><?php echo htmlspecialchars($content['author_name']); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span><?php echo date('d/m/Y', strtotime($content['created_at'])); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-tag"></i>
                                <span><?php echo $content['categories'] ?: 'Sem categoria'; ?></span>
                            </div>
                        </div>
                        
                        <div class="content-target">
                            <i class="fas fa-target"></i>
                            <span>
                                <?php
                                switch($content['target_type']) {
                                    case 'all':
                                        echo 'Todos os usuários';
                                        break;
                                    case 'user':
                                        echo 'Usuário específico';
                                        break;
                                    case 'group':
                                        echo 'Grupo específico';
                                        break;
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="content-footer">
                        <div class="content-status">
                            <span class="status-badge <?php echo $content['status']; ?>">
                                <?php echo ucfirst($content['status']); ?>
                            </span>
                        </div>
                        <div class="content-stats">
                            <span><i class="fas fa-eye"></i> 0</span>
                            <span><i class="fas fa-download"></i> 0</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Criar/Editar Conteúdo -->
<div id="contentModal" class="modal">
    <div class="modal-content extra-large">
        <div class="modal-header">
            <h2 id="modalTitle">Criar Conteúdo</h2>
            <span class="close" onclick="closeContentModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="contentForm" enctype="multipart/form-data">
                <input type="hidden" id="contentId" name="content_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="contentTitle">Título *</label>
                        <input type="text" id="contentTitle" name="title" class="form-control" required placeholder="Ex: Receita de Salada Fit">
                    </div>
                    <div class="form-group">
                        <label for="contentType">Tipo de Conteúdo *</label>
                        <select id="contentType" name="content_type" class="form-control" required onchange="toggleContentFields()">
                            <option value="">Selecione...</option>
                            <option value="chef">Chef</option>
                            <option value="supplements">Suplementos</option>
                            <option value="videos">Vídeos</option>
                            <option value="articles">Artigos</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="contentDescription">Descrição</label>
                    <textarea id="contentDescription" name="description" class="form-control" rows="3" placeholder="Descreva o conteúdo"></textarea>
                </div>
                
                <div class="form-group" id="fileUploadGroup">
                    <label for="contentFile">Arquivo</label>
                    <input type="file" id="contentFile" name="file" class="form-control" accept="image/*,video/*,.pdf">
                    <small>Formatos aceitos: Imagens, Vídeos, PDF</small>
                </div>
                
                <div class="form-group" id="contentTextGroup" style="display: none;">
                    <label for="contentText">Conteúdo do Artigo</label>
                    <textarea id="contentText" name="content_text" rows="10"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="targetType"><i class="fas fa-users"></i> Público-Alvo *</label>
                        <select id="targetType" name="target_type" class="form-control" required onchange="toggleTargetFields()">
                            <option value="">Selecione...</option>
                            <option value="all">Todos os usuários</option>
                            <option value="user">Usuário específico</option>
                            <option value="group">Grupo específico</option>
                        </select>
                    </div>
                    <div class="form-group" id="targetIdGroup" style="display: none;">
                        <label for="targetId"><i class="fas fa-list"></i> Selecionar</label>
                        <select id="targetId" name="target_id" class="form-control">
                            <option value="">Selecione...</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Categorias</label>
                    <div class="categories-selection">
                        <?php foreach ($categories as $category): ?>
                            <div class="category-item">
                                <label>
                                    <input type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>">
                                    <i class="fas fa-tag"></i>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="contentStatus">Status</label>
                    <select id="contentStatus" name="status" class="form-control">
                        <option value="draft">Rascunho</option>
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeContentModal()">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="saveContent()">Salvar Conteúdo</button>
        </div>
    </div>
</div>

<script>
// Dados para JavaScript
const users = <?php echo json_encode($users); ?>;
const groups = <?php echo json_encode($groups); ?>;

// Função para abrir modal de criar conteúdo
function openCreateContentModal() {
    document.getElementById('modalTitle').textContent = 'Criar Conteúdo';
    document.getElementById('contentForm').reset();
    document.getElementById('contentId').value = '';
    document.getElementById('contentModal').style.display = 'block';
}

// Função para editar conteúdo
function editContent(contentId) {
    // Implementar busca de dados do conteúdo e preenchimento do modal
    alert('Editar conteúdo: ' + contentId);
}

// Função para visualizar conteúdo
function viewContent(contentId) {
    // Implementar visualização do conteúdo
    alert('Visualizar conteúdo: ' + contentId);
}

// Função para excluir conteúdo
function deleteContent(contentId) {
    if (confirm('Tem certeza que deseja excluir este conteúdo?')) {
        // Implementar exclusão do conteúdo
        alert('Excluir conteúdo: ' + contentId);
    }
}

// Função para fechar modal
function closeContentModal() {
    document.getElementById('contentModal').style.display = 'none';
}

// Função para salvar conteúdo
function saveContent() {
    const form = document.getElementById('contentForm');
    const formData = new FormData(form);
    
    // Implementar salvamento do conteúdo
    alert('Salvar conteúdo');
}

// Função para alternar campos baseado no tipo de conteúdo
function toggleContentFields() {
    const contentType = document.getElementById('contentType').value;
    const fileUploadGroup = document.getElementById('fileUploadGroup');
    const contentTextGroup = document.getElementById('contentTextGroup');
    
    if (contentType === 'articles') {
        fileUploadGroup.style.display = 'none';
        contentTextGroup.style.display = 'block';
    } else {
        fileUploadGroup.style.display = 'block';
        contentTextGroup.style.display = 'none';
    }
}

// Função para alternar campos baseado no público-alvo
function toggleTargetFields() {
    const targetType = document.getElementById('targetType').value;
    const targetIdGroup = document.getElementById('targetIdGroup');
    const targetId = document.getElementById('targetId');
    
    if (targetType === 'all') {
        targetIdGroup.style.display = 'none';
    } else {
        targetIdGroup.style.display = 'block';
        
        // Limpar opções
        targetId.innerHTML = '<option value="">Selecione...</option>';
        
        // Adicionar opções baseadas no tipo
        if (targetType === 'user') {
            users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = user.name;
                targetId.appendChild(option);
            });
        } else if (targetType === 'group') {
            groups.forEach(group => {
                const option = document.createElement('option');
                option.value = group.id;
                option.textContent = group.name;
                targetId.appendChild(option);
            });
        }
    }
}

// Função para filtrar conteúdo
function filterContent() {
    const typeFilter = document.getElementById('typeFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    const searchFilter = document.getElementById('searchFilter').value.toLowerCase();
    const cards = document.querySelectorAll('.content-card');
    
    cards.forEach(card => {
        const type = card.dataset.type;
        const status = card.dataset.status;
        const title = card.querySelector('h3').textContent.toLowerCase();
        const description = card.querySelector('p').textContent.toLowerCase();
        
        const typeMatch = !typeFilter || type === typeFilter;
        const statusMatch = !statusFilter || status === statusFilter;
        const searchMatch = !searchFilter || title.includes(searchFilter) || description.includes(searchFilter);
        
        if (typeMatch && statusMatch && searchMatch) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    const modal = document.getElementById('contentModal');
    if (event.target === modal) {
        closeContentModal();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
