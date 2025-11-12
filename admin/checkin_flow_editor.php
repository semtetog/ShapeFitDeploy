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

// Buscar distribuições existentes
$distribution_query = "SELECT target_type, target_id FROM sf_checkin_distribution WHERE config_id = ?";
$stmt_dist = $conn->prepare($distribution_query);
$stmt_dist->bind_param("i", $checkin_id);
$stmt_dist->execute();
$dist_result = $stmt_dist->get_result();
$distribution = ['groups' => [], 'users' => []];
while ($d = $dist_result->fetch_assoc()) {
    if ($d['target_type'] === 'group') {
        $distribution['groups'][] = (int)$d['target_id'];
    } else {
        $distribution['users'][] = (int)$d['target_id'];
    }
}
$stmt_dist->close();

// Buscar grupos e usuários para distribuição
$groups_query = "SELECT id, group_name as name FROM sf_user_groups WHERE admin_id = ? AND is_active = 1 ORDER BY group_name";
$stmt_groups = $conn->prepare($groups_query);
$stmt_groups->bind_param("i", $admin_id);
$stmt_groups->execute();
$groups_result = $stmt_groups->get_result();
$groups = $groups_result->fetch_all(MYSQLI_ASSOC);
$stmt_groups->close();

$users_query = "SELECT u.id, u.name, u.email, up.profile_image_filename 
                FROM sf_users u 
                LEFT JOIN sf_user_profiles up ON u.id = up.user_id 
                WHERE u.onboarding_complete = 1
                ORDER BY u.name";
$users_result = $conn->query($users_query);
$users = $users_result->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<style>
:root {
    --accent-orange: #FF6B00;
    --text-primary: #F5F5F5;
    --text-secondary: #A3A3A3;
    --glass-border: rgba(255, 255, 255, 0.1);
    
    --sidebar-width: 256px;
    /* O content-wrapper tem padding: 1rem 2rem (vertical horizontal) */
    --content-wrapper-padding-h: 2rem;
    --content-wrapper-padding-v: 1rem;
    --gap-size: 1.5rem;
    --mockup-width: 410px;
    --mockup-height: calc(100vh - (var(--content-wrapper-padding-v) * 2));
}

/* ========================================================================= */
/* LAYOUT PRINCIPAL - REFATORADO COMPLETAMENTE */
/* ========================================================================= */

/* Container principal - layout simples e direto */
.checkin-flow-editor {
    display: block;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow-x: visible;
    overflow-y: visible;
    margin: 0;
    padding: 0;
    position: relative;
}

/* PAINEL DO CELULAR (FIXO À ESQUERDA) */
.mobile-mockup-panel {
    position: fixed;
    /* Alinhar topo com o padding do content-wrapper */
    top: var(--content-wrapper-padding-v);
    /* Celular posicionado após sidebar + padding horizontal do content-wrapper */
    left: calc(var(--sidebar-width) + var(--content-wrapper-padding-h));
    width: var(--mockup-width);
    height: var(--mockup-height);
    max-height: 820px;
    z-index: 10;
    margin: 0;
    transform: none;
}

/* Estilos base - zoom 110% (referência perfeita) */
/* Os ajustes para 100% e 125% serão feitos via JavaScript */

.mobile-mockup-wrapper {
    width: 100%;
    height: 100%;
    padding: 12px;
    background: #1a1a1a;
    border-radius: 40px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
    border: 1px solid var(--glass-border);
}

.mobile-screen {
    width: 100%;
    height: 100%;
    background: #121212;
    border-radius: 28px;
    overflow: hidden;
    position: relative;
}

#checkin-preview-frame {
    width: 100%;
    height: 100%;
    border: none;
}

/* PAINEL DE CONFIGURAÇÕES (DIREITA) */
.config-panel {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    /* O painel começa após o celular fixed
       margin-left = largura do celular + gap entre celular e painel */
    margin-left: calc(var(--mockup-width) + var(--gap-size));
    /* Alinhar topo com o celular (mesmo padding do content-wrapper) */
    margin-top: var(--content-wrapper-padding-v);
    /* width se ajusta automaticamente, usando max-width para limitar
       Permite que o painel se reduza quando necessário, mas nunca ultrapasse a borda direita */
    width: 100%;
    max-width: calc(100vw - var(--sidebar-width) - var(--content-wrapper-padding-h) - var(--mockup-width) - var(--gap-size) - var(--content-wrapper-padding-h));
    min-width: 600px;
    box-sizing: border-box;
    overflow-x: visible;
    overflow-y: visible;
    word-wrap: break-word;
    /* Garantir que o painel nunca ultrapasse a borda direita */
    position: relative;
}

/* Garantir que elementos internos se ajustem ao tamanho do painel */
.config-panel > * {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    word-wrap: break-word;
    overflow-wrap: break-word;
    overflow-x: visible;
}

/* Header dentro do card de configurações */
.checkin-config-section .editor-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--glass-border);
    max-width: 100%;
    box-sizing: border-box;
    flex-wrap: wrap;
    gap: 1rem;
}

