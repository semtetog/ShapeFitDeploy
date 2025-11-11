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

.flow-canvas-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
    background: 
        linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
    background-size: 20px 20px;
    background-color: rgba(20, 20, 20, 0.5);
}

.flow-canvas-container {
    position: relative;
    width: 100%;
    height: 100%;
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

/* Layout principal - 3 colunas */
.flow-editor-layout {
    display: grid;
    grid-template-columns: 280px 1fr 320px;
    gap: 1rem;
    height: calc(100vh - 200px);
    min-height: 600px;
}

/* Sidebar esquerda - Biblioteca de blocos */
.flow-blocks-sidebar {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 1rem;
    overflow-y: auto;
    height: 100%;
}

.flow-blocks-sidebar h3 {
    margin: 0 0 1rem 0;
    font-size: 1rem;
    color: var(--text-primary);
    font-weight: 700;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--glass-border);
}

.blocks-category {
    margin-bottom: 1.5rem;
}

.blocks-category-title {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
    font-weight: 600;
}

/* Canvas central */
.flow-canvas-wrapper {
    position: relative;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    overflow: hidden;
    height: 100%;
}

/* Sidebar direita - Propriedades */
.flow-properties-sidebar {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 1rem;
    overflow-y: auto;
    height: 100%;
}

.flow-properties-sidebar h3 {
    margin: 0 0 1rem 0;
    font-size: 1rem;
    color: var(--text-primary);
    font-weight: 700;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--glass-border);
}

.properties-empty {
    text-align: center;
    padding: 2rem 1rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.node-palette {
    display: none; /* Substituído pela sidebar */
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
        <div style="display: flex; gap: 1rem; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="padding: 0.5rem 1rem; background: rgba(255, 107, 0, 0.1); border: 1px solid rgba(255, 107, 0, 0.3); border-radius: 8px; font-size: 0.875rem; font-weight: 600; color: var(--accent-orange);">
                    <?php 
                    $status = $checkin['status'] ?? 'draft';
                    echo $status === 'published' ? 'Publicado' : 'Rascunho'; 
                    ?>
                </span>
            </div>
            <button class="btn-toolbar" onclick="openPreview()" title="Preview do Chat">
                <i class="fas fa-eye"></i> Preview
            </button>
            <button class="btn-toolbar" onclick="publishFlow()" title="Publicar Fluxo">
                <i class="fas fa-rocket"></i> Publicar
            </button>
            <button class="btn-toolbar primary" onclick="saveFlow()">
                <i class="fas fa-save"></i> Salvar
            </button>
            <button class="btn-toolbar" onclick="window.location.href='checkin.php'">
                <i class="fas fa-arrow-left"></i> Voltar
            </button>
        </div>
    </div>

    <div class="flow-editor-toolbar">
        <div class="toolbar-section">
            <button class="btn-toolbar" onclick="addNode('bot_message', null, null, {}, 'text')" title="Adicionar Mensagem de Texto">
                <i class="fas fa-comment"></i> Texto
            </button>
            <button class="btn-toolbar" onclick="addNode('question', null, null, {}, 'text')" title="Adicionar Pergunta">
                <i class="fas fa-question"></i> Pergunta
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

    <div class="flow-editor-layout">
        <!-- Sidebar Esquerda - Biblioteca de Blocos -->
        <div class="flow-blocks-sidebar">
            <h3><i class="fas fa-cubes"></i> Blocos</h3>
            
            <div class="blocks-category">
                <div class="blocks-category-title">Mensagens</div>
                <div class="palette-item" draggable="true" data-type="bot_message" data-subtype="text">
                    <i class="fas fa-comment"></i>
                    <span>Mensagem do Bot</span>
                </div>
            </div>
            
            <div class="blocks-category">
                <div class="blocks-category-title">Perguntas</div>
                <div class="palette-item" draggable="true" data-type="question" data-subtype="text">
                    <i class="fas fa-keyboard"></i>
                    <span>Texto</span>
                </div>
                <div class="palette-item" draggable="true" data-type="question" data-subtype="textarea">
                    <i class="fas fa-align-left"></i>
                    <span>Texto Longo</span>
                </div>
                <div class="palette-item" draggable="true" data-type="question" data-subtype="multiple_choice">
                    <i class="fas fa-list"></i>
                    <span>Múltipla Escolha</span>
                </div>
                <div class="palette-item" draggable="true" data-type="question" data-subtype="checkbox">
                    <i class="fas fa-check-square"></i>
                    <span>Checkbox</span>
                </div>
                <div class="palette-item" draggable="true" data-type="question" data-subtype="number">
                    <i class="fas fa-hashtag"></i>
                    <span>Número</span>
                </div>
                <div class="palette-item" draggable="true" data-type="question" data-subtype="email">
                    <i class="fas fa-envelope"></i>
                    <span>Email</span>
                </div>
                <div class="palette-item" draggable="true" data-type="question" data-subtype="phone">
                    <i class="fas fa-phone"></i>
                    <span>Telefone</span>
                </div>
                <div class="palette-item" draggable="true" data-type="question" data-subtype="date">
                    <i class="fas fa-calendar"></i>
                    <span>Data</span>
                </div>
                <div class="palette-item" draggable="true" data-type="question" data-subtype="time">
                    <i class="fas fa-clock"></i>
                    <span>Hora</span>
                </div>
                <div class="palette-item" draggable="true" data-type="question" data-subtype="rating">
                    <i class="fas fa-star"></i>
                    <span>Avaliação</span>
                </div>
                <div class="palette-item" draggable="true" data-type="question" data-subtype="slider">
                    <i class="fas fa-sliders-h"></i>
                    <span>Slider</span>
                </div>
                <div class="palette-item" draggable="true" data-type="question" data-subtype="yesno">
                    <i class="fas fa-check-circle"></i>
                    <span>Sim/Não</span>
                </div>
                <div class="palette-item" draggable="true" data-type="question" data-subtype="chips">
                    <i class="fas fa-tags"></i>
                    <span>Chips</span>
                </div>
            </div>
            
            <div class="blocks-category">
                <div class="blocks-category-title">Ações</div>
                <div class="palette-item" draggable="true" data-type="action" data-subtype="end">
                    <i class="fas fa-stop-circle"></i>
                    <span>Finalizar</span>
                </div>
                <div class="palette-item" draggable="true" data-type="action" data-subtype="jump">
                    <i class="fas fa-forward"></i>
                    <span>Pular para</span>
                </div>
            </div>
        </div>
        
        <!-- Canvas Central -->
        <div class="flow-canvas-wrapper">
            <svg id="connectionsLayer" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 1; overflow: visible; transform-origin: top left;">
                <defs>
                    <marker id="arrowhead" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto" markerUnits="strokeWidth">
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
        
        <!-- Sidebar Direita - Propriedades -->
        <div class="flow-properties-sidebar">
            <h3><i class="fas fa-cog"></i> Propriedades</h3>
            <div id="propertiesContent" class="properties-empty">
                <i class="fas fa-mouse-pointer" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                <p>Selecione um bloco para editar suas propriedades</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Preview/Chat Simulado -->
<div id="previewModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px; height: 90vh; max-height: 90vh;">
        <div class="modal-header">
            <h3><i class="fas fa-comments"></i> Preview - Chat Simulado</h3>
            <button class="close" onclick="closePreview()" style="background: transparent; border: none; color: white; font-size: 24px; cursor: pointer; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%;">&times;</button>
        </div>
        <div class="modal-body" style="padding: 0; height: calc(100% - 60px); display: flex; flex-direction: column; overflow: hidden;">
            <div id="chatMessages" style="flex: 1; overflow-y: auto; padding: 1rem; background: #0b141a; -webkit-overflow-scrolling: touch;">
                <!-- Mensagens do chat aparecerão aqui -->
            </div>
            <div id="chatInputContainer" style="padding: 1rem; background: #202c33; border-top: 1px solid var(--glass-border); flex-shrink: 0;">
                <!-- Inputs dinâmicos aparecerão aqui -->
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos do modal de preview */
#previewModal.active {
    display: flex !important;
    align-items: center;
    justify-content: center;
}

#previewModal .modal-content {
    display: flex;
    flex-direction: column;
}

