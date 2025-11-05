<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];

// --- LÓGICA DO CONTADOR DE PESO (MESMA DO MAIN_APP) ---
$can_edit_weight = true;
$days_until_next_weight_update = 0;
try {
    $stmt_last_weight = $conn->prepare("SELECT MAX(date_recorded) AS last_date FROM sf_user_weight_history WHERE user_id = ?");
    if ($stmt_last_weight) {
        $stmt_last_weight->bind_param("i", $user_id);
        $stmt_last_weight->execute();
        $result = $stmt_last_weight->get_result()->fetch_assoc();
        $stmt_last_weight->close();
        if ($result && !empty($result['last_date'])) {
            $last_log_date = new DateTime($result['last_date']);
            $unlock_date = (clone $last_log_date)->modify('+7 days');
            $today = new DateTime('today');
            if ($today < $unlock_date) {
                $can_edit_weight = false;
                $days_until_next_weight_update = (int)$today->diff($unlock_date)->days;
                if ($days_until_next_weight_update == 0) $days_until_next_weight_update = 1;
            }
        }
    }
} catch (Exception $e) { error_log("Erro ao processar data de peso: " . $e->getMessage()); }

// Buscar dados completos do perfil (onboarding) - mesma query do admin
$profile_data = [];
$stmt = $conn->prepare("
    SELECT u.*, p.* 
    FROM sf_users u 
    LEFT JOIN sf_user_profiles p ON u.id = p.user_id 
    WHERE u.id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile_data = $result->fetch_assoc();
$stmt->close();

// Verificar se os dados foram carregados
if (!$profile_data) {
    error_log("Erro: profile_data está vazio para user_id: " . $user_id);
    echo '<div class="container"><p class="error-message">Erro ao carregar os dados do seu perfil. Por favor, tente novamente mais tarde.</p></div>';
    require_once APP_ROOT_PATH . '/includes/layout_footer.php';
    die();
}

// Restrições alimentares - carregar do banco
$user_selected_restrictions = [];
$all_restrictions = [];

// Carregar restrições selecionadas pelo usuário
$stmt = $conn->prepare("SELECT restriction_id FROM sf_user_selected_restrictions WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_selected_restrictions[] = $row['restriction_id'];
}
$stmt->close();

// Carregar todas as restrições disponíveis
$stmt = $conn->prepare("SELECT id, name FROM sf_dietary_restrictions_options ORDER BY id");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $all_restrictions[$row['id']] = $row['name'];
}
$stmt->close();

// Foto de perfil - usar initials como no admin
$custom_photo_filename = $profile_data['profile_image_filename'] ?? null;
$profile_image_html = '';

if ($custom_photo_filename) {
    // Adicionar timestamp para evitar cache
    $profile_image_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($custom_photo_filename) . '?t=' . time();
    $profile_image_html = '<img src="' . $profile_image_url . '" alt="Foto de Perfil" id="profile-photo-display" class="profile-photo">';
} else {
    // Usar ícone laranja como no main_app
    $profile_image_html = '<div id="profile-photo-display" class="profile-photo profile-icon-placeholder"><i class="fas fa-user"></i></div>';
}

// CSRF token - pegar o token gerado pelo layout_header
$csrf_token_for_html = $_SESSION['csrf_token'] ?? '';

$page_title = "Editar Perfil";
$extra_js = ['script.js'];
$extra_css = ['pages/_edit_profile.css'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* CSS do layout nativo para mobile - Edit Profile */
.edit-profile-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 20px 8px 20px 8px;
}

/* Header com título */
.profile-header {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 24px;
}

.profile-title {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.profile-title i {
    color: var(--accent-orange);
    font-size: 1.6rem;
}


/* Card de foto de perfil */
.profile-photo-card {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    text-align: center;
}

.profile-photo-wrapper {
    position: relative;
    display: inline-block;
    margin-bottom: 16px;
}

.profile-photo {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--accent-orange);
    cursor: pointer;
    transition: transform 0.3s ease;
}

.profile-photo:hover {
    transform: scale(1.05);
}

.profile-photo.profile-icon-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.05);
    border: 3px solid var(--accent-orange);
    cursor: pointer;
    transition: transform 0.3s ease;
}

.profile-photo.profile-icon-placeholder:hover {
    transform: scale(1.05);
}

.profile-photo.profile-icon-placeholder i {
    color: var(--accent-orange);
    font-size: 2.5rem;
}

.photo-upload-text {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin: 0 0 12px 0;
}

.remove-photo-btn {
    background: rgba(255, 59, 48, 0.1);
    border: 1px solid rgba(255, 59, 48, 0.3);
    color: #ff3b30;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.remove-photo-btn:hover {
    background: rgba(255, 59, 48, 0.2);
    border-color: rgba(255, 59, 48, 0.5);
    transform: translateY(-1px);
}

.remove-photo-btn i {
    font-size: 0.8rem;
}

/* Cards de informações */
.info-card {
    background: rgba(255, 255, 255, 0.03);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.card-icon {
    font-size: 1.5rem;
    color: var(--accent-orange);
}

.card-title {
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

/* Formulário */
.form-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-label {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-secondary);
    margin: 0;
}

.form-input, .form-select {
    width: 100%;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 1rem;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.form-input:focus, .form-select:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
    box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.1);
}

.form-input::placeholder {
    color: var(--text-secondary);
    opacity: 0.7;
}

.form-input-readonly {
    background: rgba(255, 255, 255, 0.03) !important;
    border-color: rgba(255, 255, 255, 0.08) !important;
    color: rgba(255, 255, 255, 0.6) !important;
    cursor: not-allowed;
    opacity: 0.8;
}