.checkin-config-section .editor-header:first-child {
    margin-top: 0;
    padding-top: 0;
}

.checkin-config-section .editor-header h1 {
    margin: 0;
    font-size: 1.5rem;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
}

.checkin-config-section .editor-header h1 i {
    color: var(--accent-orange);
    font-size: 1.25rem;
}

.checkin-config-section .header-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
    max-width: 100%;
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
    gap: 0.5rem;
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
    gap: 0.25rem;
    padding: 0.1875rem 0.375rem;
    border-radius: 4px;
    font-size: 0.625rem;
    font-weight: 600;
    letter-spacing: 0.2px;
    text-transform: uppercase;
    flex-shrink: 0;
    white-space: nowrap;
}

/* Cores diferentes para cada tipo de bloco */
.block-type-badge[data-type="text"],
.block-type-badge.text {
    background: rgba(59, 130, 246, 0.15);
    color: #3B82F6;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.block-type-badge[data-type="multiple_choice"],
.block-type-badge.multiple_choice {
    background: rgba(34, 197, 94, 0.15);
    color: #22C55E;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.block-type-badge[data-type="scale"],
.block-type-badge.scale {
    background: rgba(168, 85, 247, 0.15);
    color: #A855F7;
    border: 1px solid rgba(168, 85, 247, 0.3);
}

.block-type-badge i {
    font-size: 0.625rem;
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

/* Texto ao lado da tag no header */
.block-preview-text {
    color: var(--text-primary);
    font-size: 0.875rem;
    line-height: 1.4;
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-left: 0.5rem;
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

/* Seção de Configuração */
.checkin-config-section {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow-x: visible;
    overflow-y: visible;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.section-title {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 1.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-title i {
    color: var(--accent-orange);
    font-size: 1rem;
}

.config-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    overflow-x: visible;
    overflow-y: visible;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    width: 100%;
        max-width: 100%;
    box-sizing: border-box;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.form-group label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
}

.form-group .required {
    color: var(--accent-orange);
}

.form-group input[type="text"],
.form-group textarea {
    width: 100%;
    max-width: 100%;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 0.875rem;
    font-family: 'Montserrat', sans-serif;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

/* Custom Select (estilo igual ao recipes) */
.custom-select-wrapper {
    position: relative;
    width: 100%;
}

.custom-select-wrapper::after {
    content: '\f078';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    top: 50%;
    right: 1rem;
    transform: translateY(-50%);
    color: var(--text-secondary);
    pointer-events: none;
    font-size: 0.875rem;
    z-index: 1;
    transition: all 0.3s ease;
}

.custom-select-wrapper:focus-within::after {
    color: var(--accent-orange);
    transform: translateY(-50%) rotate(180deg);
}

.custom-select-wrapper select {
    width: 100%;
    padding: 0.875rem 1.25rem;
    padding-right: 2.75rem;
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 0.95rem;
    font-family: 'Montserrat', sans-serif;
    font-weight: 500;
    transition: all 0.3s ease;
    -webkit-appearance: none;
    appearance: none;
    cursor: pointer;
    box-sizing: border-box;
}

.custom-select-wrapper select:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 255, 255, 0.15);
}

.custom-select-wrapper select:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
}

.form-group input[type="text"]:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(255, 107, 0, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    font-weight: 500;
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

/* Toggle Switch - Interruptor Moderno */
.toggle-switch-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.toggle-switch-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-secondary);
    min-width: 50px;
    text-align: left;
    transition: color 0.3s ease;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
    cursor: pointer;
    flex-shrink: 0;
}

.toggle-switch-input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-switch-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #EF4444; /* Vermelho quando desativado */
    transition: all 0.3s ease;
    border-radius: 26px;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2);
}

.toggle-switch-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: all 0.3s ease;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* Quando está ativo (checked) - Verde */
.toggle-switch-input:checked + .toggle-switch-slider {
    background-color: #22C55E; /* Verde quando ativado */
    box-shadow: 0 0 8px rgba(34, 197, 94, 0.4);
}

.toggle-switch-input:checked + .toggle-switch-slider:before {
    transform: translateX(24px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
}

/* Hover effect */
.toggle-switch:hover .toggle-switch-slider {
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2), 0 0 12px rgba(255, 255, 255, 0.1);
}

.toggle-switch-input:checked:hover + .toggle-switch-slider {
    box-shadow: 0 0 12px rgba(34, 197, 94, 0.6);
}

.toggle-switch-input:not(:checked):hover + .toggle-switch-slider {
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.2), 0 0 12px rgba(239, 68, 68, 0.3);
}

/* Atualizar label quando está ativo */
.toggle-switch-input:checked ~ .toggle-switch-label,
.toggle-switch-wrapper:has(.toggle-switch-input:checked) .toggle-switch-label {
    color: #22C55E;
    font-weight: 700;
}

.toggle-switch-wrapper:has(.toggle-switch-input:not(:checked)) .toggle-switch-label {
    color: #EF4444;
}