/* Estilos responsivos para mobile */
@media (max-width: 768px) {
    #previewModal .modal-content {
        max-width: 100%;
        height: 100vh;
        max-height: 100vh;
        border-radius: 0;
    }
    
    .flow-editor-layout {
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr auto;
    }
    
    .flow-blocks-sidebar {
        display: none;
        position: fixed;
        left: 0;
        top: 0;
        width: 280px;
        height: 100vh;
        z-index: 1000;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .flow-blocks-sidebar.open {
        display: block;
        transform: translateX(0);
    }
    
    .flow-properties-sidebar {
        display: none;
        position: fixed;
        right: 0;
        top: 0;
        width: 320px;
        height: 100vh;
        z-index: 1000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
    }
    
    .flow-properties-sidebar.open {
        display: block;
        transform: translateX(0);
    }
}

/* Estilos para formulários */
.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    color: var(--text-primary);
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.875rem;
    font-family: inherit;
    box-sizing: border-box;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

.form-group small {
    display: block;
    margin-top: 0.25rem;
    color: var(--text-secondary);
    font-size: 0.75rem;
}

.form-group input[type="checkbox"] {
    width: auto;
    margin-right: 0.5rem;
}
</style>

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
let updateConnectionsTimeout = null;

// Inicializar canvas - aguardar DOM
let canvas, connectionsLayer, canvasContainer;

function initCanvas() {
    canvas = document.getElementById('flowCanvas');
    connectionsLayer = document.getElementById('connectionsLayer');
    canvasContainer = document.querySelector('.flow-canvas-wrapper') || document.querySelector('.flow-canvas-container');
    
    if (!canvas || !connectionsLayer) {
        console.error('Canvas ou connectionsLayer não encontrado!');
        return false;
    }
    
    return true;
}

// Configurar eventos do canvas após inicialização
function setupCanvasEvents() {
    if (!canvas) return;
    
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
            if (connectionsLayer) {
                connectionsLayer.style.transform = `translate(${canvasOffset.x}px, ${canvasOffset.y}px) scale(${zoomLevel})`;
            }
            
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
    
    // Drag & Drop do canvas
    canvas.addEventListener('dragover', (e) => {
        e.preventDefault();
    });

    canvas.addEventListener('drop', (e) => {
        e.preventDefault();
        const nodeType = e.dataTransfer.getData('nodeType');
        const nodeSubtype = e.dataTransfer.getData('nodeSubtype');
        if (!nodeType) return;
        
        const canvasRect = canvas.getBoundingClientRect();
        const x = (e.clientX - canvasRect.left - canvasOffset.x) / zoomLevel;
        const y = (e.clientY - canvasRect.top - canvasOffset.y) / zoomLevel;
        
        addNode(nodeType, x, y, {}, nodeSubtype);
    });
}

// Carregar fluxo existente ou criar padrão
function loadFlow() {
    const savedFlow = <?php echo isset($saved_flow) && $saved_flow ? json_encode($saved_flow) : 'null'; ?>;
    const questions = <?php echo json_encode($questions); ?>;
    
    if (savedFlow && savedFlow.nodes && savedFlow.nodes.length > 0) {
        // Carregar fluxo salvo
        savedFlow.nodes.forEach(node => {
            nodes.push(node);
            renderNode(node);
        });
        
        if (savedFlow.connections) {
            connections = savedFlow.connections.map(conn => ({
                from: conn.from,
                to: conn.to,
                condition: conn.condition || null,
                priority: conn.priority !== undefined ? conn.priority : 0
            }));
        }
        
        const maxId = nodes.length > 0 ? Math.max(...nodes.map(n => {
            const match = n.id.match(/node_(\d+)/);
            return match ? parseInt(match[1]) : 0;
        })) : 0;
        nodeIdCounter = maxId + 1;
    } else {
        // Criar fluxo padrão baseado nas perguntas
        if (questions && questions.length > 0) {
            let y = 100;
            questions.forEach((q, index) => {
                addNode('question', 200, y + (index * 150), {
                    prompt: q.question_text,
                    variable_name: `question_${q.id}`,
                    question_type: q.question_type,
                    options: q.options
                }, q.question_type === 'multiple_choice' ? 'multiple_choice' : 'text');
            });
        } else {
            // Fluxo padrão vazio
            addNode('bot_message', 200, 100, { prompt: 'Bem-vindo ao check-in!' }, 'text');
        }
    }
    
    updateConnections();
}

