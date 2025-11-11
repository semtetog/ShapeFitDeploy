<?php
// admin/ajax_content_management.php - AJAX endpoint para gerenciamento de conteúdo

// Iniciar output buffering para capturar qualquer saída inesperada
ob_start();

// Handler de erros para garantir que sempre retorne JSON
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() === 0) return false;
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $message]);
    exit;
});

// Handler de exceções não capturadas
set_exception_handler(function($exception) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $exception->getMessage()]);
    exit;
});

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

header('Content-Type: application/json');

$admin_id = $_SESSION['admin_id'] ?? 1;
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'save_content':
            saveContent($conn, $admin_id);
            break;
        case 'get_content':
            getContent($conn, $admin_id);
            break;
        case 'delete_content':
            deleteContent($conn, $admin_id);
            break;
        case 'get_stats':
            getStats($conn, $admin_id);
            break;
        default:
            throw new Exception('Ação não especificada');
    }
} catch (Exception $e) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
    exit;
}

function saveContent($conn, $admin_id) {
    $content_id = (int)($_POST['content_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $content_type = $_POST['content_type'] ?? '';
    $target_type = $_POST['target_type'] ?? 'all';
    $target_id = !empty($_POST['target_id']) ? (int)$_POST['target_id'] : null;
    $status = $_POST['status'] ?? 'draft';
    $content_text = trim($_POST['content_text'] ?? '');
    $categories = isset($_POST['categories']) ? (is_array($_POST['categories']) ? $_POST['categories'] : [$_POST['categories']]) : [];
    
    // Verificar se as colunas target_type, target_id e status existem
    $has_target_type = false;
    $has_target_id = false;
    $has_status = false;
    try {
        $check_target_type = $conn->query("SHOW COLUMNS FROM sf_member_content LIKE 'target_type'");
        if ($check_target_type && $check_target_type->num_rows > 0) {
            $has_target_type = true;
        }
        $check_target_id = $conn->query("SHOW COLUMNS FROM sf_member_content LIKE 'target_id'");
        if ($check_target_id && $check_target_id->num_rows > 0) {
            $has_target_id = true;
        }
        $check_status = $conn->query("SHOW COLUMNS FROM sf_member_content LIKE 'status'");
        if ($check_status && $check_status->num_rows > 0) {
            $has_status = true;
        }
    } catch (Exception $e) {
        // Se não conseguir verificar, assume que não existem
        $has_target_type = false;
        $has_target_id = false;
        $has_status = false;
    }
    
    // Se status não existe, usar 'active' como padrão mas não salvar no banco
    if (!$has_status) {
        $status = 'active'; // Apenas para validação local
    }
    
    // Validar campos obrigatórios
    if (empty($title)) {
        throw new Exception('Título é obrigatório');
    }
    if (empty($content_type)) {
        throw new Exception('Tipo de conteúdo é obrigatório');
    }
    
    // Validar tipos permitidos
    $allowed_types = ['chef', 'supplements', 'videos', 'articles', 'pdf'];
    if (!in_array($content_type, $allowed_types)) {
        throw new Exception('Tipo de conteúdo inválido');
    }
    
    // Validar conteúdo para artigos
    if ($content_type === 'articles' && empty($content_text) && $content_id == 0) {
        throw new Exception('Conteúdo do artigo é obrigatório');
    }
    
    // Processar upload de arquivo
    $file_path = null;
    $file_name = null;
    $file_size = null;
    $mime_type = null;
    
    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        
        // Verificar erros de upload do PHP
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'Arquivo excede o tamanho máximo permitido pelo servidor (upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o tamanho máximo permitido pelo formulário',
                UPLOAD_ERR_PARTIAL => 'Upload parcial do arquivo',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
                UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário não encontrado',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao escrever arquivo no disco',
                UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão'
            ];
            $error_msg = $upload_errors[$file['error']] ?? 'Erro desconhecido no upload (código: ' . $file['error'] . ')';
            throw new Exception($error_msg);
        }
        
        // Validar tipo de arquivo
        $allowed_mime_types = [
            // Imagens
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            // Vídeos
            'video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo', 'video/webm',
            // PDFs
            'application/pdf'
        ];
        
        if (!file_exists($file['tmp_name'])) {
            throw new Exception('Arquivo temporário não encontrado. Verifique as configurações de upload do servidor.');
        }
        
        $file_mime = mime_content_type($file['tmp_name']);
        if ($file_mime === false) {
            $file_mime = $file['type'];
        }
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validar extensão também
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi', 'webm', 'pdf'];
        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Formato de arquivo não permitido. Use: JPG, PNG, GIF, WebP, MP4, MOV, AVI, WebM ou PDF');
        }
        
        // Validar tamanho (máximo 100MB para vídeos, 10MB para outros)
        $max_size = in_array($file_extension, ['mp4', 'mov', 'avi', 'webm']) ? 100 * 1024 * 1024 : 10 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            throw new Exception('Arquivo muito grande. Máximo: ' . ($max_size / (1024 * 1024)) . 'MB');
        }
        
        // Criar diretório de upload
        $upload_dir = APP_ROOT_PATH . '/assets/content/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception('Erro ao criar diretório de upload. Verifique as permissões.');
            }
        }
        
        // Verificar se o diretório é gravável
        if (!is_writable($upload_dir)) {
            throw new Exception('Diretório de upload não tem permissão de escrita. Verifique as permissões.');
        }
        
        // Gerar nome único para o arquivo
        $file_name = $content_id > 0 ? 'content_' . $content_id . '_' . time() : 'content_' . time() . '_' . uniqid();
        $file_name .= '.' . $file_extension;
        $file_path_db = '/assets/content/' . $file_name;
        $file_path_full = $upload_dir . $file_name;
        
        // Mover arquivo
        if (!move_uploaded_file($file['tmp_name'], $file_path_full)) {
            $error = error_get_last();
            throw new Exception('Erro ao fazer upload do arquivo. ' . ($error ? $error['message'] : 'Verifique as permissões do diretório.'));
        }
        
        $file_path = $file_path_db;
        $file_size = $file['size'];
        $mime_type = $file_mime;
    } elseif ($content_type !== 'articles' && $content_id == 0) {
        // Para novos conteúdos (exceto artigos), arquivo é obrigatório
        throw new Exception('Arquivo é obrigatório para este tipo de conteúdo');
    }
    
    // Iniciar transação
    $conn->begin_transaction();
    
    try {
        if ($content_id > 0) {
            // Atualizar conteúdo existente
            if ($file_path) {
                // Buscar arquivo antigo para deletar
                $stmt_old = $conn->prepare("SELECT file_path FROM sf_member_content WHERE id = ? AND admin_id = ?");
                $stmt_old->bind_param("ii", $content_id, $admin_id);
                $stmt_old->execute();
                $old_file = $stmt_old->get_result()->fetch_assoc();
                $stmt_old->close();
                
                if ($old_file && $old_file['file_path']) {
                    $old_file_full = APP_ROOT_PATH . $old_file['file_path'];
                    if (file_exists($old_file_full)) {
                        unlink($old_file_full);
                    }
                }
                
                // Construir query dinamicamente baseado nas colunas existentes
                $update_fields = ["title = ?", "description = ?", "content_type = ?", "file_path = ?", "file_name = ?", "file_size = ?", "mime_type = ?", "content_text = ?"];
                $update_values = [$title, $description, $content_type, $file_path, $file_name, $file_size, $mime_type, $content_text];
                $param_types = "sssssis";
                
                if ($has_target_type && $has_target_id) {
                    $update_fields[] = "target_type = ?";
                    $update_fields[] = "target_id = ?";
                    $update_values[] = $target_type;
                    $update_values[] = $target_id;
                    $param_types .= "ss";
                }
                
                if ($has_status) {
                    $update_fields[] = "status = ?";
                    $update_values[] = $status;
                    $param_types .= "s";
                }
                
                $update_fields[] = "updated_at = NOW()";
                $update_values[] = $content_id;
                $update_values[] = $admin_id;
                $param_types .= "ii";
                
                $sql = "UPDATE sf_member_content SET " . implode(", ", $update_fields) . " WHERE id = ? AND admin_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($param_types, ...$update_values);
            } else {
                // Atualizar sem alterar arquivo (mantém arquivo existente)
                $update_fields = ["title = ?", "description = ?", "content_type = ?", "content_text = ?"];
                $update_values = [$title, $description, $content_type, $content_text];
                $param_types = "ssss";
                
                if ($has_target_type && $has_target_id) {
                    $update_fields[] = "target_type = ?";
                    $update_fields[] = "target_id = ?";
                    $update_values[] = $target_type;
                    $update_values[] = $target_id;
                    $param_types .= "ss";
                }
                
                if ($has_status) {
                    $update_fields[] = "status = ?";
                    $update_values[] = $status;
                    $param_types .= "s";
                }
                
                $update_fields[] = "updated_at = NOW()";
                $update_values[] = $content_id;
                $update_values[] = $admin_id;
                $param_types .= "ii";
                
                $sql = "UPDATE sf_member_content SET " . implode(", ", $update_fields) . " WHERE id = ? AND admin_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($param_types, ...$update_values);
            }
        } else {
            // Criar novo conteúdo
            if (!$file_path && $content_type !== 'articles') {
                throw new Exception('Arquivo é obrigatório para este tipo de conteúdo');
            }
            
            // Construir query dinamicamente baseado nas colunas existentes
            $insert_fields = ["admin_id", "title", "description", "content_type", "file_path", "file_name", "file_size", "mime_type", "content_text"];
            $insert_values = [$admin_id, $title, $description, $content_type, $file_path, $file_name, $file_size, $mime_type, $content_text];
            $param_types = "isssssis";
            $placeholders = ["?", "?", "?", "?", "?", "?", "?", "?", "?"];
            
            if ($has_target_type && $has_target_id) {
                $insert_fields[] = "target_type";
                $insert_fields[] = "target_id";
                $insert_values[] = $target_type;
                $insert_values[] = $target_id;
                $param_types .= "ss";
                $placeholders[] = "?";
                $placeholders[] = "?";
            }
            
            if ($has_status) {
                $insert_fields[] = "status";
                $insert_values[] = $status;
                $param_types .= "s";
                $placeholders[] = "?";
            }
            
            $sql = "INSERT INTO sf_member_content (" . implode(", ", $insert_fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($param_types, ...$insert_values);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Erro ao salvar conteúdo: ' . $stmt->error);
        }
        
        if ($content_id == 0) {
            $content_id = $stmt->insert_id;
        }
        $stmt->close();
        
        // Salvar categorias (sempre deletar e reinserir para garantir consistência)
        // Deletar categorias antigas
        $stmt_del = $conn->prepare("DELETE FROM sf_content_category_relations WHERE content_id = ?");
        $stmt_del->bind_param("i", $content_id);
        $stmt_del->execute();
        $stmt_del->close();
        
        // Inserir novas categorias se houver
        if (!empty($categories)) {
            $stmt_cat = $conn->prepare("INSERT INTO sf_content_category_relations (content_id, category_id) VALUES (?, ?)");
            foreach ($categories as $category_id) {
                $cat_id = (int)$category_id;
                if ($cat_id > 0) {
                    $stmt_cat->bind_param("ii", $content_id, $cat_id);
                    $stmt_cat->execute();
                }
            }
            $stmt_cat->close();
        }
        
        $conn->commit();
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => $content_id > 0 ? 'Conteúdo atualizado com sucesso' : 'Conteúdo criado com sucesso',
            'content_id' => $content_id
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function getContent($conn, $admin_id) {
    $content_id = (int)($_GET['content_id'] ?? $_POST['content_id'] ?? 0);
    
    if ($content_id <= 0) {
        throw new Exception('ID do conteúdo inválido');
    }
    
    // Buscar conteúdo
    $stmt = $conn->prepare("
        SELECT mc.*, a.full_name as author_name
        FROM sf_member_content mc
        LEFT JOIN sf_admins a ON mc.admin_id = a.id
        WHERE mc.id = ? AND mc.admin_id = ?
    ");
    $stmt->bind_param("ii", $content_id, $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        throw new Exception('Conteúdo não encontrado');
    }
    
    $content = $result->fetch_assoc();
    $stmt->close();
    
    // Buscar categorias
    $stmt_cat = $conn->prepare("
        SELECT category_id 
        FROM sf_content_category_relations 
        WHERE content_id = ?
    ");
    $stmt_cat->bind_param("i", $content_id);
    $stmt_cat->execute();
    $categories_result = $stmt_cat->get_result();
    $categories = [];
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row['category_id'];
    }
    $stmt_cat->close();
    
    $content['categories'] = $categories;
    
    ob_clean();
    echo json_encode(['success' => true, 'content' => $content]);
}

function deleteContent($conn, $admin_id) {
    $content_id = (int)($_POST['content_id'] ?? 0);
    
    if ($content_id <= 0) {
        throw new Exception('ID do conteúdo inválido');
    }
    
    // Buscar arquivo para deletar
    $stmt_file = $conn->prepare("SELECT file_path FROM sf_member_content WHERE id = ? AND admin_id = ?");
    $stmt_file->bind_param("ii", $content_id, $admin_id);
    $stmt_file->execute();
    $file_result = $stmt_file->get_result()->fetch_assoc();
    $stmt_file->close();
    
    if ($file_result && $file_result['file_path']) {
        $file_path = APP_ROOT_PATH . $file_result['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Deletar conteúdo (cascata deletará categorias)
    $stmt = $conn->prepare("DELETE FROM sf_member_content WHERE id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $content_id, $admin_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Erro ao deletar conteúdo: ' . $stmt->error);
    }
    
    $stmt->close();
    
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Conteúdo deletado com sucesso']);
}

function getStats($conn, $admin_id) {
    // Total
    $total = $conn->query("SELECT COUNT(*) as count FROM sf_member_content WHERE admin_id = $admin_id")->fetch_assoc()['count'];
    
    // Por status
    $stats_query = "SELECT status, COUNT(*) as count 
                    FROM sf_member_content 
                    WHERE admin_id = $admin_id
                    GROUP BY status";
    $stats_result = $conn->query($stats_query);
    $stats_by_status = ['active' => 0, 'inactive' => 0, 'draft' => 0];
    while ($row = $stats_result->fetch_assoc()) {
        $stats_by_status[$row['status']] = $row['count'];
    }
    
    // Por tipo
    $type_query = "SELECT content_type, COUNT(*) as count 
                   FROM sf_member_content 
                   WHERE admin_id = $admin_id
                   GROUP BY content_type";
    $type_result = $conn->query($type_query);
    $stats_by_type = [];
    while ($row = $type_result->fetch_assoc()) {
        $stats_by_type[$row['content_type']] = $row['count'];
    }
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => $total,
            'active' => $stats_by_status['active'],
            'inactive' => $stats_by_status['inactive'],
            'draft' => $stats_by_status['draft'],
            'by_type' => $stats_by_type
        ]
    ]);
}

