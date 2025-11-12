<?php
// admin/checkin_flow_editor.php - Editor Simples de Fluxo de Check-in

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'checkin';
$page_title = 'Editor de Fluxo - Check-in';

$admin_id = $_SESSION['admin_id'] ?? 1;
$checkin_id = (int)($_GET['id'] ?? 0);

if ($checkin_id === 0) {
    header('Location: checkin.php');
    exit;
}

// Buscar check-in
$checkin_query = "SELECT * FROM sf_checkin_configs WHERE id = ? AND admin_id = ?";
$stmt = $conn->prepare($checkin_query);
$stmt->bind_param("ii", $checkin_id, $admin_id);
$stmt->execute();
$checkin_result = $stmt->get_result();
$checkin = $checkin_result->fetch_assoc();
$stmt->close();

if (!$checkin) {
    header('Location: checkin.php');
    exit;
}

// Buscar blocos/perguntas existentes
$blocks_query = "SELECT * FROM sf_checkin_questions WHERE config_id = ? ORDER BY order_index ASC";
$stmt_blocks = $conn->prepare($blocks_query);
$stmt_blocks->bind_param("i", $checkin_id);
$stmt_blocks->execute();
$blocks_result = $stmt_blocks->get_result();
$blocks = [];
while ($b = $blocks_result->fetch_assoc()) {
    $b['options'] = !empty($b['options']) ? json_decode($b['options'], true) : null;
    $blocks[] = $b;
}
$stmt_blocks->close();

require_once __DIR__ . '/includes/header.php';
?>

<style>
.checkin-flow-editor {
    padding: 1.5rem 2rem;
    max-width: 1000px;
    margin: 0 auto;
}

.editor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--glass-border);
}

.editor-header h1 {
    margin: 0;
    font-size: 1.5rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
}

.editor-header h1 i {
    color: var(--accent-orange);
    font-size: 1.25rem;
}

.header-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

/* Botões no estilo das outras páginas */
.btn {
    padding: 0.625rem 0.75rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.8125rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    text-decoration: none;
    border: 1px solid;
    background: transparent;
    color: var(--text-primary);
    font-family: 'Montserrat', sans-serif;
    line-height: 1.2;
}

.btn-primary {
    background: rgba(255, 107, 0, 0.1);
    color: var(--accent-orange);
    border-color: rgba(255, 107, 0, 0.3);
}

.btn-primary:hover {
    background: rgba(255, 107, 0, 0.2);
    border-color: var(--accent-orange);
    transform: translateY(-1px);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    border-color: var(--glass-border);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--accent-orange);
    color: var(--accent-orange);
}

.btn-danger {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.3);
}

.btn-danger:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: #ef4444;
}

.btn i {
    font-size: 0.8125rem;
    flex-shrink: 0;
}

.blocks-container {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.block-item {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 1rem;
    transition: all 0.3s ease;
    position: relative;
}

.block-item:hover {
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

.block-item.editing {
    border-color: var(--accent-orange);
    background: rgba(255, 107, 0, 0.05);
}

.block-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    gap: 0.75rem;
}

.block-header-left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
    min-width: 0;
}

.drag-handle {
    cursor: move;
    color: var(--text-secondary);
    font-size: 0.875rem;
    transition: color 0.2s ease;
    flex-shrink: 0;
}

.drag-handle:hover {
    color: var(--accent-orange);
}

.block-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.25rem 0.5rem;
    background: rgba(255, 107, 0, 0.1);
    color: var(--accent-orange);
    border-radius: 6px;
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.block-type-badge i {
    font-size: 0.6875rem;
}

.block-actions {
    display: flex;
    gap: 0.375rem;
    flex-shrink: 0;
}

.block-actions button {
    padding: 0.375rem 0.5rem;
    background: transparent;
    border: 1px solid var(--glass-border);
    border-radius: 6px;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    min-width: 28px;
    height: 28px;
}

.block-actions button:hover {
    border-color: var(--accent-orange);
    color: var(--accent-orange);
    background: rgba(255, 107, 0, 0.1);
}