function addNode(type, x = null, y = null, data = {}, subtype = null) {
    const nodeId = `node_${nodeIdCounter++}`;
    
    // Dados padrão baseado no tipo
    const defaultData = {
        bot_message: { prompt: 'Nova mensagem do bot', delay: 0, auto_continue: false },
        question: { prompt: 'Nova pergunta', variable_name: '', required: true, placeholder: '' },
        action: { action_type: 'end' }
    };
    
    const node = {
        id: nodeId,
        type: type,
        subtype: subtype || (type === 'question' ? 'text' : null),
        x: x || Math.random() * 400 + 100,
        y: y || Math.random() * 300 + 100,
        data: { ...defaultData[type], ...data }
    };
    
    nodes.push(node);
    renderNode(node);
    selectNode(nodeId);
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
        'bot_message': 'Mensagem',
        'question': 'Pergunta',
        'action': 'Ação'
    };
    
    const typeIcons = {
        'bot_message': 'fa-comment',
        'question': 'fa-question-circle',
        'action': 'fa-cog'
    };
    
    const subtypeLabels = {
        'text': 'Texto',
        'textarea': 'Texto Longo',
        'multiple_choice': 'Múltipla Escolha',
        'checkbox': 'Checkbox',
        'number': 'Número',
        'email': 'Email',
        'phone': 'Telefone',
        'date': 'Data',
        'time': 'Hora',
        'rating': 'Avaliação',
        'slider': 'Slider',
        'yesno': 'Sim/Não',
        'chips': 'Chips'
    };
    
    let displayLabel = typeLabels[node.type] || node.type;
    if (node.subtype && subtypeLabels[node.subtype]) {
        displayLabel = `${displayLabel}: ${subtypeLabels[node.subtype]}`;
    }
    
    nodeEl.innerHTML = `
        <div class="flow-node-header">
            <div class="flow-node-type">
                <i class="fas ${typeIcons[node.type] || 'fa-cube'}"></i>
                <span>${displayLabel}</span>
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
    
    if (canvas) {
        canvas.appendChild(nodeEl);
    }
    
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
        case 'bot_message':
            return node.data.prompt || node.data.title || 'Nova mensagem do bot';
        case 'question':
            return node.data.prompt || `Nova pergunta (${node.subtype || 'text'})`;
        case 'action':
            if (node.data.action_type === 'end') {
                return 'Finalizar check-in';
            } else if (node.data.action_type === 'jump') {
                return `Pular para: ${node.data.jump_to || '...'}`;
            }
            return 'Ação';
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
        renderPropertiesPanel(nodeId);
    }
}

function renderPropertiesPanel(nodeId) {
    // Se nodeId é null, mostrar estado vazio
    if (!nodeId) {
        document.getElementById('propertiesContent').innerHTML = `
            <div class="properties-empty">
                <i class="fas fa-mouse-pointer" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                <p>Selecione um bloco para editar suas propriedades</p>
            </div>
        `;
        return;
    }
    
    const node = nodes.find(n => n.id === nodeId);
    if (!node) {
        document.getElementById('propertiesContent').innerHTML = `
            <div class="properties-empty">
                <i class="fas fa-mouse-pointer" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                <p>Selecione um bloco para editar suas propriedades</p>
            </div>
        `;
        return;
    }
    
    const propsContent = document.getElementById('propertiesContent');
    
    if (node.type === 'bot_message') {
        propsContent.innerHTML = `
            <div class="form-group">
                <label>Título (opcional)</label>
                <input type="text" id="prop-title" value="${node.data.title || ''}" placeholder="Título da mensagem" onchange="updateNodeProperty('${nodeId}', 'title', this.value)">
            </div>
            <div class="form-group">
                <label>Mensagem *</label>
                <textarea id="prop-prompt" rows="4" placeholder="Digite a mensagem do bot... Use {{variavel}} para placeholders" onchange="updateNodeProperty('${nodeId}', 'prompt', this.value)">${node.data.prompt || ''}</textarea>
                <small style="color: var(--text-secondary); font-size: 0.75rem;">Use {{nome_variavel}} para inserir valores de respostas anteriores</small>
            </div>
            <div class="form-group">
                <label>Delay (ms)</label>
                <input type="number" id="prop-delay" value="${node.data.delay || 0}" min="0" onchange="updateNodeProperty('${nodeId}', 'delay', parseInt(this.value))">
            </div>
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" id="prop-auto-continue" ${node.data.auto_continue ? 'checked' : ''} onchange="updateNodeProperty('${nodeId}', 'auto_continue', this.checked)">
                    <span>Avançar automaticamente</span>
                </label>
            </div>
        `;
    } else if (node.type === 'question') {
        const subtypes = {
            'text': 'Texto',
            'textarea': 'Texto Longo',
            'multiple_choice': 'Múltipla Escolha',
            'checkbox': 'Checkbox',
            'number': 'Número',
            'email': 'Email',
            'phone': 'Telefone',
            'date': 'Data',
            'time': 'Hora',
            'rating': 'Avaliação',
            'slider': 'Slider',
            'yesno': 'Sim/Não',
            'chips': 'Chips'
        };
        
        let optionsHTML = '';
        if (['multiple_choice', 'checkbox', 'chips'].includes(node.subtype)) {
            const options = node.data.options || [];
            optionsHTML = `
                <div class="form-group">
                    <label>Opções</label>
                    <div id="options-list">
                        ${options.map((opt, idx) => `
                            <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <input type="text" value="${opt.label || ''}" placeholder="Label" onchange="updateOption('${nodeId}', ${idx}, 'label', this.value)" style="flex: 1;">
                                <input type="text" value="${opt.value || ''}" placeholder="Value" onchange="updateOption('${nodeId}', ${idx}, 'value', this.value)" style="flex: 1;">
                                <button onclick="removeOption('${nodeId}', ${idx})" style="padding: 0.5rem; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 6px; color: #ef4444; cursor: pointer;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        `).join('')}
                    </div>
                    <button onclick="addOption('${nodeId}')" style="width: 100%; padding: 0.5rem; background: rgba(255, 107, 0, 0.1); border: 1px solid rgba(255, 107, 0, 0.3); border-radius: 6px; color: var(--accent-orange); cursor: pointer; margin-top: 0.5rem;">
                        <i class="fas fa-plus"></i> Adicionar Opção
                    </button>
                </div>
            `;
        }
        
        propsContent.innerHTML = `
            <div class="form-group">
                <label>Tipo de Pergunta</label>
                <select id="prop-subtype" onchange="updateNodeProperty('${nodeId}', 'subtype', this.value)">
                    ${Object.entries(subtypes).map(([val, label]) => `
                        <option value="${val}" ${node.subtype === val ? 'selected' : ''}>${label}</option>
                    `).join('')}
                </select>
            </div>
            <div class="form-group">
                <label>Pergunta/Prompt *</label>
                <textarea id="prop-prompt" rows="3" placeholder="Digite a pergunta... Use {{variavel}} para placeholders" onchange="updateNodeProperty('${nodeId}', 'prompt', this.value)">${node.data.prompt || ''}</textarea>
                <small style="color: var(--text-secondary); font-size: 0.75rem;">Use {{nome_variavel}} para inserir valores de respostas anteriores</small>
            </div>
            <div class="form-group">
                <label>Nome da Variável *</label>
                <input type="text" id="prop-variable" value="${node.data.variable_name || ''}" placeholder="ex: nome_completo" pattern="[a-z0-9_]+" onchange="updateNodeProperty('${nodeId}', 'variable_name', this.value)">
                <small style="color: var(--text-secondary); font-size: 0.75rem;">Apenas letras minúsculas, números e underscore</small>
            </div>
            <div class="form-group">
                <label>Placeholder</label>
                <input type="text" id="prop-placeholder" value="${node.data.placeholder || ''}" placeholder="Texto de ajuda..." onchange="updateNodeProperty('${nodeId}', 'placeholder', this.value)">
            </div>
            ${optionsHTML}
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" id="prop-required" ${node.data.required !== false ? 'checked' : ''} onchange="updateNodeProperty('${nodeId}', 'required', this.checked)">
                    <span>Obrigatório</span>
                </label>
            </div>
            ${node.subtype === 'number' ? `
                <div class="form-group">
                    <label>Valor Mínimo</label>
                    <input type="number" id="prop-min" value="${node.data.min || ''}" onchange="updateNodeProperty('${nodeId}', 'min', this.value ? parseFloat(this.value) : null)">
                </div>
                <div class="form-group">
                    <label>Valor Máximo</label>
                    <input type="number" id="prop-max" value="${node.data.max || ''}" onchange="updateNodeProperty('${nodeId}', 'max', this.value ? parseFloat(this.value) : null)">
                </div>
            ` : ''}
            ${node.subtype === 'slider' ? `
                <div class="form-group">
                    <label>Valor Mínimo</label>
                    <input type="number" id="prop-min" value="${node.data.min || 0}" onchange="updateNodeProperty('${nodeId}', 'min', parseFloat(this.value))">
                </div>
                <div class="form-group">
                    <label>Valor Máximo</label>
                    <input type="number" id="prop-max" value="${node.data.max || 100}" onchange="updateNodeProperty('${nodeId}', 'max', parseFloat(this.value))">
                </div>
                <div class="form-group">
                    <label>Passo</label>
                    <input type="number" id="prop-step" value="${node.data.step || 1}" min="0.1" step="0.1" onchange="updateNodeProperty('${nodeId}', 'step', parseFloat(this.value))">
                </div>
            ` : ''}
            ${node.subtype === 'rating' ? `
                <div class="form-group">
                    <label>Máximo de Estrelas</label>
                    <input type="number" id="prop-max" value="${node.data.max || 5}" min="1" max="10" onchange="updateNodeProperty('${nodeId}', 'max', parseInt(this.value))">
                </div>
            ` : ''}
        `;
    } else if (node.type === 'action') {
        propsContent.innerHTML = `
            <div class="form-group">
                <label>Tipo de Ação</label>
                <select id="prop-action-type" onchange="updateNodeProperty('${nodeId}', 'action_type', this.value)">
                    <option value="end" ${node.data.action_type === 'end' ? 'selected' : ''}>Finalizar</option>
                    <option value="jump" ${node.data.action_type === 'jump' ? 'selected' : ''}>Pular para Bloco</option>
                </select>
            </div>
            ${node.data.action_type === 'jump' ? `
                <div class="form-group">
                    <label>Bloco de Destino</label>
                    <select id="prop-jump-to" onchange="updateNodeProperty('${nodeId}', 'jump_to', this.value)">
                        <option value="">Selecione...</option>
                        ${nodes.filter(n => n.id !== nodeId).map(n => `
                            <option value="${n.id}" ${node.data.jump_to === n.id ? 'selected' : ''}>${n.data.title || n.data.prompt || n.id}</option>
                        `).join('')}
                    </select>
                </div>
            ` : ''}
        `;
    }
}

function updateNodeProperty(nodeId, prop, value) {
    const node = nodes.find(n => n.id === nodeId);
    if (!node) return;
    
    if (prop === 'subtype') {
        node.subtype = value;
        // Resetar opções se mudar de tipo
        if (!['multiple_choice', 'checkbox', 'chips'].includes(value)) {
            node.data.options = null;
        }
    } else {
        node.data[prop] = value;
    }
    
    // Atualizar visual do nó
    const nodeEl = document.getElementById(nodeId);
    if (nodeEl) {
        const contentEl = nodeEl.querySelector('.flow-node-content');
        if (contentEl) {
            contentEl.textContent = getNodeContent(node);
        }
    }
    
    updateConnections();
}

function addOption(nodeId) {
    const node = nodes.find(n => n.id === nodeId);
    if (!node) return;
    
    if (!node.data.options) node.data.options = [];
    node.data.options.push({ label: '', value: '' });
    renderPropertiesPanel(nodeId);
}

function updateOption(nodeId, index, prop, value) {
    const node = nodes.find(n => n.id === nodeId);
    if (!node || !node.data.options) return;
    
    node.data.options[index][prop] = value;
}

function removeOption(nodeId, index) {
    const node = nodes.find(n => n.id === nodeId);
    if (!node || !node.data.options) return;
    
    node.data.options.splice(index, 1);
    renderPropertiesPanel(nodeId);
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
    if (!isDragging || !currentDraggingNode || !canvas) return;
    if (isPanning) return; // Não arrastar nó se estiver fazendo pan
    
    const node = nodes.find(n => n.id === currentDraggingNode);
    if (!node) return;
    
    const canvasRect = canvas.getBoundingClientRect();
    
    // Calcular nova posição considerando zoom e pan
    node.x = (e.clientX - canvasRect.left - canvasOffset.x) / zoomLevel - dragOffset.x;
    node.y = (e.clientY - canvasRect.top - canvasOffset.y) / zoomLevel - dragOffset.y;
    
    // Limitar dentro dos bounds do canvas (opcional)
    node.x = Math.max(0, node.x);
    node.y = Math.max(0, node.y);
    
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
    if (canvas) {
        canvas.classList.remove('dragging');
    }
    document.removeEventListener('mousemove', dragNode);
    document.removeEventListener('mouseup', stopDragNode);
}

function startConnection(e, nodeId, connectorType) {
    // Só permitir conectar de output para input
    if (connectorType !== 'output') {
        return;
    }
    
    if (!connectionsLayer) {
        console.error('connectionsLayer não inicializado');
        return;
    }
    
    isConnecting = true;
    connectionStart = { nodeId, connectorType };
    
    const nodeEl = document.getElementById(nodeId);
    if (!nodeEl) return;
    
    const connectorEl = nodeEl.querySelector(`[data-connector="${connectorType}"]`);
    if (!connectorEl) return;
    
    const rect = connectorEl.getBoundingClientRect();
    const svgRect = connectionsLayer.getBoundingClientRect();
    
    // Calcular posição inicial relativa ao SVG
    connectionStart.x = rect.left + rect.width / 2 - svgRect.left;
    connectionStart.y = rect.top + rect.height / 2 - svgRect.top;
    
    document.addEventListener('mousemove', drawConnection);
    document.addEventListener('mouseup', endConnection);
    
    e.stopPropagation();
    e.preventDefault();
}

function drawConnection(e) {
    if (!isConnecting || !connectionStart || !connectionsLayer) return;
    
    // Remover linha temporária anterior
    const existing = connectionsLayer.querySelector('#temp-connection');
    if (existing) existing.remove();
    
    // Calcular posição do mouse relativa ao SVG
    const svgRect = connectionsLayer.getBoundingClientRect();
    const x = e.clientX - svgRect.left;
    const y = e.clientY - svgRect.top;
    
    // Criar linha temporária curva
    const midX = (connectionStart.x + x) / 2;
    const midY = (connectionStart.y + y) / 2;
    const curveOffset = Math.abs(x - connectionStart.x) * 0.3;
    
    const cp1x = connectionStart.x + curveOffset;
    const cp1y = connectionStart.y;
    const cp2x = x - curveOffset;
    const cp2y = y;
    
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('id', 'temp-connection');
    const pathData = `M ${connectionStart.x} ${connectionStart.y} C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${x} ${y}`;
    path.setAttribute('d', pathData);
    path.setAttribute('class', 'flow-connection-line connecting');
    path.setAttribute('stroke', 'var(--accent-orange)');
    path.setAttribute('stroke-width', '2');
    path.setAttribute('stroke-dasharray', '5,5');
    path.setAttribute('fill', 'none');
    connectionsLayer.appendChild(path);
}

function endConnection(e) {
    if (!isConnecting) return;
    
    // Remover linha temporária
    if (connectionsLayer) {
        const tempLine = connectionsLayer.querySelector('#temp-connection');
        if (tempLine) tempLine.remove();
    }
    
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
    document.removeEventListener('mousemove', drawConnection);
    document.removeEventListener('mouseup', endConnection);
    
    // Atualizar conexões após criar nova
    updateConnections();
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
    
    connections.push({ 
        from: fromNodeId, 
        to: toNodeId,
        condition: null,
        priority: connections.filter(c => c.from === fromNodeId).length
    });
    updateConnections();
}

function selectConnection(fromNodeId, toNodeId) {
    const conn = connections.find(c => c.from === fromNodeId && c.to === toNodeId);
    if (conn) {
        renderConnectionProperties(conn, fromNodeId, toNodeId);
    }
}

function renderConnectionProperties(conn, fromNodeId, toNodeId) {
    const fromNode = nodes.find(n => n.id === fromNodeId);
    const toNode = nodes.find(n => n.id === toNodeId);
    
    if (!fromNode || !toNode) return;
    
    // Obter variáveis disponíveis
    const variables = extractVariables();
    
    const propsContent = document.getElementById('propertiesContent');
    propsContent.innerHTML = `
        <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--glass-border);">
            <h4 style="margin: 0 0 0.5rem 0; color: var(--text-primary); font-size: 0.9rem;">Conexão</h4>
            <p style="margin: 0; color: var(--text-secondary); font-size: 0.8rem;">
                ${getNodeContent(fromNode)} → ${getNodeContent(toNode)}
            </p>
        </div>
        <div class="form-group">
            <label>Prioridade</label>
            <input type="number" id="conn-priority" value="${conn.priority || 0}" min="0" onchange="updateConnectionProperty('${fromNodeId}', '${toNodeId}', 'priority', parseInt(this.value))">
            <small style="color: var(--text-secondary); font-size: 0.75rem;">Menor número = maior prioridade</small>
        </div>
        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" id="conn-has-condition" ${conn.condition ? 'checked' : ''} onchange="toggleConnectionCondition('${fromNodeId}', '${toNodeId}', this.checked)">
                <span>Adicionar Condição</span>
            </label>
        </div>
        ${conn.condition ? `
            <div id="condition-editor" class="form-group">
                <label>Condição</label>
                <select id="conn-var" onchange="updateConditionVar('${fromNodeId}', '${toNodeId}')">
                    <option value="">Selecione uma variável...</option>
                    ${variables.map(v => `
                        <option value="${v.name}" ${conn.condition?.var === v.name ? 'selected' : ''}>${v.name} (${v.type})</option>
                    `).join('')}
                </select>
                <select id="conn-operator" style="margin-top: 0.5rem;" onchange="updateConditionOperator('${fromNodeId}', '${toNodeId}')">
                    <option value="==" ${conn.condition?.op === '==' ? 'selected' : ''}>Igual a (==)</option>
                    <option value="!=" ${conn.condition?.op === '!=' ? 'selected' : ''}>Diferente de (!=)</option>
                    <option value=">" ${conn.condition?.op === '>' ? 'selected' : ''}>Maior que (>)</option>
                    <option value=">=" ${conn.condition?.op === '>=' ? 'selected' : ''}>Maior ou igual (>=)</option>
                    <option value="<" ${conn.condition?.op === '<' ? 'selected' : ''}>Menor que (<)</option>
                    <option value="<=" ${conn.condition?.op === '<=' ? 'selected' : ''}>Menor ou igual (<=)</option>
                    <option value="contains" ${conn.condition?.op === 'contains' ? 'selected' : ''}>Contém</option>
                    <option value="startsWith" ${conn.condition?.op === 'startsWith' ? 'selected' : ''}>Começa com</option>
                </select>
                <input type="text" id="conn-value" value="${conn.condition?.value || ''}" placeholder="Valor de comparação" style="margin-top: 0.5rem;" onchange="updateConditionValue('${fromNodeId}', '${toNodeId}', this.value)">
                <button onclick="removeConnectionCondition('${fromNodeId}', '${toNodeId}')" style="width: 100%; margin-top: 0.5rem; padding: 0.5rem; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 6px; color: #ef4444; cursor: pointer;">
                    <i class="fas fa-trash"></i> Remover Condição
                </button>
            </div>
        ` : ''}
    `;
}

function toggleConnectionCondition(fromNodeId, toNodeId, enabled) {
    const conn = connections.find(c => c.from === fromNodeId && c.to === toNodeId);
    if (!conn) return;
    
    if (enabled) {
        conn.condition = { var: '', op: '==', value: '' };
    } else {
        conn.condition = null;
    }
    
    renderConnectionProperties(conn, fromNodeId, toNodeId);
}

function updateConnectionProperty(fromNodeId, toNodeId, prop, value) {
    const conn = connections.find(c => c.from === fromNodeId && c.to === toNodeId);
    if (!conn) return;
    
    conn[prop] = value;
    updateConnections();
}

function updateConditionVar(fromNodeId, toNodeId) {
    const conn = connections.find(c => c.from === fromNodeId && c.to === toNodeId);
    if (!conn || !conn.condition) return;
    
    const select = document.getElementById('conn-var');
    conn.condition.var = select.value;
    updateConnections();
}

function updateConditionOperator(fromNodeId, toNodeId) {
    const conn = connections.find(c => c.from === fromNodeId && c.to === toNodeId);
    if (!conn || !conn.condition) return;
    
    const select = document.getElementById('conn-operator');
    conn.condition.op = select.value;
    updateConnections();
}

function updateConditionValue(fromNodeId, toNodeId, value) {
    const conn = connections.find(c => c.from === fromNodeId && c.to === toNodeId);
    if (!conn || !conn.condition) return;
    
    conn.condition.value = value;
    updateConnections();
}

function removeConnectionCondition(fromNodeId, toNodeId) {
    const conn = connections.find(c => c.from === fromNodeId && c.to === toNodeId);
    if (!conn) return;
    
    conn.condition = null;
    renderConnectionProperties(conn, fromNodeId, toNodeId);
    updateConnections();
}

function updateConnections() {
    if (!connectionsLayer) return;
    
    // Limpar TODAS as linhas existentes (incluindo temporárias)
    const allLines = connectionsLayer.querySelectorAll('line, path');
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
        
        // Criar linha curva (path) ao invés de linha reta
        const midX = (x1 + x2) / 2;
        const midY = (y1 + y2) / 2;
        const curveOffset = Math.abs(x2 - x1) * 0.3;
        
        // Calcular pontos de controle para curva suave
        const cp1x = x1 + curveOffset;
        const cp1y = y1;
        const cp2x = x2 - curveOffset;
        const cp2y = y2;
        
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('id', lineId);
        const pathData = `M ${x1} ${y1} C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${x2} ${y2}`;
        path.setAttribute('d', pathData);
        
        // Estilo baseado em condição
        const hasCondition = conn.condition && conn.condition.var;
        const lineClass = hasCondition ? 'flow-connection-line conditional' : 'flow-connection-line';
        path.setAttribute('class', lineClass);
        path.setAttribute('stroke', 'var(--accent-orange)');
        path.setAttribute('stroke-width', '2');
        path.setAttribute('fill', 'none');
        path.setAttribute('marker-end', 'url(#arrowhead)');
        if (hasCondition) {
            path.setAttribute('stroke-dasharray', '5,5');
        }
        path.dataset.from = conn.from;
        path.dataset.to = conn.to;
        path.style.cursor = 'pointer';
        path.style.pointerEvents = 'stroke';
        
        path.addEventListener('click', (e) => {
            e.stopPropagation();
            // Mostrar menu de contexto
            const menu = document.createElement('div');
            menu.style.cssText = `
                position: fixed;
                top: ${e.clientY}px;
                left: ${e.clientX}px;
                background: rgba(20, 20, 20, 0.95);
                border: 1px solid var(--glass-border);
                border-radius: 8px;
                padding: 0.5rem;
                z-index: 10000;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            `;
            menu.innerHTML = `
                <button onclick="selectConnection('${conn.from}', '${conn.to}'); this.parentElement.remove();" style="width: 100%; padding: 0.5rem; background: transparent; border: none; color: var(--text-primary); cursor: pointer; text-align: left; border-radius: 4px; margin-bottom: 0.25rem;">
                    <i class="fas fa-edit"></i> Editar
                </button>
                <button onclick="if(confirm('Deseja remover esta conexão?')) { connections = connections.filter(c => !(c.from === '${conn.from}' && c.to === '${conn.to}')); updateConnections(); } this.parentElement.remove();" style="width: 100%; padding: 0.5rem; background: transparent; border: none; color: #ef4444; cursor: pointer; text-align: left; border-radius: 4px;">
                    <i class="fas fa-trash"></i> Remover
                </button>
            `;
            document.body.appendChild(menu);
            
            // Remover menu ao clicar fora
            setTimeout(() => {
                const removeMenu = (e) => {
                    if (!menu.contains(e.target)) {
                        menu.remove();
                        document.removeEventListener('click', removeMenu);
                    }
                };
                setTimeout(() => document.addEventListener('click', removeMenu), 100);
            }, 100);
        });
        
        connectionsLayer.appendChild(path);
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
    
    // Limpar painel de propriedades se era o selecionado
    if (selectedNode === nodeId) {
        selectedNode = null;
        renderPropertiesPanel(null);
    }
    
    updateConnections();
}

function editNode(nodeId) {
    // Selecionar o nó (já abre o painel de propriedades)
    selectNode(nodeId);
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
    if (!canvas || !connectionsLayer) return;
    
    canvas.style.transform = `translate(${canvasOffset.x}px, ${canvasOffset.y}px) scale(${zoomLevel})`;
    canvas.style.transformOrigin = 'top left';
    connectionsLayer.style.transform = `translate(${canvasOffset.x}px, ${canvasOffset.y}px) scale(${zoomLevel})`;
    connectionsLayer.style.transformOrigin = 'top left';
    updateConnections();
}

function saveFlow() {
    // Validar fluxo antes de salvar
    if (!validateFlow()) {
        return;
    }
    
    const flowData = {
        nodes: nodes,
        connections: connections,
        variables: extractVariables()
    };
    
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

function publishFlow() {
    if (!validateFlow()) {
        return;
    }
    
    if (!confirm('Deseja publicar este fluxo? Isso criará uma versão imutável que será usada pelos usuários.')) {
        return;
    }
    
    const flowData = {
        nodes: nodes,
        connections: connections,
        variables: extractVariables()
    };
    
    fetch('ajax_checkin.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'publish_flow',
            checkin_id: <?php echo $checkin_id; ?>,
            flow: flowData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Fluxo publicado com sucesso!');
            location.reload();
        } else {
            alert('Erro ao publicar: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao publicar fluxo');
    });
}

function validateFlow() {
    // Verificar se há pelo menos um nó
    if (nodes.length === 0) {
        alert('Adicione pelo menos um bloco ao fluxo');
        return false;
    }
    
    // Verificar se há variáveis duplicadas
    const variables = extractVariables();
    const varNames = variables.map(v => v.name);
    const duplicates = varNames.filter((name, index) => varNames.indexOf(name) !== index);
    if (duplicates.length > 0) {
        alert(`Variáveis duplicadas encontradas: ${duplicates.join(', ')}`);
        return false;
    }
    
    // Verificar se todas as perguntas têm variável
    const questionsWithoutVar = nodes.filter(n => 
        n.type === 'question' && (!n.data.variable_name || n.data.variable_name.trim() === '')
    );
    if (questionsWithoutVar.length > 0) {
        alert('Todas as perguntas devem ter um nome de variável definido');
        return false;
    }
    
    return true;
}

function extractVariables() {
    const vars = [];
    const seen = new Set();
    
    nodes.forEach(node => {
        if (node.type === 'question' && node.data.variable_name) {
            const varName = node.data.variable_name.trim();
            if (varName && !seen.has(varName)) {
                seen.add(varName);
                vars.push({
                    name: varName,
                    type: getVariableType(node.subtype),
                    required: node.data.required !== false
                });
            }
        }
    });
    
    return vars;
}

function getVariableType(subtype) {
    const typeMap = {
        'text': 'string',
        'textarea': 'string',
        'number': 'number',
        'email': 'string',
        'phone': 'string',
        'date': 'date',
        'time': 'time',
        'rating': 'number',
        'slider': 'number',
        'yesno': 'boolean',
        'multiple_choice': 'string',
        'checkbox': 'json',
        'chips': 'json'
    };
    return typeMap[subtype] || 'string';
}

function openPreview() {
    if (nodes.length === 0) {
        alert('Adicione blocos ao fluxo antes de visualizar');
        return;
    }
    
    const modal = document.getElementById('previewModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        
        // Iniciar simulação do chat
        startChatSimulation();
    }
}

function closePreview() {
    const modal = document.getElementById('previewModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Limpar estado do chat
    chatState = null;
    const messagesDiv = document.getElementById('chatMessages');
    const inputDiv = document.getElementById('chatInputContainer');
    if (messagesDiv) messagesDiv.innerHTML = '';
    if (inputDiv) inputDiv.innerHTML = '';
}

let chatState = null;

function startChatSimulation() {
    chatState = {
        currentBlock: findStartBlock(),
        answers: {},
        history: []
    };
    
    if (!chatState.currentBlock) {
        // Se não há bloco inicial, usar o primeiro
        chatState.currentBlock = nodes[0];
    }
    
    renderNextBlock();
}

function findStartBlock() {
    // Encontrar bloco sem conexões de entrada (start)
    const blocksWithInputs = new Set();
    connections.forEach(conn => {
        blocksWithInputs.add(conn.to);
    });
    
    return nodes.find(n => !blocksWithInputs.has(n.id)) || nodes[0];
}

function renderNextBlock() {
    if (!chatState || !chatState.currentBlock) {
        addChatMessage('Fluxo finalizado!', 'bot');
        return;
    }
    
    const block = chatState.currentBlock;
    const messagesDiv = document.getElementById('chatMessages');
    const inputDiv = document.getElementById('chatInputContainer');
    
    if (block.type === 'bot_message') {
        // Processar placeholders na mensagem
        const message = processPlaceholders(block.data.prompt || '...', chatState.answers);
        addChatMessage(message, 'bot');
        
        // Aguardar delay e avançar
        const delay = block.data.delay || (block.data.auto_continue ? 500 : 0);
        if (delay > 0 || block.data.auto_continue) {
            setTimeout(() => {
                moveToNextBlock();
            }, delay);
        } else {
            // Mostrar botão "Continuar"
            const inputDiv = document.getElementById('chatInputContainer');
            inputDiv.innerHTML = '';
            const continueBtn = document.createElement('button');
            continueBtn.textContent = 'Continuar';
            continueBtn.style.cssText = `
                width: 100%;
                padding: 0.75rem;
                background: var(--accent-orange);
                border: none;
                border-radius: 8px;
                color: white;
                cursor: pointer;
                font-weight: 600;
            `;
            continueBtn.onclick = () => moveToNextBlock();
            inputDiv.appendChild(continueBtn);
        }
        
    } else if (block.type === 'question') {
        // Processar placeholders na pergunta
        const question = processPlaceholders(block.data.prompt || '...', chatState.answers);
        addChatMessage(question, 'bot');
        renderQuestionInput(block);
        
    } else if (block.type === 'action') {
        if (block.data.action_type === 'end') {
            addChatMessage('Check-in finalizado! Obrigado pelas suas respostas.', 'bot');
            return;
        } else if (block.data.action_type === 'jump') {
            const targetBlock = nodes.find(n => n.id === block.data.jump_to);
            if (targetBlock) {
                chatState.currentBlock = targetBlock;
                renderNextBlock();
            }
        }
    }
}

function addChatMessage(text, type) {
    const messagesDiv = document.getElementById('chatMessages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `chat-message ${type}`;
    messageDiv.style.cssText = `
        margin-bottom: 1rem;
        display: flex;
        ${type === 'bot' ? 'justify-content: flex-start;' : 'justify-content: flex-end;'}
        animation: fadeInUp 0.3s ease;
    `;
    
    const bubble = document.createElement('div');
    bubble.style.cssText = `
        max-width: 70%;
        padding: 0.75rem 1rem;
        border-radius: ${type === 'bot' ? '0 12px 12px 12px' : '12px 0 12px 12px'};
        background: ${type === 'bot' ? 'rgba(255, 255, 255, 0.1)' : 'var(--accent-orange)'};
        color: ${type === 'bot' ? 'var(--text-primary)' : 'white'};
        word-wrap: break-word;
    `;
    
    // Processar placeholders
    const processedText = processPlaceholders(text, chatState?.answers || {});
    bubble.innerHTML = processedText.replace(/\n/g, '<br>');
    
    messageDiv.appendChild(bubble);
    messagesDiv.appendChild(messageDiv);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

function processPlaceholders(text, answers) {
    if (!text) return '';
    
    // Substituir {{variavel}} pelos valores
    return text.replace(/\{\{(\w+)\}\}/g, (match, varName) => {
        const value = answers[varName];
        if (value === undefined || value === null) {
            return match; // Manter placeholder se não houver valor
        }
        return String(value);
    });
}

function renderQuestionInput(block) {
    const inputDiv = document.getElementById('chatInputContainer');
    inputDiv.innerHTML = '';
    
    if (block.subtype === 'multiple_choice' || block.subtype === 'chips') {
        const options = block.data.options || [];
        options.forEach(opt => {
            const btn = document.createElement('button');
            btn.textContent = opt.label || opt.value;
            btn.style.cssText = `
                width: 100%;
                padding: 0.75rem;
                margin-bottom: 0.5rem;
                background: rgba(255, 107, 0, 0.1);
                border: 1px solid rgba(255, 107, 0, 0.3);
                border-radius: 8px;
                color: var(--accent-orange);
                cursor: pointer;
                font-weight: 600;
            `;
            btn.onclick = () => submitAnswer(block, opt.value);
            inputDiv.appendChild(btn);
        });
    } else if (block.subtype === 'yesno') {
        ['Sim', 'Não'].forEach(val => {
            const btn = document.createElement('button');
            btn.textContent = val;
            btn.style.cssText = `
                flex: 1;
                padding: 0.75rem;
                margin: 0 0.25rem;
                background: rgba(255, 107, 0, 0.1);
                border: 1px solid rgba(255, 107, 0, 0.3);
                border-radius: 8px;
                color: var(--accent-orange);
                cursor: pointer;
                font-weight: 600;
            `;
            btn.onclick = () => submitAnswer(block, val === 'Sim' ? 'true' : 'false');
            inputDiv.appendChild(btn);
        });
        inputDiv.style.display = 'flex';
    } else {
        const input = document.createElement('input');
        input.type = block.subtype === 'number' ? 'number' : 
                    block.subtype === 'email' ? 'email' :
                    block.subtype === 'date' ? 'date' :
                    block.subtype === 'time' ? 'time' : 'text';
        input.placeholder = block.data.placeholder || 'Digite sua resposta...';
        input.style.cssText = `
            width: 100%;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
        `;
        input.onkeypress = (e) => {
            if (e.key === 'Enter') {
                submitAnswer(block, input.value);
            }
        };
        const sendBtn = document.createElement('button');
        sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        sendBtn.style.cssText = `
            margin-top: 0.5rem;
            width: 100%;
            padding: 0.75rem;
            background: var(--accent-orange);
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            font-weight: 600;
        `;
        sendBtn.onclick = () => submitAnswer(block, input.value);
        inputDiv.appendChild(input);
        inputDiv.appendChild(sendBtn);
    }
}

function submitAnswer(block, value) {
    if (!value || (typeof value === 'string' && value.trim() === '')) {
        if (block.data.required !== false) {
            alert('Esta pergunta é obrigatória');
            return;
        }
    }
    
    // Validar valor baseado no tipo
    if (!validateAnswer(block, value)) {
        return;
    }
    
    // Normalizar valor
    const normalizedValue = normalizeAnswer(block, value);
    
    // Salvar resposta
    chatState.answers[block.data.variable_name] = normalizedValue;
    
    // Mostrar resposta do usuário
    const displayValue = block.subtype === 'yesno' ? (value === 'true' ? 'Sim' : 'Não') : value;
    addChatMessage(displayValue, 'user');
    
    // Limpar input
    const inputDiv = document.getElementById('chatInputContainer');
    inputDiv.innerHTML = '';
    
    // Mover para próximo bloco
    setTimeout(() => moveToNextBlock(), 200);
}

function validateAnswer(block, value) {
    if (block.subtype === 'number') {
        const num = Number(value);
        if (isNaN(num)) {
            alert('Por favor, insira um número válido');
            return false;
        }
        if (block.data.min !== undefined && num < block.data.min) {
            alert(`O valor deve ser maior ou igual a ${block.data.min}`);
            return false;
        }
        if (block.data.max !== undefined && num > block.data.max) {
            alert(`O valor deve ser menor ou igual a ${block.data.max}`);
            return false;
        }
    } else if (block.subtype === 'email') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            alert('Por favor, insira um email válido');
            return false;
        }
    } else if (block.subtype === 'multiple_choice' || block.subtype === 'chips') {
        const options = block.data.options || [];
        const validValues = options.map(opt => opt.value);
        if (!validValues.includes(value)) {
            alert('Por favor, selecione uma opção válida');
            return false;
        }
    }
    return true;
}

function normalizeAnswer(block, value) {
    if (block.subtype === 'number') {
        return Number(value);
    } else if (block.subtype === 'yesno') {
        return value === 'true' || value === true || value === 'Sim';
    } else if (block.subtype === 'checkbox') {
        // Se já é array, retornar; senão, criar array
        return Array.isArray(value) ? value : [value];
    }
    return String(value);
}

function moveToNextBlock() {
    if (!chatState) return;
    
    const currentBlockId = chatState.currentBlock.id;
    
    // Encontrar próximo bloco baseado nas conexões
    let nextConnections = connections.filter(c => c.from === currentBlockId);
    
    if (nextConnections.length === 0) {
        // Fim do fluxo
        addChatMessage('Fluxo finalizado! Obrigado pelas suas respostas.', 'bot');
        return;
    }
    
    // Ordenar por prioridade
    nextConnections.sort((a, b) => (a.priority || 0) - (b.priority || 0));
    
    // Avaliar condições
    let nextBlock = null;
    for (const conn of nextConnections) {
        if (conn.condition) {
            // Avaliar condição
            if (evaluateCondition(conn.condition, chatState.answers)) {
                nextBlock = nodes.find(n => n.id === conn.to);
                break;
            }
        } else {
            // Conexão sem condição (fallback/default)
            if (!nextBlock) {
                nextBlock = nodes.find(n => n.id === conn.to);
            }
        }
    }
    
    // Se nenhuma condição foi satisfeita, usar o fallback
    if (!nextBlock && nextConnections.length > 0) {
        nextBlock = nodes.find(n => n.id === nextConnections[0].to);
    }
    
    if (nextBlock) {
        chatState.currentBlock = nextBlock;
        setTimeout(() => renderNextBlock(), 300);
    } else {
        addChatMessage('Fluxo finalizado! Obrigado pelas suas respostas.', 'bot');
    }
}

function evaluateCondition(condition, answers) {
    if (!condition || !condition.var) return true;
    
    const varValue = answers[condition.var];
    const compareValue = condition.value;
    
    if (varValue === undefined || varValue === null) {
        return false;
    }
    
    switch (condition.op) {
        case '==':
            return String(varValue) === String(compareValue);
        case '!=':
            return String(varValue) !== String(compareValue);
        case '>':
            return Number(varValue) > Number(compareValue);
        case '>=':
            return Number(varValue) >= Number(compareValue);
        case '<':
            return Number(varValue) < Number(compareValue);
        case '<=':
            return Number(varValue) <= Number(compareValue);
        case 'contains':
            return String(varValue).toLowerCase().includes(String(compareValue).toLowerCase());
        case 'startsWith':
            return String(varValue).toLowerCase().startsWith(String(compareValue).toLowerCase());
        default:
            return true;
    }
}

// Drag & Drop da paleta
function setupPaletteDragDrop() {
    document.querySelectorAll('.palette-item').forEach(item => {
        item.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('nodeType', item.dataset.type);
            e.dataTransfer.setData('nodeSubtype', item.dataset.subtype || '');
        });
    });
}

// Inicializar tudo quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    if (initCanvas()) {
        setupCanvasEvents();
        setupPaletteDragDrop();
        loadFlow();
    } else {
        console.error('Erro ao inicializar canvas');
    }
});

// Fallback caso DOMContentLoaded já tenha disparado
if (document.readyState === 'loading') {
    // DOM ainda não carregou, aguardar
} else {
    // DOM já carregou
    if (initCanvas()) {
        setupCanvasEvents();
        setupPaletteDragDrop();
        loadFlow();
    }
}

// Atualizar conexões quando o canvas é redimensionado
window.addEventListener('resize', updateConnections);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

