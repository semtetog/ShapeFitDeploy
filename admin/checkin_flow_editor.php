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
}

.flow-canvas.dragging {
    cursor: grabbing;
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
    transition: all 0.3s ease;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
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
        <svg id="connectionsLayer" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 1;">
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
let dragOffset = { x: 0, y: 0 };
let canvasOffset = { x: 0, y: 0 };
let isConnecting = false;
let connectionStart = null;
let zoomLevel = 1;
let nodeIdCounter = 0;

// Inicializar canvas
const canvas = document.getElementById('flowCanvas');
const connectionsLayer = document.getElementById('connectionsLayer');

// Carregar fluxo existente ou criar padrão
function loadFlow() {
    // TODO: Carregar do banco de dados
    // Por enquanto, criar fluxo padrão baseado nas perguntas
    const questions = <?php echo json_encode($questions); ?>;
    
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
    nodeEl.addEventListener('mousedown', (e) => startDragNode(e, node.id));
    nodeEl.addEventListener('click', (e) => {
        if (e.target.closest('.btn-node-action')) return;
        selectNode(node.id);
    });
    
    const connectors = nodeEl.querySelectorAll('.flow-node-connector');
    connectors.forEach(connector => {
        connector.addEventListener('mousedown', (e) => startConnection(e, node.id, connector.dataset.connector));
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
    
    isDragging = true;
    const nodeEl = document.getElementById(nodeId);
    const rect = nodeEl.getBoundingClientRect();
    const canvasRect = canvas.getBoundingClientRect();
    
    dragOffset.x = e.clientX - rect.left - canvasRect.left;
    dragOffset.y = e.clientY - rect.top - canvasRect.top;
    
    canvas.classList.add('dragging');
    
    document.addEventListener('mousemove', dragNode);
    document.addEventListener('mouseup', stopDragNode);
    
    e.preventDefault();
}

function dragNode(e) {
    if (!isDragging || !selectedNode) return;
    
    const canvasRect = canvas.getBoundingClientRect();
    const node = nodes.find(n => n.id === selectedNode);
    if (!node) return;
    
    node.x = e.clientX - canvasRect.left - dragOffset.x;
    node.y = e.clientY - canvasRect.top - dragOffset.y;
    
    const nodeEl = document.getElementById(selectedNode);
    nodeEl.style.left = node.x + 'px';
    nodeEl.style.top = node.y + 'px';
    
    updateConnections();
}

function stopDragNode() {
    isDragging = false;
    canvas.classList.remove('dragging');
    document.removeEventListener('mousemove', dragNode);
    document.removeEventListener('mouseup', stopDragNode);
}

function startConnection(e, nodeId, connectorType) {
    isConnecting = true;
    connectionStart = { nodeId, connectorType };
    
    const node = nodes.find(n => n.id === nodeId);
    const nodeEl = document.getElementById(nodeId);
    const connectorEl = nodeEl.querySelector(`[data-connector="${connectorType}"]`);
    const rect = connectorEl.getBoundingClientRect();
    const canvasRect = canvas.getBoundingClientRect();
    
    connectionStart.x = rect.left - canvasRect.left + rect.width / 2;
    connectionStart.y = rect.top - canvasRect.top + rect.height / 2;
    
    canvas.addEventListener('mousemove', drawConnection);
    canvas.addEventListener('mouseup', endConnection);
    
    e.stopPropagation();
}

function drawConnection(e) {
    if (!isConnecting) return;
    
    const canvasRect = canvas.getBoundingClientRect();
    const x = e.clientX - canvasRect.left;
    const y = e.clientY - canvasRect.top;
    
    // Remover linha temporária anterior
    const existing = connectionsLayer.querySelector('.temp-connection');
    if (existing) existing.remove();
    
    // Criar linha temporária
    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
    line.setAttribute('x1', connectionStart.x);
    line.setAttribute('y1', connectionStart.y);
    line.setAttribute('x2', x);
    line.setAttribute('y2', y);
    line.setAttribute('class', 'flow-connection-line connecting temp-connection');
    connectionsLayer.appendChild(line);
}

function endConnection(e) {
    if (!isConnecting) return;
    
    const tempLine = connectionsLayer.querySelector('.temp-connection');
    if (tempLine) tempLine.remove();
    
    const target = e.target.closest('.flow-node-connector');
    if (target && connectionStart) {
        const targetNodeId = target.dataset.node;
        const targetConnector = target.dataset.connector;
        
        // Só conectar output -> input
        if (connectionStart.connectorType === 'output' && targetConnector === 'input' && 
            connectionStart.nodeId !== targetNodeId) {
            addConnection(connectionStart.nodeId, targetNodeId);
        }
    }
    
    isConnecting = false;
    connectionStart = null;
    canvas.removeEventListener('mousemove', drawConnection);
    canvas.removeEventListener('mouseup', endConnection);
}

function addConnection(fromNodeId, toNodeId) {
    // Verificar se já existe
    if (connections.some(c => c.from === fromNodeId && c.to === toNodeId)) {
        return;
    }
    
    connections.push({ from: fromNodeId, to: toNodeId });
    updateConnections();
}

function updateConnections() {
    // Limpar conexões existentes
    connectionsLayer.querySelectorAll('.flow-connection').forEach(el => el.remove());
    
    // Desenhar novas conexões
    connections.forEach(conn => {
        const fromNode = nodes.find(n => n.id === conn.from);
        const toNode = nodes.find(n => n.id === conn.to);
        
        if (!fromNode || !toNode) return;
        
        const fromEl = document.getElementById(conn.from);
        const toEl = document.getElementById(conn.to);
        
        if (!fromEl || !toEl) return;
        
        const fromConnector = fromEl.querySelector('.flow-node-connector.output');
        const toConnector = toEl.querySelector('.flow-node-connector.input');
        
        if (!fromConnector || !toConnector) return;
        
        const fromRect = fromConnector.getBoundingClientRect();
        const toRect = toConnector.getBoundingClientRect();
        const canvasRect = canvas.getBoundingClientRect();
        
        const x1 = fromRect.left - canvasRect.left + fromRect.width / 2;
        const y1 = fromRect.top - canvasRect.top + fromRect.height / 2;
        const x2 = toRect.left - canvasRect.left + toRect.width / 2;
        const y2 = toRect.top - canvasRect.top + toRect.height / 2;
        
        const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('x1', x1);
        line.setAttribute('y1', y1);
        line.setAttribute('x2', x2);
        line.setAttribute('y2', y2);
        line.setAttribute('class', 'flow-connection-line');
        line.dataset.from = conn.from;
        line.dataset.to = conn.to;
        
        line.addEventListener('click', () => {
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
    canvas.style.transform = `scale(${zoomLevel})`;
    canvas.style.transformOrigin = 'top left';
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

