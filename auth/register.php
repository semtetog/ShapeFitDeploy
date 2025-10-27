<?php
// --- INÍCIO DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---
require_once '../includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireGuest();

$errors = [];
$submitted_name = '';
$submitted_email = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verificar CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = "Erro de validação. Tente novamente.";
        error_log("CSRF token mismatch on register page.");
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $submitted_name = $name;
        $submitted_email = $email;

        if (empty($name)) $errors['name'] = "Nome é obrigatório.";
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Email inválido.";
        } else {
            $stmt_check_email = $conn->prepare("SELECT id FROM sf_users WHERE email = ?");
            if ($stmt_check_email) {
                $stmt_check_email->bind_param("s", $email);
                $stmt_check_email->execute();
                $result_check_email = $stmt_check_email->get_result();
                if ($result_check_email->num_rows > 0) {
                    $errors['email'] = "Este email já está cadastrado.";
                }
                $stmt_check_email->close();
            } else {
                $errors['form'] = "Erro ao verificar email. Tente mais tarde.";
                error_log("Register error - prepare check_email failed: " . $conn->error);
            }
        }
        
        if (empty($password) || strlen($password) < 6) {
            $errors['password'] = "Senha deve ter pelo menos 6 caracteres.";
        }
        
        if ($password !== $confirm_password) {
            $errors['confirm_password'] = "As senhas não coincidem.";
        }

        if (empty($errors)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt_insert_user = $conn->prepare("INSERT INTO sf_users (name, email, password_hash, onboarding_complete) VALUES (?, ?, ?, FALSE)");
            if ($stmt_insert_user) {
                $stmt_insert_user->bind_param("sss", $name, $email, $password_hash);
                if ($stmt_insert_user->execute()) {
                    regenerateSession();
                    $new_user_id = $stmt_insert_user->insert_id;
                    $_SESSION['user_id'] = $new_user_id;
                    $_SESSION['email'] = $email;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['onboarding_complete'] = false; // <-- CORREÇÃO APLICADA

                    header("Location: " . BASE_APP_URL . "/onboarding/onboarding.php");
                    exit();
                } else {
                    $errors['form'] = "Erro ao registrar. Tente novamente.";
                    error_log("Register error - execute insert_user failed: " . $stmt_insert_user->error);
                }
                $stmt_insert_user->close();
            } else {
                $errors['form'] = "Erro no sistema de registro. Tente mais tarde.";
                error_log("Register error - prepare insert_user failed: " . $conn->error);
            }
        }
    }
}

