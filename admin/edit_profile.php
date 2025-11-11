<?php
// admin/edit_profile.php - Página para editar perfil do admin

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$admin_id = $_SESSION['admin_id'];
$success_message = '';
$error_message = '';

// Buscar dados do admin (verificar quais colunas existem)
$admin_data = null;

// Verificar quais colunas existem na tabela
$columns_result = $conn->query("SHOW COLUMNS FROM sf_admins");
$available_columns = [];
if ($columns_result) {
    while ($row = $columns_result->fetch_assoc()) {
        $available_columns[] = $row['Field'];
    }
}

$has_profile_image = in_array('profile_image_filename', $available_columns);
$has_full_name = in_array('full_name', $available_columns);
$has_name = in_array('name', $available_columns);
$has_username = in_array('username', $available_columns);
$has_email = in_array('email', $available_columns);

$name_field = $has_full_name ? 'full_name' : ($has_name ? 'name' : 'full_name');
$select_fields = ["id", "$name_field as full_name"];

if ($has_username) {
    $select_fields[] = "username";
}
if ($has_email) {
    $select_fields[] = "email";
}
if ($has_profile_image) {
    $select_fields[] = "profile_image_filename";
}

$select_sql = "SELECT " . implode(", ", $select_fields) . " FROM sf_admins WHERE id = ?";
$stmt = $conn->prepare($select_sql);
if ($stmt) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $admin_data = $result->fetch_assoc();
    }
    $stmt->close();
}

