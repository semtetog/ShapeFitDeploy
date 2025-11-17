<?php
// onboarding/step7_restrictions_ask.php
require_once '../includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$errors = [];
$has_restrictions = $_SESSION['onboarding_data']['has_dietary_restrictions'] ?? null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['has_restrictions'])) {
        $errors['restrictions'] = "Por favor, selecione uma opção.";
    } else {
        $has_restrictions_bool = ($_POST['has_restrictions'] === '1');
        $_SESSION['onboarding_data']['has_dietary_restrictions'] = $has_restrictions_bool;
        
        if ($has_restrictions_bool) {
            header("Location: " . BASE_APP_URL . "/onboarding/step8_restrictions_select.php");
        } else {
            $_SESSION['onboarding_data']['selected_restrictions'] = [];
            header("Location: " . BASE_APP_URL . "/onboarding/step2_register_details.php");
        }
        exit();
    }
}

$show_support_widget = false; 
$page_title = "Restrições Alimentares";
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
        :root { --bg-color: #101010; --primary-orange-gradient: linear-gradient(45deg, #FFAE00, #F83600); --text-primary: #F5F5F5; --text-secondary: #A3A3A3; --font-family: 'Montserrat', sans-serif; --glass-border: rgba(255, 255, 255, 0.1); --danger-color: #F44336; }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html { height: 100%; width: 100%; overflow: hidden; background-color: var(--bg-color); overscroll-behavior-y: contain; }
        body { position: fixed; top: 0; left: 0; width: 100%; height: calc(var(--vh, 1vh) * 100); overflow: hidden; background-color: var(--bg-color); overscroll-behavior-y: contain; color: var(--text-primary); font-family: var(--font-family); -webkit-font-smoothing: antialiased; display: flex; justify-content: center; align-items: center; }
        .app-container { width: 100%; max-width: 480px; height: 100%; display: flex; flex-direction: column; padding: 24px; position: relative; }
        .header-nav { position: absolute; top: 25px; left: 20px; z-index: 10; }
        .back-button { color: var(--text-secondary); text-decoration: none; font-size: 1.5rem; padding: 5px; transition: color 0.3s ease; }
        .back-button:hover { color: var(--text-primary); }
        .content-wrapper { flex-grow: 1; display: flex; flex-direction: column; justify-content: center; text-align: left; width: 100%; }
        .page-title { font-size: 32px; font-weight: 700; margin-bottom: 10px; line-height: 1.2; }
        .page-subtitle { color: var(--text-secondary); font-size: 1rem; margin-bottom: 40px; font-weight: 400; }
        .selectable-options { display: flex; flex-direction: column; gap: 15px; margin-bottom: 30px; }
        .selectable-options input[type="radio"] { display: none; }
        .selectable-options label { display: block; width: 100%; padding: 18px 24px; font-size: 1rem; font-weight: 500; text-align: center; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--glass-border); border-radius: 16px; color: var(--text-primary); cursor: pointer; transition: all 0.3s ease; }
        .selectable-options input[type="radio"]:checked + label { background-image: var(--primary-orange-gradient); border-color: transparent; font-weight: 600; }
        .btn-primary { background-image: var(--primary-orange-gradient); color: var(--text-primary); border: none; border-radius: 16px; padding: 16px 24px; font-size: 1.1rem; font-weight: 600; cursor: pointer; width: 100%; }
        .error-message { color: var(--danger-color); text-align: center; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="header-nav">
            <a href="<?php echo BASE_APP_URL . '/onboarding/step6_bowel.php'; ?>" class="back-button"><i class="fa-solid fa-arrow-left"></i></a>
        </div>
        <div class="content-wrapper">
            <h1 class="page-title">Você possui alguma<br>restrição alimentar?</h1>
            <p class="page-subtitle">Eu só posso te indicar o que você pode ou prefere comer.</p>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <div class="selectable-options">
                    <div>
                        <input type="radio" id="restr_yes" name="has_restrictions" value="1" <?php if ($has_restrictions === true) echo 'checked'; ?> required>
                        <label for="restr_yes">Sim, possuo restrições</label>
                    </div>
                    <div>
                        <input type="radio" id="restr_no" name="has_restrictions" value="0" <?php if ($has_restrictions === false) echo 'checked'; ?> required>
                        <label for="restr_no">Não, não possuo</label>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Continuar</button>
                <?php if (isset($errors['restrictions'])): ?><p class="error-message"><?php echo htmlspecialchars($errors['restrictions']); ?></p><?php endif; ?>
            </form>
        </div>
    </div>
    <script>
        function setRealViewportHeight() { const vh = window.innerHeight * 0.01; document.documentElement.style.setProperty('--vh', `${vh}px`); }
        window.addEventListener('resize', setRealViewportHeight); setRealViewportHeight();
        document.body.addEventListener('touchmove', function(event) { event.preventDefault(); }, { passive: false });
        (function() { const i=document.querySelectorAll('input,button'); i.forEach(i=>{i.addEventListener('focusin',()=>{setTimeout(()=>{window.scrollTo(0,0)},0)}),i.addEventListener('blur',()=>{window.scrollTo(0,0)})})})();
    </script>
</body>
</html>