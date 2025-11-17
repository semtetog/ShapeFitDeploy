<?php
// onboarding/step8_restrictions_select.php
require_once '../includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['onboarding_data']['has_dietary_restrictions']) || $_SESSION['onboarding_data']['has_dietary_restrictions'] !== true) {
    header("Location: " . BASE_APP_URL . "/onboarding/step7_restrictions_ask.php");
    exit();
}

$selected_ids = $_SESSION['onboarding_data']['selected_restrictions'] ?? [];
$errors = [];
$restriction_options = [];

$result = $conn->query("SELECT id, name, slug FROM sf_dietary_restrictions_options ORDER BY name ASC");
if ($result) { while ($row = $result->fetch_assoc()) { $restriction_options[] = $row; } }
else { $errors['db'] = "Erro ao carregar opções de restrição."; }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $posted_ids = $_POST['restrictions'] ?? [];
    $_SESSION['onboarding_data']['selected_restrictions'] = $posted_ids;
    header("Location: " . BASE_APP_URL . "/onboarding/step2_register_details.php");
    exit();
}

$show_support_widget = false; 
$page_title = "Selecione as Restrições";
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
        .selectable-options { display: flex; flex-direction: column; gap: 15px; margin-bottom: 30px; /* Adicionado para scroll */ overflow-y: auto; max-height: 40vh; }
        .selectable-options input[type="checkbox"] { display: none; }
        .selectable-options label { display: block; width: 100%; padding: 18px 24px; font-size: 1rem; font-weight: 500; text-align: center; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--glass-border); border-radius: 16px; color: var(--text-primary); cursor: pointer; transition: all 0.3s ease; }
        .selectable-options input[type="checkbox"]:checked + label { background-image: var(--primary-orange-gradient); border-color: transparent; font-weight: 600; }
        .btn-primary { background-image: var(--primary-orange-gradient); color: var(--text-primary); border: none; border-radius: 16px; padding: 16px 24px; font-size: 1.1rem; font-weight: 600; cursor: pointer; width: 100%; }
        .error-message { color: var(--danger-color); text-align: center; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="header-nav">
            <a href="<?php echo BASE_APP_URL . '/onboarding/step7_restrictions_ask.php'; ?>" class="back-button"><i class="fa-solid fa-arrow-left"></i></a>
        </div>
        <div class="content-wrapper">
            <h1 class="page-title">Quais são suas<br>restrições?</h1>
            <p class="page-subtitle">Selecione todas as opções que se aplicam a você.</p>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <div class="selectable-options">
                    <?php if (!empty($restriction_options)): ?>
                        <?php foreach ($restriction_options as $option): ?>
                            <div>
                                <input type="checkbox" id="restr_<?php echo $option['id']; ?>" name="restrictions[]" value="<?php echo $option['id']; ?>" <?php if(in_array($option['id'], $selected_ids)) echo 'checked'; ?>>
                                <label for="restr_<?php echo $option['id']; ?>"><?php echo htmlspecialchars($option['name']); ?></label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn-primary">Continuar</button>
                <?php if (isset($errors['db'])): ?><p class="error-message"><?php echo htmlspecialchars($errors['db']); ?></p><?php endif; ?>
            </form>
        </div>
    </div>
    <script>
        function setRealViewportHeight() { const vh = window.innerHeight * 0.01; document.documentElement.style.setProperty('--vh', `${vh}px`); }
        window.addEventListener('resize', setRealViewportHeight); setRealViewportHeight();
        document.body.addEventListener('touchmove', function(event) { if (!event.target.closest('.selectable-options')) { event.preventDefault(); } }, { passive: false });
        (function() { const i=document.querySelectorAll('input,button'); i.forEach(i=>{i.addEventListener('focusin',()=>{setTimeout(()=>{window.scrollTo(0,0)},0)}),i.addEventListener('blur',()=>{window.scrollTo(0,0)})})})();
    </script>
</body>
</html>