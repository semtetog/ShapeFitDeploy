<?php
// Definir fuso horário do Brasil
date_default_timezone_set('America/Sao_Paulo');

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';


$user_id = $_SESSION['user_id'];
$page_title = "Fotos e Medidas";

// Gerar CSRF token se não existir
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Buscar dados do usuário
$user_profile_data = getUserProfileData($conn, $user_id);

// Buscar histórico de medidas organizado por sessão
$history_data = [];
$stmt = $conn->prepare("SELECT * FROM sf_user_measurements WHERE user_id = ? ORDER BY created_at DESC, date_recorded DESC LIMIT 20");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $history_data[] = $row;
}
$stmt->close();

// Buscar último registro para comparação
$last_measurement = !empty($history_data) ? $history_data[0] : null;

// Processar exclusão de foto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_photo') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Token CSRF inválido');
    }
    
    try {
        $measurement_id = intval($_POST['measurement_id']);
        $photo_type = $_POST['photo_type'];
        
        // Buscar nome do arquivo
        $stmt = $conn->prepare("SELECT {$photo_type} FROM sf_user_measurements WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $measurement_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result && $result[$photo_type]) {
            // Deletar arquivo físico
            $file_path = APP_ROOT_PATH . '/uploads/measurements/' . $result[$photo_type];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            
            // Atualizar banco de dados
            $stmt = $conn->prepare("UPDATE sf_user_measurements SET {$photo_type} = NULL WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $measurement_id, $user_id);
            $stmt->execute();
            $stmt->close();
            
            // Verificar se ainda há fotos neste registro
            $stmt_check = $conn->prepare("SELECT photo_front, photo_side, photo_back FROM sf_user_measurements WHERE id = ? AND user_id = ?");
            $stmt_check->bind_param("ii", $measurement_id, $user_id);
            $stmt_check->execute();
            $remaining_photos = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();
            
            // Se não há mais fotos, deletar o registro completo
            if (empty($remaining_photos['photo_front']) && empty($remaining_photos['photo_side']) && empty($remaining_photos['photo_back'])) {
                $stmt_delete = $conn->prepare("DELETE FROM sf_user_measurements WHERE id = ? AND user_id = ?");
                $stmt_delete->bind_param("ii", $measurement_id, $user_id);
                $stmt_delete->execute();
                $stmt_delete->close();
            }
            
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1&msg=" . urlencode("Foto removida com sucesso!"));
            exit();
        }
    } catch (Exception $e) {
        $error_message = "Erro ao remover foto: " . $e->getMessage();
    }
}

// Processar formulário de medidas e fotos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_measurements') {
    error_log("=== DEBUG: Formulário recebido ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    
    // Verificar CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF token inválido. Expected: " . ($_SESSION['csrf_token'] ?? 'null') . ", Received: " . ($_POST['csrf_token'] ?? 'null'));
        die('Token CSRF inválido');
    }
    
    try {
        $date_recorded = $_POST['date_recorded'];
        $weight_kg = floatval($_POST['weight_kg']);
        
        // Sempre criar um novo registro para cada sessão de fotos
        $current_time = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("INSERT INTO sf_user_measurements (user_id, date_recorded, weight_kg, created_at) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isds", $user_id, $date_recorded, $weight_kg, $current_time);
        $stmt->execute();
        $measurement_id = $conn->insert_id;
        $stmt->close();
        
        // Processar upload das fotos
        $upload_dir = APP_ROOT_PATH . '/uploads/measurements/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $photo_types = ['front', 'side', 'back'];
        $uploaded_photos = [];
        
        foreach ($photo_types as $type) {
            if (isset($_FILES["photo_$type"]) && $_FILES["photo_$type"]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES["photo_$type"];
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = "user_{$user_id}_measurement_{$measurement_id}_{$type}." . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $uploaded_photos[$type] = $new_filename;
                }
            }
        }
        
        // Atualizar registro com nomes das fotos
        if (!empty($uploaded_photos)) {
            $photo_front = $uploaded_photos['front'] ?? null;
            $photo_side = $uploaded_photos['side'] ?? null;
            $photo_back = $uploaded_photos['back'] ?? null;
            
            $stmt = $conn->prepare("UPDATE sf_user_measurements SET photo_front = ?, photo_side = ?, photo_back = ? WHERE id = ?");
            $stmt->bind_param("sssi", $photo_front, $photo_side, $photo_back, $measurement_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Redirecionar para evitar reenvio
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=1&msg=" . urlencode("Sessão de fotos salva com sucesso!"));
        exit();
        
    } catch (Exception $e) {
        $error_message = "Erro ao salvar medidas: " . $e->getMessage();
    }
}

require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<style>
/* Estilos da página de medidas - seguindo o padrão da progress.php */
.measurements-page-grid {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 20px 8px 15px 8px; /* Bottom padding reduzido 6x total */
}