.block-actions button.btn-danger {
    border-color: rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.block-actions button.btn-danger:hover {
    background: rgba(239, 68, 68, 0.1);
    border-color: #ef4444;
}

.block-content {
    color: var(--text-primary);
    line-height: 1.5;
    font-size: 0.875rem;
}

.block-content.preview {
    white-space: pre-wrap;
    word-wrap: break-word;
}

.block-options {
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--glass-border);
}

.block-options-list {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.option-item {
    padding: 0.375rem 0.5rem;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 6px;
    font-size: 0.8125rem;
    color: var(--text-secondary);
}

.add-block-section {
    margin-top: 1.5rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.02);
    border: 1px dashed var(--glass-border);
    border-radius: 12px;
    text-align: center;
}

.add-block-section h3 {
    margin: 0 0 0.75rem 0;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.add-block-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-wrap: wrap;
}

.add-block-btn {
    padding: 0.5rem 0.75rem;
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.3);
    border-radius: 8px;
    color: var(--accent-orange);
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    font-size: 0.8125rem;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.add-block-btn:hover {
    background: rgba(255, 107, 0, 0.2);
    border-color: var(--accent-orange);
    transform: translateY(-1px);
}

.add-block-btn i {
    font-size: 0.75rem;
}

/* Formulário de edição */
.block-edit-form {
    display: none;
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px solid var(--glass-border);
}

.block-item.editing .block-edit-form {
    display: block;
}

.form-group {
    margin-bottom: 0.75rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.375rem;
    color: var(--text-primary);
    font-weight: 600;
    font-size: 0.8125rem;
}

.form-control {
    width: 100%;
    padding: 0.625rem 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.875rem;
    font-family: inherit;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

textarea.form-control {
    min-height: 80px;
    resize: vertical;
}

.options-editor {
    margin-top: 0.75rem;
}

.options-editor label {
    margin-bottom: 0.5rem;
}

.option-input-group {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    align-items: center;
}

.option-input-group input {
    flex: 1;
    padding: 0.5rem 0.625rem;
    font-size: 0.8125rem;
}

.option-input-group button {
    padding: 0.375rem 0.625rem;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.75rem;
    font-weight: 600;
    transition: all 0.2s ease;
}

.option-input-group button:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: #ef4444;
}

.add-option-btn {
    margin-top: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.3);
    border-radius: 6px;
    color: var(--accent-orange);
    cursor: pointer;
    font-size: 0.8125rem;
    font-weight: 600;
    transition: all 0.2s ease;
}

.add-option-btn:hover {
    background: rgba(255, 107, 0, 0.2);
    border-color: var(--accent-orange);
}

.form-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.75rem;
}

.empty-state {
    text-align: center;
    padding: 2rem 1rem;
    color: var(--text-secondary);
}

.empty-state i {
    font-size: 2rem;
    margin-bottom: 0.75rem;
    opacity: 0.5;
    color: var(--text-secondary);
}

.empty-state p {
    font-size: 0.875rem;
    margin: 0;
}

.block-item.dragging {
    opacity: 0.5;
}

.block-item.drag-over {
    border-top: 2px solid var(--accent-orange);
}
</style>

