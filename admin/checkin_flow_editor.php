<?php
// admin/checkin_flow_editor.php - Editor Visual de Fluxo de Check-in

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

// Buscar perguntas
$questions_query = "SELECT * FROM sf_checkin_questions WHERE config_id = ? ORDER BY order_index ASC";
$stmt_questions = $conn->prepare($questions_query);
$stmt_questions->bind_param("i", $checkin_id);
$stmt_questions->execute();
$questions_result = $stmt_questions->get_result();
$questions = [];
while ($q = $questions_result->fetch_assoc()) {
    $q['options'] = !empty($q['options']) ? json_decode($q['options'], true) : null;
    $questions[] = $q;
}
$stmt_questions->close();

// Buscar fluxo salvo (se existir)
$saved_flow = null;
if (!empty($checkin['flow_data'])) {
    $saved_flow = json_decode($checkin['flow_data'], true);
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
.flow-editor-page {
    padding: 1.5rem 2rem;
    min-height: 100vh;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.flow-editor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--glass-border);
}

.flow-editor-header h2 {
    margin: 0;
    font-size: 1.75rem;
    color: var(--text-primary);
}

.flow-editor-toolbar {
    display: flex;
    gap: 1rem;
    align-items: center;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
}

.toolbar-section {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.toolbar-divider {
    width: 1px;
    height: 32px;
    background: var(--glass-border);
    margin: 0 0.5rem;
}

.btn-toolbar {
    padding: 0.625rem 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    color: var(--text-primary);
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-toolbar:hover {
    background: rgba(255, 107, 0, 0.1);
    border-color: var(--accent-orange);
    color: var(--accent-orange);
}

.btn-toolbar.primary {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    color: white;
}

.btn-toolbar.primary:hover {
    background: #e55a00;
    transform: translateY(-2px);
}

.flow-canvas-container {
    position: relative;
    width: 100%;
    height: calc(100vh - 300px);
    min-height: 600px;
    background: 
        linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
    background-size: 20px 20px;
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    overflow: hidden;
    background-color: rgba(20, 20, 20, 0.5);
}

.flow-canvas {
    width: 100%;
    height: 100%;
    position: relative;
    cursor: grab;
    transform-origin: top left;
    transition: transform 0.1s ease-out;
}

.flow-canvas.dragging {
    cursor: grabbing;
}

.flow-canvas.panning {
    cursor: grabbing !important;
}

.flow-node {
    position: absolute;
    min-width: 200px;
    max-width: 300px;
    background: rgba(255, 255, 255, 0.08);
    border: 2px solid var(--glass-border);
    border-radius: 12px;
    padding: 1rem;
    cursor: move;
    transition: box-shadow 0.3s ease, border-color 0.3s ease;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
    user-select: none;
}

.flow-node:hover {
    border-color: var(--accent-orange);
    box-shadow: 0 6px 24px rgba(255, 107, 0, 0.3);
    transform: translateY(-2px);
}

.flow-node.selected {
    border-color: var(--accent-orange);
    box-shadow: 0 0 0 4px rgba(255, 107, 0, 0.2);
}

.flow-node-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--glass-border);
}

.flow-node-type {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    font-size: 0.875rem;
    color: var(--accent-orange);
    text-transform: uppercase;
}

.flow-node-actions {
    display: flex;
    gap: 0.5rem;
}

.btn-node-action {
    width: 24px;
    height: 24px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    font-size: 0.75rem;
}

.btn-node-action:hover {
    background: rgba(255, 107, 0, 0.2);
    color: var(--accent-orange);
}

.flow-node-content {
    font-size: 0.9rem;
    color: var(--text-primary);
    line-height: 1.5;
    word-wrap: break-word;
}

.flow-node-connector {
    position: absolute;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--accent-orange);
    border: 2px solid rgba(255, 255, 255, 0.2);
    cursor: pointer;
    transition: all 0.2s ease;
    z-index: 10;
}

.flow-node-connector:hover {
    transform: scale(1.3);
    box-shadow: 0 0 8px rgba(255, 107, 0, 0.6);
}

.flow-node-connector.input {
    top: -8px;
    left: 50%;
    transform: translateX(-50%);
}

.flow-node-connector.output {
    bottom: -8px;
    left: 50%;
    transform: translateX(-50%);
}