/* Header da página */
.page-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.back-button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: var(--text-primary);
    text-decoration: none;
    transition: all 0.3s ease;
}

.back-button:hover {
    background: rgba(255, 255, 255, 0.12);
    transform: translateX(-2px);
}

.page-header h1 {
    font-size: 1.9rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
}

/* Resumo atual */
.current-summary {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.summary-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 20px 16px;
    text-align: center;
    min-height: 120px;
    transition: all 0.3s ease;
}

.summary-card:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.12);
    transform: translateY(-2px);
}

.summary-icon {
    font-size: 2rem;
    margin-bottom: 8px;
    line-height: 1;
    color: var(--accent-orange);
}

.summary-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 8px 0;
}

.summary-value {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0;
    line-height: 1.2;
}

.summary-unit {
    display: block;
    font-size: 0.75rem;
    font-weight: 400;
    color: var(--text-secondary);
    margin-top: 2px;
}

.summary-period {
    color: var(--text-secondary);
    font-size: 0.75rem;
    text-align: center;
    margin-top: 4px;
}

/* Formulário moderno */
.measurements-form {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 20px;
    padding: 24px;
    backdrop-filter: blur(10px);
}

.form-section {
    margin-bottom: 24px;
}

.form-section:last-child {
    margin-bottom: 0;
}

.section-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Upload de fotos */
.photos-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}

.photo-upload {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.05);
    border: 2px dashed rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 20px 12px;
    min-height: 120px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.photo-upload:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(255, 107, 0, 0.5);
}

.photo-upload.has-image {
    border-style: solid;
    border-color: rgba(255, 107, 0, 0.3);
}

.photo-upload input[type="file"] {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.upload-content {
    text-align: center;
    cursor: pointer;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.upload-icon {
    font-size: 1.5rem;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.section-title i {
    color: var(--accent-orange);
    margin-right: 8px;
}

.upload-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.upload-hint {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

/* Pré-visualização de fotos */
.photo-preview {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
    border-radius: 8px;
    overflow: hidden;
}

.photo-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 8px;
}

.photo-preview .upload-icon,
.photo-preview .upload-label,
.photo-preview .upload-hint {
    position: relative;
    z-index: 2;
    background: transparent;
    padding: 0;
    border-radius: 0;
    backdrop-filter: none;
}

/* Quando há imagem, os textos ficam sobrepostos */
.photo-preview:has(img) .upload-icon,
.photo-preview:has(img) .upload-label,
.photo-preview:has(img) .upload-hint {
    position: absolute;
    background: rgba(0, 0, 0, 0.7);
    padding: 4px 8px;
    border-radius: 4px;
    backdrop-filter: blur(5px);
}

/* Posicionamento quando há imagem */
.photo-preview:has(img) .upload-icon {
    top: 8px;
    right: 8px;
    font-size: 1.2rem;
    color: white;
    margin: 0;
    padding: 6px;
}

.photo-preview:has(img) .upload-label {
    bottom: 8px;
    left: 8px;
    color: white;
    font-size: 0.8rem;
    margin: 0;
    padding: 4px 8px;
}

.photo-preview:has(img) .upload-hint {
    bottom: 8px;
    right: 8px;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.7rem;
    margin: 0;
    padding: 2px 6px;
}

/* Estilos normais para slots vazios */
.photo-preview .upload-icon {
    font-size: 1.5rem;
    color: var(--text-secondary);
    margin-bottom: 8px;
}

.photo-preview .upload-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.photo-preview .upload-hint {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

/* Controles de enquadramento */
.photo-crop-controls {
    position: absolute;
    bottom: 35px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 3;
}

.remove-icon-btn {
    width: 36px;
    height: 36px;
    background: rgba(0, 0, 0, 0.6);
    border: none;
    border-radius: 50%;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
}

.photo-upload:hover .remove-icon-btn {
    opacity: 1;
}

.remove-icon-btn:hover {
    background: rgba(220, 53, 69, 0.9);
    color: white;
    transform: scale(1.1);
}

.remove-icon-btn i {
    font-size: 0.9rem;
}


/* Grid de medidas */
.measurements-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-primary);
}

.form-control {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 12px 16px;
    color: var(--text-primary);
    font-size: 0.9rem;
    transition: all 0.3s ease;
    width: 100%;
    box-sizing: border-box;
    max-width: 100%;
    min-width: 0;
    overflow: hidden;
}

/* Específico para inputs de data */
input[type="date"].form-control {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    border-radius: 12px;
}

/* Garantir que o ícone do calendário respeite os cantos arredondados em todos os dispositivos */
.form-group .form-control[type="date"] {
    border-radius: 12px;
    overflow: hidden;
}

.form-control:focus {
    outline: none;
    border-color: rgba(255, 107, 0, 0.5);
    background: rgba(255, 255, 255, 0.08);
}

.form-control::placeholder {
    color: var(--text-secondary);
}

/* Botão de ação */
.form-actions {
    margin-top: 24px;
    text-align: center;
}

.btn-primary {
    background: linear-gradient(135deg, #FF6B00 0%, #FF8533 100%);
    border: none;
    border-radius: 12px;
    padding: 14px 32px;
    color: white;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(255, 107, 0, 0.3);
}

/* Histórico de medidas */
.history-grid {
    display: grid;
    gap: 16px;
}

.history-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 20px;
    transition: all 0.3s ease;
}

.history-card:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.12);
}

