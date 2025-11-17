<?php
// scan_barcode.php - Scanner de código de barras com câmera e integração Open Food Facts
require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();

$page_title = "Escanear Código de Barras";
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* === BARCODE SCANNER PAGE === */
.scanner-container {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: transparent;
    padding: env(safe-area-inset-top) 24px 0 24px;
}

.scanner-header {
    display: flex;
    align-items: center;
    padding: 16px 0;
    background: transparent;
    position: sticky;
    top: 0;
    z-index: 100;
    margin-bottom: 16px;
}


.scanner-title {
    flex: 1;
    text-align: center;
    font-size: 18px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.camera-container {
    flex: 1;
    position: relative;
    background: #000;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
    margin-bottom: 16px;
    min-height: 300px;
}

#camera-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.scanning-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}

.scan-frame {
    width: 280px;
    height: 180px;
    border: 3px solid var(--accent-orange);
    border-radius: 16px;
    position: relative;
    box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
}

.scan-line {
    position: absolute;
    width: 100%;
    height: 3px;
    background: var(--accent-orange);
    box-shadow: 0 0 10px var(--accent-orange);
    animation: scan 2s ease-in-out infinite;
}

@keyframes scan {
    0%, 100% { top: 0; }
    50% { top: calc(100% - 3px); }
}

.scan-instruction {
    margin-top: 24px;
    background: rgba(0, 0, 0, 0.7);
    padding: 12px 24px;
    border-radius: 12px;
    color: #fff;
    font-size: 14px;
    text-align: center;
}

.scanner-controls {
    padding: 0;
    background: transparent;
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 16px;
}

.manual-input-section {
    background: rgba(255, 255, 255, 0.04);
    border-radius: 16px;
    padding: 16px;
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.manual-input-label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-bottom: 8px;
    display: block;
}

.manual-input-row {
    display: flex;
    gap: 8px;
}

.manual-barcode-input {
    flex: 1;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px;
    padding: 10px 12px;
    color: var(--text-primary);
    font-size: 14px;
}

.manual-barcode-input:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

.search-btn {
    background: var(--accent-orange);
    border: none;
    border-radius: 8px;
    padding: 10px 20px;
    color: white;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.search-btn:hover {
    background: #ff7a1a;
}

.search-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.action-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
}

.action-btn {
    padding: 12px 16px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
    text-align: center;
    flex-direction: column;
}

.action-btn:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.action-btn i {
    font-size: 16px;
}

/* Loading overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-overlay.active {
    display: flex;
}

.loading-content {
    text-align: center;
    color: #fff;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 4px solid rgba(255, 255, 255, 0.2);
    border-top-color: var(--accent-orange);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.camera-error {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-secondary);
}

.camera-error i {
    font-size: 48px;
    color: var(--accent-orange);
    margin-bottom: 16px;
}

.camera-error h3 {
    font-size: 18px;
    color: var(--text-primary);
    margin: 0 0 8px 0;
}

.camera-error p {
    font-size: 14px;
    margin: 0;
}
</style>

<div class="scanner-container">
    <!-- Header -->
    <div class="scanner-header">
        <h1 class="scanner-title">Escanear Código de Barras</h1>
    </div>

    <!-- Camera Container -->
    <div class="camera-container" id="camera-container">
        <video id="camera-video" autoplay playsinline></video>
        <div class="scanning-overlay">
            <div class="scan-frame">
                <div class="scan-line"></div>
            </div>
            <div class="scan-instruction">
                Aponte a câmera para o código de barras
            </div>
        </div>
    </div>

    <!-- Controls -->
    <div class="scanner-controls">
        <div class="manual-input-section">
            <label class="manual-input-label">Ou digite o código manualmente:</label>
            <div class="manual-input-row">
                <input 
                    type="text" 
                    id="manual-barcode-input" 
                    class="manual-barcode-input" 
                    placeholder="Ex: 7891234567890"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    readonly>
                <button class="search-btn" onclick="searchManualBarcode()">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>

        <div class="action-buttons">
            <a href="<?php echo BASE_APP_URL; ?>/create_custom_food.php" class="action-btn">
                <i class="fas fa-plus"></i>
                Cadastrar Manual
            </a>
            <a href="<?php echo BASE_APP_URL; ?>/add_food_to_diary.php" class="action-btn">
                <i class="fas fa-arrow-left"></i>
                Voltar
            </a>
        </div>
    </div>
</div>

<!-- Modal de Produto Não Encontrado -->
<div id="product-not-found-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <h3>Produto não encontrado</h3>
        <p>Este produto não está na nossa base de dados. Deseja cadastrá-lo manualmente?</p>
        <div class="modal-actions">
            <button class="btn-secondary" onclick="closeProductNotFoundModal()">Fechar</button>
            <button class="btn-primary" onclick="registerManually()">Cadastrar Manual</button>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loading-overlay">
    <div class="loading-content">
        <div class="spinner"></div>
        <p>Buscando produto...</p>
    </div>
</div>

<script src="https://unpkg.com/@zxing/library@latest"></script>
<script>
let codeReader = null;
let selectedDeviceId = null;
let scanning = false;

// Inicializar scanner ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    initializeScanner();
    
    // Remover readonly quando clicar no input
    const manualInput = document.getElementById('manual-barcode-input');
    manualInput.addEventListener('click', function() {
        this.removeAttribute('readonly');
        this.focus();
    });
    
    // Adicionar readonly novamente quando perder o foco (se estiver vazio)
    manualInput.addEventListener('blur', function() {
        if (this.value.trim() === '') {
            this.setAttribute('readonly', 'readonly');
        }
    });
});

async function initializeScanner() {
    try {
        codeReader = new ZXing.BrowserMultiFormatReader();
        
        // Solicitar permissão e obter câmera traseira
        const videoInputDevices = await codeReader.listVideoInputDevices();
        
        if (videoInputDevices.length === 0) {
            showCameraError('Nenhuma câmera encontrada no dispositivo.');
            return;
        }

        // Tentar usar câmera traseira (environment) se disponível
        selectedDeviceId = videoInputDevices[0].deviceId;
        for (const device of videoInputDevices) {
            if (device.label.toLowerCase().includes('back') || device.label.toLowerCase().includes('traseira')) {
                selectedDeviceId = device.deviceId;
                break;
            }
        }

        startScanning();
    } catch (err) {
        console.error('Erro ao inicializar scanner:', err);
        showCameraError('Não foi possível acessar a câmera. Verifique as permissões.');
    }
}

function startScanning() {
    if (scanning) return;
    scanning = true;

    const videoElement = document.getElementById('camera-video');
    
    codeReader.decodeFromVideoDevice(selectedDeviceId, videoElement, (result, err) => {
        if (result) {
            // Código de barras detectado!
            const barcode = result.text;
            console.log('Código detectado:', barcode);
            
            // Parar scanning temporariamente
            scanning = false;
            
            // Buscar produto
            searchBarcode(barcode);
        }
        
        if (err && !(err instanceof ZXing.NotFoundException)) {
            console.error('Erro no scanner:', err);
        }
    });
}

function showCameraError(message) {
    const container = document.getElementById('camera-container');
    container.innerHTML = `
        <div class="camera-error">
            <i class="fas fa-camera-slash"></i>
            <h3>Câmera Indisponível</h3>
            <p>${message}</p>
        </div>
    `;
}

async function searchBarcode(barcode) {
    // Mostrar loading
    document.getElementById('loading-overlay').classList.add('active');
    
    try {
        const response = await fetch(`<?php echo BASE_APP_URL; ?>/ajax_lookup_barcode.php?barcode=${encodeURIComponent(barcode)}`);
        const data = await response.json();
        
        // Esconder loading
        document.getElementById('loading-overlay').classList.remove('active');
        
        if (data.success) {
            // Produto encontrado! Redirecionar para criar/editar
            const product = data.data;
            const params = new URLSearchParams({
                food_name: product.name || '',
                brand_name: product.brand || '',
                kcal_100g: product.kcal_100g || '',
                protein_100g: product.protein_100g || '',
                carbs_100g: product.carbs_100g || '',
                fat_100g: product.fat_100g || '',
                barcode: barcode
            });
            
            window.location.href = `<?php echo BASE_APP_URL; ?>/create_custom_food.php?${params.toString()}`;
        } else {
            // Produto não encontrado - mostrar modal em vez de alert
            showProductNotFoundModal(barcode);
        }
    } catch (error) {
        console.error('Erro ao buscar produto:', error);
        document.getElementById('loading-overlay').classList.remove('active');
        showProductNotFoundModal(barcode);
    }
}

function searchManualBarcode() {
    const input = document.getElementById('manual-barcode-input');
    const barcode = input.value.trim();
    
    if (!barcode) {
        alert('Por favor, digite um código de barras.');
        return;
    }
    
    if (!/^\d+$/.test(barcode)) {
        alert('Código de barras inválido. Use apenas números.');
        return;
    }
    
    searchBarcode(barcode);
}

// Permitir buscar ao pressionar Enter
document.getElementById('manual-barcode-input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchManualBarcode();
    }
});

// Limpar recursos ao sair da página
window.addEventListener('beforeunload', function() {
    if (codeReader) {
        codeReader.reset();
    }
});

// Função para mostrar modal de produto não encontrado
function showProductNotFoundModal(barcode) {
    const modal = document.getElementById('product-not-found-modal');
    const barcodeInput = document.getElementById('manual-barcode-input');
    
    // Preencher o input com o código escaneado
    barcodeInput.value = barcode;
    
    // Mostrar modal
    modal.style.display = 'flex';
}

// Função para fechar modal
function closeProductNotFoundModal() {
    document.getElementById('product-not-found-modal').style.display = 'none';
}

// Função para cadastrar manualmente
function registerManually() {
    const barcode = document.getElementById('manual-barcode-input').value;
    if (barcode) {
        window.location.href = `<?php echo BASE_APP_URL; ?>/create_custom_food.php?barcode=${barcode}`;
    } else {
        window.location.href = `<?php echo BASE_APP_URL; ?>/create_custom_food.php`;
    }
}
</script>

<?php require_once APP_ROOT_PATH . '/includes/layout_footer.php'; ?>