if (!$admin_data) {
    header("Location: " . BASE_ADMIN_URL . "/dashboard.php");
    exit();
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $remove_photo = isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1';
    
    if (empty($full_name)) {
        $error_message = 'Nome é obrigatório';
    } else {
        try {
            $conn->begin_transaction();
            
            // Verificar qual campo de nome usar
            $columns_result = $conn->query("SHOW COLUMNS FROM sf_admins");
            $available_columns = [];
            if ($columns_result) {
                while ($row = $columns_result->fetch_assoc()) {
                    $available_columns[] = $row['Field'];
                }
            }
            $has_full_name = in_array('full_name', $available_columns);
            $has_name = in_array('name', $available_columns);
            $name_field = $has_full_name ? 'full_name' : ($has_name ? 'name' : 'full_name');
            
            // Atualizar nome
            $update_fields = ["$name_field = ?"];
            $update_values = [$full_name];
            $param_types = "s";
            
            // Atualizar senha se fornecida
            if (!empty($new_password)) {
                if ($new_password !== $confirm_password) {
                    throw new Exception('As senhas não coincidem');
                }
                if (strlen($new_password) < 6) {
                    throw new Exception('A senha deve ter pelo menos 6 caracteres');
                }
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_fields[] = "password_hash = ?";
                $update_values[] = $password_hash;
                $param_types .= "s";
            }
            
            // Verificar se coluna profile_image_filename existe
            $columns_result = $conn->query("SHOW COLUMNS FROM sf_admins");
            $available_columns = [];
            if ($columns_result) {
                while ($row = $columns_result->fetch_assoc()) {
                    $available_columns[] = $row['Field'];
                }
            }
            $has_profile_image = in_array('profile_image_filename', $available_columns);
            
            // Remover foto se solicitado
            if ($has_profile_image && $remove_photo) {
                $upload_dir = APP_ROOT_PATH . '/assets/images/users/';
                if (!empty($admin_data['profile_image_filename'])) {
                    $old_file = $upload_dir . $admin_data['profile_image_filename'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                // Usar NULL diretamente na query, não via bind_param
                $update_fields[] = "profile_image_filename = NULL";
            }
            // Upload de nova foto
            elseif ($has_profile_image && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_image'];
                
                // Validar que é uma imagem
                $image_mime = mime_content_type($file['tmp_name']);
                if ($image_mime === false) {
                    $image_mime = $file['type'];
                }
                
                if (!str_starts_with($image_mime, 'image/')) {
                    throw new Exception('Arquivo deve ser uma imagem válida');
                }
                
                // Validar tamanho (máximo 5MB)
                if ($file['size'] > 5 * 1024 * 1024) {
                    throw new Exception('Imagem muito grande. Máximo: 5MB');
                }
                
                // Criar diretório se não existir
                $upload_dir = APP_ROOT_PATH . '/assets/images/users/';
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        throw new Exception('Erro ao criar diretório de upload');
                    }
                }
                
                // Deletar foto antiga se existir
                if (!empty($admin_data['profile_image_filename'])) {
                    $old_file = $upload_dir . $admin_data['profile_image_filename'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                
                // Gerar nome único para a imagem
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $new_filename = 'admin_' . $admin_id . '_' . time() . '_' . uniqid() . '.' . $extension;
                $file_path = $upload_dir . $new_filename;
                
                if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                    throw new Exception('Erro ao fazer upload da imagem');
                }
                
                $update_fields[] = "profile_image_filename = ?";
                $update_values[] = $new_filename;
                $param_types .= "s";
            }
            
            $update_values[] = $admin_id;
            $param_types .= "i";
            
            $sql = "UPDATE sf_admins SET " . implode(", ", $update_fields) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception('Erro ao preparar query: ' . $conn->error);
            }
            
            $stmt->bind_param($param_types, ...$update_values);
            if (!$stmt->execute()) {
                throw new Exception('Erro ao atualizar perfil: ' . $stmt->error);
            }
            
            $stmt->close();
            
            // Atualizar sessão
            $_SESSION['admin_name'] = $full_name;
            
            $conn->commit();
            $success_message = 'Perfil atualizado com sucesso!';
            
            // Recarregar dados do admin
            $columns_result = $conn->query("SHOW COLUMNS FROM sf_admins");
            $available_columns = [];
            if ($columns_result) {
                while ($row = $columns_result->fetch_assoc()) {
                    $available_columns[] = $row['Field'];
                }
            }
            
            $has_profile_image = in_array('profile_image_filename', $available_columns);
            $has_full_name = in_array('full_name', $available_columns);
            $has_name = in_array('name', $available_columns);
            $has_username = in_array('username', $available_columns);
            $has_email = in_array('email', $available_columns);
            
            $name_field = $has_full_name ? 'full_name' : ($has_name ? 'name' : 'full_name');
            $select_fields = ["id", "$name_field as full_name"];
            
            if ($has_username) {
                $select_fields[] = "username";
            }
            if ($has_email) {
                $select_fields[] = "email";
            }
            if ($has_profile_image) {
                $select_fields[] = "profile_image_filename";
            }
            
            $select_sql = "SELECT " . implode(", ", $select_fields) . " FROM sf_admins WHERE id = ?";
            $stmt = $conn->prepare($select_sql);
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $admin_data = $result->fetch_assoc();
            }
            $stmt->close();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

$page_title = "Editar Perfil";

require_once __DIR__ . '/includes/header.php';

// Preparar dados da foto
$admin_name = $admin_data['full_name'];
$has_photo = false;
$avatar_url = '';

if (!empty($admin_data['profile_image_filename']) && file_exists(APP_ROOT_PATH . '/assets/images/users/' . $admin_data['profile_image_filename'])) {
    $avatar_url = BASE_APP_URL . '/assets/images/users/' . htmlspecialchars($admin_data['profile_image_filename']);
    $has_photo = true;
}

$name_parts = explode(' ', trim($admin_name));
$initials = count($name_parts) > 1 
    ? strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1)) 
    : (!empty($name_parts[0]) ? strtoupper(substr($name_parts[0], 0, 2)) : 'AD');
?>

<style>
/* Estilos consistentes com o painel admin */
.edit-profile-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
}

.page-header {
    margin-bottom: 2rem;
}

.page-header h1 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    font-family: 'Montserrat', sans-serif;
}

.profile-card {
    background: linear-gradient(135deg, rgba(30, 30, 30, 0.98) 0%, rgba(20, 20, 20, 0.98) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.profile-photo-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.profile-photo-wrapper {
    position: relative;
    cursor: pointer;
    transition: all 0.3s ease;
}

.profile-photo-wrapper:hover {
    transform: scale(1.05);
}

.profile-photo-wrapper:hover .photo-overlay {
    opacity: 1;
}

.profile-photo {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    border: 3px solid var(--accent-orange);
    object-fit: cover;
    display: block;
    background: rgba(255, 107, 0, 0.1);
}

.profile-photo-placeholder {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    border: 3px solid var(--accent-orange);
    background: rgba(255, 107, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    font-weight: 700;
    color: var(--accent-orange);
    font-family: 'Montserrat', sans-serif;
}

.photo-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.photo-overlay i {
    color: white;
    font-size: 2rem;
}

.photo-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    justify-content: center;
}

.btn-photo-action {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-family: 'Montserrat', sans-serif;
    border: none;
}

.btn-change-photo {
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.3);
    color: var(--accent-orange);
}

.btn-change-photo:hover {
    background: rgba(255, 107, 0, 0.2);
    border-color: var(--accent-orange);
}

.btn-remove-photo {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #EF4444;
}

.btn-remove-photo:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: #EF4444;
}