// Gerar CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token_for_html = $_SESSION['csrf_token'];
$page_title = "Cadastro";
?>
<!-- O RESTANTE DO SEU HTML CONTINUA IGUAL -->
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#101010">
    
    <link rel="manifest" href="/manifest.json">

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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        html {
            height: 100%;
            width: 100%;
            overflow: hidden;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(circle at 90% 90%, var(--bg-gradient-start) 0%, transparent 40%),
                radial-gradient(circle at 10% 10%, var(--bg-gradient-start) 0%, transparent 40%);
            background-attachment: fixed;
            overscroll-behavior-y: contain; 
        }

        html::after {
            content: "";
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800"><filter id="f"><feTurbulence type="fractalNoise" baseFrequency="0.7" numOctaves="1" stitchTiles="stitch"/></filter><rect width="100%" height="100%" filter="url(%23f)"/></svg>');
            opacity: 0.03;
            z-index: -1;
            pointer-events: none;
        }

        body {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: calc(var(--vh, 1vh) * 100);
            overflow: hidden;
            background-color: transparent; /* Alterado para transparente */
            overscroll-behavior-y: contain;
            color: var(--text-primary);
            font-family: var(--font-family);
            -webkit-font-smoothing: antialiased;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .app-container {
            width: 100%;
            max-width: 480px;
            max-height: 100%;
            overflow-y: auto; /* Alterado para auto para permitir rolagem se necessário */
            -webkit-overflow-scrolling: touch;
            padding: calc(40px + env(safe-area-inset-top)) 24px calc(40px + env(safe-area-inset-bottom));
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-logo {
            display: block;
            max-width: 120px;
            height: auto;
            margin-bottom: 50px;
        }

        .page-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-bottom: 40px;
            font-weight: 400;
        }

        .register-form {
            width: 100%;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group .icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1rem;
            transition: color 0.3s ease;
        }

        /* Garante que o ícone fique centralizado em relação ao input,
           mesmo quando a altura do .form-group muda por causa do erro */
        .input-with-icon { position: relative; }
        .input-with-icon .icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
        }

		/* Toggle de visibilidade da senha (olhinho) */
		.input-with-icon.has-toggle .form-control { padding-right: 50px; }
		.toggle-visibility {
			position: absolute;
			right: 18px;
			top: 50%;
			transform: translateY(-50%);
			background: transparent;
			border: none;
			color: var(--text-secondary);
			font-size: 1rem;
			cursor: pointer;
			padding: 4px;
		}
		.toggle-visibility:focus { outline: none; }
		.form-group:focus-within .toggle-visibility { color: var(--accent-orange); }

        .form-control {
            width: 100%;
            padding: 16px 20px 16px 50px;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            color: var(--text-primary);
            transition: border-color 0.3s ease;
            outline: none;
        }
        
        .form-control::placeholder {
            color: var(--text-secondary);
        }

        .form-control:focus {
            border-color: var(--accent-orange);
        }

        .form-control:focus + .icon {
            color: var(--accent-orange);
        }
        /* Usa :focus-within para mudar a cor do ícone quando o input recebe foco */
        .form-group:focus-within .icon { color: var(--accent-orange); }

        .form-control:-webkit-autofill,
        .form-control:-webkit-autofill:hover, 
        .form-control:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--text-primary);
            box-shadow: 0 0 0px 1000px rgba(30, 30, 30, 0.8) inset;
            -webkit-box-shadow: 0 0 0px 1000px rgba(30, 30, 30, 0.8) inset;
            transition: background-color 5000s ease-in-out 0s;
            border-color: var(--glass-border);
        }
        .form-control:-webkit-autofill:focus {
            border-color: var(--accent-orange);
        }

        .error-message {
            color: var(--danger-color);
            font-size: 0.85rem;
            margin-top: 8px;
            text-align: left;
            padding-left: 5px;
            display: block;
        }

        .form-error {
            background-color: rgba(244, 67, 54, 0.1);
            border: 1px solid rgba(244, 67, 54, 0.3);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .btn-primary {
            background-image: var(--primary-orange-gradient);
            background-size: 150% auto;
            color: var(--text-primary);
            border: none;
            border-radius: 16px;
            padding: 16px 24px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s ease, background-position 0.4s ease;
            margin-top: 15px;
        }

        .btn-primary:hover {
            background-position: right center;
        }

        .btn-primary:active {
            transform: scale(0.98);
        }
        
        .link-text {
            color: var(--text-secondary);
            margin-top: 40px;
            font-size: 1rem;
        }

        .link-text a {
            color: var(--accent-orange);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .link-text a:hover {
            color: #ff9e3d;
        }
    </style>
</head>
<body>
    <main class="app-container">
        <img src="<?php echo BASE_ASSET_URL; ?>/assets/images/SHAPE-FIT-LOGO.png" alt="Shape Fit Logo" class="login-logo">
        <h1 class="page-title">Crie sua Conta</h1>
        <p class="page-subtitle">Comece sua jornada no ShapeFIT!</p>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" autocomplete="on" class="register-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_for_html; ?>">
            
            <?php if (isset($errors['form'])): ?>
                <div class="form-error">
                    <?php echo htmlspecialchars($errors['form']); ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <div class="input-with-icon">
                    <i class="fa-solid fa-user icon"></i>
                    <input type="text" name="name" id="name" class="form-control" placeholder="Nome completo" required value="<?php echo htmlspecialchars($submitted_name); ?>" autocomplete="name">
                </div>
                <?php if (isset($errors['name'])): ?><span class="error-message"><?php echo htmlspecialchars($errors['name']); ?></span><?php endif; ?>
            </div>
            
            <div class="form-group">
                <div class="input-with-icon">
                    <i class="fa-solid fa-envelope icon"></i>
                    <input type="email" name="email" id="email" class="form-control" placeholder="Email" required value="<?php echo htmlspecialchars($submitted_email); ?>" autocomplete="email">
                </div>
                <?php if (isset($errors['email'])): ?><span class="error-message"><?php echo htmlspecialchars($errors['email']); ?></span><?php endif; ?>
            </div>
            
			<div class="form-group">
				<div class="input-with-icon has-toggle">
                    <i class="fa-solid fa-lock icon"></i>
					<input type="password" name="password" id="password" class="form-control" placeholder="Senha (mín. 6 caracteres)" required autocomplete="new-password">
					<button type="button" class="toggle-visibility" data-target="password" aria-label="Mostrar senha">
						<i class="fa-solid fa-eye"></i>
					</button>
                </div>
                <?php if (isset($errors['password'])): ?><span class="error-message"><?php echo htmlspecialchars($errors['password']); ?></span><?php endif; ?>
            </div>

			<div class="form-group">
				<div class="input-with-icon has-toggle">
                    <i class="fa-solid fa-lock icon"></i>
					<input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirme a senha" required autocomplete="new-password">
					<button type="button" class="toggle-visibility" data-target="confirm_password" aria-label="Mostrar senha">
						<i class="fa-solid fa-eye"></i>
					</button>
                </div>
                <?php if (isset($errors['confirm_password'])): ?><span class="error-message"><?php echo htmlspecialchars($errors['confirm_password']); ?></span><?php endif; ?>
            </div>
            
            <button type="submit" class="btn-primary">Cadastrar</button>
            
            <p class="link-text">
                Já tem uma conta? <a href="<?php echo BASE_APP_URL; ?>/auth/login.php">Faça login</a>
            </p>
        </form>
    </main>

    <script>
        function setRealViewportHeight() { const vh = window.innerHeight * 0.01; document.documentElement.style.setProperty('--vh', `${vh}px`); }
        window.addEventListener('resize', setRealViewportHeight);
        setRealViewportHeight();
    </script>
    
    <script>
        if (window.navigator.standalone === true) {
            document.addEventListener('click', function(e) {
                var target = e.target;
                while (target && target.nodeName !== 'A') { target = target.parentNode; }
                if (target && target.nodeName === 'A' && target.getAttribute('href') && target.target !== '_blank') {
                    e.preventDefault();
                    window.location.href = target.href;
                }
            }, false);
        }
    </script>

	<script>
		// Toggle de visibilidade de senha
		document.querySelectorAll('.toggle-visibility').forEach(function(button) {
			button.addEventListener('click', function() {
				var inputId = button.getAttribute('data-target');
				var input = document.getElementById(inputId);
				if (!input) return;
				var isPassword = input.getAttribute('type') === 'password';
				input.setAttribute('type', isPassword ? 'text' : 'password');
				var icon = button.querySelector('i');
				if (icon) {
					if (isPassword) {
						icon.classList.remove('fa-eye');
						icon.classList.add('fa-eye-slash');
						button.setAttribute('aria-label', 'Esconder senha');
					} else {
						icon.classList.remove('fa-eye-slash');
						icon.classList.add('fa-eye');
						button.setAttribute('aria-label', 'Mostrar senha');
					}
				}
			});
		});
	</script>
</body>
</html>