<?php
// admin/includes/units_editor.php - Modal para editar conversões de unidades

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/units_manager.php';
?>

<!-- Modal para editar unidades -->
<div class="units-editor-modal" id="units-editor-modal">
    <div class="units-editor-overlay" onclick="closeUnitsEditor()"></div>
    <div class="units-editor-content">
        <button class="sleep-modal-close" onclick="closeUnitsEditor()" type="button">
            <i class="fas fa-times"></i>
        </button>
        <div class="units-editor-header">
            <h3 id="units-editor-title">Editar Unidades de Medida</h3>
        </div>
        
        <div class="units-editor-body">
            <div class="food-info">
                <h4 id="units-food-name">Nome do Alimento</h4>
                <p class="food-categories" id="units-food-categories">Categorias: </p>
            </div>
            
        <div class="units-section">
            <div class="units-header">
                <h4>Unidades de Medida</h4>
                <button class="btn-add-unit" onclick="addNewUnit()">
                    <i class="fas fa-plus"></i> Adicionar Unidade
                </button>
            </div>
            
            <div class="units-list" id="units-list">
                <!-- Unidades serão carregadas aqui via JavaScript -->
            </div>
        </div>
        </div>
        
        <div class="units-editor-footer">
            <button class="btn-cancel" onclick="closeUnitsEditor()">Cancelar</button>
            <button class="btn-save" onclick="saveUnits()">Salvar Alterações</button>
        </div>
    </div>
</div>

<!-- Modal para adicionar/editar unidade individual -->
<div class="unit-edit-modal" id="unit-edit-modal">
    <div class="unit-edit-overlay" onclick="closeUnitEdit()"></div>
    <div class="unit-edit-content">
        <button class="sleep-modal-close" onclick="closeUnitEdit()" type="button">
            <i class="fas fa-times"></i>
        </button>
        <div class="unit-edit-header">
            <h4 id="unit-edit-title">Adicionar Unidade</h4>
        </div>
        
        <div class="unit-edit-body">
            <div class="form-group">
                <label for="unit-name">Nome da Unidade</label>
                <input type="text" id="unit-name" placeholder="Ex: Unidade, Fatia, Colher de sopa">
            </div>
            
            <div class="form-group">
                <label for="unit-abbreviation">Abreviação</label>
                <input type="text" id="unit-abbreviation" placeholder="Ex: un, fat, cs">
            </div>
            
            <div class="form-group">
                <label for="unit-conversion">Conversão para Gramas</label>
                <div class="conversion-input">
                    <span class="conversion-label">1 <span id="unit-name-display">unidade</span> =</span>
                    <input type="text" id="unit-conversion" placeholder="150" pattern="[0-9]+([.,][0-9]+)?">
                    <span class="conversion-gramas">gramas</span>
                </div>
                <small class="help-text">Ex: 1 colher de chá = 5 gramas</small>
            </div>
            
        </div>
        
        <div class="unit-edit-footer">
            <button class="btn-cancel" onclick="closeUnitEdit()">Cancelar</button>
            <button class="btn-save" onclick="saveUnit()">Salvar Unidade</button>
        </div>
    </div>
</div>

<style>
/* ========================================================================= */
/*       UNITS EDITOR - ESTILO VIEW_USER MODERNO                            */
/* ========================================================================= */

/* Modal principal - estilo view_user */
.units-editor-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.1s ease;
}

.units-editor-modal.visible {
    opacity: 1;
    pointer-events: all;
}

/* Overlay separado - igual view_user para blur mais rápido */
.units-editor-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    transition: none !important;
}

.units-editor-content {
    position: relative;
    background: linear-gradient(135deg, rgba(30, 30, 30, 0.98) 0%, rgba(20, 20, 20, 0.98) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    width: 90%;
    max-width: 600px;
    max-height: 85vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
    transform: scale(0.95);
    transition: transform 0.3s ease;
}

.units-editor-modal.visible .units-editor-content {
    transform: scale(1);
}

.units-editor-header {
    padding: 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.units-editor-header h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-primary);
    font-family: 'Montserrat', sans-serif;
}

/* Botão X - copiado do sleep-modal-close do view_user */
.sleep-modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    width: 2.5rem;
    height: 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    z-index: 10;
}

.sleep-modal-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--accent-orange);
}