/* Preview Settings Styling */
.preview-settings-grid {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 1.5rem;
    align-items: stretch;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.preview-setting-item {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
    justify-content: space-between;
    min-height: 140px;
}

.preview-setting-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    min-height: 28px;
    margin-bottom: 0.75rem;
}

/* Linha Divisória Vertical */
.preview-settings-divider {
    width: 1px;
    background: var(--glass-border);
    position: relative;
    margin: 0.5rem 0;
    align-self: stretch;
    min-height: 100px;
}

.preview-setting-header label {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.delay-value-display {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--accent-orange);
}

.delay-hint {
    color: var(--text-secondary);
    font-size: 0.75rem;
    font-weight: 400;
    font-style: italic;
}

/* Professional Slider Styling */
.slider-wrapper {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.5rem 0;
}

.delay-slider {
    -webkit-appearance: none;
    appearance: none;
    width: 100%;
    height: 6px;
    border-radius: 3px;
    outline: none;
    transition: all 0.3s ease;
    cursor: pointer;
    background: linear-gradient(to right, 
        rgba(255, 107, 0, 0.3) 0%, 
        rgba(255, 107, 0, 0.3) var(--slider-progress, 10%), 
        rgba(255, 255, 255, 0.1) var(--slider-progress, 10%), 
        rgba(255, 255, 255, 0.1) 100%);
}

.delay-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: linear-gradient(135deg, #FF6B00 0%, #FF8533 100%);
    cursor: pointer;
    border: 2px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 2px 8px rgba(255, 107, 0, 0.3);
    transition: all 0.3s ease;
}

.delay-slider::-webkit-slider-thumb:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(255, 107, 0, 0.5);
}

.delay-slider::-webkit-slider-thumb:active {
    transform: scale(1.15);
    box-shadow: 0 2px 6px rgba(255, 107, 0, 0.4);
}

.delay-slider::-moz-range-thumb {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: linear-gradient(135deg, #FF6B00 0%, #FF8533 100%);
    cursor: pointer;
    border: 2px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 2px 8px rgba(255, 107, 0, 0.3);
    transition: all 0.3s ease;
}

.delay-slider::-moz-range-thumb:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(255, 107, 0, 0.5);
}

.delay-slider::-moz-range-thumb:active {
    transform: scale(1.15);
    box-shadow: 0 2px 6px rgba(255, 107, 0, 0.4);
}

.delay-slider::-moz-range-track {
    height: 6px;
    border-radius: 3px;
    background: linear-gradient(to right, 
        rgba(255, 107, 0, 0.3) 0%, 
        rgba(255, 107, 0, 0.3) var(--slider-progress, 10%), 
        rgba(255, 255, 255, 0.1) var(--slider-progress, 10%), 
        rgba(255, 255, 255, 0.1) 100%);
}

.delay-slider:focus {
    outline: none;
}

.delay-slider:focus::-webkit-slider-thumb {
    box-shadow: 0 0 0 4px rgba(255, 107, 0, 0.2);
}

.delay-slider:focus::-moz-range-thumb {
    box-shadow: 0 0 0 4px rgba(255, 107, 0, 0.2);
}

.slider-labels {
    display: flex;
    justify-content: space-between;
    font-size: 0.6875rem;
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.form-hint {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.75rem;
    color: var(--text-secondary);
    font-style: italic;
}

.form-hint-aligned {
    margin-top: auto;
    padding-top: 0.5rem;
    line-height: 1.4;
}

.preview-settings {
    padding: 1rem;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}


.blocks-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    margin-top: -0.5rem;
    gap: 1rem;
}

/* Distribuição */
.distribution-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid var(--glass-border);
}

.distribution-tab {
    padding: 0.75rem 1rem;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.distribution-tab:hover {
    color: var(--text-primary);
}

.distribution-tab.active {
    color: var(--accent-orange);
    border-bottom-color: var(--accent-orange);
}

.distribution-content {
    display: none;
    max-height: 300px;
    overflow-y: auto;
}

.distribution-content.active {
    display: block;
}

.distribution-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.distribution-item {
    padding: 0.5rem;
    border-radius: 6px;
    transition: background 0.2s ease;
}

.distribution-item:hover {
    background: rgba(255, 255, 255, 0.05);
}

.distribution-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    font-size: 0.875rem;
    color: var(--text-primary);
}

.distribution-checkbox input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .blocks-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

/* Seção de adicionar bloco */
.add-block-section {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 2rem;
}

.add-block-section h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 1rem 0;
}

.add-block-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.add-block-btn {
    flex: 1;
    min-width: 150px;
    padding: 1rem;
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.3);
    border-radius: 8px;
    color: var(--accent-orange);
    cursor: pointer;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.add-block-btn:hover {
    background: rgba(255, 107, 0, 0.2);
    border-color: var(--accent-orange);
    transform: translateY(-2px);
}
</style>

