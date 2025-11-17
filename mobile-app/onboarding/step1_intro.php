<?php
// onboarding/step1_intro.php
require_once '../includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Limpa dados antigos de onboarding, mas mantém nome/email do registro inicial
$temp_name = $_SESSION['user_name'] ?? null;
$temp_email = $_SESSION['email'] ?? null;
$_SESSION['onboarding_data'] = [];
if ($temp_name) $_SESSION['onboarding_data']['name'] = $temp_name;
if ($temp_email) $_SESSION['onboarding_data']['email'] = $temp_email;

$show_support_widget = false; 
$page_title = "Calcular Meta";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#101010">
    <title><?php echo htmlspecialchars($page_title); ?> - ShapeFIT</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        :root { --bg-color: #101010; --primary-orange-gradient: linear-gradient(45deg, #FFAE00, #F83600); --text-primary: #F5F5F5; --text-secondary: #A3A3A3; --font-family: 'Montserrat', sans-serif; }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html { height: 100%; width: 100%; overflow: hidden; background-color: var(--bg-color); overscroll-behavior-y: contain; }
        body { position: fixed; top: 0; left: 0; width: 100%; height: calc(var(--vh, 1vh) * 100); overflow: hidden; background-color: var(--bg-color); overscroll-behavior-y: contain; color: var(--text-primary); font-family: var(--font-family); display: flex; justify-content: center; align-items: center; }
        .app-container { width: 100%; max-width: 480px; height: 100%; display: flex; flex-direction: column; padding: 24px; text-align: left; }
        .content-wrapper { flex-grow: 1; display: flex; flex-direction: column; justify-content: center; }
        .page-title { font-size: 32px; font-weight: 700; margin-bottom: 10px; line-height: 1.2; }
        .page-subtitle { color: var(--text-secondary); font-size: 1rem; margin-bottom: 50px; font-weight: 400; }
        .btn-primary { background-image: var(--primary-orange-gradient); color: var(--text-primary); border: none; border-radius: 16px; padding: 16px 24px; font-size: 1.1rem; font-weight: 600; cursor: pointer; width: 100%; text-decoration: none; text-align: center; display: block; }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="content-wrapper">
            <h1 class="page-title">Vamos calcular<br>sua meta?</h1>
            <p class="page-subtitle">Preciso que você responda algumas perguntas.</p>
            <a href="<?php echo BASE_APP_URL; ?>/onboarding/step4_objective.php" class="btn-primary">Continuar</a>
        </div>
    </div>
    <script>
        function setRealViewportHeight() { const vh = window.innerHeight * 0.01; document.documentElement.style.setProperty('--vh', `${vh}px`); }
        window.addEventListener('resize', setRealViewportHeight); setRealViewportHeight();
        document.body.addEventListener('touchmove', function(event) { event.preventDefault(); }, { passive: false });
    </script>
</body>
</html>