.history-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}

.history-date {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
}

.history-content {
    display: grid;
    gap: 16px;
}

.history-photos {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
    gap: 12px;
}

.history-photo {
    aspect-ratio: 1;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.history-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.history-measurements {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
}

.measurement-item {
    text-align: center;
    padding: 8px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
}

.measurement-label {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-bottom: 4px;
}

.measurement-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-primary);
}

/* Estado vazio */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    color: var(--text-secondary);
}

/* Responsividade */
@media (max-width: 768px) {
    .measurements-page-grid {
        padding: 20px 6px 15px 6px;
    }
    
    .page-header h1 {
        font-size: 1.7rem;
    }
    
    .current-summary {
        gap: 12px;
    }
    
    .summary-card {
        padding: 16px 12px;
        min-height: 100px;
    }
    
    .summary-icon {
        font-size: 1.8rem;
        margin-bottom: 6px;
    }
    
    .summary-label {
        font-size: 0.85rem;
    }
    
    .summary-value {
        font-size: 1rem;
    }
    
    .measurements-form {
        padding: 20px;
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        overflow: hidden;
    }
    
    /* Garantir que inputs não saiam do container no mobile */
    .form-control {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }
    
    /* Específico para input de data - forçar largura e manter bordas arredondadas */
    input[type="date"].form-control,
    input[type="date"] {
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
        overflow: hidden !important;
        border-radius: 12px !important;
    }
    
    /* Garantir que o ícone do calendário respeite os cantos arredondados */
    .form-group .form-control[type="date"] {
        border-radius: 12px !important;
        overflow: hidden !important;
    }
    
    /* Garantir que o container pai também respeite as dimensões */
    .form-group {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        overflow: hidden;
    }
    
    .photos-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .photo-upload {
        min-height: 100px;
        padding: 16px 12px;
    }
    
    .remove-icon-btn {
        width: 32px;
        height: 32px;
        font-size: 0.8rem;
    }
    
    .remove-icon-btn i {
        font-size: 0.8rem;
    }
    
    .measurements-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .history-measurements {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    
    /* Modal de enquadramento responsivo */
    .crop-modal-content {
        padding: 16px;
        max-width: 95vw;
    }
    
    .crop-modal-title {
        font-size: 1rem;
        margin-bottom: 16px;
    }
    
    .crop-image {
        max-height: 50vh;
    }
    
    .crop-controls {
        gap: 8px;
    }
    
    .crop-control-btn {
        padding: 10px 12px;
        font-size: 0.8rem;
        gap: 6px;
    }
    
    .crop-control-btn i {
        font-size: 0.8rem;
    }
    
    .history-photo-container {
        position: relative;
        display: inline-block;
    }
    
    .history-photo {
        cursor: pointer;
        transition: transform 0.3s ease;
    }
    
    .history-photo:hover {
        transform: scale(1.05);
    }
    
    .photo-label {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(0, 0, 0, 0.7);
        color: white;
        padding: 4px 8px;
        font-size: 0.8rem;
        text-align: center;
        border-radius: 0 0 8px 8px;
    }
    
    .delete-photo-btn {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        font-size: 0.7rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        z-index: 10;
    }
    
    .delete-photo-btn:hover {
        background: #c82333;
        transform: scale(1.1);
    }
    
    /* Nova Galeria Funcional */
    .gallery-container {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .session-group {
        background: var(--card-bg);
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--border-color);
    }
    
    .session-header {
        background: var(--primary-bg);
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .session-header h4 {
        margin: 0;
        color: var(--primary-text-color);
        font-size: 1.1rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .session-card {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .session-card:last-child {
        border-bottom: none;
    }
    
    .session-info {
        display: flex;
        gap: 15px;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }
    
    .session-time, .session-weight {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 0.9rem;
        color: var(--secondary-text-color);
        background: rgba(255, 255, 255, 0.05);
        padding: 6px 10px;
        border-radius: 6px;
    }
    
    .session-photos {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 12px;
    }
    
    .photo-item-container {
        position: relative;
        aspect-ratio: 1;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .photo-item {
        width: 100%;
        height: 100%;
        position: relative;
        cursor: pointer;
        transition: transform 0.3s ease;
    }
    
    .photo-item:hover {
        transform: scale(1.02);
    }
    
    .photo-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    
    .photo-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
        padding: 8px;
    }
    
    .photo-type {
        color: white;
        font-size: 0.8rem;
        font-weight: 500;
    }
    
    .photo-item-container .delete-photo-btn {
        position: absolute;
        top: 6px;
        right: 6px;
        width: 20px;
        height: 20px;
        font-size: 0.6rem;
        z-index: 10;
    }
    
    @media (max-width: 480px) {
        .session-photos {
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        
        .session-info {
            gap: 10px;
        }
        
        .session-time, .session-weight {
            font-size: 0.8rem;
            padding: 4px 8px;
        }
    }
    
}
</style>

<div class="app-container">
    <section class="measurements-page-grid">
        <!-- Header da página -->
        <header class="page-header">
            <a href="<?php echo BASE_APP_URL; ?>/progress.php" class="back-button">
                <i class="fas fa-chevron-left"></i>
            </a>
            <h1>Fotos e Medidas</h1>
        </header>
        
        <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
            <div class="alert alert-success" style="background: rgba(40, 167, 69, 0.1); border: 1px solid rgba(40, 167, 69, 0.3); color: #28a745; padding: 12px; margin: 10px 0; border-radius: 8px; text-align: center;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['msg'] ?? 'Medidas e fotos salvos com sucesso!'); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error" style="background: rgba(220, 53, 69, 0.1); border: 1px solid rgba(220, 53, 69, 0.3); color: #dc3545; padding: 12px; margin: 10px 0; border-radius: 8px; text-align: center;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>


    <!-- Formulário para novos registros -->
        <form id="measurements-form" method="POST" enctype="multipart/form-data" class="measurements-form">
        <input type="hidden" name="action" value="save_measurements">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <!-- Data do Registro -->
        <div class="form-section">
                <div class="form-group">
                    <label for="date_recorded"><i class="fas fa-calendar-alt"></i> Data do Registro</label>
                    <div class="date-input-wrapper">
                        <input type="date" id="date_recorded" name="date_recorded" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
            </div>
        </div>

            <!-- Fotos de Progresso -->
        <div class="form-section">
                <h3 class="section-title"><i class="fas fa-camera"></i> Fotos de Progresso</h3>
                <div class="photos-grid">
                    <div class="photo-upload">
                        <input type="file" name="photo_front" id="photo_front" accept="image/*">
                        <label for="photo_front" class="upload-content">
                            <div class="photo-preview" id="frontPreview">
                                <div class="upload-icon"><i class="fas fa-camera"></i></div>
                                <div class="upload-label">Frente</div>
                                <div class="upload-hint">Toque para adicionar</div>
                            </div>
                        </label>
                        <div class="photo-crop-controls" id="frontCropControls" style="display: none;">
                            <button type="button" class="remove-icon-btn" onclick="removePhoto('front')" title="Remover foto">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="photo-upload">
                        <input type="file" name="photo_side" id="photo_side" accept="image/*">
                        <label for="photo_side" class="upload-content">
                            <div class="photo-preview" id="sidePreview">
                                <div class="upload-icon"><i class="fas fa-camera"></i></div>
                                <div class="upload-label">Lado</div>
                                <div class="upload-hint">Toque para adicionar</div>
                            </div>
                        </label>
                        <div class="photo-crop-controls" id="sideCropControls" style="display: none;">
                            <button type="button" class="remove-icon-btn" onclick="removePhoto('side')" title="Remover foto">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="photo-upload">
                        <input type="file" name="photo_back" id="photo_back" accept="image/*">
                        <label for="photo_back" class="upload-content">
                            <div class="photo-preview" id="backPreview">
                                <div class="upload-icon"><i class="fas fa-camera"></i></div>
                                <div class="upload-label">Costas</div>
                                <div class="upload-hint">Toque para adicionar</div>
                </div>
                        </label>
                        <div class="photo-crop-controls" id="backCropControls" style="display: none;">
                            <button type="button" class="remove-icon-btn" onclick="removePhoto('back')" title="Remover foto">
                                <i class="fas fa-trash"></i>
                            </button>
                </div>
                </div>
            </div>
        </div>

            <!-- Medidas Corporais -->
        <div class="form-section">
                <h3 class="section-title"><i class="fas fa-ruler"></i> Medidas Corporais</h3>
            <div class="measurements-grid">
                    <div class="form-group">
                        <label for="weight_kg"><i class="fas fa-weight"></i> Peso (kg)</label>
                        <input type="number" id="weight_kg" name="weight_kg" class="form-control" step="0.1" min="0" placeholder="Ex: 70.5" required>
                    </div>
                    <div class="form-group">
                        <label for="neck">Pescoço (cm)</label>
                        <input type="number" step="0.1" name="neck" id="neck" class="form-control" placeholder="Opcional">
                    </div>
                    <div class="form-group">
                        <label for="chest">Tórax (cm)</label>
                        <input type="number" step="0.1" name="chest" id="chest" class="form-control" placeholder="Opcional">
                    </div>
                    <div class="form-group">
                        <label for="waist">Cintura (cm)</label>
                        <input type="number" step="0.1" name="waist" id="waist" class="form-control" placeholder="Opcional">
                    </div>
                    <div class="form-group">
                        <label for="abdomen">Abdômen (cm)</label>
                        <input type="number" step="0.1" name="abdomen" id="abdomen" class="form-control" placeholder="Opcional">
                    </div>
                    <div class="form-group">
                        <label for="hips">Quadril (cm)</label>
                        <input type="number" step="0.1" name="hips" id="hips" class="form-control" placeholder="Opcional">
                    </div>
            </div>
        </div>

        <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Salvar Medidas e Fotos</button>
        </div>
    </form>

        <!-- Galeria de Fotos -->
        <div class="glass-card">
            <h3 class="section-title"><i class="fas fa-images"></i> Galeria de Fotos</h3>
            
            <?php if (empty($history_data)): ?>
                <div class="empty-state">
                    <div style="font-size: 3rem; margin-bottom: 16px;"><i class="fas fa-camera"></i></div>
                    <h4 style="color: var(--text-primary); margin-bottom: 8px;">Nenhuma foto ainda</h4>
                    <p>Adicione suas fotos acima para começar a acompanhar seu progresso!</p>
                </div>
            <?php else: ?>
                <div class="gallery-container">
                    <?php 
                    // Agrupar por data e sessão
                    $grouped_sessions = [];
                    foreach ($history_data as $record) {
                        $date_key = date('Y-m-d', strtotime($record['date_recorded']));
                        $time_key = isset($record['created_at']) ? date('H:i', strtotime($record['created_at'])) : date('H:i');
                        
                        if (!isset($grouped_sessions[$date_key])) {
                            $grouped_sessions[$date_key] = [];
                        }
                        
                        $session_key = $time_key;
                        if (!isset($grouped_sessions[$date_key][$session_key])) {
                            $grouped_sessions[$date_key][$session_key] = [
                                'date' => $record['date_recorded'],
                                'time' => $time_key,
                                'weight' => $record['weight_kg'],
                                'photos' => [],
                                'measurements' => []
                            ];
                        }
                        
                        // Adicionar fotos
                        $photo_types = ['photo_front' => 'Frente', 'photo_side' => 'Lado', 'photo_back' => 'Costas'];
                        foreach ($photo_types as $photo_key => $photo_label) {
                            if ($record[$photo_key]) {
                                $grouped_sessions[$date_key][$session_key]['photos'][] = [
                                    'id' => $record['id'],
                                    'type' => $photo_key,
                                    'label' => $photo_label,
                                    'filename' => $record[$photo_key]
                                ];
                            }
                        }
                        
                        // Adicionar medidas
                        $measurement_fields = ['neck', 'chest', 'waist', 'abdomen', 'hips'];
                        foreach ($measurement_fields as $field) {
                            if ($record[$field]) {
                                $grouped_sessions[$date_key][$session_key]['measurements'][$field] = $record[$field];
                            }
                        }
                    }
                    
                    // Exibir sessões agrupadas (apenas as que têm fotos)
                    foreach ($grouped_sessions as $date_key => $sessions):
                        $date_display = date('d/m/Y', strtotime($date_key));
                        
                        // Filtrar sessões que têm fotos
                        $sessions_with_photos = array_filter($sessions, function($session) {
                            return !empty($session['photos']);
                        });
                        
                        // Só mostrar se há sessões com fotos
                        if (!empty($sessions_with_photos)):
                    ?>
                        <div class="session-group">
                            <div class="session-header">
                                <h4><i class="fas fa-calendar-day"></i> <?php echo $date_display; ?></h4>
                            </div>
                            
                            <?php foreach ($sessions_with_photos as $time_key => $session): ?>
                                <div class="session-card">
                                    <div class="session-info">
                                        <span class="session-time">
                                            <i class="fas fa-clock"></i> <?php echo $session['time']; ?>
                                        </span>
                                        <?php if ($session['weight']): ?>
                                            <span class="session-weight">
                                                <i class="fas fa-weight"></i> <?php echo number_format($session['weight'], 1); ?> kg
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="session-photos">
                                        <?php foreach ($session['photos'] as $photo): ?>
                                            <div class="photo-item-container">
                                                <div class="photo-item" onclick="openPhotoModal('<?php echo BASE_APP_URL; ?>/uploads/measurements/<?php echo htmlspecialchars($photo['filename']); ?>', '<?php echo $photo['label']; ?>', '<?php echo $date_display . ' ' . $session['time']; ?>', <?php echo $photo['id']; ?>, '<?php echo $photo['type']; ?>')">
                                                    <img src="<?php echo BASE_APP_URL; ?>/uploads/measurements/<?php echo htmlspecialchars($photo['filename']); ?>" alt="<?php echo $photo['label']; ?>" onerror="this.style.display='none'">
                                                    <div class="photo-overlay">
                                                        <span class="photo-type"><?php echo $photo['label']; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
// Preview de fotos e controles de enquadramento
document.addEventListener('DOMContentLoaded', function() {
    const photoInputs = document.querySelectorAll('input[type="file"]');
    
    
    photoInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById(input.id.replace('photo_', '') + 'Preview');
                    if (preview) {
                        // Remove elementos existentes
                        preview.innerHTML = '';
                        
                        // Cria a imagem
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.width = '100%';
                        img.style.height = '100%';
                        img.style.objectFit = 'cover';
                        img.style.borderRadius = '8px';
                        
                        // Adiciona overlay com informações
                        const overlay = document.createElement('div');
                        overlay.style.position = 'absolute';
                        overlay.style.top = '0';
                        overlay.style.left = '0';
                        overlay.style.right = '0';
                        overlay.style.bottom = '0';
                        overlay.style.background = 'rgba(0, 0, 0, 0.3)';
                        overlay.style.borderRadius = '8px';
                        overlay.style.display = 'flex';
                        overlay.style.flexDirection = 'column';
                        overlay.style.alignItems = 'center';
                        overlay.style.justifyContent = 'center';
                        overlay.style.color = 'white';
                        
                        const label = document.createElement('div');
                        const photoType = input.id.replace('photo_', '');
                        const labelText = photoType === 'front' ? 'Frente' : 
                                        photoType === 'side' ? 'Lado' : 
                                        photoType === 'back' ? 'Costas' : photoType;
                        label.textContent = labelText;
                        label.style.fontWeight = '600';
                        label.style.fontSize = '0.9rem';
                        label.style.marginBottom = '4px';
                        
                        const hint = document.createElement('div');
                        hint.textContent = 'Toque para alterar';
                        hint.style.fontSize = '0.75rem';
                        hint.style.opacity = '0.8';
                        
                        overlay.appendChild(label);
                        overlay.appendChild(hint);
                        
                        preview.appendChild(img);
                        preview.appendChild(overlay);
                        
                        // Mostra controles de enquadramento
                        const controls = document.getElementById(input.id.replace('photo_', '') + 'CropControls');
                        if (controls) {
                            controls.style.display = 'flex';
                        }
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    });
});


// Função para remover foto
function removePhoto(photoType) {
    const input = document.getElementById('photo_' + photoType);
    const preview = document.getElementById(photoType + 'Preview');
    const controls = document.getElementById(photoType + 'CropControls');
    
    // Limpa o input
    input.value = '';
    
    // Restaura a pré-visualização original
    const labelText = photoType === 'front' ? 'Frente' : 
                     photoType === 'side' ? 'Lado' : 
                     photoType === 'back' ? 'Costas' : photoType;
    
    preview.innerHTML = `
        <div class="upload-icon"><i class="fas fa-camera"></i></div>
        <div class="upload-label">${labelText}</div>
        <div class="upload-hint">Toque para adicionar</div>
    `;
    
    // Esconde os controles
    if (controls) {
        controls.style.display = 'none';
    }
}

// Função para abrir modal de foto
function openPhotoModal(imageSrc, label, date, photoId = null, photoType = null) {
    // Coletar todas as fotos disponíveis
    const allPhotos = [];
    document.querySelectorAll('.photo-item img').forEach((img, index) => {
        if (img.src && !img.src.includes('data:image')) {
            const photoItem = img.closest('.photo-item');
            const onclick = photoItem.getAttribute('onclick');
            if (onclick) {
                const match = onclick.match(/openPhotoModal\('([^']+)',\s*'([^']+)',\s*'([^']+)',\s*(\d+),\s*'([^']+)'\)/);
                if (match) {
                    allPhotos.push({
                        src: match[1],
                        label: match[2],
                        date: match[3],
                        id: match[4],
                        type: match[5]
                    });
                }
            }
        }
    });
    
    // Encontrar o índice da foto clicada
    let currentIndex = allPhotos.findIndex(photo => photo.src === imageSrc);
    if (currentIndex === -1) currentIndex = 0;
    
    // Criar modal dinamicamente
    const modal = document.createElement('div');
    modal.id = 'photoModal';
    modal.className = 'photo-modal';
    modal.innerHTML = `
        <div class="photo-modal-content">
            <div class="photo-modal-header">
                <div class="photo-modal-title">
                    <h3>${label}</h3>
                    <span class="photo-modal-date">${date}</span>
                </div>
                <div class="photo-modal-actions">
                    <button class="photo-modal-close" onclick="closePhotoModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="photo-modal-body">
                <div class="photo-viewer">
                    ${allPhotos.length > 1 ? `<button class="photo-nav-btn photo-prev" onclick="navigatePhoto(-1)">
                        <i class="fas fa-chevron-left"></i>
                    </button>` : ''}
                    <div class="photo-container">
                        <img id="modalPhoto" src="${imageSrc}" alt="${label}">
                    </div>
                    ${allPhotos.length > 1 ? `<button class="photo-nav-btn photo-next" onclick="navigatePhoto(1)">
                        <i class="fas fa-chevron-right"></i>
                    </button>` : ''}
                </div>
            </div>
            <div class="photo-modal-footer">
                <div class="photo-modal-info">
                    ${allPhotos.length > 1 ? `<div class="photo-counter">
                        <span id="photoCounter">${currentIndex + 1} / ${allPhotos.length}</span>
                    </div>` : ''}
                    ${photoId ? `<button class="delete-photo-btn-modal" onclick="deletePhoto(${photoId}, '${photoType}')" title="Excluir foto">
                        <i class="fas fa-trash-alt"></i>
                        <span>Excluir</span>
                    </button>` : ''}
                </div>
            </div>
        </div>
    `;
    
    // Armazenar dados das fotos no modal
    modal.allPhotos = allPhotos;
    modal.currentIndex = currentIndex;
    
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    modal.style.display = 'block';
}

// Função para navegar entre fotos
function navigatePhoto(direction) {
    const modal = document.getElementById('photoModal');
    if (!modal || !modal.allPhotos) return;
    
    modal.currentIndex += direction;
    
    // Circular navigation
    if (modal.currentIndex >= modal.allPhotos.length) {
        modal.currentIndex = 0;
    } else if (modal.currentIndex < 0) {
        modal.currentIndex = modal.allPhotos.length - 1;
    }
    
    const photo = modal.allPhotos[modal.currentIndex];
    const img = document.getElementById('modalPhoto');
    const counter = document.getElementById('photoCounter');
    const title = modal.querySelector('.photo-modal-title h3');
    const date = modal.querySelector('.photo-modal-date');
    const deleteBtn = modal.querySelector('.delete-photo-btn-modal');
    
    if (img) img.src = photo.src;
    if (counter) counter.textContent = `${modal.currentIndex + 1} / ${modal.allPhotos.length}`;
    if (title) title.textContent = photo.label;
    if (date) date.textContent = photo.date;
    
    // Atualizar botão de exclusão
    if (deleteBtn) {
        deleteBtn.setAttribute('onclick', `deletePhoto(${photo.id}, '${photo.type}')`);
    }
}

// Função para fechar modal
function closePhotoModal() {
    const modal = document.getElementById('photoModal');
    if (modal) {
        modal.remove();
        document.body.style.overflow = 'auto';
    }
}

// Função para deletar foto
function deletePhoto(measurementId, photoType) {
    if (confirm('Tem certeza que deseja remover esta foto?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_photo">
            <input type="hidden" name="measurement_id" value="${measurementId}">
            <input type="hidden" name="photo_type" value="${photoType}">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePhotoModal();
    }
});

// Fechar modal clicando fora
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('photo-modal')) {
        closePhotoModal();
    }
});
</script>

<style>
/* ======================================================= */
/* --- MODAL DE FOTOS - FULL SCREEN IMERSIVO --- */
/* ======================================================= */

.photo-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

/* Conteúdo com margens de segurança */
.photo-modal-content {
    position: relative;
    width: 100%;
    height: 100%;
    background: transparent;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    margin: 0;
    border-radius: 0;
    box-shadow: none;
    
    /* Aplicar margens de segurança ao conteúdo */
    padding-top: env(safe-area-inset-top);
    padding-bottom: env(safe-area-inset-bottom);
    padding-left: env(safe-area-inset-left);
    padding-right: env(safe-area-inset-right);
}

/* Header flutuante transparente no topo */
.photo-modal-header {
    position: absolute;
    top: env(safe-area-inset-top);
    left: env(safe-area-inset-left);
    right: env(safe-area-inset-right);
    z-index: 10;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: transparent;
    border-bottom: none;
}

.photo-modal-title h3 {
    margin: 0;
    color: var(--text-primary);
    font-size: 1.2rem;
    font-weight: 600;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
}

.photo-modal-date {
    color: var(--text-secondary);
    font-size: 0.85rem;
    display: block;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
}

/* Rodapé flutuante transparente na base */
.photo-modal-footer {
    position: absolute;
    bottom: env(safe-area-inset-bottom);
    left: env(safe-area-inset-left);
    right: env(safe-area-inset-right);
    z-index: 10;
    padding: 20px;
    background: transparent;
    border-top: none;
}

.photo-modal-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Botão de excluir transparente */
.delete-photo-btn-modal {
    background: rgba(220, 53, 69, 0.8);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 12px;
    padding: 10px 16px;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    font-weight: 600;
    backdrop-filter: blur(10px);
}

.delete-photo-btn-modal:hover {
    background: rgba(220, 53, 69, 1);
    transform: scale(1.05);
}

/* Botão de fechar transparente */
.photo-modal-close {
    background: rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.3);
    font-size: 1.1rem;
    color: var(--text-primary);
    cursor: pointer;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.photo-modal-close:hover {
    background: rgba(0, 0, 0, 0.7);
    transform: scale(1.1);
}

/* Corpo principal que contém apenas a imagem */
.photo-modal-body {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    width: 100%;
    background: transparent;
}

.photo-viewer {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    background: transparent;
}

/* Botões de navegação laterais transparentes */
.photo-nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: var(--text-primary);
    width: 44px;
    height: 44px;
    border-radius: 50%;
    font-size: 1rem;
    cursor: pointer;
    z-index: 10;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.photo-nav-btn:hover {
    background: rgba(0, 0, 0, 0.7);
    transform: translateY(-50%) scale(1.1);
}

.photo-prev {
    left: 15px;
}

.photo-next {
    right: 15px;
}

/* Container da imagem - sem fundo, apenas a foto */
.photo-container {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
}

.photo-container img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: 0;
}