.units-editor-body {
    padding: 2rem;
    flex: 1;
    overflow-y: auto;
}

/* Food info card - estilo dashboard-card */
.food-info {
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    transition: all 0.3s ease;
}

.food-info:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
    transform: translateY(-1px);
}

.food-info h4 {
    margin: 0 0 0.75rem 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    font-family: 'Montserrat', sans-serif;
}

.food-categories {
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.95rem;
}

/* Units section */
.units-section h4 {
    margin: 0 0 1.5rem 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    font-family: 'Montserrat', sans-serif;
}

.units-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.units-header h4 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-primary);
    font-family: 'Montserrat', sans-serif;
}

/* Botão adicionar - estilo btn-primary */
.btn-add-unit {
    background: linear-gradient(135deg, #FF6600, #FF8533);
    border: none;
    border-radius: 12px;
    padding: 0.75rem 1.5rem;
    color: white;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Montserrat', sans-serif;
}

.btn-add-unit:hover {
    background: linear-gradient(135deg, #FF8533, #FF6600);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3);
}

.units-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

/* Empty state */
.no-units {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-secondary);
    background: rgba(255, 255, 255, 0.02);
    border: 2px dashed rgba(255, 255, 255, 0.1);
    border-radius: 16px;
}

.no-units i {
    font-size: 3rem;
    color: var(--accent-orange);
    margin-bottom: 1rem;
    opacity: 0.5;
}

.no-units p {
    margin: 0.5rem 0;
    font-size: 0.95rem;
}

.no-units p:first-of-type {
    font-weight: 600;
    color: var(--text-primary);
}

/* Unit item card - estilo dashboard-card */
.unit-item {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 1.5rem;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 1.5rem;
    align-items: center;
    transition: all 0.3s ease;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

.unit-item:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

.unit-info {
    flex: 1;
}

.unit-name {
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 0.5rem 0;
    font-size: 1rem;
    font-family: 'Montserrat', sans-serif;
}

.unit-conversion {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0;
}

.unit-conversion i {
    color: var(--accent-orange);
    font-size: 0.85rem;
}

.unit-conversion strong {
    color: var(--text-primary);
    font-weight: 600;
}

.unit-actions {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    justify-content: flex-end;
}

/* Botões de ação - estilo btn-secondary */
.btn-edit-unit, .btn-delete-unit {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 0.625rem 1rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Montserrat', sans-serif;
}

.btn-edit-unit:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--accent-orange);
    color: var(--accent-orange);
    transform: translateY(-2px);
}

.btn-delete-unit:hover {
    background: rgba(244, 67, 54, 0.1);
    border-color: #F44336;
    color: #F44336;
    transform: translateY(-2px);
}

/* Footer - estilo custom-modal-footer */
.units-editor-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

/* Botões footer - estilo btn-modal */
.btn-cancel, .btn-save {
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Montserrat', sans-serif;
}

.btn-cancel {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-secondary);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-cancel:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
    transform: translateY(-2px);
}

.btn-save {
    background: linear-gradient(135deg, #FF6600, #FF8533);
    color: white;
}

.btn-save:hover {
    background: linear-gradient(135deg, #FF8533, #FF6600);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3);
}

/* Modal de edição de unidade individual - estilo view_user */
.unit-edit-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 10001;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.1s ease;
}

.unit-edit-modal.visible {
    opacity: 1;
    pointer-events: all;
}

/* Overlay separado - igual view_user para blur mais rápido */
.unit-edit-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    transition: none !important;
}

.unit-edit-content {
    position: relative;
    background: linear-gradient(135deg, rgba(30, 30, 30, 0.98) 0%, rgba(20, 20, 20, 0.98) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 20px;
    width: 90%;
    max-width: 450px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
    transform: scale(0.95);
    transition: transform 0.3s ease;
}

.unit-edit-modal.visible .unit-edit-content {
    transform: scale(1);
}

.unit-edit-header {
    padding: 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.unit-edit-header h4 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    font-family: 'Montserrat', sans-serif;
}


.unit-edit-body {
    padding: 2rem;
}

/* Form groups - estilo view_user */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.75rem;
    color: var(--text-primary);
    font-weight: 600;
    font-size: 0.9rem;
    font-family: 'Montserrat', sans-serif;
}