<div class="checkin-flow-editor">
    <!-- PAINEL DO CELULAR (ESQUERDA - FIXO) -->
    <div class="mobile-mockup-panel">
        <div class="mobile-mockup-wrapper">
            <div class="mobile-screen">
                <iframe id="checkin-preview-frame" src="_admin_checkin_preview.php?id=<?php echo htmlspecialchars($checkin_id); ?>"></iframe>
            </div>
        </div>
    </div>

    <!-- PAINEL DE CONFIGURAÇÕES (DIREITA) -->
    <div class="config-panel">
        <!-- Seção de Configuração do Check-in -->
        <div class="checkin-config-section">
            <div class="editor-header">
                <h1>
                    <i class="fas fa-clipboard-check"></i>
                    Editor de Check-in
                </h1>
                <div class="header-actions">
                    <a href="checkin.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <button onclick="saveAll()" class="btn btn-primary">
                        <i class="fas fa-save"></i> Salvar Tudo
                    </button>
            </div>
            </div>
            
            <div class="config-form">
            <div class="form-row">
            <div class="form-group">
                    <label for="checkinName">Nome do Check-in <span class="required">*</span></label>
                    <input type="text" id="checkinName" value="<?php echo htmlspecialchars($checkin['name']); ?>" required>
            </div>
                
            <div class="form-group">
                    <label for="checkinDay">Dia da Semana <span class="required">*</span></label>
                    <div class="custom-select-wrapper">
                        <select id="checkinDay">
                            <?php 
                            $days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
                            for ($i = 0; $i < 7; $i++): 
                            ?>
                                <option value="<?php echo $i; ?>" <?php echo $checkin['day_of_week'] == $i ? 'selected' : ''; ?>>
                                    <?php echo $days[$i]; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
            </div>
            </div>
            </div>
            
            <div class="form-group">
                <label for="checkinDescription">Descrição</label>
                <textarea id="checkinDescription" rows="2"><?php echo htmlspecialchars($checkin['description'] ?? ''); ?></textarea>
            </div>
            
            <!-- Preview Settings -->
                <div class="form-group">
                <label>Configurações do Chat</label>
                <div class="preview-settings">
                    <div class="preview-settings-grid">
                        <div class="preview-setting-item">
                            <div class="preview-setting-header">
                                <label for="messageDelay">Delay entre Mensagens</label>
                                <span class="delay-value-display" id="delayValueDisplay">500ms <span class="delay-hint">(0.5s)</span></span>
                            </div>
                            <div class="slider-wrapper">
                                <input type="range" id="messageDelay" value="500" min="0" max="5000" step="50" class="delay-slider">
                                <div class="slider-labels">
                                    <span>0ms</span>
                                    <span>2500ms</span>
                                    <span>5000ms</span>
                    </div>
                </div>
                            <small class="form-hint form-hint-aligned">Tempo de espera entre mensagens do bot</small>
                        </div>
                        <div class="preview-settings-divider"></div>
                        <div class="preview-setting-item">
                            <div class="preview-setting-header">
                                <label>Efeito de Digitação</label>
                                <span class="delay-value-display" style="opacity: 0; pointer-events: none; visibility: hidden;">500ms</span>
                            </div>
                            <div class="toggle-switch-wrapper" onclick="event.stopPropagation()" style="margin-bottom: 0.125rem;">
                                <label class="toggle-switch">
                                    <input type="checkbox" class="toggle-switch-input" id="typingEffect" checked>
                                    <span class="toggle-switch-slider"></span>
                                </label>
                                <span class="toggle-switch-label" id="typingEffectLabel" style="color: #22C55E; font-weight: 700;">Ativado</span>
                            </div>
                            <small class="form-hint form-hint-aligned">Simula digitação nas mensagens do bot</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Status do Check-in -->
            <div class="form-group">
                <label>Status do Check-in</label>
                <div class="toggle-switch-wrapper" onclick="event.stopPropagation()">
                    <?php
                    $is_active = $checkin['is_active'] == 1;
                    ?>
                    <label class="toggle-switch">
                        <input type="checkbox" 
                               class="toggle-switch-input" 
                               id="checkinActive"
                               <?php echo $is_active ? 'checked' : ''; ?>>
                        <span class="toggle-switch-slider"></span>
                    </label>
                    <span class="toggle-switch-label" id="checkinActiveLabel" style="color: <?php echo $is_active ? '#22C55E' : '#EF4444'; ?>; font-weight: <?php echo $is_active ? '700' : '600'; ?>;"><?php echo $is_active ? 'Ativo' : 'Inativo'; ?></span>
            </div>
            </div>
            
            <!-- Distribuição -->
            <div class="form-group">
                <label>Distribuição</label>
                <div class="distribution-tabs">
                    <div class="distribution-tab active" onclick="switchDistributionTab('groups', this)">
                        <i class="fas fa-users"></i> Grupos
            </div>
                    <div class="distribution-tab" onclick="switchDistributionTab('users', this)">
                        <i class="fas fa-user"></i> Usuários
            </div>
                </div>
                
                <div id="groupsDistribution" class="distribution-content active">
                    <div class="distribution-list">
                        <?php foreach ($groups as $group): ?>
                            <div class="distribution-item">
                                <label class="distribution-checkbox">
                                    <input type="checkbox" 
                                           value="<?php echo $group['id']; ?>" 
                                           class="distribution-group"
                                           <?php echo in_array($group['id'], $distribution['groups']) ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($group['name']); ?></span>
                </label>
            </div>
                        <?php endforeach; ?>
                </div>
                </div>
                
                <div id="usersDistribution" class="distribution-content">
                    <div class="distribution-list">
                        <?php foreach ($users as $user): ?>
                            <div class="distribution-item">
                                <label class="distribution-checkbox">
                                    <input type="checkbox" 
                                           value="<?php echo $user['id']; ?>" 
                                           class="distribution-user"
                                           <?php echo in_array($user['id'], $distribution['users']) ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($user['name']); ?></span>
                                </label>
                </div>
                        <?php endforeach; ?>
                </div>
                </div>
                </div>
        </div>
    </div>

    <!-- Seção de Blocos do Fluxo -->
    <div class="blocks-section">
        <div class="blocks-header">
            <h2 class="section-title">
                <i class="fas fa-stream"></i> Fluxo de Perguntas
            </h2>
            <button onclick="showAddBlockSection()" class="btn btn-primary">
                <i class="fas fa-plus"></i> Adicionar Bloco
            </button>
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
                            <span class="block-type-badge" data-type="<?php echo htmlspecialchars($block['question_type']); ?>">
                                <?php
                                $type_icons = [
                                    'text' => 'fa-comment',
                                    'multiple_choice' => 'fa-list',
                                    'scale' => 'fa-sliders-h'
                                ];
                                $icon = $type_icons[$block['question_type']] ?? 'fa-question';
                                ?>
                                <i class="fas <?php echo $icon; ?>"></i>
                                <?php 
                                $type_names = [
                                    'text' => 'Mensagem',
                                    'multiple_choice' => 'Múltipla Escolha',
                                    'scale' => 'Escala'
                                ];
                                echo $type_names[$block['question_type']] ?? ucfirst(str_replace('_', ' ', $block['question_type'])); 
                                ?>
                            </span>
                            <span class="block-preview-text"><?php echo htmlspecialchars($block['question_text']); ?></span>
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
                    <div class="block-content preview" style="display: none;">
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
                                <div class="custom-select-wrapper">
                                    <select name="question_type" class="form-control" onchange="toggleOptionsEditor(this, <?php echo $block['id']; ?>)">
                                        <option value="text" <?php echo $block['question_type'] === 'text' ? 'selected' : ''; ?>>Mensagem de Texto</option>
                                        <option value="multiple_choice" <?php echo $block['question_type'] === 'multiple_choice' ? 'selected' : ''; ?>>Múltipla Escolha</option>
                                        <option value="scale" <?php echo $block['question_type'] === 'scale' ? 'selected' : ''; ?>>Escala (0-10)</option>
                    </select>
                </div>
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

    <div class="add-block-section" id="addBlockSection" style="display: none;">
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
</div>

