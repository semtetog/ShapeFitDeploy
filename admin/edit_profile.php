<?php
// admin/edit_profile.php - Página para editar perfil do admin

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$admin_id = $_SESSION['admin_id'];
$success_message = '';
$error_message = '';

// Buscar dados do admin
$admin_data = null;
$stmt = $conn->prepare("SELECT id, full_name, username, email, profile_image_filename FROM sf_admins WHERE id = ?");
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
    
    if (empty($full_name)) {
        $error_message = 'Nome é obrigatório';
    } else {
        try {
            $conn->begin_transaction();
            
            // Atualizar nome
            $update_fields = ["full_name = ?"];
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
            
            // Processar upload de foto
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
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
            $stmt = $conn->prepare("SELECT id, full_name, username, email, profile_image_filename FROM sf_admins WHERE id = ?");
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
?>

<style>
.edit-profile-container {
    max-width: 600px;
    margin: 0 auto;
}

.profile-header {
    display: flex;
    align-items: center;
    gap: 2rem;
    margin-bottom: 2rem;
    padding: 2rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
}

.profile-avatar-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: rgba(255, 107, 0, 0.1);
    border: 3px solid var(--accent-orange);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-avatar-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--accent-orange);
}

.profile-info-section {
    flex: 1;
}

.profile-form {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 2rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-primary);
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="password"],
.form-group input[type="file"] {
    width: 100%;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border);
    border-radius: 8px;
    color: var(--text-primary);
    font-size: 1rem;
}

.form-group input[type="file"] {
    padding: 0.5rem;
    cursor: pointer;
}

.form-group small {
    display: block;
    margin-top: 0.5rem;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: var(--accent-orange);
    color: white;
}

.btn-primary:hover {
    background: #ff8c33;
    transform: translateY(-2px);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-primary);
    border: 1px solid var(--glass-border);
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
}

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background: rgba(76, 175, 80, 0.2);
    border: 1px solid rgba(76, 175, 80, 0.3);
    color: #4CAF50;
}

.alert-error {
    background: rgba(244, 67, 54, 0.2);
    border: 1px solid rgba(244, 67, 54, 0.3);
    color: #F44336;
}
</style>

<div class="content-wrapper">
    <div class="edit-profile-container">
        <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 2rem; color: var(--text-primary);">Editar Perfil</h1>
        
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
        
        <div class="profile-header">
            <div class="profile-avatar-section">
                <div class="profile-avatar">
                    <?php
                    $admin_name = $admin_data['full_name'];
                    $has_photo = false;
                    $avatar_url = '';
                    
                    if (!empty($admin_data['profile_image_filename']) && file_exists(APP_ROOT_PATH . '/assets/images/users/' . $admin_data['profile_image_filename'])) {
                        $avatar_url = BASE_APP_URL . '/assets/images/users/' . htmlspecialchars($admin_data['profile_image_filename']);
                        $has_photo = true;
                    }
                    
                    if ($has_photo):
                    ?>
                        <img src="<?php echo $avatar_url; ?>" alt="<?php echo htmlspecialchars($admin_name); ?>">
                    <?php else: ?>
                        <?php
                        $name_parts = explode(' ', trim($admin_name));
                        $initials = count($name_parts) > 1 
                            ? strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1)) 
                            : (!empty($name_parts[0]) ? strtoupper(substr($name_parts[0], 0, 2)) : 'AD');
                        ?>
                        <div class="profile-avatar-placeholder"><?php echo $initials; ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="profile-info-section">
                <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary);">
                    <?php echo htmlspecialchars($admin_data['full_name']); ?>
                </h2>
                <p style="color: var(--text-secondary); margin: 0;">
                    <?php echo htmlspecialchars($admin_data['email']); ?>
                </p>
            </div>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="profile-form">
            <div class="form-group">
                <label for="full_name">Nome Completo <span style="color: var(--accent-orange);">*</span></label>
                <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($admin_data['full_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin_data['email']); ?>" disabled>
                <small>O e-mail não pode ser alterado</small>
            </div>
            
            <div class="form-group">
                <label for="username">Usuário</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($admin_data['username']); ?>" disabled>
                <small>O nome de usuário não pode ser alterado</small>
            </div>
            
            <div class="form-group">
                <label for="profile_image">Foto de Perfil</label>
                <input type="file" id="profile_image" name="profile_image" accept="image/*">
                <small>Formatos aceitos: JPG, PNG, WebP. Máximo: 5MB</small>
            </div>
            
            <div class="form-group">
                <label for="new_password">Nova Senha</label>
                <input type="password" id="new_password" name="new_password" placeholder="Deixe em branco para manter a senha atual">
                <small>Mínimo de 6 caracteres</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmar Nova Senha</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirme a nova senha">
            </div>
            
            <div class="form-actions">
                <a href="<?php echo BASE_ADMIN_URL; ?>/dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

