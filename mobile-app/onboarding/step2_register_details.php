<?php
// onboarding/step2_register_details.php
require_once '../includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$errors = [];
$name = $_POST['name'] ?? ($_SESSION['onboarding_data']['name'] ?? ($_SESSION['user_name'] ?? ''));
$uf_selected = $_POST['uf'] ?? ($_SESSION['onboarding_data']['uf'] ?? '');
$city_text = $_POST['city'] ?? ($_SESSION['onboarding_data']['city'] ?? '');
$phone_ddd = $_POST['phone_ddd'] ?? ($_SESSION['onboarding_data']['phone_ddd'] ?? '');
$phone_number = $_POST['phone_number'] ?? ($_SESSION['onboarding_data']['phone_number'] ?? '');

$ufs_from_api = [];
$json_estados = @file_get_contents("https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome");
if ($json_estados) {
    $lista_estados_api = json_decode($json_estados, true);
    if (is_array($lista_estados_api)) {
        foreach ($lista_estados_api as $estado) {
            if (isset($estado['sigla'], $estado['nome'])) {
                $ufs_from_api[] = ['sigla' => $estado['sigla'], 'nome' => $estado['nome']];
            }
        }
    }
}
if (empty($ufs_from_api)) {
    error_log("ShapeFit - Falha ao buscar UFs da API do IBGE.");
    // Fallback básico se a API falhar
    $ufs_from_api = [['sigla' => 'SP', 'nome' => 'São Paulo'], ['sigla' => 'RJ', 'nome' => 'Rio de Janeiro']]; 
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $uf_selected = trim($_POST['uf'] ?? '');
    $city_text = trim($_POST['city'] ?? '');
    $phone_ddd = trim($_POST['phone_ddd'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');

    if (empty($name)) $errors['name'] = "Nome é obrigatório.";
    if (empty($uf_selected)) $errors['uf'] = "UF é obrigatória.";
    if (empty($city_text)) $errors['city'] = "Cidade é obrigatória.";
    if (empty($phone_ddd) || !preg_match('/^[0-9]{2}$/', $phone_ddd)) $errors['phone_ddd'] = "DDD inválido.";
    if (empty($phone_number) || !preg_match('/^[0-9]{8,9}$/', $phone_number)) $errors['phone_number'] = "Celular inválido.";

    if (empty($errors)) {
        $_SESSION['onboarding_data']['name'] = $name;
        $_SESSION['onboarding_data']['uf'] = $uf_selected;
        $_SESSION['onboarding_data']['city'] = $city_text;
        $_SESSION['onboarding_data']['phone_ddd'] = $phone_ddd;
        $_SESSION['onboarding_data']['phone_number'] = $phone_number;
        header("Location: " . BASE_APP_URL . "/onboarding/step3_personal_data.php");
        exit();
    }
}

// Lógica correta para o botão voltar
$link_voltar = ($_SESSION['onboarding_data']['has_dietary_restrictions'] ?? false)
    ? BASE_APP_URL . "/onboarding/step8_restrictions_select.php"
    : BASE_APP_URL . "/onboarding/step7_restrictions_ask.php";

$show_support_widget = false;
$page_title = "Finalizar Cadastro";
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
        :root { --bg-color: #101010; --primary-orange-gradient: linear-gradient(45deg, #FFAE00, #F83600); --text-primary: #F5F5F5; --text-secondary: #A3A3A3; --font-family: 'Montserrat', sans-serif; --glass-border: rgba(255, 255, 255, 0.1); --danger-color: #F44336; --accent-orange: #FF6B00; }
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html { height: 100%; width: 100%; overflow: hidden; background-color: var(--bg-color); overscroll-behavior-y: contain; }
        body { position: fixed; top: 0; left: 0; width: 100%; height: calc(var(--vh, 1vh) * 100); overflow: hidden; background-color: var(--bg-color); overscroll-behavior-y: contain; color: var(--text-primary); font-family: var(--font-family); display: flex; justify-content: center; align-items: center; }
        .app-container { width: 100%; max-width: 480px; height: 100%; display: flex; flex-direction: column; padding: 24px; position: relative; }
        .header-nav { position: absolute; top: 25px; left: 20px; z-index: 10; }
        .back-button { color: var(--text-secondary); text-decoration: none; font-size: 1.5rem; padding: 5px; transition: color 0.3s ease; }
        .back-button:hover { color: var(--text-primary); }
        .content-wrapper { flex-grow: 1; display: flex; flex-direction: column; justify-content: center; text-align: left; width: 100%; overflow-y: auto; padding-top: 60px; }
        .page-title { font-size: 32px; font-weight: 700; margin-bottom: 10px; line-height: 1.2; }
        .page-subtitle { color: var(--text-secondary); font-size: 1rem; margin-bottom: 40px; font-weight: 400; }
        .form-group { margin-bottom: 20px; position: relative; }
        .form-group .icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); z-index: 2; }
        .form-control { width: 100%; padding: 16px 20px 16px 50px; font-size: 1rem; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--glass-border); border-radius: 16px; color: var(--text-primary); outline: none; -webkit-appearance: none; appearance: none; }
        .form-control:focus { border-color: var(--accent-orange); }
        .form-control:focus + .icon { color: var(--accent-orange); }
        select.form-control { background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23a3a3a3%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 1.2rem center; background-size: .65em auto; padding-right: 2.5rem; }
        .form-row { display: flex; gap: 12px; }
        .form-row .form-group.uf-group { flex: 0 0 100px; }
        .phone-input-group { display: flex; gap: 12px; }
        .phone-input-group .form-group { margin-bottom: 0; }
        .phone-input-group .form-group:nth-child(1) { flex: 0 0 70px; }
        .phone-input-group .form-group:nth-child(2) { flex: 0 0 80px; }
        .phone-input-group .form-group:nth-child(3) { flex-grow: 1; }
        .phone-input-group .form-control { padding-left: 15px; text-align: center; }
        .phone-input-group .form-control#phone_number { text-align: left; padding-left: 20px; }
        .btn-primary { background-image: var(--primary-orange-gradient); color: var(--text-primary); border: none; border-radius: 16px; padding: 16px 24px; font-size: 1.1rem; font-weight: 600; cursor: pointer; width: 100%; margin-top: 20px; }
        .error-message { color: var(--danger-color); font-size: 0.85rem; margin-top: 8px; text-align: left; display: block; }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="header-nav">
            <a href="<?php echo $link_voltar; ?>" class="back-button"><i class="fa-solid fa-arrow-left"></i></a>
        </div>
        <div class="content-wrapper">
            <h1 class="page-title">Finalize seu<br>cadastro</h1>
            <p class="page-subtitle">Complete seus dados para começar a usar o app.</p>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <div class="form-group">
                    <i class="fa-solid fa-user icon"></i>
                    <input type="text" name="name" class="form-control" placeholder="Nome completo" required value="<?php echo htmlspecialchars($name); ?>">
                    <?php if (isset($errors['name'])): ?><span class="error-message"><?php echo htmlspecialchars($errors['name']); ?></span><?php endif; ?>
                </div>
                <div class="form-row">
                    <div class="form-group uf-group">
                        <i class="fa-solid fa-map-marker-alt icon"></i>
                        <select name="uf" class="form-control" required>
                            <option value="">UF</option>
                            <?php foreach ($ufs_from_api as $estado): ?>
                                <option value="<?php echo htmlspecialchars($estado['sigla']); ?>" <?php if ($uf_selected == $estado['sigla']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($estado['sigla']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                         <?php if (isset($errors['uf'])): ?><span class="error-message"><?php echo htmlspecialchars($errors['uf']); ?></span><?php endif; ?>
                    </div>
                    <div class="form-group" style="flex-grow: 1;">
                        <i class="fa-solid fa-city icon"></i>
                        <input type="text" name="city" class="form-control" placeholder="Cidade" required value="<?php echo htmlspecialchars($city_text); ?>">
                        <?php if (isset($errors['city'])): ?><span class="error-message"><?php echo htmlspecialchars($errors['city']); ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <div class="phone-input-group">
                        <div class="form-group">
                            <input type="text" class="form-control" value="+55" readonly>
                        </div>
                        <div class="form-group">
                            <input type="tel" name="phone_ddd" class="form-control" placeholder="DDD" maxlength="2" required value="<?php echo htmlspecialchars($phone_ddd); ?>">
                        </div>
                        <div class="form-group">
                            <input type="tel" id="phone_number" name="phone_number" class="form-control" placeholder="Celular" maxlength="9" required value="<?php echo htmlspecialchars($phone_number); ?>">
                        </div>
                    </div>
                    <?php if (isset($errors['phone_ddd'])): ?><span class="error-message"><?php echo htmlspecialchars($errors['phone_ddd']); ?></span>
                    <?php elseif (isset($errors['phone_number'])): ?><span class="error-message"><?php echo htmlspecialchars($errors['phone_number']); ?></span><?php endif; ?>
                </div>
                <button type="submit" class="btn-primary">Continuar</button>
            </form>
        </div>
    </div>
    <script>
        function setRealViewportHeight() { const vh = window.innerHeight * 0.01; document.documentElement.style.setProperty('--vh', `${vh}px`); }
        window.addEventListener('resize', setRealViewportHeight); setRealViewportHeight();
        document.body.addEventListener('touchmove', function(event) { if (!event.target.closest('.content-wrapper')) { event.preventDefault(); } }, { passive: false });
        (function() { const i=document.querySelectorAll('input,select,button'); i.forEach(i=>{i.addEventListener('focusin',()=>{setTimeout(()=>{window.scrollTo(0,0)},0)}),i.addEventListener('blur',()=>{window.scrollTo(0,0)})})})();
    </script>
</body>
</html>