<script>
const checkinId = <?php echo $checkin_id; ?>;
let editingBlockId = null;
let blockCounter = <?php echo count($blocks); ?>;

// Mostrar seção de adicionar bloco
function showAddBlockSection() {
    const section = document.getElementById('addBlockSection');
    section.style.display = section.style.display === 'none' ? 'block' : 'none';
}

// Adicionar novo bloco
function addBlock(type) {
    const blockId = 'new_' + Date.now();
    document.getElementById('addBlockSection').style.display = 'none';
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
                        <span class="block-type-badge" data-type="${type}">
                            <i class="fas ${typeIcons[type]}"></i>
                            ${typeNames[type]}
                        </span>
                        <span class="block-preview-text"></span>
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
                        <div class="custom-select-wrapper">
                            <select name="question_type" class="form-control" onchange="toggleOptionsEditor(this, '${blockId}')">
                                <option value="text" ${type === 'text' ? 'selected' : ''}>Mensagem de Texto</option>
                                <option value="multiple_choice" ${type === 'multiple_choice' ? 'selected' : ''}>Múltipla Escolha</option>
                                <option value="scale" ${type === 'scale' ? 'selected' : ''}>Escala (0-10)</option>
                </select>
                        </div>
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
            updatePreview();
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
                // Atualizar preview do texto ao lado da tag
                const block = document.querySelector(`[data-block-id="${blockId}"]`);
                if (block) {
                    const previewText = block.querySelector('.block-preview-text');
                    if (previewText) {
                        previewText.textContent = questionText;
                    }
                    // Atualizar badge com o tipo correto
                    const badge = block.querySelector('.block-type-badge');
                    if (badge) {
                        badge.setAttribute('data-type', questionType);
                    }
                }
                updatePreview();
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
    
    // Atualizar badge com o tipo correto
    const block = select.closest('.block-item');
    if (block) {
        const badge = block.querySelector('.block-type-badge');
        if (badge) {
            badge.setAttribute('data-type', select.value);
            
            // Atualizar texto do badge
            const typeNames = {
                'text': 'Mensagem',
                'multiple_choice': 'Múltipla Escolha',
                'scale': 'Escala'
            };
            const typeIcons = {
                'text': 'fa-comment',
                'multiple_choice': 'fa-list',
                'scale': 'fa-sliders-h'
            };
            badge.innerHTML = `<i class="fas ${typeIcons[select.value]}"></i> ${typeNames[select.value] || select.value}`;
        }
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

// Alternar entre tabs de distribuição
function switchDistributionTab(tab, element) {
    document.querySelectorAll('.distribution-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.distribution-content').forEach(c => c.classList.remove('active'));
    
    if (element) {
        element.classList.add('active');
    } else {
        // Fallback: encontrar pela tab
        const tabs = document.querySelectorAll('.distribution-tab');
        if (tab === 'groups') {
            tabs[0]?.classList.add('active');
        } else {
            tabs[1]?.classList.add('active');
        }
    }
    
    document.getElementById(tab === 'groups' ? 'groupsDistribution' : 'usersDistribution').classList.add('active');
}

// Salvar tudo (configuração + distribuição + ordem dos blocos)
function saveAll() {
    // Validar campos obrigatórios
    const name = document.getElementById('checkinName').value.trim();
    if (!name) {
        alert('Nome do check-in é obrigatório!');
        return;
    }
    
    // Coletar dados da configuração
    const configData = {
        action: 'update_checkin_config',
        checkin_id: checkinId,
        name: name,
        description: document.getElementById('checkinDescription').value.trim(),
        day_of_week: parseInt(document.getElementById('checkinDay').value),
        is_active: document.getElementById('checkinActive').checked ? 1 : 0
    };
    
    // Coletar distribuição
    const groups = Array.from(document.querySelectorAll('.distribution-group:checked')).map(cb => parseInt(cb.value));
    const users = Array.from(document.querySelectorAll('.distribution-user:checked')).map(cb => parseInt(cb.value));
    const distribution = { groups, users };
    
    // Salvar configuração e distribuição
    fetch('ajax_checkin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            ...configData,
            distribution: distribution
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Salvar ordem dos blocos
            saveBlockOrder();
        } else {
            alert('Erro ao salvar configuração: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar configuração');
    });
}

// Salvar ordem dos blocos
function saveBlockOrder() {
    const blocks = Array.from(document.querySelectorAll('.block-item'));
    const order = blocks.map((block, index) => ({
        id: block.dataset.blockId,
        order: index
    })).filter(item => !item.id.toString().startsWith('new_'));
    
    if (order.length === 0) {
        alert('Configuração salva com sucesso!');
        updatePreview();
        return;
    }
    
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
            alert('Tudo salvo com sucesso!');
            updatePreview();
            location.reload();
        } else {
            alert('Erro ao salvar ordem dos blocos: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar ordem dos blocos');
    });
}