<div class="checkin-flow-editor">
    <div class="editor-header">
        <h1>
            <i class="fas fa-clipboard-check"></i>
            Editor de Fluxo: <?php echo htmlspecialchars($checkin['name']); ?>
        </h1>
        <div class="header-actions">
            <a href="checkin.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <button onclick="saveFlow()" class="btn btn-primary">
                <i class="fas fa-save"></i> Salvar
            </button>
        </div>
    </div>

    <div class="blocks-container" id="blocksContainer">
        <?php if (empty($blocks)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>Nenhum bloco criado ainda. Adicione o primeiro bloco abaixo!</p>
            </div>
        <?php else: ?>
            <?php foreach ($blocks as $index => $block): ?>
                <div class="block-item" data-block-id="<?php echo $block['id']; ?>" data-order="<?php echo $block['order_index']; ?>">
                    <div class="block-header">
                        <div class="block-header-left">
                            <i class="fas fa-grip-vertical drag-handle"></i>
                            <span class="block-type-badge">
                                <?php
                                $type_icons = [
                                    'text' => 'fa-comment',
                                    'multiple_choice' => 'fa-list',
                                    'scale' => 'fa-sliders-h'
                                ];
                                $icon = $type_icons[$block['question_type']] ?? 'fa-question';
                                ?>
                                <i class="fas <?php echo $icon; ?>"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $block['question_type'])); ?>
                            </span>
                        </div>
                        <div class="block-actions">
                            <button onclick="editBlock(<?php echo $block['id']; ?>)" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteBlock(<?php echo $block['id']; ?>)" title="Excluir" class="btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="block-content preview">
                        <?php echo nl2br(htmlspecialchars($block['question_text'])); ?>
                        <?php if ($block['question_type'] !== 'text' && !empty($block['options'])): ?>
                            <div class="block-options">
                                <div class="block-options-list">
                                    <?php foreach ($block['options'] as $opt): ?>
                                        <div class="option-item">• <?php echo htmlspecialchars($opt); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="block-edit-form" id="editForm_<?php echo $block['id']; ?>">
                        <form onsubmit="saveBlock(event, <?php echo $block['id']; ?>)">
                            <div class="form-group">
                                <label>Texto da Pergunta/Mensagem</label>
                                <textarea name="question_text" class="form-control" required><?php echo htmlspecialchars($block['question_text']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Tipo</label>
                                <select name="question_type" class="form-control" onchange="toggleOptionsEditor(this, <?php echo $block['id']; ?>)">
                                    <option value="text" <?php echo $block['question_type'] === 'text' ? 'selected' : ''; ?>>Mensagem de Texto</option>
                                    <option value="multiple_choice" <?php echo $block['question_type'] === 'multiple_choice' ? 'selected' : ''; ?>>Múltipla Escolha</option>
                                    <option value="scale" <?php echo $block['question_type'] === 'scale' ? 'selected' : ''; ?>>Escala (0-10)</option>
                                </select>
                            </div>
                            <div class="options-editor" id="optionsEditor_<?php echo $block['id']; ?>" style="display: <?php echo in_array($block['question_type'], ['multiple_choice', 'scale']) ? 'block' : 'none'; ?>;">
                                <label>Opções</label>
                                <div id="optionsList_<?php echo $block['id']; ?>">
                                    <?php if (!empty($block['options'])): ?>
                                        <?php foreach ($block['options'] as $opt): ?>
                                            <div class="option-input-group">
                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($opt); ?>" placeholder="Texto da opção">
                                                <button type="button" onclick="removeOption(this)">Remover</button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="add-option-btn" onclick="addOption(<?php echo $block['id']; ?>)">
                                    <i class="fas fa-plus"></i> Adicionar Opção
                                </button>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Salvar</button>
                                <button type="button" class="btn btn-secondary" onclick="cancelEdit(<?php echo $block['id']; ?>)">Cancelar</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

            <div class="add-block-section">
        <h3>Adicionar Novo Bloco</h3>
        <div class="add-block-buttons">
            <button class="add-block-btn" onclick="addBlock('text')">
                <i class="fas fa-comment"></i> Mensagem
            </button>
            <button class="add-block-btn" onclick="addBlock('multiple_choice')">
                <i class="fas fa-list"></i> Múltipla Escolha
            </button>
            <button class="add-block-btn" onclick="addBlock('scale')">
                <i class="fas fa-sliders-h"></i> Escala
            </button>
        </div>
    </div>
</div>

<script>
const checkinId = <?php echo $checkin_id; ?>;
let editingBlockId = null;
let blockCounter = <?php echo count($blocks); ?>;

// Adicionar novo bloco
function addBlock(type) {
    const blockId = 'new_' + Date.now();
    const typeNames = {
        'text': 'Mensagem de Texto',
        'multiple_choice': 'Múltipla Escolha',
        'scale': 'Escala (0-10)'
    };
    const typeIcons = {
        'text': 'fa-comment',
        'multiple_choice': 'fa-list',
        'scale': 'fa-sliders-h'
    };
    
    const blockHtml = `
        <div class="block-item editing" data-block-id="${blockId}" data-order="${blockCounter++}">
                <div class="block-header">
                    <div class="block-header-left">
                        <i class="fas fa-grip-vertical drag-handle"></i>
                        <span class="block-type-badge">
                            <i class="fas ${typeIcons[type]}"></i>
                            ${typeNames[type]}
                        </span>
                    </div>
                    <div class="block-actions">
                        <button onclick="deleteBlock('${blockId}')" title="Excluir" class="btn-danger">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            <div class="block-content preview" style="display: none;"></div>
            <div class="block-edit-form" id="editForm_${blockId}">
                <form onsubmit="saveNewBlock(event, '${blockId}', '${type}')">
                    <div class="form-group">
                        <label>Texto da Pergunta/Mensagem</label>
                        <textarea name="question_text" class="form-control" required placeholder="Digite o texto..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="question_type" class="form-control" onchange="toggleOptionsEditor(this, '${blockId}')">
                            <option value="text" ${type === 'text' ? 'selected' : ''}>Mensagem de Texto</option>
                            <option value="multiple_choice" ${type === 'multiple_choice' ? 'selected' : ''}>Múltipla Escolha</option>
                            <option value="scale" ${type === 'scale' ? 'selected' : ''}>Escala (0-10)</option>
                        </select>
                    </div>
                    <div class="options-editor" id="optionsEditor_${blockId}" style="display: ${type !== 'text' ? 'block' : 'none'};">
                        <label>Opções</label>
                        <div id="optionsList_${blockId}"></div>
                        <button type="button" class="add-option-btn" onclick="addOption('${blockId}')">
                            <i class="fas fa-plus"></i> Adicionar Opção
                        </button>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Salvar</button>
                        <button type="button" class="btn btn-secondary" onclick="cancelNewBlock('${blockId}')">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    const container = document.getElementById('blocksContainer');
    if (container.querySelector('.empty-state')) {
        container.innerHTML = '';
    }
    container.insertAdjacentHTML('beforeend', blockHtml);
    
    // Scroll para o novo bloco
    const newBlock = container.querySelector(`[data-block-id="${blockId}"]`);
    newBlock.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Salvar novo bloco
function saveNewBlock(event, blockId, defaultType) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const questionText = formData.get('question_text');
    const questionType = formData.get('question_type') || defaultType;
    
    // Coletar opções
    const options = [];
    if (questionType !== 'text') {
        const optionsList = document.getElementById(`optionsList_${blockId}`);
        const optionInputs = optionsList.querySelectorAll('input');
        optionInputs.forEach(input => {
            const val = input.value.trim();
            if (val) options.push(val);
        });
    }
    
    // Salvar no servidor
    fetch('ajax_checkin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'save_block',
            config_id: checkinId,
            question_text: questionText,
            question_type: questionType,
            options: JSON.stringify(options),
            order_index: document.querySelectorAll('.block-item').length
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro ao salvar: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar bloco');
    });
}

// Cancelar novo bloco
function cancelNewBlock(blockId) {
    const block = document.querySelector(`[data-block-id="${blockId}"]`);
    if (block) {
        block.remove();
    }
    
    // Se não houver mais blocos, mostrar empty state
    const container = document.getElementById('blocksContainer');
    if (container.children.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>Nenhum bloco criado ainda. Adicione o primeiro bloco abaixo!</p>
            </div>
        `;
    }
}

