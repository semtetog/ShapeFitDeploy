<?php
// admin/includes/units_editor.php - Modal para editar conversões de unidades

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/units_manager.php';
?>

<!-- Modal para editar unidades -->
<div class="units-editor-modal" id="units-editor-modal">
    <div class="units-editor-content">
        <div class="units-editor-header">
            <h3 id="units-editor-title">Editar Unidades de Medida</h3>
            <button class="units-editor-close" onclick="closeUnitsEditor()">&times;</button>
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
    <div class="unit-edit-content">
        <div class="unit-edit-header">
            <h4 id="unit-edit-title">Adicionar Unidade</h4>
            <button class="unit-edit-close" onclick="closeUnitEdit()">&times;</button>
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
/* Modal de edição de unidades */
.units-editor-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
}

.units-editor-modal.visible {
    display: flex;
}

.units-editor-content {
    background: #1a1a1a;
    border-radius: 16px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.units-editor-header {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.units-editor-header h3 {
    margin: 0;
    color: var(--text-primary);
    font-size: 1.2rem;
}

.units-editor-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.units-editor-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.units-editor-body {
    padding: 24px;
    flex: 1;
    overflow-y: auto;
}

.food-info {
    margin-bottom: 24px;
    padding: 16px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.food-info h4 {
    margin: 0 0 8px 0;
    color: var(--text-primary);
    font-size: 1.1rem;
}

.food-categories {
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.units-section h4 {
    margin: 0 0 16px 0;
    color: var(--text-primary);
    font-size: 1rem;
}

.units-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.units-header h4 {
    margin: 0;
    color: var(--text-primary);
    font-size: 1rem;
}

.btn-add-unit {
    background: var(--accent-orange);
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    color: white;
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-add-unit:hover {
    background: #ff7a1a;
    transform: translateY(-1px);
}

.units-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}


.no-units {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-secondary);
    background: rgba(255, 255, 255, 0.02);
    border: 2px dashed rgba(255, 255, 255, 0.1);
    border-radius: 12px;
}

.no-units p {
    margin: 0 0 8px 0;
    font-size: 0.9rem;
}

.no-units p:last-child {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.unit-item {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 16px;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 16px;
    align-items: center;
    transition: all 0.2s ease;
}

.unit-item:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: var(--accent-orange);
}

.unit-info {
    flex: 1;
}

.unit-name {
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 4px 0;
}

.unit-conversion {
    font-size: 0.9rem;
    color: var(--text-secondary);
    margin: 0;
}

.unit-actions {
    display: flex;
    gap: 8px;
    align-items: center;
    justify-content: flex-end;
}

.btn-edit-unit, .btn-delete-unit {
    background: none;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 6px;
    padding: 6px 12px;
    color: var(--text-secondary);
    font-size: 0.8rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-edit-unit:hover {
    border-color: var(--accent-orange);
    color: var(--accent-orange);
}

.btn-delete-unit:hover {
    border-color: #ef4444;
    color: #ef4444;
}

.units-editor-footer {
    padding: 20px 24px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.btn-cancel, .btn-save {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
}

.btn-cancel {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-secondary);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.btn-cancel:hover {
    background: rgba(255, 255, 255, 0.15);
    color: var(--text-primary);
}

.btn-save {
    background: var(--accent-orange);
    color: white;
}

.btn-save:hover {
    background: #ff7a1a;
    transform: translateY(-1px);
}

/* Modal de edição de unidade individual */
.unit-edit-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    z-index: 10001;
    display: none;
    align-items: center;
    justify-content: center;
}

.unit-edit-modal.visible {
    display: flex;
}

.unit-edit-content {
    background: #1a1a1a;
    border-radius: 16px;
    width: 90%;
    max-width: 400px;
    overflow: hidden;
}

.unit-edit-header {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.unit-edit-header h4 {
    margin: 0;
    color: var(--text-primary);
    font-size: 1.1rem;
}

.unit-edit-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 20px;
    cursor: pointer;
    padding: 0;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.unit-edit-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.unit-edit-body {
    padding: 24px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--text-primary);
    font-weight: 500;
    font-size: 0.9rem;
}

.form-group input[type="text"],
.form-group input[type="number"] {
    width: 100%;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 12px;
    color: var(--text-primary);
    font-size: 0.9rem;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.form-group input:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

.conversion-input {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 12px;
}

.conversion-label {
    color: var(--text-primary);
    font-size: 0.9rem;
    font-weight: 500;
    white-space: nowrap;
}

.conversion-label #unit-name-display {
    color: var(--accent-orange);
    font-weight: 600;
}

.conversion-input input {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 6px;
    padding: 8px 12px;
    text-align: center;
    flex: 1;
    color: var(--text-primary);
    font-size: 0.9rem;
    font-weight: 600;
    min-width: 80px;
}

.conversion-input input:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.12);
}

.conversion-gramas {
    color: var(--text-secondary);
    font-size: 0.9rem;
    font-weight: 500;
    white-space: nowrap;
}

.help-text {
    display: block;
    margin-top: 6px;
    color: var(--text-secondary);
    font-size: 0.8rem;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    margin: 0;
    padding: 20px 24px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    transition: all 0.2s ease;
    user-select: none;
    min-height: 60px;
    width: 100%;
}

.checkbox-label:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: var(--accent-orange);
}

.checkbox-label input[type="checkbox"] {
    display: none;
}

.checkmark {
    width: 28px;
    height: 28px;
    flex-shrink: 0;
    border: 3px solid rgba(255, 255, 255, 0.4);
    border-radius: 8px;
    position: relative;
    transition: all 0.2s ease;
    background: rgba(255, 255, 255, 0.02);
    box-sizing: border-box;
}

.checkbox-label:hover .checkmark {
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.05);
}