/* Contador de fotos transparente */
.photo-counter {
    background: rgba(0, 0, 0, 0.5);
    color: var(--text-secondary);
    border: 1px solid rgba(255, 255, 255, 0.3);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
}

/* FALLBACKS PARA MARGENS DE SEGURANÇA DO MODAL */
@supports (padding: max(0px)) {
    .photo-modal-content {
        /* Fallback para navegadores que não suportam env() */
        padding-top: max(env(safe-area-inset-top), 0px);
        padding-bottom: max(env(safe-area-inset-bottom), 0px);
        padding-left: max(env(safe-area-inset-left), 0px);
        padding-right: max(env(safe-area-inset-right), 0px);
    }
    
    .photo-modal-header {
        top: max(env(safe-area-inset-top), 0px);
        left: max(env(safe-area-inset-left), 0px);
        right: max(env(safe-area-inset-right), 0px);
    }
    
    .photo-modal-footer {
        bottom: max(env(safe-area-inset-bottom), 0px);
        left: max(env(safe-area-inset-left), 0px);
        right: max(env(safe-area-inset-right), 0px);
    }
}

/* REGRAS ESPECÍFICAS PARA iOS */
@supports (-webkit-touch-callout: none) {
    .photo-modal-content {
        /* iOS específico - garante que as margens sejam respeitadas */
        padding-top: env(safe-area-inset-top);
        padding-bottom: env(safe-area-inset-bottom);
        padding-left: env(safe-area-inset-left);
        padding-right: env(safe-area-inset-right);
    }
    
    .photo-modal-header {
        top: env(safe-area-inset-top);
        left: env(safe-area-inset-left);
        right: env(safe-area-inset-right);
    }
    
    .photo-modal-footer {
        bottom: env(safe-area-inset-bottom);
        left: env(safe-area-inset-left);
        right: env(safe-area-inset-right);
    }
}