// Editar bloco existente
function editBlock(blockId) {
    // Fechar outros editores
    document.querySelectorAll('.block-item.editing').forEach(item => {
        if (item.dataset.blockId != blockId) {
            item.classList.remove('editing');
            const form = item.querySelector('.block-edit-form');
            if (form) form.style.display = 'none';
            const preview = item.querySelector('.block-content.preview');
            if (preview) preview.style.display = 'block';
        }
    });
    
    const block = document.querySelector(`[data-block-id="${blockId}"]`);
    if (block) {
        block.classList.add('editing');
        const form = block.querySelector('.block-edit-form');
        const preview = block.querySelector('.block-content.preview');
        if (form) form.style.display = 'block';
        if (preview) preview.style.display = 'none';
        editingBlockId = blockId;
    }
}

// Cancelar edição
function cancelEdit(blockId) {
    const block = document.querySelector(`[data-block-id="${blockId}"]`);
    if (block) {
        block.classList.remove('editing');
        const form = block.querySelector('.block-edit-form');
        const preview = block.querySelector('.block-content.preview');
        if (form) form.style.display = 'none';
        if (preview) preview.style.display = 'block';
        editingBlockId = null;
    }
}

// Salvar bloco editado
function saveBlock(event, blockId) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const questionText = formData.get('question_text');
    const questionType = formData.get('question_type');
    
    // Coletar opções
    const options = [];
    if (questionType !== 'text') {
        const optionsList = document.getElementById(`optionsList_${blockId}`);
        const optionInputs = optionsList.querySelectorAll('input');
        optionInputs.forEach(input => {
            const val = input.value.trim();
            if (val) options.push(val);
        });
    }
    
    // Salvar no servidor
    fetch('ajax_checkin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'update_block',
            block_id: blockId,
            question_text: questionText,
            question_type: questionType,
            options: JSON.stringify(options)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro ao salvar: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar bloco');
    });
}