.flow-connection {
    position: absolute;
    pointer-events: none;
    z-index: 1;
}

.flow-connection-line {
    stroke: var(--accent-orange);
    stroke-width: 2;
    fill: none;
    marker-end: url(#arrowhead);
}

.flow-connection-line.connecting {
    stroke-dasharray: 5,5;
    animation: dash 0.5s linear infinite;
}

@keyframes dash {
    to {
        stroke-dashoffset: -10;
    }
}

.node-palette {
    position: fixed;
    right: 2rem;
    top: 50%;
    transform: translateY(-50%);
    width: 200px;
    background: rgba(20, 20, 20, 0.95);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 1rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
    z-index: 100;
}

.node-palette h3 {
    margin: 0 0 1rem 0;
    font-size: 1rem;
    color: var(--text-primary);
    font-weight: 700;
}

.palette-item {
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    margin-bottom: 0.5rem;
    cursor: grab;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-primary);
}

.palette-item:hover {
    background: rgba(255, 107, 0, 0.1);
    border-color: var(--accent-orange);
    transform: translateX(4px);
}

.palette-item:active {
    cursor: grabbing;
}

.palette-item i {
    color: var(--accent-orange);
    width: 20px;
    text-align: center;
}

.zoom-controls {
    position: absolute;
    bottom: 1rem;
    right: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    z-index: 50;
}