/* Botão de restrições */
.restrictions-button {
    width: 100%;
    padding: 16px 20px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: var(--text-primary);
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.restrictions-button:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--accent-orange);
}

.restrictions-button i {
    color: var(--accent-orange);
    transition: transform 0.3s ease;
}

.restrictions-button:hover i {
    transform: translateX(4px);
}

/* Botão de salvar */
.save-button {
    width: 100%;
    padding: 16px 24px;
    background: linear-gradient(135deg, var(--accent-orange), #ff8c00);
    border: none;
    border-radius: 16px;
    color: white;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.save-button:hover {
    transform: translateY(-2px);
}

/* Modal de restrições e crop */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.modal-overlay.visible {
    opacity: 1;
    visibility: visible;
}

.restrictions-modal {
    background: rgba(20, 20, 20, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 20px;
    max-width: 350px;
    width: 90%;
    max-height: 70vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    flex-shrink: 0;
}

.modal-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 4px;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
}

.restrictions-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 20px;
    flex: 1;
    overflow-y: auto;
}

.restriction-item {
    padding: 0;
}

.custom-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.85rem;
}

.custom-checkbox:hover {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 140, 0, 0.3);
}

.custom-checkbox input[type="checkbox"] {
    display: none;
}

.checkmark {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 3px;
    position: relative;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.custom-checkbox input[type="checkbox"]:checked + .checkmark {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
}


.checkbox-label {
    color: var(--text-primary);
    font-size: 0.85rem;
    font-weight: 500;
    line-height: 1.2;
}

.modal-actions {
    display: flex;
    gap: 10px;
    flex-shrink: 0;
}

.modal-btn {
    flex: 1;
    padding: 10px 16px;
    border: none;
    border-radius: 0;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.modal-btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.modal-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
}

.modal-btn-primary {
    background: linear-gradient(135deg, var(--accent-orange), #ff8c00);
    color: white;
}

.modal-btn-primary:hover {
    transform: translateY(-1px);
}

/* Modal de crop de foto */
.crop-modal {
    background: rgba(20, 20, 20, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 16px;
    max-width: 350px;
    width: 90%;
    max-height: 75vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.crop-container {
    width: 100%;
    height: 280px; 
    position: relative;
    margin-bottom: 16px;
    border-radius: 12px;
    overflow: hidden;
    background: #000;
}

.crop-background {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: blur(15px);
    transform: scale(1.2);
}

.crop-image {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: auto;
    height: auto;
    max-width: none;
    max-height: none;
    cursor: move;
    z-index: 10;
}

.crop-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 20;
}

.crop-circle {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 200px;
    height: 200px;
    border: 2px solid var(--accent-orange);
    border-radius: 50%;
    background: transparent;
    box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.6);
}


/* Responsividade */
@media (max-width: 768px) {
    .edit-profile-grid {
        padding: 20px 6px 20px 6px;
    }
    
    .info-card {
        padding: 20px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .profile-header {
        justify-content: center;
    }
    
    .profile-title {
        font-size: 1.6rem;
    }
    
    .profile-photo {
        width: 80px;
        height: 80px;
    }
    
    .restrictions-modal {
        width: 95%;
        padding: 16px;
        max-height: 80vh;
    }
    
    .restrictions-grid {
        grid-template-columns: 1fr;
        gap: 6px;
    }
    
    .custom-checkbox {
        padding: 6px 10px;
        font-size: 0.8rem;
    }
    
    .checkmark {
        width: 14px;
        height: 14px;
    }
    
    .checkbox-label {
        font-size: 0.8rem;
    }
}

/* Modal de Exercícios */
.exercise-modal {
    background: var(--bg-secondary);
    border-radius: 20px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    display: none;
}

/* Corrigir para mostrar o modal quando o overlay tiver a classe active */
.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.modal-overlay.active .exercise-modal {
    display: block;
}

.custom-activity-modal {
    background: var(--bg-secondary);
    border-radius: 20px;
    width: 90%;
    max-width: 400px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    display: none;
}

/* Corrigir para mostrar o modal de atividades customizadas */
.modal-overlay.active .custom-activity-modal {
    display: block;
}

/* Exercise Options */
.selectable-options-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.selectable-options-grid input[type="checkbox"] {
    display: none;
}

.selectable-options-grid label {
    display: block;
    padding: 15px 20px;
    background: rgba(255, 255, 255, 0.05);
    border: 2px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    color: var(--text-primary);
    font-weight: 500;
}

.selectable-options-grid input[type="checkbox"]:checked + label {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    color: white;
}

.option-button {
    padding: 15px 20px;
    background: rgba(255, 255, 255, 0.05);
    border: 2px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: var(--text-primary);
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    width: 100%;
}

.option-button:hover, .option-button.active {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    color: white;
}

.selectable-options {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.selectable-options input[type="checkbox"] {
    display: none;
}

.selectable-options label {
    padding: 10px 20px;
    background: rgba(255, 255, 255, 0.05);
    border: 2px solid rgba(255, 255, 255, 0.1);
    border-radius: 25px;
    cursor: pointer;
    transition: all 0.3s ease;
    color: var(--text-primary);
    font-weight: 500;
}

.selectable-options input[type="checkbox"]:checked + label {
    background: var(--accent-orange);
    border-color: var(--accent-orange);
    color: white;
}

/* Exercise Duration */
.exercise-duration-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    margin-bottom: 10px;
}

.exercise-name {
    font-weight: 600;
    color: var(--text-primary);
}

.duration-input-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.duration-input-group input {
    width: 80px;
    padding: 8px 12px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    color: var(--text-primary);
    text-align: center;
}

.duration-unit {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

/* Activity Tags */
.activities-list {
    margin-top: 15px;
}

.activity-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--accent-orange);
    color: white;
    padding: 8px 12px;
    border-radius: 20px;
    margin: 5px;
    font-size: 0.9rem;
}

.remove-tag {
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    font-size: 1.2rem;
    padding: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.remove-tag:hover {
    background: rgba(255, 255, 255, 0.2);
}
</style>

<div class="app-container">
    <div class="edit-profile-grid">
        <!-- Header com título -->
        <div class="profile-header">
            <h1 class="profile-title">
                <i class="fas fa-user-edit"></i>
                Editar Perfil
            </h1>
        </div>

        <form id="edit-profile-form" action="edit_profile_form.php" method="POST" enctype="multipart/form-data">
            
            <!-- Card de foto de perfil -->
            <div class="profile-photo-card">
                <div class="profile-photo-wrapper">
                    <?php echo $profile_image_html; ?>
                    <input type="file" id="profile-photo-input" name="profile_photo" accept="image/*" style="display: none;">
                    <input type="hidden" id="remove-photo-input" name="remove_photo" value="0">
                </div>
                <p class="photo-upload-text">Clique na foto para alterar</p>
                <?php if ($custom_photo_filename): ?>
                <button type="button" id="remove-photo-btn" class="remove-photo-btn">
                    <i class="fas fa-trash"></i>
                    Remover Foto
                </button>
                <?php endif; ?>
            </div>

            <!-- Informações pessoais -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-user card-icon"></i>
                    <h3 class="card-title">Informações Pessoais</h3>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name" class="form-label">Nome Completo</label>
                        <input type="text" id="name" name="name" class="form-input" 
                               value="<?php echo htmlspecialchars($profile_data['name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">E-mail</label>
                        <div class="form-input form-input-readonly">
                            <?php echo htmlspecialchars($profile_data['email'] ?? ''); ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city" class="form-label">Cidade</label>
                            <input type="text" id="city" name="city" class="form-input" 
                                   value="<?php echo htmlspecialchars($profile_data['city'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="uf" class="form-label">UF</label>
                            <input type="text" id="uf" name="uf" class="form-input" 
                                   value="<?php echo htmlspecialchars($profile_data['uf'] ?? ''); ?>" 
                                   placeholder="SP" maxlength="2">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone_ddd" class="form-label">DDD</label>
                            <input type="text" id="phone_ddd" name="phone_ddd" class="form-input" 
                                   value="<?php echo htmlspecialchars($profile_data['phone_ddd'] ?? ''); ?>" 
                                   placeholder="11" maxlength="2">
                        </div>
                        <div class="form-group">
                            <label for="phone_number" class="form-label">Telefone</label>
                            <input type="text" id="phone_number" name="phone_number" class="form-input" 
                                   value="<?php echo htmlspecialchars($profile_data['phone_number'] ?? ''); ?>" 
                                   placeholder="999999999">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Informações físicas -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-weight card-icon"></i>
                    <h3 class="card-title">Informações Físicas</h3>
                </div>
                <div class="form-grid">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="dob" class="form-label">Data de Nascimento</label>
                            <input type="date" id="dob" name="dob" class="form-input" 
                                   value="<?php echo $profile_data['dob'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="gender" class="form-label">Gênero</label>
                            <select id="gender" name="gender" class="form-select">
                                <option value="" <?php echo empty($profile_data['gender']) ? 'selected' : ''; ?>>Selecione</option>
                                <option value="male" <?php echo ($profile_data['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Masculino</option>
                                <option value="female" <?php echo ($profile_data['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Feminino</option>
                                <option value="other" <?php echo ($profile_data['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Outro</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="height_cm" class="form-label">Altura (cm)</label>
                            <input type="number" id="height_cm" name="height_cm" class="form-input" 
                                   value="<?php echo $profile_data['height_cm'] ?? ''; ?>" min="100" max="250" step="0.1">
                        </div>
                        <div class="form-group">
                            <label for="weight_kg" class="form-label">Peso Atual (kg)</label>
                            <?php if ($can_edit_weight): ?>
                                <input type="number" id="weight_kg" name="weight_kg" class="form-input" 
                                       value="<?php echo $profile_data['weight_kg'] ?? ''; ?>" min="30" max="300" step="0.1">
                            <?php else: ?>
                                <input type="number" id="weight_kg" name="weight_kg" class="form-input form-input-readonly" 
                                       value="<?php echo $profile_data['weight_kg'] ?? ''; ?>" min="30" max="300" step="0.1" readonly>
                                <div style="margin-top: 8px; font-size: 0.85rem; color: var(--accent-orange);">
                                    <i class="fas fa-clock"></i> Você só pode ajustar o peso a cada 7 dias. Próximo ajuste em <strong><?php echo $days_until_next_weight_update; ?></strong> <?php echo $days_until_next_weight_update == 1 ? 'dia' : 'dias'; ?>.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="objective" class="form-label">Objetivo Principal</label>
                        <select id="objective" name="objective" class="form-select">
                            <option value="" <?php echo empty($profile_data['objective']) ? 'selected' : ''; ?>>Selecione um objetivo</option>
                            <option value="lose_fat" <?php echo ($profile_data['objective'] ?? '') === 'lose_fat' ? 'selected' : ''; ?>>Emagrecimento</option>
                            <option value="maintain_weight" <?php echo ($profile_data['objective'] ?? '') === 'maintain_weight' ? 'selected' : ''; ?>>Manter Peso</option>
                            <option value="gain_muscle" <?php echo ($profile_data['objective'] ?? '') === 'gain_muscle' ? 'selected' : ''; ?>>Hipertrofia</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Estilo de vida -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-running card-icon"></i>
                    <h3 class="card-title">Estilo de Vida</h3>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="exercise_type" class="form-label">Exercícios Praticados</label>
                        <a href="edit_exercises.php" class="restrictions-button">
                            <span id="exercise-display"><?php 
                                $exercise_display = $profile_data['exercise_type'] ?? '';
                                if (empty($exercise_display) || $exercise_display === '0') {
                                    echo 'Nenhum exercício selecionado';
                                } else {
                                    echo htmlspecialchars($exercise_display);
                                }
                            ?></span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <input type="hidden" id="exercise_type" name="exercise_type" value="<?php echo htmlspecialchars($profile_data['exercise_type'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="exercise_frequency" class="form-label">Frequência de Exercícios</label>
                        <select id="exercise_frequency" name="exercise_frequency" class="form-select">
                            <option value="sedentary" <?php echo ($profile_data['exercise_frequency'] ?? '') === 'sedentary' ? 'selected' : ''; ?>>Sedentário</option>
                            <option value="1_2x_week" <?php echo ($profile_data['exercise_frequency'] ?? '') === '1_2x_week' ? 'selected' : ''; ?>>1 a 2x/semana</option>
                            <option value="3_4x_week" <?php echo ($profile_data['exercise_frequency'] ?? '') === '3_4x_week' ? 'selected' : ''; ?>>3 a 4x/semana</option>
                            <option value="5_6x_week" <?php echo ($profile_data['exercise_frequency'] ?? '') === '5_6x_week' ? 'selected' : ''; ?>>5 a 6x/semana</option>
                            <option value="6_7x_week" <?php echo ($profile_data['exercise_frequency'] ?? '') === '6_7x_week' ? 'selected' : ''; ?>>6 a 7x/semana</option>
                            <option value="7plus_week" <?php echo ($profile_data['exercise_frequency'] ?? '') === '7plus_week' ? 'selected' : ''; ?>>+ de 7x/semana</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="water_intake_liters" class="form-label">Consumo de Água</label>
                            <select id="water_intake_liters" name="water_intake_liters" class="form-select">
                                <option value="_1l" <?php echo ($profile_data['water_intake_liters'] ?? '') === '_1l' ? 'selected' : ''; ?>>Até 1 Litro</option>
                                <option value="1_2l" <?php echo ($profile_data['water_intake_liters'] ?? '') === '1_2l' ? 'selected' : ''; ?>>1 a 2 Litros</option>
                                <option value="2_3l" <?php echo ($profile_data['water_intake_liters'] ?? '') === '2_3l' ? 'selected' : ''; ?>>2 a 3 Litros</option>
                                <option value="3plus_l" <?php echo ($profile_data['water_intake_liters'] ?? '') === '3plus_l' ? 'selected' : ''; ?>>Mais de 3 Litros</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="sleep_time_bed" class="form-label">Horário de Dormir</label>
                            <input type="time" id="sleep_time_bed" name="sleep_time_bed" class="form-input" 
                                   value="<?php echo $profile_data['sleep_time_bed'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="sleep_time_wake" class="form-label">Horário de Acordar</label>
                        <input type="time" id="sleep_time_wake" name="sleep_time_wake" class="form-input" 
                               value="<?php echo $profile_data['sleep_time_wake'] ?? ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Alimentação -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-utensils card-icon"></i>
                    <h3 class="card-title">Alimentação</h3>
                </div>
                <div class="form-grid">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="meat_consumption" class="form-label">Consome Carne</label>
                            <select id="meat_consumption" name="meat_consumption" class="form-select">
                                <option value="1" <?php echo ($profile_data['meat_consumption'] ?? '') ? 'selected' : ''; ?>>Sim</option>
                                <option value="0" <?php echo !($profile_data['meat_consumption'] ?? '') ? 'selected' : ''; ?>>Não</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="vegetarian_type" class="form-label">Tipo Vegetariano</label>
                            <select id="vegetarian_type" name="vegetarian_type" class="form-select">
                                <option value="not_like" <?php echo ($profile_data['vegetarian_type'] ?? '') === 'not_like' ? 'selected' : ''; ?>>Apenas não gosta</option>
                                <option value="strict_vegetarian" <?php echo ($profile_data['vegetarian_type'] ?? '') === 'strict_vegetarian' ? 'selected' : ''; ?>>Vegetariano Estrito</option>
                                <option value="ovolacto" <?php echo ($profile_data['vegetarian_type'] ?? '') === 'ovolacto' ? 'selected' : ''; ?>>Ovolactovegetariano</option>
                                <option value="vegan" <?php echo ($profile_data['vegetarian_type'] ?? '') === 'vegan' ? 'selected' : ''; ?>>Vegano</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="lactose_intolerance" class="form-label">Intolerância à Lactose</label>
                            <select id="lactose_intolerance" name="lactose_intolerance" class="form-select">
                                <option value="0" <?php echo !($profile_data['lactose_intolerance'] ?? '') ? 'selected' : ''; ?>>Não</option>
                                <option value="1" <?php echo ($profile_data['lactose_intolerance'] ?? '') ? 'selected' : ''; ?>>Sim</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="gluten_intolerance" class="form-label">Intolerância ao Glúten</label>
                            <select id="gluten_intolerance" name="gluten_intolerance" class="form-select">
                                <option value="0" <?php echo !($profile_data['gluten_intolerance'] ?? '') ? 'selected' : ''; ?>>Não</option>
                                <option value="1" <?php echo ($profile_data['gluten_intolerance'] ?? '') ? 'selected' : ''; ?>>Sim</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Restrições alimentares -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-ban card-icon"></i>
                    <h3 class="card-title">Restrições Alimentares</h3>
                </div>
                <div class="form-group">
                    <label class="form-label">Selecione suas restrições (opcional)</label>
                    <button type="button" id="open-restrictions-modal" class="restrictions-button">
                        <span id="restrictions-selected-count">
                            <?php
                            $count = count($user_selected_restrictions);
                            if ($count === 0) {
                                echo 'Nenhuma restrição';
                            } elseif ($count === 1) {
                                echo '1 restrição selecionada';
                            } else {
                                echo $count . ' restrições selecionadas';
                            }
                            ?>
                        </span>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>

            <!-- Botão de salvar -->
            <button type="submit" class="save-button">
                <i class="fas fa-save"></i>
                Salvar Alterações
            </button>
        </form>
    </div>
</div>

<!-- Modal de Exercícios -->
<div id="exercise-modal" class="modal-overlay">
    <div class="exercise-modal">
        <div class="modal-header">
            <h3 class="modal-title">Quais exercícios você pratica?</h3>
            <button type="button" class="modal-close" id="close-exercise-modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p class="modal-subtitle">Selecione suas atividades e defina quanto tempo dura cada treino.</p>
            
            <div id="exercise-options-wrapper">
                <div class="selectable-options-grid">
                    <input type="checkbox" id="ex1" name="exercise_types[]" value="Musculação">
                    <label for="ex1">Musculação</label>
                    
                    <input type="checkbox" id="ex2" name="exercise_types[]" value="Corrida">
                    <label for="ex2">Corrida</label>
                    
                    <input type="checkbox" id="ex3" name="exercise_types[]" value="Crossfit">
                    <label for="ex3">Crossfit</label>
                    
                    <input type="checkbox" id="ex4" name="exercise_types[]" value="Natação">
                    <label for="ex4">Natação</label>
                    
                    <input type="checkbox" id="ex5" name="exercise_types[]" value="Yoga">
                    <label for="ex5">Yoga</label>
                    
                    <input type="checkbox" id="ex6" name="exercise_types[]" value="Futebol">
                    <label for="ex6">Futebol</label>
                    
                    <button type="button" class="option-button" id="other-activity-btn">Outro</button>
                </div>
            </div>
            
            <div class="selectable-options" style="margin-top: 15px;">
                <input type="checkbox" id="ex-none" name="exercise_type_none">
                <label for="ex-none">Nenhuma / Não pratico</label>
            </div>
            
            <!-- Duração dos Exercícios -->
            <div id="exercise-duration-wrapper" style="margin-top: 30px; display: none;">
                <h3>Duração dos Exercícios</h3>
                <div id="exercise-duration-fields">
                    <!-- Campos de duração serão inseridos dinamicamente -->
                </div>
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="modal-btn modal-btn-secondary" id="cancel-exercise-modal">
                Cancelar
            </button>
            <button type="button" class="modal-btn modal-btn-primary" id="save-exercise-modal">
                Salvar
            </button>
        </div>
    </div>
</div>

<!-- Modal de Atividade Customizada -->
<div id="custom-activity-modal" class="modal-overlay">
    <div class="custom-activity-modal">
        <div class="modal-header">
            <h3 class="modal-title">Adicionar Atividade</h3>
            <button type="button" class="modal-close" id="close-custom-modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="custom-activity-input" class="form-label">Nome da Atividade</label>
                <input type="text" id="custom-activity-input" class="form-input" placeholder="Ex: Pilates, Dança, etc.">
            </div>
            <div id="custom-activities-list" class="activities-list">
                <!-- Atividades customizadas serão exibidas aqui -->
            </div>
            <input type="hidden" id="custom-activities-hidden-input" name="custom_activities">
        </div>
        <div class="modal-actions">
            <button type="button" class="modal-btn modal-btn-primary" id="add-activity-btn">Adicionar</button>
        </div>
    </div>
</div>

<!-- Modal de Restrições Alimentares -->
<div id="restrictions-modal" class="modal-overlay">
    <div class="restrictions-modal">
        <div class="modal-header">
            <h3 class="modal-title">Restrições Alimentares</h3>
            <button type="button" class="modal-close" id="close-restrictions-modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="restrictions-grid" id="modal-restrictions-grid">
            <?php foreach ($all_restrictions as $id => $name): ?>
            <div class="restriction-item">
                <label class="custom-checkbox">
                    <input type="checkbox" id="modal_restriction_<?php echo $id; ?>"
                           value="<?php echo $id; ?>"
                           <?php echo in_array($id, $user_selected_restrictions) ? 'checked' : ''; ?>>
                    <span class="checkmark"></span>
                    <span class="checkbox-label"><?php echo htmlspecialchars($name); ?></span>
                </label>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="modal-actions">
            <button type="button" class="modal-btn modal-btn-secondary" id="cancel-restrictions">
                Cancelar
            </button>
            <button type="button" class="modal-btn modal-btn-primary" id="save-restrictions">
                Salvar
            </button>
        </div>
    </div>
</div>

<!-- Modal de Crop de Foto -->
<div id="crop-modal" class="modal-overlay">
    <div class="crop-modal">
        <div class="modal-header">
            <h3 class="modal-title">Ajustar Foto</h3>
            <button type="button" class="modal-close" id="close-crop-modal">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="crop-container">
            <img id="crop-background" class="crop-background" src="" alt="Background">
            <img id="crop-image" class="crop-image" src="" alt="Imagem para cortar">
            <div class="crop-overlay">
                <div class="crop-circle"></div>
            </div>
        </div>

        <div class="modal-actions">
            <button type="button" class="modal-btn modal-btn-secondary" id="cancel-crop">
                Cancelar
            </button>
            <button type="button" class="modal-btn modal-btn-primary" id="save-crop">
                Salvar
            </button>
        </div>
    </div>
</div>

<script>
// Variáveis para o crop
let currentImageFile = null;
let isDragging = false;
let isZooming = false;
let startX = 0;
let startY = 0;
let currentX = 0;
let currentY = 0;
let currentScale = 1;
let lastDistance = 0;

// Lógica para upload de foto
document.getElementById('profile-photo-display').addEventListener('click', function() {
    document.getElementById('profile-photo-input').click();
});

// Lógica para remover foto
const removePhotoBtn = document.getElementById('remove-photo-btn');
if (removePhotoBtn) {
    removePhotoBtn.addEventListener('click', function() {
        if (confirm('Tem certeza que deseja remover sua foto de perfil?')) {
            // Trocar foto por ícone placeholder
            const photoDisplay = document.getElementById('profile-photo-display');
            const placeholderDiv = document.createElement('div');
            placeholderDiv.id = 'profile-photo-display';
            placeholderDiv.className = 'profile-photo profile-icon-placeholder';
            placeholderDiv.innerHTML = '<i class="fas fa-user"></i>';
            
            // Substituir elemento
            photoDisplay.parentNode.replaceChild(placeholderDiv, photoDisplay);
            
            // Adicionar evento de click ao novo elemento
            placeholderDiv.addEventListener('click', () => document.getElementById('profile-photo-input').click());
            
            // Marcar para remoção no backend
            document.getElementById('remove-photo-input').value = '1';
            
            // Remover o botão de remover
            removePhotoBtn.remove();
            
            // Limpar input de arquivo
            document.getElementById('profile-photo-input').value = '';
        }
    });
}

document.getElementById('profile-photo-input').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        currentImageFile = file;
        const reader = new FileReader();
        reader.onload = function(e) {
            openCropModal(e.target.result);
        };
        reader.readAsDataURL(file);
    }
});

// Abrir modal de crop
function openCropModal(imageSrc) {
    const modal = document.getElementById('crop-modal');
    const cropImage = document.getElementById('crop-image');
    const cropBackground = document.getElementById('crop-background');
    
    cropImage.src = imageSrc;
    cropBackground.src = imageSrc;
    
    modal.classList.add('visible');
    
    // Resetar posição e zoom
    currentX = 0;
    currentY = 0;
    currentScale = 1;
    
    cropImage.onload = () => {
        // CORREÇÃO: Calcular escala inicial para a foto caber na máscara, sem zoom.
        const cropCircleDiameter = 200; // Deve ser o mesmo valor do CSS
        const minImageDim = Math.min(cropImage.naturalWidth, cropImage.naturalHeight);
        
        let initialScale = 1;
        if (minImageDim > cropCircleDiameter) {
            initialScale = cropCircleDiameter / minImageDim;
        }
        currentScale = initialScale;
        updateImageTransform();
    };
}

// Fechar modal de crop
function closeCropModal() {
    document.getElementById('crop-modal').classList.remove('visible');
    currentImageFile = null;
    document.getElementById('profile-photo-input').value = '';
}

// Atualizar transformação da imagem na tela
function updateImageTransform() {
    const cropImage = document.getElementById('crop-image');
    cropImage.style.transform = `translate(-50%, -50%) translate(${currentX}px, ${currentY}px) scale(${currentScale})`;
}

// Calcular distância entre dois toques (para zoom)
function getDistance(touch1, touch2) {
    const dx = touch1.clientX - touch2.clientX;
    const dy = touch1.clientY - touch2.clientY;
    return Math.sqrt(dx * dx + dy * dy);
}

// Event listeners para mouse (desktop)
const cropImageEl = document.getElementById('crop-image');
cropImageEl.addEventListener('mousedown', function(e) {
    e.preventDefault();
    isDragging = true;
    startX = e.clientX - currentX;
    startY = e.clientY - currentY;
});

document.addEventListener('mousemove', function(e) {
    if (!isDragging) return;
    e.preventDefault();
    currentX = e.clientX - startX;
    currentY = e.clientY - startY;
    updateImageTransform();
});

document.addEventListener('mouseup', () => { isDragging = false; });
document.addEventListener('mouseleave', () => { isDragging = false; });

// Event listeners para touch (mobile)
cropImageEl.addEventListener('touchstart', function(e) {
    e.preventDefault();
    if (e.touches.length === 1) {
        isDragging = true;
        isZooming = false;
        const touch = e.touches[0];
        startX = touch.clientX - currentX;
        startY = touch.clientY - currentY;
    } else if (e.touches.length === 2) {
        isDragging = false;
        isZooming = true;
        lastDistance = getDistance(e.touches[0], e.touches[1]);
    }
}, { passive: false });

document.addEventListener('touchmove', function(e) {
    if (!isDragging && !isZooming) return;
    e.preventDefault();
    
    if (e.touches.length === 1 && isDragging) {
        const touch = e.touches[0];
        currentX = touch.clientX - startX;
        currentY = touch.clientY - startY;
        updateImageTransform();
    } else if (e.touches.length === 2 && isZooming) {
        const distance = getDistance(e.touches[0], e.touches[1]);
        const scaleChange = distance / lastDistance;
        currentScale = Math.max(0.2, Math.min(5, currentScale * scaleChange));
        lastDistance = distance;
        updateImageTransform();
    }
}, { passive: false });

document.addEventListener('touchend', function(e) {
    if (e.touches.length === 0) {
        isDragging = false;
        isZooming = false;
    } else if (e.touches.length === 1) {
        isZooming = false;
        isDragging = true;
        const touch = e.touches[0];
        startX = touch.clientX - currentX;
        startY = touch.clientY - currentY;
    }
});

// Event listeners dos botões do modal
document.getElementById('close-crop-modal').addEventListener('click', closeCropModal);
document.getElementById('cancel-crop').addEventListener('click', closeCropModal);

// CORREÇÃO: Lógica de salvamento para incluir o fundo com blur.
document.getElementById('save-crop').addEventListener('click', function() {
    if (!currentImageFile || !cropImageEl.complete || cropImageEl.naturalWidth === 0) return;

    const cropImage = document.getElementById('crop-image');
    const cropCircleDiameter = 200; // Tamanho original do canvas

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    canvas.width = cropCircleDiameter;
    canvas.height = cropCircleDiameter;

    // 1. Desenhar o fundo desfocado, simulando 'object-fit: cover'
    const hRatio = canvas.width / cropImage.naturalWidth;
    const vRatio = canvas.height / cropImage.naturalHeight;
    const ratio = Math.max(hRatio, vRatio);
    const centerShift_x = (canvas.width - cropImage.naturalWidth * ratio) / 2;
    const centerShift_y = (canvas.height - cropImage.naturalHeight * ratio) / 2;
    
    ctx.save();
    ctx.filter = 'blur(15px)'; // Aplica o desfoque via canvas
    ctx.drawImage(cropImage, 0, 0, cropImage.naturalWidth, cropImage.naturalHeight,
                  centerShift_x, centerShift_y, cropImage.naturalWidth * ratio, cropImage.naturalHeight * ratio);
    ctx.restore(); // Limpa o filtro para os próximos desenhos

    // 2. Desenhar a imagem nítida e circular por cima
    const sourceCropSize = cropCircleDiameter / currentScale;
    const sourceCenterX = cropImage.naturalWidth / 2 - (currentX / currentScale);
    const sourceCenterY = cropImage.naturalHeight / 2 - (currentY / currentScale);
    const sx = sourceCenterX - sourceCropSize / 2;
    const sy = sourceCenterY - sourceCropSize / 2;
    
    ctx.save();
    ctx.beginPath();
    ctx.arc(cropCircleDiameter / 2, cropCircleDiameter / 2, cropCircleDiameter / 2, 0, Math.PI * 2, true);
    ctx.clip(); // Cria a máscara circular
    
    ctx.drawImage(
        cropImage,
        sx, sy, sourceCropSize, sourceCropSize,
        0, 0, cropCircleDiameter, cropCircleDiameter
    );
    ctx.restore();

    // 3. Converter para Blob e atualizar a interface
    canvas.toBlob(function(blob) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const photoDisplay = document.getElementById('profile-photo-display');
            
            if (photoDisplay.tagName === 'DIV') {
                const newImg = document.createElement('img');
                newImg.id = 'profile-photo-display';
                newImg.className = 'profile-photo';
                newImg.src = e.target.result;
                newImg.alt = 'Foto de Perfil';
                photoDisplay.parentNode.replaceChild(newImg, photoDisplay);
                
                newImg.addEventListener('click', () => document.getElementById('profile-photo-input').click());
            } else {
                photoDisplay.src = e.target.result;
            }
            
            // Converter blob para base64 e enviar como hidden input
            const reader = new FileReader();
            reader.onload = function(e) {
                const base64Data = e.target.result;
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'profile_photo_base64';
                hiddenInput.value = base64Data;
                document.getElementById('edit-profile-form').appendChild(hiddenInput);
            };
            reader.readAsDataURL(blob);
        };
        reader.readAsDataURL(blob);
    }, 'image/jpeg', 1.0); // Qualidade máxima (100%) para preservar qualidade da foto original
    
    closeCropModal();
});


// Fechar modal clicando no overlay
document.getElementById('crop-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCropModal();
    }
});

// Lógica do modal de restrições
let selectedRestrictions = <?php echo json_encode(array_map('strval', $user_selected_restrictions)); ?>;

document.getElementById('open-restrictions-modal').addEventListener('click', function() {
    document.getElementById('restrictions-modal').classList.add('visible');
    const currentCheckboxes = document.querySelectorAll('#modal-restrictions-grid input[type="checkbox"]');
    currentCheckboxes.forEach(cb => {
        cb.checked = selectedRestrictions.includes(cb.value);
    });
});

function closeModal() {
    document.getElementById('restrictions-modal').classList.remove('visible');
}

document.getElementById('close-restrictions-modal').addEventListener('click', closeModal);
document.getElementById('cancel-restrictions').addEventListener('click', closeModal);

document.getElementById('save-restrictions').addEventListener('click', function() {
    const checkboxes = document.querySelectorAll('#modal-restrictions-grid input[type="checkbox"]');
    selectedRestrictions = [];
    checkboxes.forEach(checkbox => {
        if (checkbox.checked) {
            selectedRestrictions.push(checkbox.value);
        }
    });

    const count = selectedRestrictions.length;
    const countText = count === 0 ? 'Nenhuma restrição' :
                     count === 1 ? '1 restrição selecionada' :
                     `${count} restrições selecionadas`;
    document.getElementById('restrictions-selected-count').textContent = countText;

    closeModal();
});

document.getElementById('restrictions-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

document.getElementById('edit-profile-form').addEventListener('submit', function(e) {
    this.querySelectorAll('input[name="dietary_restrictions[]"]').forEach(input => input.remove());
    selectedRestrictions.forEach(restrictionId => {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'dietary_restrictions[]';
        hiddenInput.value = restrictionId;
        this.appendChild(hiddenInput);
    });
});

// Lógica do modal de exercícios
let selectedExercises = [];
let customActivities = [];

function loadCurrentExercises() {
    const currentExercises = document.getElementById('exercise_type').value;
    console.log('Current exercises:', currentExercises);
    
    if (currentExercises && currentExercises !== '0' && currentExercises !== '') {
        const exercises = currentExercises.split(', ');
        exercises.forEach(exercise => {
            const checkbox = document.querySelector(`input[value="${exercise}"]`);
            if (checkbox) {
                checkbox.checked = true;
            } else {
                customActivities.push(exercise);
            }
        });
        renderExerciseTags();
        updateExerciseDurationFields();
    }
}

function renderExerciseTags() {
    const activityList = document.getElementById('custom-activities-list');
    activityList.innerHTML = '';
    customActivities.forEach(activity => {
        const tag = document.createElement('div');
        tag.className = 'activity-tag';
        tag.textContent = activity;
        const removeBtn = document.createElement('button');
        removeBtn.className = 'remove-tag';
        removeBtn.innerHTML = '&times;';
        removeBtn.onclick = () => { 
            customActivities = customActivities.filter(item => item !== activity); 
            renderExerciseTags(); 
            updateExerciseDurationFields(); 
        };
        tag.appendChild(removeBtn);
        activityList.appendChild(tag);
    });
    document.getElementById('custom-activities-hidden-input').value = customActivities.join(',');
    document.getElementById('other-activity-btn').classList.toggle('active', customActivities.length > 0);
    updateExerciseDurationFields();
}

function updateExerciseDurationFields() {
    selectedExercises = [];
    
    const allExerciseCheckboxes = document.querySelectorAll('#exercise-options-wrapper input[type="checkbox"]');
    allExerciseCheckboxes.forEach(checkbox => {
        if (checkbox.checked && checkbox.id !== 'ex-none') {
            selectedExercises.push(checkbox.value);
        }
    });
    
    selectedExercises = selectedExercises.concat(customActivities);
    
    const exerciseDurationWrapper = document.getElementById('exercise-duration-wrapper');
    const exerciseDurationFields = document.getElementById('exercise-duration-fields');
    
    // Sempre manter a seção de duração oculta
    exerciseDurationWrapper.style.display = 'none';
}

function renderExerciseDurationFields() {
    const exerciseDurationFields = document.getElementById('exercise-duration-fields');
    exerciseDurationFields.innerHTML = '';
    
    selectedExercises.forEach(exercise => {
        const durationItem = document.createElement('div');
        durationItem.className = 'exercise-duration-item';
        
        durationItem.innerHTML = `
            <div class="exercise-name">${exercise}</div>
            <div class="duration-input-group">
                <input type="number" name="exercise_duration[${exercise}]" min="15" max="300" value="60" required>
                <span class="duration-unit">min</span>
            </div>
        `;
        
        exerciseDurationFields.appendChild(durationItem);
    });
}

function addCustomActivity() {
    const activityInput = document.getElementById('custom-activity-input');
    const newActivity = activityInput.value.trim();
    if (newActivity && !customActivities.includes(newActivity)) {
        customActivities.push(newActivity);
        activityInput.value = '';
        renderExerciseTags();
    }
    activityInput.focus();
}

function saveExercises() {
    const exercises = [];
    
    const allExerciseCheckboxes = document.querySelectorAll('#exercise-options-wrapper input[type="checkbox"]');
    allExerciseCheckboxes.forEach(checkbox => {
        if (checkbox.checked && checkbox.id !== 'ex-none') {
            exercises.push(checkbox.value);
        }
    });
    
    exercises.push(...customActivities);
    
    const exerciseString = exercises.join(', ');
    document.getElementById('exercise_type').value = exerciseString;
    document.getElementById('exercise-display').textContent = exerciseString || 'Nenhum exercício selecionado';
    document.getElementById('exercise-modal').classList.remove('active');
}

// Event listeners para exercícios
document.getElementById('edit-exercises-btn').addEventListener('click', () => {
    console.log('Edit exercises button clicked');
    const modal = document.getElementById('exercise-modal');
    console.log('Modal element:', modal);
    if (modal) {
        loadCurrentExercises();
        modal.classList.add('active');
        console.log('Modal should be active now');
    } else {
        console.error('Exercise modal not found!');
    }
});

document.getElementById('close-exercise-modal').addEventListener('click', () => {
    document.getElementById('exercise-modal').classList.remove('active');
});

document.getElementById('cancel-exercise-modal').addEventListener('click', () => {
    document.getElementById('exercise-modal').classList.remove('active');
});

document.getElementById('save-exercise-modal').addEventListener('click', saveExercises);

document.getElementById('other-activity-btn').addEventListener('click', () => {
    document.getElementById('custom-activity-modal').classList.add('active');
});

document.getElementById('close-custom-modal').addEventListener('click', () => {
    document.getElementById('custom-activity-modal').classList.remove('active');
});

document.getElementById('add-activity-btn').addEventListener('click', addCustomActivity);

document.getElementById('custom-activity-input').addEventListener('keypress', (e) => { 
    if (e.key === 'Enter') { 
        e.preventDefault(); 
        addCustomActivity(); 
    } 
});

document.getElementById('ex-none').addEventListener('change', function() {
    if (this.checked) {
        const allExerciseCheckboxes = document.querySelectorAll('#exercise-options-wrapper input[type="checkbox"]');
        allExerciseCheckboxes.forEach(checkbox => {
            if (checkbox.id !== 'ex-none') {
                checkbox.checked = false;
            }
        });
        customActivities = [];
        renderExerciseTags();
    }
    updateExerciseDurationFields();
});

const allExerciseCheckboxes = document.querySelectorAll('#exercise-options-wrapper input[type="checkbox"]');
allExerciseCheckboxes.forEach(checkbox => {
    if (checkbox.id !== 'ex-none') {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                document.getElementById('ex-none').checked = false;
            }
            updateExerciseDurationFields();
        });
    }
});
</script>

<?php 
require_once APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php'; 
?>