.form-group input[type="text"],
.form-group input[type="number"] {
    width: 100%;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 0.875rem 1.25rem;
    color: var(--text-primary);
    font-size: 0.95rem;
    transition: all 0.3s ease;
    box-sizing: border-box;
    font-family: 'Montserrat', sans-serif;
}

.form-group input:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
}

.conversion-input {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 0.875rem 1.25rem;
}

.conversion-label {
    color: var(--text-primary);
    font-size: 0.95rem;
    font-weight: 500;
    white-space: nowrap;
    font-family: 'Montserrat', sans-serif;
}

.conversion-label #unit-name-display {
    color: var(--accent-orange);
    font-weight: 700;
}

.conversion-input input {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 10px;
    padding: 0.75rem 1rem;
    text-align: center;
    flex: 1;
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: 700;
    min-width: 100px;
    font-family: 'Montserrat', sans-serif;
}

.conversion-input input:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.12);
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
}

.conversion-gramas {
    color: var(--text-secondary);
    font-size: 0.95rem;
    font-weight: 500;
    white-space: nowrap;
    font-family: 'Montserrat', sans-serif;
}

.help-text {
    display: block;
    margin-top: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.85rem;
}

.unit-edit-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.default-badge {
    background: var(--accent-orange);
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    margin-left: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-family: 'Montserrat', sans-serif;
}

/* Animações - otimizadas para performance */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.unit-item {
    will-change: transform, opacity;
}