// Atualizar preview
function updatePreview() {
    const iframe = document.getElementById('checkin-preview-frame');
    if (!iframe || !iframe.contentWindow) return;
    
    // Coletar dados do check-in
    const name = document.getElementById('checkinName').value.trim();
    
    // Coletar blocos
    const blocks = Array.from(document.querySelectorAll('.block-item'));
    const questions = blocks.map(block => {
        const blockId = block.dataset.blockId;
        const questionText = block.querySelector('textarea[name="question_text"]')?.value || 
                            block.querySelector('.block-content.preview')?.textContent.trim() || '';
        const questionType = block.querySelector('select[name="question_type"]')?.value || 
                            block.querySelector('.block-type-badge')?.textContent.trim() || 'text';
        
        // Coletar opções
        let options = [];
        if (questionType !== 'text') {
            const optionsList = block.querySelector(`#optionsList_${blockId}`);
            if (optionsList) {
                const optionInputs = optionsList.querySelectorAll('input');
                optionInputs.forEach(input => {
                    const val = input.value.trim();
                    if (val) options.push(val);
                });
            } else {
                // Tentar pegar do preview
                const optionItems = block.querySelectorAll('.option-item');
                optionItems.forEach(item => {
                    const val = item.textContent.replace('•', '').trim();
                    if (val) options.push(val);
                });
            }
        }
        
        // Mapear tipo
        let type = 'text';
        if (typeof questionType === 'string') {
            if (questionType.includes('Múltipla') || questionType === 'multiple_choice') type = 'multiple_choice';
            else if (questionType.includes('Escala') || questionType === 'scale') type = 'scale';
        } else if (questionType === 'multiple_choice') type = 'multiple_choice';
        else if (questionType === 'scale') type = 'scale';
        
        return {
            id: blockId,
            question_text: questionText,
            question_type: type,
            options: options
        };
    }).filter(q => q.question_text);
    
    // Enviar para o preview
    iframe.contentWindow.postMessage({
        type: 'updateCheckin',
        checkinName: name,
        questions: questions
    }, '*');
}