.btn-zoom {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: rgba(20, 20, 20, 0.9);
    border: 1px solid var(--glass-border);
    color: var(--text-primary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.btn-zoom:hover {
    background: rgba(255, 107, 0, 0.2);
    border-color: var(--accent-orange);
    color: var(--accent-orange);
}
</style>

<div class="flow-editor-page">
    <div class="flow-editor-header">
        <div>
            <h2><i class="fas fa-project-diagram"></i> Editor de Fluxo - <?php echo htmlspecialchars($checkin['name']); ?></h2>
            <p style="margin: 0.5rem 0 0 0; color: var(--text-secondary); font-size: 0.9rem;">
                Arraste blocos para criar o fluxo de conversa do check-in
            </p>
        </div>
        <div style="display: flex; gap: 1rem;">
            <button class="btn-toolbar" onclick="window.location.href='checkin.php'">
                <i class="fas fa-arrow-left"></i> Voltar
            </button>
            <button class="btn-toolbar primary" onclick="saveFlow()">
                <i class="fas fa-save"></i> Salvar Fluxo
            </button>
        </div>
    </div>

    <div class="flow-editor-toolbar">
        <div class="toolbar-section">
            <button class="btn-toolbar" onclick="addNode('text')" title="Adicionar Mensagem de Texto">
                <i class="fas fa-comment"></i> Texto
            </button>
            <button class="btn-toolbar" onclick="addNode('question')" title="Adicionar Pergunta">
                <i class="fas fa-question"></i> Pergunta
            </button>
            <button class="btn-toolbar" onclick="addNode('condition')" title="Adicionar Condição">
                <i class="fas fa-code-branch"></i> Condição
            </button>
        </div>
        <div class="toolbar-divider"></div>
        <div class="toolbar-section">
            <button class="btn-toolbar" onclick="clearCanvas()" title="Limpar Canvas">
                <i class="fas fa-trash"></i> Limpar
            </button>
            <button class="btn-toolbar" onclick="resetFlow()" title="Resetar para Padrão">
                <i class="fas fa-redo"></i> Resetar
            </button>
        </div>
        <div class="toolbar-divider"></div>
        <div class="toolbar-section">
            <button class="btn-toolbar" onclick="zoomIn()" title="Zoom In">
                <i class="fas fa-search-plus"></i>
            </button>
            <button class="btn-toolbar" onclick="zoomOut()" title="Zoom Out">
                <i class="fas fa-search-minus"></i>
            </button>
            <button class="btn-toolbar" onclick="resetZoom()" title="Resetar Zoom">
                <i class="fas fa-expand-arrows-alt"></i>
            </button>
        </div>
    </div>

    <div class="flow-canvas-container">
        <svg id="connectionsLayer" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: stroke; z-index: 1; overflow: visible;">
            <defs>
                <marker id="arrowhead" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto">
                    <polygon points="0 0, 10 3, 0 6" fill="var(--accent-orange)" />
                </marker>
            </defs>
        </svg>
        <div id="flowCanvas" class="flow-canvas"></div>
        <div class="zoom-controls">
            <button class="btn-zoom" onclick="zoomIn()" title="Zoom In">
                <i class="fas fa-plus"></i>
            </button>
            <button class="btn-zoom" onclick="zoomOut()" title="Zoom Out">
                <i class="fas fa-minus"></i>
            </button>
            <button class="btn-zoom" onclick="resetZoom()" title="Resetar Zoom">
                <i class="fas fa-expand"></i>
            </button>
        </div>
    </div>
</div>

<div class="node-palette">
    <h3>Blocos</h3>
    <div class="palette-item" draggable="true" data-type="text">
        <i class="fas fa-comment"></i>
        <span>Mensagem</span>
    </div>
    <div class="palette-item" draggable="true" data-type="question">
        <i class="fas fa-question-circle"></i>
        <span>Pergunta</span>
    </div>
    <div class="palette-item" draggable="true" data-type="condition">
        <i class="fas fa-code-branch"></i>
        <span>Condição</span>
    </div>
    <div class="palette-item" draggable="true" data-type="delay">
        <i class="fas fa-clock"></i>
        <span>Aguardar</span>
    </div>
    <div class="palette-item" draggable="true" data-type="end">
        <i class="fas fa-stop-circle"></i>
        <span>Finalizar</span>
    </div>
</div>

<script>
// Estado do editor
let nodes = [];
let connections = [];
let selectedNode = null;
let isDragging = false;
let isPanning = false;
let dragOffset = { x: 0, y: 0 };
let panStart = { x: 0, y: 0 };
let canvasOffset = { x: 0, y: 0 };
let isConnecting = false;
let connectionStart = null;
let zoomLevel = 1;
let nodeIdCounter = 0;
let currentDraggingNode = null;

// Inicializar canvas
const canvas = document.getElementById('flowCanvas');
const connectionsLayer = document.getElementById('connectionsLayer');
const canvasContainer = document.querySelector('.flow-canvas-container');

// Pan do canvas (arrastar o canvas inteiro com botão direito ou espaço)
canvas.addEventListener('mousedown', (e) => {
    // Se clicou em um nó ou conector, não fazer pan
    if (e.target.closest('.flow-node') || e.target.closest('.flow-node-connector')) {
        return;
    }
    
    // Pan com botão direito ou botão do meio ou Ctrl/Cmd
    if (e.button === 2 || e.button === 1 || e.ctrlKey || e.metaKey) {
        isPanning = true;
        panStart.x = e.clientX - canvasOffset.x;
        panStart.y = e.clientY - canvasOffset.y;
        canvas.style.cursor = 'grabbing';
        canvas.classList.add('panning');
        e.preventDefault();
    }
});

canvas.addEventListener('mousemove', (e) => {
    if (isPanning) {
        canvasOffset.x = e.clientX - panStart.x;
        canvasOffset.y = e.clientY - panStart.y;
        
        // Aplicar transformação ao canvas
        canvas.style.transform = `translate(${canvasOffset.x}px, ${canvasOffset.y}px) scale(${zoomLevel})`;
        connectionsLayer.style.transform = `translate(${canvasOffset.x}px, ${canvasOffset.y}px) scale(${zoomLevel})`;
        
        updateConnections();
    }
});

canvas.addEventListener('mouseup', () => {
    if (isPanning) {
        isPanning = false;
        canvas.style.cursor = 'grab';
        canvas.classList.remove('panning');
    }
});

canvas.addEventListener('mouseleave', () => {
    if (isPanning) {
        isPanning = false;
        canvas.style.cursor = 'grab';
        canvas.classList.remove('panning');
    }
});

// Prevenir menu de contexto no canvas
canvas.addEventListener('contextmenu', (e) => {
    e.preventDefault();
});

// Atualizar cursor
canvas.style.cursor = 'grab';

// Carregar fluxo existente ou criar padrão
function loadFlow() {
    const savedFlow = <?php echo $saved_flow ? json_encode($saved_flow) : 'null'; ?>;
    const questions = <?php echo json_encode($questions); ?>;
    
    if (savedFlow && savedFlow.nodes && savedFlow.nodes.length > 0) {
        // Carregar fluxo salvo
        savedFlow.nodes.forEach(node => {
            nodes.push(node);
            renderNode(node);
        });
        
        if (savedFlow.connections) {
            connections = savedFlow.connections;
        }
        
        const maxId = nodes.length > 0 ? Math.max(...nodes.map(n => {
            const match = n.id.match(/node_(\d+)/);
            return match ? parseInt(match[1]) : 0;
        })) : 0;
        nodeIdCounter = maxId + 1;
    } else {
        // Criar fluxo padrão baseado nas perguntas
        if (questions.length > 0) {
            let y = 100;
            questions.forEach((q, index) => {
                addNode('question', 200, y + (index * 150), {
                    questionId: q.id,
                    text: q.question_text,
                    type: q.question_type,
                    options: q.options
                });
            });
        } else {
            // Fluxo padrão vazio
            addNode('text', 200, 100, { text: 'Bem-vindo ao check-in!' });
        }
    }
    
    updateConnections();
}

function addNode(type, x = null, y = null, data = {}) {
    const nodeId = `node_${nodeIdCounter++}`;
    const node = {
        id: nodeId,
        type: type,
        x: x || Math.random() * 400 + 100,
        y: y || Math.random() * 300 + 100,
        data: data
    };
    
    nodes.push(node);
    renderNode(node);
    return node;
}

function renderNode(node) {
    const nodeEl = document.createElement('div');
    nodeEl.className = 'flow-node';
    nodeEl.id = node.id;
    nodeEl.style.left = node.x + 'px';
    nodeEl.style.top = node.y + 'px';
    nodeEl.dataset.nodeId = node.id;
    
    const typeLabels = {
        'text': 'Mensagem',
        'question': 'Pergunta',
        'condition': 'Condição',
        'delay': 'Aguardar',
        'end': 'Finalizar'
    };
    
    const typeIcons = {
        'text': 'fa-comment',
        'question': 'fa-question-circle',
        'condition': 'fa-code-branch',
        'delay': 'fa-clock',
        'end': 'fa-stop-circle'
    };
    
    nodeEl.innerHTML = `
        <div class="flow-node-header">
            <div class="flow-node-type">
                <i class="fas ${typeIcons[node.type]}"></i>
                <span>${typeLabels[node.type]}</span>
            </div>
            <div class="flow-node-actions">
                <button class="btn-node-action" onclick="editNode('${node.id}')" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-node-action" onclick="deleteNode('${node.id}')" title="Excluir">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <div class="flow-node-content">
            ${getNodeContent(node)}
        </div>
        <div class="flow-node-connector input" data-connector="input" data-node="${node.id}"></div>
        <div class="flow-node-connector output" data-connector="output" data-node="${node.id}"></div>
    `;
    
    canvas.appendChild(nodeEl);
    
    // Event listeners
    nodeEl.addEventListener('mousedown', (e) => {
        if (e.target.closest('.flow-node-connector')) return;
        if (e.target.closest('.btn-node-action')) return;
        e.stopPropagation();
        startDragNode(e, node.id);
    });
    
    nodeEl.addEventListener('click', (e) => {
        if (e.target.closest('.btn-node-action')) return;
        if (e.target.closest('.flow-node-connector')) return;
        e.stopPropagation();
        selectNode(node.id);
    });
    
    const connectors = nodeEl.querySelectorAll('.flow-node-connector');
    connectors.forEach(connector => {
        connector.addEventListener('mousedown', (e) => {
            e.stopPropagation();
            startConnection(e, node.id, connector.dataset.connector);
        });
    });
}

function getNodeContent(node) {
    switch(node.type) {
        case 'text':
            return node.data.text || 'Nova mensagem';
        case 'question':
            return node.data.text || 'Nova pergunta';
        case 'condition':
            return node.data.condition || 'Nova condição';
        case 'delay':
            return `Aguardar ${node.data.delay || 0} segundos`;
        case 'end':
            return 'Finalizar check-in';
        default:
            return '';
    }
}

function selectNode(nodeId) {
    // Deselecionar todos
    document.querySelectorAll('.flow-node').forEach(n => n.classList.remove('selected'));
    
    // Selecionar novo
    const nodeEl = document.getElementById(nodeId);
    if (nodeEl) {
        nodeEl.classList.add('selected');
        selectedNode = nodeId;
    }
}

function startDragNode(e, nodeId) {
    if (e.target.closest('.flow-node-connector')) return;
    if (e.target.closest('.btn-node-action')) return;
    
    isDragging = true;
    currentDraggingNode = nodeId;
    selectedNode = nodeId;
    selectNode(nodeId);
    
    const nodeEl = document.getElementById(nodeId);
    const node = nodes.find(n => n.id === nodeId);
    if (!node || !nodeEl) return;
    
    const canvasRect = canvas.getBoundingClientRect();
    
    // Calcular offset correto considerando zoom e pan
    // Posição do mouse em coordenadas do canvas
    const mouseX = e.clientX - canvasRect.left;
    const mouseY = e.clientY - canvasRect.top;
    
    // Converter para coordenadas do canvas (considerando zoom e pan)
    const canvasX = (mouseX - canvasOffset.x) / zoomLevel;
    const canvasY = (mouseY - canvasOffset.y) / zoomLevel;
    
    // Offset relativo à posição do nó
    dragOffset.x = canvasX - node.x;
    dragOffset.y = canvasY - node.y;
    
    canvas.classList.add('dragging');
    nodeEl.style.zIndex = '1000';
    
    document.addEventListener('mousemove', dragNode);
    document.addEventListener('mouseup', stopDragNode);
    
    e.preventDefault();
    e.stopPropagation();
}

function dragNode(e) {
    if (!isDragging || !currentDraggingNode) return;
    if (isPanning) return; // Não arrastar nó se estiver fazendo pan
    
    const canvasRect = canvas.getBoundingClientRect();
    const node = nodes.find(n => n.id === currentDraggingNode);
    if (!node) return;
    
    // Calcular nova posição considerando zoom e pan
    // Primeiro, converter coordenadas do mouse para coordenadas do canvas
    const mouseX = e.clientX - canvasRect.left;
    const mouseY = e.clientY - canvasRect.top;
    
    // Aplicar transformação inversa (considerar zoom e pan)
    const canvasX = (mouseX - canvasOffset.x) / zoomLevel;
    const canvasY = (mouseY - canvasOffset.y) / zoomLevel;
    
    // Calcular nova posição do nó
    const newX = canvasX - dragOffset.x / zoomLevel;
    const newY = canvasY - dragOffset.y / zoomLevel;
    
    // Limitar dentro dos bounds do canvas (opcional)
    node.x = Math.max(0, newX);
    node.y = Math.max(0, newY);
    
    const nodeEl = document.getElementById(currentDraggingNode);
    if (nodeEl) {
        nodeEl.style.left = node.x + 'px';
        nodeEl.style.top = node.y + 'px';
    }
    
    // Atualizar conexões durante o drag (debounced)
    if (!updateConnectionsTimeout) {
        updateConnectionsTimeout = setTimeout(() => {
            updateConnections();
            updateConnectionsTimeout = null;
        }, 16); // ~60fps
    }
}

let updateConnectionsTimeout = null;

function stopDragNode() {
    if (currentDraggingNode) {
        const nodeEl = document.getElementById(currentDraggingNode);
        if (nodeEl) {
            nodeEl.style.zIndex = '';
        }
    }
    
    // Garantir que as conexões sejam atualizadas ao final do drag
    if (updateConnectionsTimeout) {
        clearTimeout(updateConnectionsTimeout);
        updateConnectionsTimeout = null;
    }
    updateConnections();
    
    isDragging = false;
    currentDraggingNode = null;
    canvas.classList.remove('dragging');
    document.removeEventListener('mousemove', dragNode);
    document.removeEventListener('mouseup', stopDragNode);
}

function startConnection(e, nodeId, connectorType) {
    // Só permitir conectar de output para input
    if (connectorType !== 'output') {
        return;
    }
    
    isConnecting = true;
    connectionStart = { nodeId, connectorType };
    
    const nodeEl = document.getElementById(nodeId);
    const connectorEl = nodeEl.querySelector(`[data-connector="${connectorType}"]`);
    const rect = connectorEl.getBoundingClientRect();
    const svgRect = connectionsLayer.getBoundingClientRect();
    
    // Calcular posição inicial relativa ao SVG
    connectionStart.x = rect.left + rect.width / 2 - svgRect.left;
    connectionStart.y = rect.top + rect.height / 2 - svgRect.top;
    
    canvas.addEventListener('mousemove', drawConnection);
    canvas.addEventListener('mouseup', endConnection);
    document.addEventListener('mouseup', endConnection); // Também no document para garantir
    
    e.stopPropagation();
    e.preventDefault();
}

function drawConnection(e) {
    if (!isConnecting || !connectionStart) return;
    
    // Remover linha temporária anterior
    const existing = connectionsLayer.querySelector('#temp-connection');
    if (existing) existing.remove();
    
    // Calcular posição do mouse relativa ao SVG
    const svgRect = connectionsLayer.getBoundingClientRect();
    const x = e.clientX - svgRect.left;
    const y = e.clientY - svgRect.top;
    
    // Criar linha temporária
    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    line.setAttribute('id', 'temp-connection');
    line.setAttribute('x1', connectionStart.x);
    line.setAttribute('y1', connectionStart.y);
    line.setAttribute('x2', x);
    line.setAttribute('y2', y);
    line.setAttribute('class', 'flow-connection-line connecting');
    line.setAttribute('stroke', 'var(--accent-orange)');
    line.setAttribute('stroke-width', '2');
    line.setAttribute('stroke-dasharray', '5,5');
    line.setAttribute('fill', 'none');
    connectionsLayer.appendChild(line);
}

function endConnection(e) {
    if (!isConnecting) return;
    
    // Remover linha temporária
    const tempLine = connectionsLayer.querySelector('#temp-connection');
    if (tempLine) tempLine.remove();
    
    const target = e.target.closest('.flow-node-connector');
    if (target && connectionStart) {
        const targetNodeId = target.dataset.node;
        const targetConnector = target.dataset.connector;
        
        // Só conectar output -> input e não conectar consigo mesmo
        if (connectionStart.connectorType === 'output' && 
            targetConnector === 'input' && 
            connectionStart.nodeId !== targetNodeId) {
            addConnection(connectionStart.nodeId, targetNodeId);
        }
    }
    
    isConnecting = false;
    connectionStart = null;
    canvas.removeEventListener('mousemove', drawConnection);
    canvas.removeEventListener('mouseup', endConnection);
    document.removeEventListener('mouseup', endConnection);
}

function addConnection(fromNodeId, toNodeId) {
    // Verificar se já existe
    if (connections.some(c => c.from === fromNodeId && c.to === toNodeId)) {
        console.log('Conexão já existe');
        return;
    }
    
    // Verificar se os nós existem
    if (!nodes.some(n => n.id === fromNodeId) || !nodes.some(n => n.id === toNodeId)) {
        console.log('Nós não encontrados');
        return;
    }
    
    connections.push({ from: fromNodeId, to: toNodeId });
    updateConnections();
}

function updateConnections() {
    // Limpar TODAS as linhas existentes (incluindo temporárias)
    const allLines = connectionsLayer.querySelectorAll('line');
    allLines.forEach(el => el.remove());
    
    // Remover conexões inválidas (nós que não existem mais)
    connections = connections.filter(conn => {
        return nodes.some(n => n.id === conn.from) && nodes.some(n => n.id === conn.to);
    });
    
    // Desenhar novas conexões
    connections.forEach((conn, index) => {
        const fromNode = nodes.find(n => n.id === conn.from);
        const toNode = nodes.find(n => n.id === conn.to);
        
        if (!fromNode || !toNode) return;
        
        const fromEl = document.getElementById(conn.from);
        const toEl = document.getElementById(conn.to);
        
        if (!fromEl || !toEl) return;
        
        const fromConnector = fromEl.querySelector('.flow-node-connector.output');
        const toConnector = toEl.querySelector('.flow-node-connector.input');
        
        if (!fromConnector || !toConnector) return;
        
        // Calcular posições considerando zoom e pan
        const fromRect = fromConnector.getBoundingClientRect();
        const toRect = toConnector.getBoundingClientRect();
        const canvasRect = canvas.getBoundingClientRect();
        
        // Posições absolutas dos conectores
        const fromX = fromRect.left + fromRect.width / 2;
        const fromY = fromRect.top + fromRect.height / 2;
        const toX = toRect.left + toRect.width / 2;
        const toY = toRect.top + toRect.height / 2;
        
        // Converter para coordenadas do SVG (relativas ao connectionsLayer)
        const svgRect = connectionsLayer.getBoundingClientRect();
        const x1 = fromX - svgRect.left;
        const y1 = fromY - svgRect.top;
        const x2 = toX - svgRect.left;
        const y2 = toY - svgRect.top;
        
        // Criar linha única com ID
        const lineId = `connection-${conn.from}-${conn.to}`;
        
        // Verificar se já existe (não deveria, mas por segurança)
        const existing = connectionsLayer.querySelector(`#${lineId}`);
        if (existing) existing.remove();
        
        const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('id', lineId);
        line.setAttribute('x1', x1);
        line.setAttribute('y1', y1);
        line.setAttribute('x2', x2);
        line.setAttribute('y2', y2);
        line.setAttribute('class', 'flow-connection-line');
        line.setAttribute('stroke', 'var(--accent-orange)');
        line.setAttribute('stroke-width', '2');
        line.setAttribute('fill', 'none');
        line.setAttribute('marker-end', 'url(#arrowhead)');
        line.dataset.from = conn.from;
        line.dataset.to = conn.to;
        line.style.cursor = 'pointer';
        
        line.addEventListener('click', (e) => {
            e.stopPropagation();
            if (confirm('Deseja remover esta conexão?')) {
                connections = connections.filter(c => !(c.from === conn.from && c.to === conn.to));
                updateConnections();
            }
        });
        
        connectionsLayer.appendChild(line);
    });
}

function deleteNode(nodeId) {
    if (!confirm('Deseja excluir este bloco?')) return;
    
    // Remover conexões
    connections = connections.filter(c => c.from !== nodeId && c.to !== nodeId);
    
    // Remover nó
    nodes = nodes.filter(n => n.id !== nodeId);
    const nodeEl = document.getElementById(nodeId);
    if (nodeEl) nodeEl.remove();
    
    updateConnections();
}

function editNode(nodeId) {
    const node = nodes.find(n => n.id === nodeId);
    if (!node) return;
    
    // TODO: Abrir modal de edição
    alert('Editar nó: ' + nodeId);
}

function clearCanvas() {
    if (!confirm('Deseja limpar todo o canvas?')) return;
    nodes = [];
    connections = [];
    canvas.innerHTML = '';
    updateConnections();
}

function resetFlow() {
    if (!confirm('Deseja resetar para o fluxo padrão?')) return;
    clearCanvas();
    loadFlow();
}

function zoomIn() {
    zoomLevel = Math.min(zoomLevel + 0.1, 2);
    applyZoom();
}

function zoomOut() {
    zoomLevel = Math.max(zoomLevel - 0.1, 0.5);
    applyZoom();
}

function resetZoom() {
    zoomLevel = 1;
    applyZoom();
}

function applyZoom() {
    canvas.style.transform = `translate(${canvasOffset.x}px, ${canvasOffset.y}px) scale(${zoomLevel})`;
    canvas.style.transformOrigin = 'top left';
    connectionsLayer.style.transform = `translate(${canvasOffset.x}px, ${canvasOffset.y}px) scale(${zoomLevel})`;
    connectionsLayer.style.transformOrigin = 'top left';
    updateConnections();
}

function saveFlow() {
    const flowData = {
        nodes: nodes,
        connections: connections
    };
    
    // TODO: Salvar no banco de dados via AJAX
    fetch('ajax_checkin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'save_flow',
            checkin_id: <?php echo $checkin_id; ?>,
            flow: flowData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Fluxo salvo com sucesso!');
        } else {
            alert('Erro ao salvar: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar fluxo');
    });
}

// Drag & Drop da paleta
document.querySelectorAll('.palette-item').forEach(item => {
    item.addEventListener('dragstart', (e) => {
        e.dataTransfer.setData('nodeType', item.dataset.type);
    });
});

canvas.addEventListener('dragover', (e) => {
    e.preventDefault();
});

canvas.addEventListener('drop', (e) => {
    e.preventDefault();
    const nodeType = e.dataTransfer.getData('nodeType');
    if (!nodeType) return;
    
    const canvasRect = canvas.getBoundingClientRect();
    const x = e.clientX - canvasRect.left;
    const y = e.clientY - canvasRect.top;
    
    addNode(nodeType, x, y);
});

// Inicializar
loadFlow();

// Atualizar conexões quando o canvas é redimensionado
window.addEventListener('resize', updateConnections);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