.unit-item.animate-in {
    animation: slideIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

/* Responsividade */
@media (max-width: 768px) {
    .units-editor-content {
        width: 95%;
        max-height: 90vh;
    }
    
    .units-editor-body {
        padding: 1.5rem;
    }
    
    .unit-edit-content {
        width: 95%;
    }
    
    .unit-edit-body {
        padding: 1.5rem;
    }
    
    .units-header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
    
    .unit-item {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .unit-actions {
        justify-content: flex-start;
    }
}
</style>

<script>
let currentFoodId = null;
let currentUnits = [];
let editingUnitIndex = -1;
let currentCategories = []; // Armazena as categorias do alimento atual

function openUnitsEditor(foodId, foodName, categories) {
    // Verificar se o alimento está classificado
    if (!categories || categories.length === 0) {
        alert('⚠️ Classifique o alimento primeiro antes de editar as unidades!');
        return;
    }
    
    currentFoodId = foodId;
    currentCategories = categories; // Armazena as categorias
    document.getElementById('units-food-name').textContent = foodName;
    document.getElementById('units-food-categories').textContent = 'Categorias: ' + categories.join(', ');
    
    // Resetar botão de salvar
    const saveBtn = document.querySelector('.btn-save');
    saveBtn.innerHTML = 'Salvar Alterações';
    saveBtn.style.background = '';
    saveBtn.disabled = false;
    
    loadFoodUnits();
    document.getElementById('units-editor-modal').classList.add('visible');
}

function closeUnitsEditor() {
    document.getElementById('units-editor-modal').classList.remove('visible');
    currentFoodId = null;
    currentUnits = [];
    currentCategories = [];
}

function loadFoodUnits() {
    if (!currentFoodId) return;
    
    fetch(`ajax_get_food_units.php?food_id=${currentFoodId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data.length > 0) {
            // Se há unidades personalizadas, usa elas
            currentUnits = data.data;
            renderUnitsList();
        } else {
            // Se não há unidades personalizadas, carrega as padrão das categorias
            loadDefaultUnitsForCategories();
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        // Em caso de erro, também carrega as padrão das categorias
        loadDefaultUnitsForCategories();
    });
}

function loadDefaultUnitsForCategories() {
    // Carrega as unidades padrão baseadas nas categorias do alimento
    if (!currentCategories || currentCategories.length === 0) {
        currentUnits = [];
        renderUnitsList();
        return;
    }
    
    // Para cada categoria, buscar suas unidades padrão
    const promises = currentCategories.map(category => 
        fetch(`ajax_get_default_units.php?category=${encodeURIComponent(category)}`)
            .then(response => response.json())
            .then(data => data.success ? data.data : [])
            .catch(error => {
                console.error(`Erro ao carregar unidades para categoria ${category}:`, error);
                return [];
            })
    );
    
    // Esperar todas as promises e combinar os resultados
    Promise.all(promises)
    .then(allUnitsArrays => {
        // Combinar todas as unidades, removendo duplicatas por abbreviation
        const unitsMap = new Map();
        
        allUnitsArrays.forEach(unitsArray => {
            unitsArray.forEach(unit => {
                if (!unitsMap.has(unit.abbreviation)) {
                    unitsMap.set(unit.abbreviation, unit);
                }
            });
        });
        
        currentUnits = Array.from(unitsMap.values());
        renderUnitsList();
    })
    .catch(error => {
        console.error('Erro ao processar unidades:', error);
        // Fallback: usar apenas a primeira categoria
        const firstCategory = currentCategories[0];
        const defaultUnitsAbbr = window.categoryUnits[firstCategory] || ['g', 'ml', 'un'];
        currentUnits = defaultUnitsAbbr.map((abbr, index) => ({
            id: null,
            name: window.unitNames[abbr] || abbr,
            abbreviation: abbr,
            conversion_factor: 1.0,
            is_default: index === 0 ? 1 : 0
        }));
        renderUnitsList();
    });
}

function renderUnitsList() {
    const container = document.getElementById('units-list');
    
    if (currentUnits.length === 0) {
        container.innerHTML = `
            <div class="no-units">
                <i class="fas fa-ruler" style="font-size: 2rem; color: var(--accent-orange); margin-bottom: 12px;"></i>
                <p><strong>Nenhuma unidade configurada</strong></p>
                <p>Clique em "Adicionar Unidade" para começar</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = currentUnits.map((unit, index) => `
        <div class="unit-item" style="opacity: 0; transform: translateY(10px);">
            <div class="unit-info">
                <div class="unit-name">
                    ${unit.name} (${unit.abbreviation})
                </div>
                <div class="unit-conversion">
                    <i class="fas fa-exchange-alt"></i>
                    1 ${unit.abbreviation} = <strong>${parseFloat(unit.conversion_factor) % 1 === 0 ? parseFloat(unit.conversion_factor).toString() : parseFloat(unit.conversion_factor).toFixed(2)}g</strong>
                </div>
            </div>
            <div class="unit-actions">
                <button class="btn-edit-unit" onclick="editUnit(${index})" title="Editar unidade">
                    <i class="fas fa-edit"></i> Editar
                </button>
                <button class="btn-delete-unit" onclick="deleteUnit(${index})" title="Excluir unidade">
                    <i class="fas fa-trash"></i> Excluir
                </button>
            </div>
        </div>
    `).join('');
    
    // Anima os cards de forma escalonada e suave usando requestAnimationFrame
    const items = container.querySelectorAll('.unit-item');
    items.forEach((item, index) => {
        // Força reflow
        void item.offsetHeight;
        // Aplica animação com delay escalonado
        setTimeout(() => {
            item.style.transition = 'opacity 0.25s cubic-bezier(0.16, 1, 0.3, 1), transform 0.25s cubic-bezier(0.16, 1, 0.3, 1)';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, index * 30); // Delay de 30ms entre cada card
    });
}

function addNewUnit() {
    editingUnitIndex = -1;
    document.getElementById('unit-edit-title').textContent = 'Adicionar Unidade';
    document.getElementById('unit-name').value = '';
    document.getElementById('unit-abbreviation').value = '';
    document.getElementById('unit-conversion').value = '';
    document.getElementById('unit-is-default').checked = false;
    document.getElementById('unit-edit-modal').classList.add('visible');
    
    // Atualizar display do nome da unidade
    updateUnitNameDisplay();
    
    // Resetar botão de salvar para indicar mudanças pendentes
    resetSaveButton();
}

// Função para atualizar o display do nome da unidade
function updateUnitNameDisplay() {
    const unitName = document.getElementById('unit-name').value || 'unidade';
    document.getElementById('unit-name-display').textContent = unitName.toLowerCase();
}

function editUnit(index) {
    editingUnitIndex = index;
    const unit = currentUnits[index];
    
    document.getElementById('unit-edit-title').textContent = 'Editar Unidade';
    document.getElementById('unit-name').value = unit.name;
    document.getElementById('unit-abbreviation').value = unit.abbreviation;
    // Mostrar o valor sem zeros desnecessários
    const conversionValue = parseFloat(unit.conversion_factor);
    document.getElementById('unit-conversion').value = conversionValue % 1 === 0 ? conversionValue.toString() : conversionValue.toFixed(2).replace('.', ',');
    document.getElementById('unit-edit-modal').classList.add('visible');
    
    // Atualizar display do nome da unidade
    updateUnitNameDisplay();
    
    // Resetar botão de salvar para indicar mudanças pendentes
    resetSaveButton();
}

function closeUnitEdit() {
    document.getElementById('unit-edit-modal').classList.remove('visible');
    editingUnitIndex = -1;
}

function saveUnit() {
    const name = document.getElementById('unit-name').value.trim();
    const abbreviation = document.getElementById('unit-abbreviation').value.trim();
    const conversionValue = document.getElementById('unit-conversion').value.replace(',', '.');
    const conversion = parseFloat(conversionValue);
    
    if (!name || !abbreviation || !conversion || conversion <= 0) {
        alert('Por favor, preencha todos os campos com valores válidos.');
        return;
    }
    
    const unitData = {
        name: name,
        abbreviation: abbreviation,
        conversion_factor: conversion
    };
    
    if (editingUnitIndex >= 0) {
        // Editando unidade existente
        currentUnits[editingUnitIndex] = { ...currentUnits[editingUnitIndex], ...unitData };
    } else {
        // Adicionando nova unidade
        currentUnits.push(unitData);
    }
    
    
    renderUnitsList();
    closeUnitEdit();
    
    // Resetar botão de salvar para indicar mudanças pendentes
    resetSaveButton();
}

function deleteUnit(index) {
    if (confirm('Tem certeza que deseja excluir esta unidade?')) {
        currentUnits.splice(index, 1);
        renderUnitsList();
        
        // Resetar botão de salvar para indicar mudanças pendentes
        resetSaveButton();
    }
}

function resetSaveButton() {
    const saveBtn = document.querySelector('.btn-save');
    saveBtn.innerHTML = 'Salvar Alterações';
    saveBtn.style.background = '';
    saveBtn.disabled = false;
}


function saveUnits() {
    if (!currentFoodId) return;
    
    // Validações
    if (currentUnits.length === 0) {
        alert('⚠️ Adicione pelo menos uma unidade de medida!');
        return;
    }
    
    
    const formData = new FormData();
    formData.append('food_id', currentFoodId);
    formData.append('units', JSON.stringify(currentUnits));
    
    // Mostrar loading
    const saveBtn = document.querySelector('.btn-save');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    saveBtn.disabled = true;
    
    fetch('ajax_save_unit_conversions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Feedback de sucesso
            saveBtn.innerHTML = '<i class="fas fa-check"></i> Salvo!';
            saveBtn.style.background = '#22c55e';
            
            setTimeout(() => {
                // Resetar botão para permitir novas edições
                saveBtn.innerHTML = originalText;
                saveBtn.style.background = '';
                saveBtn.disabled = false;
            }, 1500);
        } else {
            alert('❌ Erro ao salvar: ' + data.error);
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('❌ Erro ao salvar unidades.');
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

// Event listener para atualizar o display do nome da unidade em tempo real
document.addEventListener('DOMContentLoaded', function() {
    const unitNameInput = document.getElementById('unit-name');
    if (unitNameInput) {
        unitNameInput.addEventListener('input', updateUnitNameDisplay);
    }
    
    // Event listener para formatar o campo de conversão
    const unitConversionInput = document.getElementById('unit-conversion');
    if (unitConversionInput) {
        unitConversionInput.addEventListener('input', function(e) {
            // Permitir apenas números, ponto e vírgula
            let value = e.target.value.replace(/[^0-9.,]/g, '');
            
            // Substituir vírgula por ponto para validação
            const normalizedValue = value.replace(',', '.');
            const numValue = parseFloat(normalizedValue);
            
            // Se for um número válido, formatar
            if (!isNaN(numValue) && numValue > 0) {
                // Se for inteiro, mostrar sem decimais
                if (numValue % 1 === 0) {
                    e.target.value = numValue.toString();
                } else {
                    // Mostrar com até 2 casas decimais
                    e.target.value = numValue.toFixed(2).replace('.', ',');
                }
            }
        });
    }
});
</script>
