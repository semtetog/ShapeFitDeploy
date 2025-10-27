<?php
// public_html/shapefit/create_custom_food.php

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

// --- LÓGICA PARA PRÉ-PREENCHER O FORMULÁRIO COM DADOS DA URL (DO OCR) ---
$kcal_prefill = isset($_GET['kcal_100g']) ? htmlspecialchars($_GET['kcal_100g']) : '';
$protein_prefill = isset($_GET['protein_100g']) ? htmlspecialchars($_GET['protein_100g']) : '';
$carbs_prefill = isset($_GET['carbs_100g']) ? htmlspecialchars($_GET['carbs_100g']) : '';
$fat_prefill = isset($_GET['fat_100g']) ? htmlspecialchars($_GET['fat_100g']) : '';
$food_name_prefill = isset($_GET['food_name']) ? htmlspecialchars($_GET['food_name']) : '';
$brand_name_prefill = isset($_GET['brand_name']) ? htmlspecialchars($_GET['brand_name']) : '';


// --- VARIÁVEIS PARA O TEMPLATE ---
$page_title = "Cadastrar Novo Alimento";
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* === CREATE CUSTOM FOOD PAGE - ESTILO MODERNO E MOBILE === */
.page-header {
    display: flex;
    align-items: center;
    padding: 16px 0;
    background: transparent;
    position: sticky;
    top: 0;
    z-index: 100;
    gap: 16px;
    margin-bottom: 20px;
}

.back-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.2s ease;
}

.back-btn:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.page-title {
    flex: 1;
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.custom-food-form {
    padding: 0 0 80px 0;
    max-width: 100%;
    margin: 0;
}

.form-card {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 16px;
}

.form-section-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--accent-orange);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0 0 16px 0;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.form-group {
    margin-bottom: 16px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 6px;
}

.form-control {
    width: 100%;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 12px 14px;
    color: var(--text-primary);
    font-size: 14px;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.3);
}

.form-divider {
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    margin: 20px 0;
}

.form-actions {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 16px 24px;
    padding-bottom: calc(16px + env(safe-area-inset-bottom));
    background: rgba(26, 26, 26, 0.95);
    backdrop-filter: blur(10px);
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    z-index: 1000;
    max-width: 480px;
    margin: 0 auto;
}

.btn-primary {
    width: 100%;
    height: 48px;
    border-radius: 12px;
    background: var(--accent-orange);
    border: none;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-primary:hover {
    background: #ff7a1a;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(255, 107, 0, 0.3);
}

.btn-primary:active {
    transform: translateY(0);
}

.nutrition-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.barcode-info {
    background: rgba(255, 193, 7, 0.1);
    border: 1px solid rgba(255, 193, 7, 0.3);
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.barcode-info i {
    font-size: 24px;
    color: var(--accent-orange);
}

.barcode-info-text {
    flex: 1;
}

.barcode-info-text strong {
    display: block;
    color: var(--text-primary);
    font-size: 13px;
    margin-bottom: 4px;
}

.barcode-info-text span {
    color: var(--text-secondary);
    font-size: 12px;
}

@media (max-width: 768px) {
    .custom-food-form {
        padding: 16px;
    }
    
    .form-card {
        padding: 16px;
    }
    
    .nutrition-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="app-container">
    <!-- Header -->
    <div class="page-header">
        <a href="javascript:history.back()" class="back-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <h1 class="page-title">Cadastrar Alimento</h1>
    </div>

    <!-- Form -->
    <form action="<?php echo BASE_APP_URL; ?>/process_save_custom_food.php" method="POST" class="custom-food-form">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <?php if (!empty($barcode = $_GET['barcode'] ?? '')): ?>
            <input type="hidden" name="barcode" value="<?php echo htmlspecialchars($barcode); ?>">
        <?php endif; ?>
        
        <!-- Informação sobre código de barras -->
        <?php if (!empty($barcode)): ?>
        <div class="barcode-info">
            <i class="fas fa-barcode"></i>
            <div class="barcode-info-text">
                <strong>Código de Barras Detectado</strong>
                <span><?php echo htmlspecialchars($barcode); ?></span>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Informações Básicas -->
        <div class="form-card">
            <h3 class="form-section-title">Informações Básicas</h3>
            
            <div class="form-group">
                <label for="food_name">Nome do Alimento *</label>
                <input 
                    type="text" 
                    id="food_name" 
                    name="food_name" 
                    class="form-control" 
                    placeholder="Ex: Pão de Forma Integral" 
                    value="<?php echo $food_name_prefill; ?>" 
                    required>
            </div>
            
            <div class="form-group">
                <label for="brand_name">Marca (opcional)</label>
                <input 
                    type="text" 
                    id="brand_name" 
                    name="brand_name" 
                    class="form-control" 
                    placeholder="Ex: Wickbold" 
                    value="<?php echo $brand_name_prefill; ?>">
            </div>
        </div>
        
        <!-- Informações Nutricionais -->
        <div class="form-card">
            <h3 class="form-section-title">Informação Nutricional (por 100g)</h3>
            
            <div class="nutrition-grid">
                <div class="form-group">
                    <label for="kcal_100g">Calorias (kcal) *</label>
                    <input 
                        type="number" 
                        id="kcal_100g" 
                        name="kcal_100g" 
                        class="form-control" 
                        placeholder="Ex: 250" 
                        value="<?php echo $kcal_prefill; ?>" 
                        required 
                        step="0.1"
                        min="0">
                </div>
                
                <div class="form-group">
                    <label for="protein_100g">Proteínas (g) *</label>
                    <input 
                        type="number" 
                        id="protein_100g" 
                        name="protein_100g" 
                        class="form-control" 
                        placeholder="Ex: 8.5" 
                        value="<?php echo $protein_prefill; ?>" 
                        required 
                        step="0.1"
                        min="0">
                </div>
                
                <div class="form-group">
                    <label for="carbs_100g">Carboidratos (g) *</label>
                    <input 
                        type="number" 
                        id="carbs_100g" 
                        name="carbs_100g" 
                        class="form-control" 
                        placeholder="Ex: 45.2" 
                        value="<?php echo $carbs_prefill; ?>" 
                        required 
                        step="0.1"
                        min="0">
                </div>
                
                <div class="form-group">
                    <label for="fat_100g">Gorduras (g) *</label>
                    <input 
                        type="number" 
                        id="fat_100g" 
                        name="fat_100g" 
                        class="form-control" 
                        placeholder="Ex: 4.1" 
                        value="<?php echo $fat_prefill; ?>" 
                        required 
                        step="0.1"
                        min="0">
                </div>
            </div>
        </div>
        
        <!-- Botão de Salvar -->
        <div class="form-actions">
            <button type="submit" class="btn-primary">
                <i class="fas fa-check"></i>
                Salvar Alimento
            </button>
        </div>
    </form>
</div>

<?php
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>