// Atualizar configurações do preview (sem reiniciar)
function updatePreviewSettings() {
    const iframe = document.getElementById('checkin-preview-frame');
    if (!iframe || !iframe.contentWindow) return;
    
    const delay = parseInt(document.getElementById('messageDelay').value) || 500;
    const typingEffect = document.getElementById('typingEffect').checked;
    
    // Apenas atualizar as configurações, sem reiniciar o preview
    iframe.contentWindow.postMessage({
        type: 'updateSettings',
        delay: delay,
        typingEffect: typingEffect
    }, '*');
}

// Atualizar apenas o nome do check-in (sem reiniciar)
function updateCheckinName() {
    const iframe = document.getElementById('checkin-preview-frame');
    if (!iframe || !iframe.contentWindow) return;
    
    const name = document.getElementById('checkinName').value.trim();
    
    // Apenas atualizar o nome, sem reiniciar o preview
    iframe.contentWindow.postMessage({
        type: 'updateName',
        checkinName: name
    }, '*');
}

// Atualizar display do delay com debounce
document.addEventListener('DOMContentLoaded', function() {
    const delaySlider = document.getElementById('messageDelay');
    const delayValueDisplay = document.getElementById('delayValueDisplay');
    
    let delayUpdateTimeout;
    let delayDisplayTimeout;
    
    function updateDelayDisplay() {
        const ms = parseInt(delaySlider.value) || 0;
        const seconds = (ms / 1000).toFixed(1);
        const progress = (ms / 5000) * 100;
        
        // Atualizar display imediatamente
        delayValueDisplay.innerHTML = `${ms}ms <span class="delay-hint">(${seconds}s)</span>`;
        
        // Atualizar progresso visual do slider
        delaySlider.style.setProperty('--slider-progress', `${progress}%`);
        
        // Atualizar configurações do preview com debounce
        clearTimeout(delayUpdateTimeout);
        delayUpdateTimeout = setTimeout(() => {
            updatePreviewSettings();
        }, 300);
    }
    
    delaySlider.addEventListener('input', updateDelayDisplay);
    updateDelayDisplay();
    
    // Atualizar label do toggle de digitação
    const typingToggle = document.getElementById('typingEffect');
    const typingLabel = document.getElementById('typingEffectLabel');
    
    typingToggle.addEventListener('change', function() {
        if (this.checked) {
            typingLabel.textContent = 'Ativado';
            typingLabel.style.color = '#22C55E';
            typingLabel.style.fontWeight = '700';
        } else {
            typingLabel.textContent = 'Desativado';
            typingLabel.style.color = '#EF4444';
            typingLabel.style.fontWeight = '600';
        }
        // Atualizar configurações em tempo real
        updatePreviewSettings();
    });
    
    // Atualizar label do toggle de check-in ativo
    const activeToggle = document.getElementById('checkinActive');
    const activeLabel = document.getElementById('checkinActiveLabel');
    
    activeToggle.addEventListener('change', function() {
        if (this.checked) {
            activeLabel.textContent = 'Ativo';
            activeLabel.style.color = '#22C55E';
            activeLabel.style.fontWeight = '700';
    } else {
            activeLabel.textContent = 'Inativo';
            activeLabel.style.color = '#EF4444';
            activeLabel.style.fontWeight = '600';
        }
    });
});