.checkbox-label input:checked + .checkmark {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    box-shadow: 0 0 0 2px rgba(249, 115, 22, 0.2);
}

.checkbox-label input:checked + .checkmark::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 16px;
    font-weight: bold;
}

.checkbox-label span:not(.checkmark) {
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: 500;
    line-height: 1.5;
    flex: 1;
    min-width: 0;
    word-wrap: break-word;
}

.unit-edit-footer {
    padding: 20px 24px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

/* Animações */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.default-badge {
    background: var(--accent-orange);
    color: white;
    font-size: 0.6rem;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.unit-conversion {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
}

.unit-conversion i {
    color: var(--accent-orange);
    font-size: 0.8rem;
}

.unit-conversion strong {
    color: var(--text-primary);
    font-weight: 600;
}

.btn-edit-unit, .btn-delete-unit {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    padding: 6px 10px;
}

.btn-edit-unit i, .btn-delete-unit i {
    font-size: 0.7rem;
}

/* Responsividade */
@media (max-width: 768px) {
    .units-editor-content {
        width: 95%;
        max-height: 90vh;
    }
    
    .units-editor-body {
        padding: 16px;
    }
    
    .unit-edit-content {
        width: 95%;
    }
    
    .unit-edit-body {
        padding: 16px;
    }
    
    .units-header {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
}
</style>

<script>
let currentFoodId = null;
let currentUnits = [];
let editingUnitIndex = -1;
let currentCategories = []; // Armazena as categorias do alimento atual
let autoAddedUnits = new Set(); // Guarda as abreviações de unidades adicionadas automaticamente (não salvas)

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
    autoAddedUnits.clear();
}

function loadFoodUnits() {
    if (!currentFoodId) return;
    
    fetch(`ajax_get_food_units.php?food_id=${currentFoodId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data.length > 0) {
            // Se há unidades personalizadas, usa elas
            currentUnits = data.data;
            // MAS também mescla com as padrão das categorias para exibir todas
            mergeWithDefaultUnits();
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

// Mescla as unidades personalizadas com as padrão das categorias
function mergeWithDefaultUnits() {
    if (!currentCategories || currentCategories.length === 0) {
        // Se não há categorias, mantém apenas as personalizadas
        renderUnitsList();
        return;
    }
    
    // Carregar unidades padrão de todas as categorias
    const promises = currentCategories.map(category => 
        fetch(`ajax_get_default_units.php?category=${encodeURIComponent(category)}`)
            .then(response => response.json())
            .then(data => data.success ? data.data : [])
            .catch(error => {
                console.error(`Erro ao carregar unidades para categoria ${category}:`, error);
                return [];
            })
    );
    
    Promise.all(promises)
    .then(allUnitsArrays => {
        // Criar um Map com as unidades personalizadas já carregadas (prioridade)
        const unitsMap = new Map();
        currentUnits.forEach(unit => {
            unitsMap.set(unit.abbreviation, unit);
        });
        
        // Adicionar unidades padrão que não são duplicatas
        allUnitsArrays.forEach(unitsArray => {
            unitsArray.forEach(unit => {
                if (!unitsMap.has(unit.abbreviation)) {
                    unitsMap.set(unit.abbreviation, unit);
                    autoAddedUnits.add(unit.abbreviation); // Marca como auto-adicionada
                }
            });
        });
        
        currentUnits = Array.from(unitsMap.values());
        renderUnitsList();
    })
    .catch(error => {
        console.error('Erro ao processar unidades:', error);
        // Em caso de erro, mantém as unidades personalizadas
        renderUnitsList();
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
        <div class="unit-item" style="animation: slideIn 0.3s ease-out;">
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
        const oldAbbreviation = currentUnits[editingUnitIndex].abbreviation;
        currentUnits[editingUnitIndex] = { ...currentUnits[editingUnitIndex], ...unitData };
        
        // Se editou uma auto-adicionada, ela agora é personalizada
        if (autoAddedUnits.has(oldAbbreviation)) {
            autoAddedUnits.delete(oldAbbreviation);
        }
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
    
    // Filtrar apenas as unidades personalizadas (remover as auto-adicionadas que não foram editadas)
    const unitsToSave = currentUnits.filter(unit => !autoAddedUnits.has(unit.abbreviation));
    
    if (unitsToSave.length === 0) {
        alert('⚠️ Todas as unidades que você tentou salvar são automáticas. Adicione ou edite pelo menos uma unidade personalizada!');
        return;
    }
    
    const formData = new FormData();
    formData.append('food_id', currentFoodId);
    formData.append('units', JSON.stringify(unitsToSave));
    
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