/* Form styles - igual content_management.php */
.challenge-form-group {
    margin-bottom: 1rem;
}

.challenge-form-group:last-child {
    margin-bottom: 0;
}

.challenge-form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-primary);
    font-family: 'Montserrat', sans-serif;
}

.challenge-form-group label:has(+ input[required])::after,
.challenge-form-group label:has(+ textarea[required])::after,
.challenge-form-group label:has(+ select[required])::after {
    content: ' *';
    color: var(--accent-orange);
    margin-left: 0.25rem;
}

.challenge-form-group label:has(span[style*="accent-orange"])::after {
    content: none;
}

.challenge-form-input,
.challenge-form-textarea {
    width: 100%;
    padding: 0.625rem 0.875rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
    box-sizing: border-box;
}

.challenge-form-input:focus,
.challenge-form-textarea:focus {
    outline: none;
    border-color: var(--accent-orange);
    background: rgba(255, 255, 255, 0.08);
}

.challenge-form-input[type="file"] {
    padding: 0.75rem;
    cursor: pointer;
}

.challenge-form-input[type="file"]::file-selector-button {
    padding: 0.5rem 1rem;
    background: rgba(255, 107, 0, 0.1);
    border: 1px solid rgba(255, 107, 0, 0.3);
    border-radius: 8px;
    color: var(--accent-orange);
    cursor: pointer;
    font-weight: 600;
    margin-right: 1rem;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
}

.challenge-form-input[type="file"]::file-selector-button:hover {
    background: rgba(255, 107, 0, 0.2);
    border-color: var(--accent-orange);
}

.challenge-form-group small {
    display: block;
    margin-top: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.75rem;
}

.challenge-form-group input[disabled] {
    opacity: 0.6;
    cursor: not-allowed;
    background: rgba(255, 255, 255, 0.03);
}

.form-footer {
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    flex-wrap: wrap;
    margin-top: 2rem;
}

.form-footer button,
.form-footer a {
    padding: 0.625rem 1.25rem;
    border-radius: 10px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    text-decoration: none;
    border: none;
}

.form-footer .btn-cancel {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-secondary);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.form-footer .btn-cancel:hover {
    background: rgba(255, 255, 255, 0.08);
    color: var(--text-primary);
}

