<?php
// login.php (VERSÃO CORRIGIDA PARA SALVAR O STATUS NA SESSÃO)

require_once '../includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireGuest();

$errors = [];
$submitted_email = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = "Erro de validação. Tente novamente.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $submitted_email = $email;
        $password = $_POST['password'] ?? '';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Por favor, insira um email válido.";
        }
        if (empty($password)) {
            $errors['password'] = "Por favor, insira sua senha.";
        }

        if (empty($errors)) {
            $stmt_login = $conn->prepare("SELECT id, password_hash, onboarding_complete, name FROM sf_users WHERE email = ?");
            if ($stmt_login) {
                $stmt_login->bind_param("s", $email);
                $stmt_login->execute();
                $result_login = $stmt_login->get_result();
                $user_login = $result_login->fetch_assoc();
                $stmt_login->close();

                if ($user_login && password_verify($password, $user_login['password_hash'])) {
                    regenerateSession();
                    $_SESSION['user_id'] = $user_login['id'];
                    $_SESSION['email'] = $email;
                    $_SESSION['user_name'] = $user_login['name'];
                    
                    // --- LINHA CRÍTICA ADICIONADA AQUI ---
                    // Salva o status do onboarding na sessão para ser usado em outras páginas.
                    $_SESSION['onboarding_complete'] = (bool)$user_login['onboarding_complete'];

                    // O redirecionamento agora é sempre para o app. A lógica do auth.php fará o resto.
                    header("Location: " . BASE_APP_URL . "/main_app.php");
                    exit();

                } else {
                    $errors['form'] = "Email ou senha incorretos.";
                }
            } else {
                $errors['form'] = "Erro no sistema de login. Tente mais tarde.";
                error_log("Login error - prepare failed: " . $conn->error);
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token_for_html = $_SESSION['csrf_token'];
$page_title = "Login";
?>
<!DOCTYPE html>
<!-- O RESTO DO SEU HTML PERMANECE EXATAMENTE IGUAL -->
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    
    <!-- PWA Meta Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="ShapeFIT">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="ShapeFIT">
    <meta name="theme-color" content="#101010">
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="<?php echo BASE_APP_URL; ?>/manifest.json">
    
    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" href="<?php echo BASE_ASSET_URL; ?>/assets/images/app-icon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_ASSET_URL; ?>/assets/images/app-icon-180x180.png">
    <link rel="apple-touch-icon" sizes="152x152" href="<?php echo BASE_ASSET_URL; ?>/assets/images/app-icon.png">
    <link rel="apple-touch-icon" sizes="144x144" href="<?php echo BASE_ASSET_URL; ?>/assets/images/app-icon.png">
    <link rel="apple-touch-icon" sizes="120x120" href="<?php echo BASE_ASSET_URL; ?>/assets/images/app-icon.png">
    <link rel="apple-touch-icon" sizes="114x114" href="<?php echo BASE_ASSET_URL; ?>/assets/images/app-icon.png">
    <link rel="apple-touch-icon" sizes="76x76" href="<?php echo BASE_ASSET_URL; ?>/assets/images/app-icon.png">
    <link rel="apple-touch-icon" sizes="72x72" href="<?php echo BASE_ASSET_URL; ?>/assets/images/app-icon.png">
    <link rel="apple-touch-icon" sizes="60x60" href="<?php echo BASE_ASSET_URL; ?>/assets/images/app-icon.png">
    <link rel="apple-touch-icon" sizes="57x57" href="<?php echo BASE_ASSET_URL; ?>/assets/images/app-icon.png">

    <title><?php echo htmlspecialchars($page_title); ?> - ShapeFIT</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        :root {
            --bg-color: #101010;
            --bg-gradient-start: #222222;
            --glass-border: rgba(255, 255, 255, 0.1);
            --primary-orange-gradient: linear-gradient(45deg, #FFAE00, #F83600);
            --text-primary: #F5F5F5;
            --text-secondary: #A3A3A3;
            --font-family: 'Montserrat', sans-serif;
            --accent-orange: #FF6B00;
            --danger-color: #F44336;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html { height: 100%; width: 100%; overflow: hidden; background-color: var(--bg-color); background-image: radial-gradient(circle at 90% 90%, var(--bg-gradient-start) 0%, transparent 40%), radial-gradient(circle at 10% 10%, var(--bg-gradient-start) 0%, transparent 40%); background-attachment: fixed; overscroll-behavior-y: contain; }
        html::after { content: ""; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800"><filter id="f"><feTurbulence type="fractalNoise" baseFrequency="0.7" numOctaves="1" stitchTiles="stitch"/></filter><rect width="100%" height="100%" filter="url(%23f)"/></svg>'); opacity: 0.03; z-index: -1; pointer-events: none; }
        body { position: fixed; top: 0; left: 0; width: 100%; height: calc(var(--vh, 1vh) * 100); overflow: hidden; background-color: var(--bg-color); overscroll-behavior-y: contain; color: var(--text-primary); font-family: var(--font-family); -webkit-font-smoothing: antialiased; display: flex; justify-content: center; align-items: center; }
        .app-container { width: 100%; max-width: 480px; max-height: 100%; overflow-y: auto; padding: 40px 24px; padding-bottom: calc(40px + env(safe-area-inset-bottom)); display: flex; flex-direction: column; align-items: center; text-align: center; animation: fadeIn 0.6s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .login-logo { display: block; max-width: 120px; height: auto; margin-bottom: 50px; }
        .page-title { font-size: 32px; font-weight: 700; margin-bottom: 40px; }
        .login-form { width: 100%; }
        .form-group { margin-bottom: 20px; position: relative; }
        .form-group .icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); font-size: 1rem; transition: color 0.3s ease; }
        .form-control { width: 100%; padding: 16px 20px 16px 50px; font-size: 1rem; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--glass-border); border-radius: 16px; color: var(--text-primary); transition: border-color 0.3s ease; outline: none; }
        .form-control::placeholder { color: var(--text-secondary); }
        .form-control:focus { border-color: var(--accent-orange); }
        .form-control:focus + .icon { color: var(--accent-orange); }
        .form-control:-webkit-autofill, .form-control:-webkit-autofill:hover, .form-control:-webkit-autofill:focus { -webkit-text-fill-color: var(--text-primary); box-shadow: 0 0 0px 1000px rgba(30, 30, 30, 0.8) inset; -webkit-box-shadow: 0 0 0px 1000px rgba(30, 30, 30, 0.8) inset; transition: background-color 5000s ease-in-out 0s; border-color: var(--glass-border); }
        .form-control:-webkit-autofill:focus { border-color: var(--accent-orange); }
        .error-message { color: var(--danger-color); font-size: 0.85rem; margin-top: 8px; text-align: left; padding-left: 5px; display: block; }
        .form-error { background-color: rgba(244, 67, 54, 0.1); border: 1px solid rgba(244, 67, 54, 0.3); border-radius: 12px; padding: 12px; margin-bottom: 20px; font-size: 0.9rem; }
        .btn-primary { background-image: var(--primary-orange-gradient); background-size: 150% auto; color: var(--text-primary); border: none; border-radius: 16px; padding: 16px 24px; font-size: 1.1rem; font-weight: 600; cursor: pointer; width: 100%; transition: transform 0.2s ease, background-position 0.4s ease; margin-top: 15px; }
        .btn-primary:hover { background-position: right center; }
        .btn-primary:active { transform: scale(0.98); }
        .link-text { color: var(--text-secondary); margin-top: 40px; font-size: 1rem; }
        .link-text a { color: var(--accent-orange); text-decoration: none; font-weight: 600; transition: color 0.3s ease; }
        .link-text a:hover { color: #ff9e3d; }
    </style>
</head>
<body>
    <main class="app-container">
        <img src="<?php echo BASE_ASSET_URL; ?>/assets/images/SHAPE-FIT-LOGO.png" alt="Shape Fit Logo" class="login-logo">
        <h1 class="page-title">Acesse sua conta</h1>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" autocomplete="on" class="login-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_for_html; ?>">
            <?php if (isset($errors['form'])): ?>
                <div class="form-error"><?php echo htmlspecialchars($errors['form']); ?></div>
            <?php endif; ?>
            <div class="form-group">
                <i class="fa-solid fa-envelope icon"></i>
                <input type="email" name="email" id="email" class="form-control" placeholder="Email" required value="<?php echo htmlspecialchars($submitted_email); ?>" autocomplete="email">
                <?php if (isset($errors['email'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['email']); ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <i class="fa-solid fa-lock icon"></i>
                <input type="password" name="password" id="password" class="form-control" placeholder="Senha" required autocomplete="current-password">
                <?php if (isset($errors['password'])): ?>
                    <span class="error-message"><?php echo htmlspecialchars($errors['password']); ?></span>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn-primary">Entrar</button>
            <p class="link-text">
                Não tem uma conta? <a href="<?php echo BASE_APP_URL; ?>/auth/register.php">Cadastre-se</a>
            </p>
        </form>
    </main>
    <script>
        function setRealViewportHeight() { const vh = window.innerHeight * 0.01; document.documentElement.style.setProperty('--vh', `${vh}px`); }
        window.addEventListener('resize', setRealViewportHeight);
        setRealViewportHeight();
        document.body.addEventListener('touchmove', function(event) { event.preventDefault(); }, { passive: false });
        (function preventIOSScroll() {
            const inputs = document.querySelectorAll('input[type="email"], input[type="password"], input[type="text"]');
            inputs.forEach(input => {
                input.addEventListener('focusin', () => { setTimeout(() => { window.scrollTo(0, 0); }, 0); });
                input.addEventListener('blur', () => { window.scrollTo(0, 0); });
            });
        })();
    </script>
</body>
</html>