/* --- CORREÇÃO DEFINITIVA PARA O CAMPO DE DATA --- */

/* 1. Estiliza a DIV que agora serve como a "caixa" visual */
.date-input-wrapper {
    position: relative;
    display: block;
    width: 100%;
    
    /* Copiamos os estilos visuais do .form-control para cá */
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px; /* O arredondamento agora é no wrapper */
    overflow: hidden; /* Garante que nada de dentro vaze */
}

/* 2. Removemos completamente o visual do input original */
.date-input-wrapper .form-control[type="date"] {
    border: none;
    background: transparent;
    box-shadow: none;
    padding: 12px 16px; /* Mantemos o padding para o texto ficar alinhado */
    width: 100%;
    height: 100%;
    box-sizing: border-box;
}

/* 3. Garante que o estado de foco seja aplicado no wrapper */
.date-input-wrapper:has(.form-control[type="date"]:focus) {
    border-color: rgba(255, 107, 0, 0.5);
    background: rgba(255, 255, 255, 0.08);
}

/* 4. Fallback para navegadores que não suportam :has() */
.date-input-wrapper:focus-within {
    border-color: rgba(255, 107, 0, 0.5);
    background: rgba(255, 255, 255, 0.08);
}

/* 5. Garantir que funcione no mobile também */
@media (max-width: 768px) {
    .date-input-wrapper {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }
    
    .date-input-wrapper .form-control[type="date"] {
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }
}

/* --- CORREÇÃO DEFINITIVA PARA PISCADA PRETA NO IOS --- */

/* 1. Garante que o fundo da página inteira tenha a cor do tema do app.
      Isso faz com que, se houver uma piscada, ela seja da mesma cor
      do app, tornando-a invisível. */
html, body {
    background-color: #1C1C1E; /* Cor de fundo do iOS Dark Mode */
}

/* 2. Aplica a remoção do highlight de toque de forma mais agressiva.
      Usando rgba(0,0,0,0) e !important para garantir que seja aplicado. */
* {
    -webkit-tap-highlight-color: rgba(0,0,0,0) !important;
}

/* 3. Adiciona uma propriedade que ajuda a prevenir falhas de
      renderização em elementos durante animações e transições no iOS. */
.photo-upload {
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
}
</style>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>