<?php
/**
 * index.php - Página de entrada principal do ShapeFit
 * 
 * Lógica de redirecionamento:
 * - Se usuário NÃO está logado → redireciona para /auth/login.php
 * - Se usuário está logado E completou onboarding → redireciona para /main_app.php
 * - Se usuário está logado MAS NÃO completou onboarding → redireciona para /onboarding/onboarding.php
 */

// Iniciar output buffering para evitar erros de headers já enviados
ob_start();

// Carregar configurações e autenticação
try {
    require_once 'includes/config.php';
    require_once 'includes/auth.php';
} catch (Exception $e) {
    error_log("Erro ao carregar includes em index.php: " . $e->getMessage());
    // Em caso de erro crítico, tenta redirecionar para login
    ob_end_clean();
    header("Location: /auth/login.php");
    exit();
}

// Verificar se usuário está logado
if (isLoggedIn()) {
    // Usuário está logado - verificar status do onboarding
    try {
        require_once 'includes/db.php';
        
        // Verificar se a conexão foi estabelecida
        if (!isset($conn) || !$conn) {
            throw new Exception("Conexão com banco de dados não estabelecida");
        }
        
        // Verificar onboarding usando a sessão primeiro (mais rápido)
        if (isset($_SESSION['onboarding_complete'])) {
            // Usar valor da sessão se disponível
            $onboarding_complete = (bool)$_SESSION['onboarding_complete'];
        } else {
            // Se não estiver na sessão, buscar do banco
            $stmt_onboarding_check = $conn->prepare("SELECT onboarding_complete FROM sf_users WHERE id = ?");
            if (!$stmt_onboarding_check) {
                throw new Exception("Erro ao preparar consulta: " . $conn->error);
            }
            
            $user_id = $_SESSION['user_id'];
            $stmt_onboarding_check->bind_param("i", $user_id);
            
            if (!$stmt_onboarding_check->execute()) {
                throw new Exception("Erro ao executar consulta: " . $stmt_onboarding_check->error);
            }
            
            $result = $stmt_onboarding_check->get_result();
            $user_data = $result->fetch_assoc();
            $stmt_onboarding_check->close();
            
            if (!$user_data) {
                // Usuário não encontrado no banco - limpar sessão e redirecionar para login
                session_destroy();
                ob_end_clean();
                header("Location: " . BASE_APP_URL . "/auth/login.php?error=user_not_found");
                exit();
            }
            
            $onboarding_complete = (bool)$user_data['onboarding_complete'];
            // Salvar na sessão para próximas requisições
            $_SESSION['onboarding_complete'] = $onboarding_complete;
        }
        
        // Redirecionar baseado no status do onboarding
        ob_end_clean();
        if ($onboarding_complete) {
            header("Location: " . BASE_APP_URL . "/main_app.php");
        } else {
            header("Location: " . BASE_APP_URL . "/onboarding/onboarding.php");
        }
        exit();
        
    } catch (Exception $e) {
        // Erro ao verificar onboarding - logar e redirecionar para login
        error_log("Erro em index.php ao verificar onboarding: " . $e->getMessage());
        ob_end_clean();
        header("Location: " . BASE_APP_URL . "/auth/login.php?error=system_error");
        exit();
    }
} else {
    // Usuário NÃO está logado - redirecionar para login
    ob_end_clean();
    header("Location: " . BASE_APP_URL . "/auth/login.php");
    exit();
}

// Este código nunca deve ser alcançado, mas por segurança:
ob_end_clean();
header("Location: " . BASE_APP_URL . "/auth/login.php");
exit();
?>