// Excluir bloco
function deleteBlock(blockId) {
    if (!confirm('Tem certeza que deseja excluir este bloco?')) {
        return;
    }
    
    // Se for um bloco novo, apenas remover do DOM
    if (blockId.toString().startsWith('new_')) {
        cancelNewBlock(blockId);
        return;
    }
    
    // Se for um bloco existente, deletar do servidor
    fetch('ajax_checkin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'delete_block',
            block_id: blockId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro ao excluir: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao excluir bloco');
    });
}

// Toggle editor de opções
function toggleOptionsEditor(select, blockId) {
    const optionsEditor = document.getElementById(`optionsEditor_${blockId}`);
    if (select.value === 'text') {
        optionsEditor.style.display = 'none';
    } else {
        optionsEditor.style.display = 'block';
    }
}

// Adicionar opção
function addOption(blockId) {
    const optionsList = document.getElementById(`optionsList_${blockId}`);
    const optionHtml = `
        <div class="option-input-group">
            <input type="text" class="form-control" placeholder="Texto da opção">
            <button type="button" onclick="removeOption(this)">Remover</button>
        </div>
    `;
    optionsList.insertAdjacentHTML('beforeend', optionHtml);
}

// Remover opção
function removeOption(button) {
    button.closest('.option-input-group').remove();
}

// Salvar ordem dos blocos
function saveFlow() {
    const blocks = Array.from(document.querySelectorAll('.block-item'));
    const order = blocks.map((block, index) => ({
        id: block.dataset.blockId,
        order: index
    })).filter(item => !item.id.toString().startsWith('new_'));
    
    fetch('ajax_checkin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'save_block_order',
            config_id: checkinId,
            order: JSON.stringify(order)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Ordem salva com sucesso!');
        } else {
            alert('Erro ao salvar ordem: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar ordem');
    });
}

// Drag and drop simples
let draggedElement = null;

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('blocksContainer');
    
    container.addEventListener('mousedown', function(e) {
        if (e.target.classList.contains('drag-handle') || e.target.closest('.drag-handle')) {
            draggedElement = e.target.closest('.block-item');
            if (draggedElement) {
                draggedElement.classList.add('dragging');
                e.preventDefault();
            }
        }
    });
    
    document.addEventListener('mousemove', function(e) {
        if (draggedElement) {
            e.preventDefault();
            const afterElement = getDragAfterElement(container, e.clientY);
            if (afterElement == null) {
                container.appendChild(draggedElement);
            } else {
                container.insertBefore(draggedElement, afterElement);
            }
        }
    });
    
    document.addEventListener('mouseup', function() {
        if (draggedElement) {
            draggedElement.classList.remove('dragging');
            draggedElement = null;
            saveFlow();
        }
    });
});

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.block-item:not(.dragging)')];
    
    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
