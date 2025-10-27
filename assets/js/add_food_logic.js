// Arquivo: assets/js/add_food_logic.js
// VERSÃO CORRIGIDA - CARREGA UNIDADES DO BANCO DE DADOS

document.addEventListener('DOMContentLoaded', function() {

    // --- SELEÇÃO DE ELEMENTOS DOM ---
    const logDateDisplay = document.getElementById('log_date_display');
    const logMealTypeDisplay = document.getElementById('log_meal_type_display');
    const logDateHidden = document.getElementById('log_date_hidden_for_meal');
    const logMealTypeHidden = document.getElementById('log_meal_type_hidden_for_meal');
    const logEntireMealForm = document.getElementById('log-entire-meal-form');
    const searchInput = document.getElementById('food-search-input');
    const resultsContainer = document.getElementById('search-results-container');
    const selectedFoodContainer = document.getElementById('selected-food-details-container');
    const currentMealListUL = document.getElementById('current-meal-items-list');
    const currentMealTotalKcalSpan = document.getElementById('current-meal-total-kcal');
    
    const scanBarcodeBtn = document.getElementById('scan-barcode-btn');
    const scannerModal = document.getElementById('barcode-scanner-modal');
    const closeScannerModalBtn = document.getElementById('close-scanner-modal-btn');
    const scannerContainer = document.getElementById('scanner-container');
    const scannerStatus = document.getElementById('scanner-status');

    const notFoundModal = document.getElementById('product-not-found-modal');
    const closeNotFoundModalBtn = notFoundModal?.querySelector('[data-close-modal="product-not-found-modal"]');
    const takeNutritionPhotoBtn = document.getElementById('take-nutrition-photo-btn');
    const chooseNutritionPhotoBtn = document.getElementById('choose-nutrition-photo-btn');
    
    const cancelButton = document.getElementById('cancel-log-entire-meal-btn');

    if (cancelButton) {
        cancelButton.addEventListener('click', function() {
            window.history.back();
        });
    }

    // --- VARIÁVEIS DE ESTADO ---
    let currentSelectedFoodData = null;
    let mealItems = [];
    let searchDebounceTimer;
    let quaggaScannerInitialized = false;
    let fileInputForNutrition;

    // --- DEFINIÇÕES E FUNÇÕES DE LÓGICA ---

    const unitGroups = {
        weight: { 'g': 'Grama (g)', 'kg': 'Quilograma (kg)' },
        volume: { 'ml': 'Mililitro (ml)', 'l': 'Litro (L)', 'tablespoon': 'Colher de Sopa', 'teaspoon': 'Colher de Chá', 'cup': 'Xícara' },
        unit: { 'unit': 'Unidade', 'slice': 'Fatia', 'piece': 'Pedaço' }
    };

    const unitConversions = {
        'g': 1, 'kg': 1000, 'ml': 1, 'l': 1000,
        'tablespoon': 15, 'teaspoon': 5, 'cup': 240,
        'slice': 25, 'unit': 150, 'piece': 50
    };

    function handleFoodSelection(foodData) {
        setTimeout(() => {
            currentSelectedFoodData = foodData;
            
            // SEMPRE carregar unidades do banco de dados
            loadFoodUnits(foodData.id);
        }, 100);
    }

    function loadFoodUnits(foodId) {
        if (typeof BASE_APP_URL === 'undefined') {
            console.error("Variável global BASE_APP_URL não foi encontrada.");
            return;
        }

        const apiUrl = `${BASE_APP_URL}/api/ajax_get_food_units.php?food_id=${encodeURIComponent(foodId)}`;
        
        fetch(apiUrl)
            .then(response => {
                if (!response.ok) throw new Error(`Erro de rede: ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.success && data.data.length > 0) {
                    // Usar unidades do banco de dados
                    let unitOptionsHTML = '';
                    let defaultUnit = '';
                    
                    data.data.forEach(unit => {
                        const isDefault = unit.is_default;
                        if (isDefault) {
                            defaultUnit = unit.abbreviation;
                        }
                        unitOptionsHTML += `<option value="${unit.abbreviation}" ${isDefault ? 'selected' : ''}>${unit.name} (${unit.abbreviation})</option>`;
                    });
                    
                    updateFoodDisplay(unitOptionsHTML, defaultUnit);
                } else {
                    // Se não houver unidades no banco, mostrar mensagem
                    console.log('Nenhuma unidade encontrada no banco para este alimento');
                    showNoUnitsMessage();
                }
            })
            .catch(error => {
                console.error('Erro ao carregar unidades:', error);
                showNoUnitsMessage();
            });
    }

    function showNoUnitsMessage() {
        selectedFoodContainer.innerHTML = `
            <h3>${currentSelectedFoodData.name} ${currentSelectedFoodData.brand && currentSelectedFoodData.brand.toUpperCase() !== 'TACO' ? `(${currentSelectedFoodData.brand})` : ''}</h3>
            <div class="no-units-message">
                <p>⚠️ Este alimento ainda não foi classificado pelas estagiárias.</p>
                <p>Unidades de medida não disponíveis.</p>
                <p>Peça para uma estagiária classificar este alimento no painel administrativo.</p>
            </div>
            <div class="form-actions-details">
                <button type="button" id="cancel-add-food-item-btn" class="btn-icon-cancel" aria-label="Cancelar"><i class="fas fa-times"></i></button>
            </div>
        `;
        
        document.getElementById('cancel-add-food-item-btn').addEventListener('click', () => {
            selectedFoodContainer.classList.remove('visible');
            setTimeout(() => {
                selectedFoodContainer.innerHTML = '';
                selectedFoodContainer.style.display = 'none';
            }, 400);
        });
        
        resultsContainer.style.display = 'none';
        searchInput.value = '';
        selectedFoodContainer.style.display = 'block';
        setTimeout(() => selectedFoodContainer.classList.add('visible'), 10);
    }

    function updateFoodDisplay(unitOptionsHTML, defaultUnit) {
        selectedFoodContainer.innerHTML = `
            <h3>${currentSelectedFoodData.name} ${currentSelectedFoodData.brand && currentSelectedFoodData.brand.toUpperCase() !== 'TACO' ? `(${currentSelectedFoodData.brand})` : ''}</h3>
            <div class="quantity-unit-row">
                <div class="form-group">
                    <label for="food-quantity">Quantidade</label>
                    <input type="number" id="food-quantity" class="form-control" value="100" min="1" step="any">
                </div>
                <div class="form-group">
                    <label for="food-unit">Medida</label>
                    <select id="food-unit" class="form-control">${unitOptionsHTML}</select>
                </div>
            </div>
            <div class="macros-preview">
                <p>Calorias <span><span id="macro-kcal">0</span> kcal</span></p>
                <p>Carboidratos <span><span id="macro-carbs">0</span> g</span></p>
                <p>Proteínas <span><span id="macro-protein">0</span> g</span></p>
                <p>Gorduras <span><span id="macro-fat">0</span> g</span></p>
            </div>
            <div class="form-actions-details">
                <button type="button" id="add-food-item-to-meal-btn" class="btn btn-primary">Adicionar Alimento</button>
                <button type="button" id="cancel-add-food-item-btn" class="btn-icon-cancel" aria-label="Cancelar"><i class="fas fa-times"></i></button>
            </div>
        `;
        
        document.getElementById('food-quantity').addEventListener('input', updateMacrosPreview);
        document.getElementById('food-unit').addEventListener('change', updateMacrosPreview);
        document.getElementById('add-food-item-to-meal-btn').addEventListener('click', addFoodToMeal);
        document.getElementById('cancel-add-food-item-btn').addEventListener('click', () => {
            selectedFoodContainer.classList.remove('visible');
            setTimeout(() => {
                selectedFoodContainer.innerHTML = '';
                selectedFoodContainer.style.display = 'none';
            }, 400);
        });
        
        resultsContainer.style.display = 'none';
        searchInput.value = '';
        updateMacrosPreview();

        selectedFoodContainer.style.display = 'block';
        setTimeout(() => selectedFoodContainer.classList.add('visible'), 10);
    }

    function updateMacrosPreview() {
        if (!currentSelectedFoodData) return;
        const quantityInput = document.getElementById('food-quantity');
        const unitSelect = document.getElementById('food-unit');
        if (!quantityInput || !unitSelect) return;

        const quantity = parseFloat(quantityInput.value) || 0;
        const unitKey = unitSelect.value;
        
        const conversionFactor = unitConversions[unitKey] || 1;
        const totalBaseAmount = quantity * conversionFactor;
        const nutrientFactor = totalBaseAmount / 100;

        document.getElementById('macro-kcal').textContent = Math.round((currentSelectedFoodData.kcal_100g || 0) * nutrientFactor);
        document.getElementById('macro-protein').textContent = ((currentSelectedFoodData.protein_100g || 0) * nutrientFactor).toFixed(1);
        document.getElementById('macro-carbs').textContent = ((currentSelectedFoodData.carbs_100g || 0) * nutrientFactor).toFixed(1);
        document.getElementById('macro-fat').textContent = ((currentSelectedFoodData.fat_100g || 0) * nutrientFactor).toFixed(1);
    }
    
    function addFoodToMeal() {
        if (!currentSelectedFoodData) return;
        const quantityInput = document.getElementById('food-quantity');
        const unitSelect = document.getElementById('food-unit');
        
        const quantity = parseFloat(quantityInput.value) || 0;
        if (quantity <= 0) {
            alert("Por favor, insira uma quantidade válida.");
            return;
        }

        mealItems.push({
            id: currentSelectedFoodData.id,
            name: currentSelectedFoodData.name,
            brand: currentSelectedFoodData.brand,
            quantity: quantity,
            unit: unitSelect.options[unitSelect.selectedIndex].text,
            kcal: parseFloat(document.getElementById('macro-kcal').textContent),
            protein: parseFloat(document.getElementById('macro-protein').textContent),
            carbs: parseFloat(document.getElementById('macro-carbs').textContent),
            fat: parseFloat(document.getElementById('macro-fat').textContent)
        });

        renderCurrentMealList();
        selectedFoodContainer.classList.remove('visible');
        setTimeout(() => {
            selectedFoodContainer.innerHTML = '';
            selectedFoodContainer.style.display = 'none';
        }, 400);
    }
    
    function renderCurrentMealList() {
        if (!currentMealListUL) return;
        currentMealListUL.innerHTML = '';
        let totalKcal = 0;

        if (mealItems.length === 0) {
            currentMealListUL.innerHTML = '<li class="empty-meal-placeholder">Nenhum alimento adicionado ainda.</li>';
        } else {
            mealItems.forEach((item, index) => {
                const listItem = document.createElement('li');
                listItem.innerHTML = `
                    <div class="meal-item-info">
                        <span class="meal-item-name">${item.name}</span>
                        <span class="meal-item-details">${item.quantity} ${item.unit}</span>
                    </div>
                    <div class="meal-item-calories">
                        <span>${Math.round(item.kcal)}</span>kcal
                    </div>
                    <button type="button" class="btn-remove-item" data-index="${index}" aria-label="Remover item">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                currentMealListUL.appendChild(listItem);
                totalKcal += isNaN(item.kcal) ? 0 : item.kcal;
            });
        }

        if (currentMealTotalKcalSpan) {
            currentMealTotalKcalSpan.textContent = Math.round(totalKcal);
        }
        document.getElementById('save-entire-meal-btn').disabled = (mealItems.length === 0);
    }
    
    function performFoodSearch() {
        const term = searchInput.value.trim();
        if (term.length < 2) {
            resultsContainer.style.display = 'none';
            return;
        }

        resultsContainer.innerHTML = '<p class="loading-results">Buscando...</p>';
        resultsContainer.style.display = 'block';
        selectedFoodContainer.classList.remove('visible');

        if (typeof BASE_APP_URL === 'undefined') {
            console.error("Variável global BASE_APP_URL não foi encontrada.");
            resultsContainer.innerHTML = '<p class="error-results">Erro de configuração.</p>';
            return;
        }

        const apiUrl = `${BASE_APP_URL}/api/ajax_search_food.php?term=${encodeURIComponent(term)}`;
        
        fetch(apiUrl)
            .then(response => {
                if (!response.ok) throw new Error(`Erro de rede: ${response.status}`);
                return response.json();
            })
            .then(data => {
                resultsContainer.innerHTML = '';
                if (data.success && data.data.length > 0) {
                    const ul = document.createElement('ul');
                    ul.className = 'search-results-list';
                    data.data.forEach(food => {
                        const li = document.createElement('li');
                        li.textContent = `${food.name} ${food.brand && food.brand.toUpperCase() !== 'TACO' ? `(${food.brand})` : ''}`;
                        li.dataset.foodData = JSON.stringify(food);
                        ul.appendChild(li);
                    });
                    resultsContainer.appendChild(ul);
                } else {
                    resultsContainer.innerHTML = `<p class="no-results">${data.message || 'Nenhum alimento encontrado.'}</p>`;
                }
            })
            .catch(error => {
                console.error('Erro na busca de alimentos:', error);
                resultsContainer.innerHTML = '<p class="error-results">Não foi possível buscar. Tente novamente.</p>';
            });
    }
    
    // --- LÓGICA DO SCANNER UNIFICADA ---

    async function lookupBarcodeOnOpenFoodFacts(barcode) {
        const notify = window.showAppNotification;
        if (notify) notify('Buscando produto...', 'info');
        else if(scannerStatus) scannerStatus.textContent = 'Buscando produto...';

        try {
            const response = await fetch(`https://world.openfoodfacts.org/api/v2/product/${barcode}.json`);

            if (response.status === 404) {
                if (notify) notify('Este produto não foi encontrado.', 'warning'); else alert('Este produto não foi encontrado.');
                if (notFoundModal) notFoundModal.classList.add('is-visible');
                return;
            }
            
            if (!response.ok) {
                throw new Error(`Erro de rede: ${response.status}`);
            }
            
            const data = await response.json();

            if (data.status === 1 && data.product && data.product.product_name) {
                const p = data.product;
                const n = p.nutriments || {};
                const k = n['energy-kcal_100g'] ?? (n['energy_100g'] ? n['energy_100g'] / 4.184 : null);
                const foodData = {
                    id: 'off_' + (p.code || barcode), name: p.product_name_pt || p.product_name, brand: p.brands || 'N/A',
                    measure_type: 'weight', serving_unit_default: 'g', kcal_100g: k ? Math.round(k) : 0,
                    protein_100g: n.proteins_100g || 0, carbs_100g: n.carbohydrates_100g || 0, fat_100g: n.fat_100g || 0,
                };
                handleFoodSelection(foodData);
            } else {
                if (notify) notify('Este produto não foi encontrado.', 'warning'); else alert('Este produto não foi encontrado.');
                if (notFoundModal) notFoundModal.classList.add('is-visible');
            }
        } catch (error) {
            console.error('Erro na API OpenFoodFacts:', error);
            if (notify) notify(error.message, 'error'); else alert("Não foi possível buscar o produto. Verifique a conexão.");
        }
    }

    // =========================================================================
    // FUNÇÃO DO SCANNER NATIVO (CAPACITOR + ML KIT) - VERSÃO FINAL ROBUSTA
    // =========================================================================

    // Variável global para evitar múltiplas instâncias do listener
    let nativeScanListener = null;

    // =========================================================================
    // FUNÇÃO DO SCANNER NATIVO (VERSÃO FINAL E LIMPA)
    // =========================================================================
    async function startNativeScanner() {
        const notify = window.showAppNotification;

        if (!window.Capacitor || !window.Capacitor.isNativePlatform() || !window.Capacitor.Plugins.BarcodeScanner) {
            notify('Função de scanner indisponível.', 'error');
            return;
        }

        const { BarcodeScanner } = window.Capacitor.Plugins;
        const { Camera } = window.Capacitor.Plugins;

        try {
            const permissionStatus = await Camera.requestPermissions();
            if (permissionStatus.camera !== 'granted') {
                notify('A permissão da câmera é necessária para escanear.', 'error');
                return;
            }
            
            const result = await BarcodeScanner.startScan();

            if (result && result.hasContent) {
                lookupBarcodeOnOpenFoodFacts(result.content);
            } else {
                notify('Scan cancelado.', 'info');
            }

        } catch (error) {
            if (error.message.toLowerCase().includes('cancelled')) {
                notify('Scan cancelado pelo usuário.', 'info');
            } else {
                notify(`ERRO: ${error.message}`, 'error');
            }
        }
    }

    function onScanSuccess(decodedText) {
        closeScannerModal();
        lookupBarcodeOnOpenFoodFacts(decodedText);
    }

    function startScanner() {
        if (typeof Quagga === 'undefined') {
            if (scannerStatus) scannerStatus.textContent = 'Erro: Biblioteca não carregada.';
            return;
        }

        Quagga.init({
            inputStream: { name: "Live", type: "LiveStream", target: scannerContainer, constraints: { width: 640, height: 480, facingMode: "environment" } },
            decoder: { readers: ["ean_reader"] },
            locate: true,
            numOfWorkers: navigator.hardwareConcurrency || 2,
        }, (err) => {
            if (err) { console.error("Erro ao iniciar Quagga:", err); if (scannerStatus) scannerStatus.textContent = 'Erro ao acessar câmera.'; return; }
            Quagga.start();
            quaggaScannerInitialized = true;
            if (scannerStatus) scannerStatus.textContent = "Aponte para o código de barras";
        });

        Quagga.onDetected(result => {
            if (!quaggaScannerInitialized) return;
            const code = result.codeResult.code;
            if (code && (code.length === 12 || code.length === 13)) {
                Quagga.stop();
                quaggaScannerInitialized = false;
                onScanSuccess(code);
            }
        });

        Quagga.onProcessed(result => {
            if (!quaggaScannerInitialized) return;
            const drawingCtx = Quagga.canvas.ctx.overlay;
            const drawingCanvas = Quagga.canvas.dom.overlay;
            drawingCtx.clearRect(0, 0, parseInt(drawingCanvas.getAttribute("width")), parseInt(drawingCanvas.getAttribute("height")));
            if (result && result.box) {
                Quagga.ImageDebug.drawPath(result.box, { x: 0, y: 1 }, drawingCtx, { color: "#4CAF50", lineWidth: 4 });
            }
        });
    }

    function stopScanner() {
        if (typeof Quagga !== 'undefined' && quaggaScannerInitialized) {
            Quagga.stop();
            quaggaScannerInitialized = false;
        }
    }

    function openScannerModal() {
        if (scannerModal) {
            scannerModal.classList.add('is-visible');
            startScanner();
        }
    }

    function closeScannerModal() {
        if (scannerModal) {
            scannerModal.classList.remove('is-visible');
            stopScanner();
        }
    }
    
    function createFileInput() {
        if (fileInputForNutrition) return;
        fileInputForNutrition = document.createElement('input');
        fileInputForNutrition.type = 'file';
        fileInputForNutrition.accept = 'image/*';
        fileInputForNutrition.style.display = 'none';
        document.body.appendChild(fileInputForNutrition);
        fileInputForNutrition.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) handleImageUpload(file);
        });
    }
    
    function handleImageUpload(file) {
        const loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'loading-overlay-fullscreen';
        loadingOverlay.innerHTML = '<div class="loader"></div><p>Analisando imagem...</p>';
        document.body.appendChild(loadingOverlay);
        if (notFoundModal) notFoundModal.classList.remove('is-visible');
        setTimeout(() => { loadingOverlay.remove(); alert("Análise da imagem concluída (simulação)!"); }, 4000);
    }

    // --- EVENT LISTENERS GERAIS ---
    logDateDisplay?.addEventListener('change', () => { if (logDateHidden) logDateHidden.value = logDateDisplay.value; });
    logMealTypeDisplay?.addEventListener('change', () => { if (logMealTypeHidden) logMealTypeHidden.value = logMealTypeDisplay.value; });
    searchInput?.addEventListener('input', () => { clearTimeout(searchDebounceTimer); searchDebounceTimer = setTimeout(performFoodSearch, 400); });
    resultsContainer?.addEventListener('click', (e) => { const li = e.target.closest('li'); if (li?.dataset.foodData) handleFoodSelection(JSON.parse(li.dataset.foodData)); });
    currentMealListUL?.addEventListener('click', (e) => { const removeBtn = e.target.closest('.btn-remove-item'); if (removeBtn) { mealItems.splice(parseInt(removeBtn.dataset.index, 10), 1); renderCurrentMealList(); } });
    logEntireMealForm?.addEventListener('submit', (e) => { if (mealItems.length === 0) { e.preventDefault(); alert('Adicione pelo menos um alimento.'); return; } let hiddenInput = logEntireMealForm.querySelector('input[name="meal_items_json"]'); if (!hiddenInput) { hiddenInput = document.createElement('input'); hiddenInput.type = 'hidden'; hiddenInput.name = 'meal_items_json'; logEntireMealForm.appendChild(hiddenInput); } hiddenInput.value = JSON.stringify(mealItems); });
    
    scanBarcodeBtn?.addEventListener('click', () => {
        if (window.Capacitor && window.Capacitor.isNativePlatform()) {
            startNativeScanner();
        } else {
            openScannerModal();
        }
    });

    closeScannerModalBtn?.addEventListener('click', closeScannerModal);
    scannerModal?.addEventListener('click', (e) => { if (e.target === scannerModal) closeScannerModal(); });
    
    closeNotFoundModalBtn?.addEventListener('click', () => { if (notFoundModal) notFoundModal.classList.remove('is-visible'); });
    
    createFileInput();
    takeNutritionPhotoBtn?.addEventListener('click', () => { if (fileInputForNutrition) { fileInputForNutrition.setAttribute('capture', 'environment'); fileInputForNutrition.click(); } });
    chooseNutritionPhotoBtn?.addEventListener('click', () => { if (fileInputForNutrition) { fileInputForNutrition.removeAttribute('capture'); fileInputForNutrition.click(); } });

    // Inicialização da página
    renderCurrentMealList();
});