.form-footer .btn-save {
    background: linear-gradient(135deg, #FF6600, #FF8533);
    color: white;
    border: none;
}

.form-footer .btn-save:hover {
    background: linear-gradient(135deg, #FF8533, #FF6600);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 102, 0, 0.3);
}

.alert {
    padding: 1rem 1.25rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.875rem;
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
}

.alert-success {
    background: rgba(76, 175, 80, 0.15);
    border: 1px solid rgba(76, 175, 80, 0.3);
    color: #4CAF50;
}

.alert-error {
    background: rgba(244, 67, 54, 0.15);
    border: 1px solid rgba(244, 67, 54, 0.3);
    color: #F44336;
}

.hidden-file-input {
    display: none;
}
</style>

<div class="content-wrapper">
    <div class="edit-profile-page">
        <div class="page-header">
            <h1>Editar Perfil</h1>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" class="profile-card">
            <!-- Seção de Foto -->
            <div class="profile-photo-section">
                <div class="profile-photo-wrapper" onclick="document.getElementById('profile_image').click()" title="Clique para trocar a foto">
                    <?php if ($has_photo): ?>
                        <img src="<?php echo $avatar_url; ?>" alt="<?php echo htmlspecialchars($admin_name); ?>" class="profile-photo" id="profilePhotoDisplay">
                    <?php else: ?>
                        <div class="profile-photo-placeholder" id="profilePhotoDisplay"><?php echo $initials; ?></div>
                    <?php endif; ?>
                    <div class="photo-overlay">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
                
                <div class="photo-actions">
                    <button type="button" class="btn-photo-action btn-change-photo" onclick="document.getElementById('profile_image').click()">
                        <i class="fas fa-camera"></i> Trocar Foto
                    </button>
                    <?php if ($has_photo): ?>
                        <button type="button" class="btn-photo-action btn-remove-photo" onclick="removePhoto()">
                            <i class="fas fa-trash"></i> Remover Foto
                        </button>
                    <?php endif; ?>
                </div>
                
                <input type="file" id="profile_image" name="profile_image" accept="image/*" class="hidden-file-input" onchange="handlePhotoChange(event)">
                <input type="hidden" id="remove_photo" name="remove_photo" value="0">
            </div>
            
            <!-- Campos do Formulário -->
            <div class="challenge-form-group">
                <label for="full_name">Nome Completo <span style="color: var(--accent-orange);">*</span></label>
                <input type="text" id="full_name" name="full_name" class="challenge-form-input" value="<?php echo htmlspecialchars($admin_data['full_name']); ?>" required>
            </div>
            
            <?php if (!empty($admin_data['email'])): ?>
                <div class="challenge-form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" class="challenge-form-input" value="<?php echo htmlspecialchars($admin_data['email']); ?>" disabled>
                    <small>O e-mail não pode ser alterado</small>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($admin_data['username'])): ?>
                <div class="challenge-form-group">
                    <label for="username">Usuário</label>
                    <input type="text" id="username" name="username" class="challenge-form-input" value="<?php echo htmlspecialchars($admin_data['username']); ?>" disabled>
                    <small>O nome de usuário não pode ser alterado</small>
                </div>
            <?php endif; ?>
            
            <div class="challenge-form-group">
                <label for="new_password">Nova Senha</label>
                <input type="password" id="new_password" name="new_password" class="challenge-form-input" placeholder="Deixe em branco para manter a senha atual">
                <small>Mínimo de 6 caracteres</small>
            </div>
            
            <div class="challenge-form-group">
                <label for="confirm_password">Confirmar Nova Senha</label>
                <input type="password" id="confirm_password" name="confirm_password" class="challenge-form-input" placeholder="Confirme a nova senha">
            </div>
            
            <div class="form-footer">
                <a href="<?php echo BASE_ADMIN_URL; ?>/dashboard.php" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Preview da foto ao selecionar
function handlePhotoChange(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Validar tipo
    if (!file.type.startsWith('image/')) {
        alert('Por favor, selecione uma imagem válida');
        event.target.value = '';
        return;
    }
    
    // Validar tamanho (5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('A imagem é muito grande. Máximo: 5MB');
        event.target.value = '';
        return;
    }
    
    // Mostrar preview
    const reader = new FileReader();
    reader.onload = function(e) {
        const display = document.getElementById('profilePhotoDisplay');
        if (display.tagName === 'DIV') {
            // Substituir placeholder por imagem
            const img = document.createElement('img');
            img.src = e.target.result;
            img.alt = 'Foto de Perfil';
            img.className = 'profile-photo';
            img.id = 'profilePhotoDisplay';
            display.parentNode.replaceChild(img, display);
        } else {
            display.src = e.target.result;
        }
        
        // Mostrar botão de remover se não estiver visível
        const removeBtn = document.querySelector('.btn-remove-photo');
        if (!removeBtn) {
            const photoActions = document.querySelector('.photo-actions');
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'btn-photo-action btn-remove-photo';
            removeButton.innerHTML = '<i class="fas fa-trash"></i> Remover Foto';
            removeButton.onclick = removePhoto;
            photoActions.appendChild(removeButton);
        }
        
        // Limpar flag de remoção
        document.getElementById('remove_photo').value = '0';
    };
    reader.readAsDataURL(file);
}

// Remover foto
function removePhoto() {
    if (!confirm('Tem certeza que deseja remover sua foto de perfil?')) {
        return;
    }
    
    const display = document.getElementById('profilePhotoDisplay');
    const adminName = '<?php echo htmlspecialchars($admin_name); ?>';
    const initials = '<?php echo htmlspecialchars($initials); ?>';
    
    // Substituir por placeholder
    const placeholder = document.createElement('div');
    placeholder.className = 'profile-photo-placeholder';
    placeholder.id = 'profilePhotoDisplay';
    placeholder.textContent = initials;
    display.parentNode.replaceChild(placeholder, display);
    
    // Limpar input de arquivo
    document.getElementById('profile_image').value = '';
    
    // Marcar para remoção
    document.getElementById('remove_photo').value = '1';
    
    // Remover botão de remover
    const removeBtn = document.querySelector('.btn-remove-photo');
    if (removeBtn) {
        removeBtn.remove();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