// Ajustar layout baseado no zoom do navegador para manter visual do zoom 110%
function adjustLayoutForZoom() {
    const mockupPanel = document.querySelector('.mobile-mockup-panel');
    const configPanel = document.querySelector('.config-panel');
    
    if (!mockupPanel || !configPanel) return;
    
    // Detectar zoom através do devicePixelRatio ou cálculo de viewport
    // devicePixelRatio: 1.0 = 100%, 1.1 = 110%, 1.25 = 125%
    const devicePixelRatio = window.devicePixelRatio || 1;
    
    // Calcular zoom aproximado baseado na largura da viewport
    // Para monitor 27" Full HD (1920px): 100% = 1920px, 110% = ~1745px, 125% = ~1536px
    const viewportWidth = window.innerWidth;
    let zoomLevel = '110%'; // padrão (referência)
    
    // Detectar zoom baseado em devicePixelRatio (mais confiável)
    if (devicePixelRatio >= 1.2) {
        zoomLevel = '125%';
    } else if (devicePixelRatio <= 0.95) {
        zoomLevel = '100%';
    } else {
        // Fallback: usar largura da viewport para detectar zoom
        // Monitor 27" Full HD como referência
        if (viewportWidth > 1800) {
            zoomLevel = '100%';
        } else if (viewportWidth < 1500) {
            zoomLevel = '125%';
        }
    }
    
    // Resetar estilos inline para aplicar novos valores
    mockupPanel.style.top = '';
    mockupPanel.style.width = '';
    mockupPanel.style.maxWidth = '';
    mockupPanel.style.height = '';
    mockupPanel.style.maxHeight = '';
    configPanel.style.marginLeft = '';
    configPanel.style.marginTop = '';
    configPanel.style.maxWidth = '';
    
    // Aplicar ajustes baseado no zoom detectado
    if (zoomLevel === '100%') {
        // Zoom 100%: manter o tamanho, mas descer levemente o celular e o menu
        mockupPanel.style.width = '440px';
        mockupPanel.style.maxWidth = '440px';
        mockupPanel.style.height = 'calc(100vh - (var(--content-wrapper-padding-v) * 1.5))';
        mockupPanel.style.maxHeight = '880px';
        
        // ↓ Descer o mockup e o painel para alinhar melhor visualmente
        mockupPanel.style.top = 'calc(var(--content-wrapper-padding-v) + 1.5rem)';
        configPanel.style.marginTop = '1.5rem';
        
        // Recalcula automaticamente o painel da direita
        configPanel.style.marginLeft = 'calc(440px + var(--gap-size))';
        configPanel.style.maxWidth = 'calc(100vw - var(--sidebar-width) - var(--content-wrapper-padding-h) - 440px - var(--gap-size) - var(--content-wrapper-padding-h))';
    } else if (zoomLevel === '125%') {
        // Zoom 125%: reduzir largura do celular e ajustar espaçamento
        mockupPanel.style.width = '350px';
        mockupPanel.style.maxWidth = '350px';
        
        // Zoom 125%: duplicar a distância entre o menu e o celular
        configPanel.style.marginLeft = 'calc(350px + 1.5rem)';
        configPanel.style.maxWidth = 'calc(100vw - var(--sidebar-width) - var(--content-wrapper-padding-h) - 350px - 1.5rem - var(--content-wrapper-padding-h))';
    }
    // Zoom 110%: manter valores padrão (já definidos no CSS)
    
    // Ajustar o header do painel direito no zoom 100%
    const editorHeader = document.querySelector('.config-panel .editor-header');
    if (editorHeader && zoomLevel === '100%') {
        editorHeader.style.marginTop = '1.5rem';
    } else if (editorHeader) {
        editorHeader.style.marginTop = '0';
    }
}

// Ajustar layout quando a página carregar
window.addEventListener('load', function() {
    adjustLayoutForZoom();
    
    const iframe = document.getElementById('checkin-preview-frame');
    if (iframe) {
        iframe.addEventListener('load', function() {
            setTimeout(() => updatePreview(), 1000);
        });
    }
});

// Ajustar layout quando a janela for redimensionada (zoom pode mudar)
window.addEventListener('resize', function() {
    adjustLayoutForZoom();
});

// Atualizar preview quando campos mudarem
document.addEventListener('DOMContentLoaded', function() {
    // Atualizar nome
    // Atualizar nome do check-in com debounce (sem reiniciar preview)
    const nameInput = document.getElementById('checkinName');
    if (nameInput) {
        let nameUpdateTimeout;
        nameInput.addEventListener('input', function() {
            clearTimeout(nameUpdateTimeout);
            nameUpdateTimeout = setTimeout(() => {
                updateCheckinName();
            }, 500);
        });
    }
    
    // Atualizar texto ao lado da tag quando digitar no textarea
    document.querySelectorAll('textarea[name="question_text"]').forEach(textarea => {
        textarea.addEventListener('input', function() {
            const block = this.closest('.block-item');
            if (block) {
                const previewText = block.querySelector('.block-preview-text');
                if (previewText) {
                    previewText.textContent = this.value;
                }
            }
        });
    });
    
    // Atualizar quando blocos mudarem (usando MutationObserver com debounce)
    const blocksContainer = document.getElementById('blocksContainer');
    if (blocksContainer) {
        let blocksUpdateTimeout;
        const observer = new MutationObserver(function(mutations) {
            // Debounce para evitar muitas atualizações
            clearTimeout(blocksUpdateTimeout);
            blocksUpdateTimeout = setTimeout(() => {
                // Apenas atualizar se realmente houver mudanças significativas
                // (não apenas mudanças de posição visual)
                const hasRealChanges = mutations.some(mutation => {
                    return mutation.type === 'childList' || 
                           (mutation.type === 'attributes' && 
                            mutation.attributeName === 'data-block-id');
                });
                if (hasRealChanges) {
                    updatePreview();
                }
            }, 1000);
        });
        observer.observe(blocksContainer, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['data-block-id', 'data-order']
        });
        
        // Adicionar listeners para novos blocos adicionados dinamicamente
        const addListenersToNewBlocks = function() {
            blocksContainer.querySelectorAll('textarea[name="question_text"]').forEach(textarea => {
                if (!textarea.hasAttribute('data-listener-added')) {
                    textarea.setAttribute('data-listener-added', 'true');
                    textarea.addEventListener('input', function() {
                        const block = this.closest('.block-item');
                        if (block) {
                            const previewText = block.querySelector('.block-preview-text');
                            if (previewText) {
                                previewText.textContent = this.value;
                            }
                        }
                    });
                }
            });
        };
        
        // Adicionar listeners quando novos blocos forem adicionados
        observer.observe(blocksContainer, {
            childList: true,
            subtree: true
        });
        
        // Adicionar listeners iniciais
        addListenersToNewBlocks();
    }
});

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

