<?php
// onboarding/step3_personal_data.php
require_once '../includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$errors = [];
$dob = $_POST['dob'] ?? ($_SESSION['onboarding_data']['dob'] ?? '');
$gender = $_POST['gender'] ?? ($_SESSION['onboarding_data']['gender'] ?? '');
$height_cm = $_POST['height_cm'] ?? ($_SESSION['onboarding_data']['height_cm'] ?? '');
$weight_display = $_POST['weight_kg'] ?? (isset($_SESSION['onboarding_data']['weight_kg']) ? str_replace('.', ',', $_SESSION['onboarding_data']['weight_kg']) : '');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $height_cm = trim($_POST['height_cm'] ?? '');
    $weight_input = trim($_POST['weight_kg'] ?? '');
    $weight_display = $weight_input;
    $weight_kg_numeric = str_replace(',', '.', $weight_input);

    if (empty($dob)) { $errors['dob'] = "Data de nascimento é obrigatória."; }
    else { $d = DateTime::createFromFormat('Y-m-d', $dob); if (!$d || $d->format('Y-m-d') !== $dob || new DateTime() < $d) { $errors['dob'] = "Data inválida ou futura."; } }
    if (empty($gender) || !in_array($gender, ['male', 'female', 'other'])) { $errors['gender'] = "Sexo é obrigatório."; }
    if (empty($height_cm) || !filter_var($height_cm, FILTER_VALIDATE_INT, ["options" => ["min_range" => 50, "max_range" => 300]])) { $errors['height_cm'] = "Altura inválida (50-300 cm)."; }
    if (empty($weight_input)) { $errors['weight_kg'] = "Peso é obrigatório."; }
    elseif (!is_numeric($weight_kg_numeric) || !filter_var($weight_kg_numeric, FILTER_VALIDATE_FLOAT, ["options" => ["min_range" => 20, "max_range" => 500]])) { $errors['weight_kg'] = "Peso inválido (20-500 kg)."; }

    if (empty($errors)) {
        $_SESSION['onboarding_data']['dob'] = $dob;
        $_SESSION['onboarding_data']['gender'] = $gender;
        $_SESSION['onboarding_data']['height_cm'] = (int)$height_cm;
        $_SESSION['onboarding_data']['weight_kg'] = number_format((float)$weight_kg_numeric, 2, '.', '');
        header("Location: " . BASE_APP_URL . "/onboarding/process_onboarding.php");
        exit();
    }
}

$show_support_widget = false;
$page_title = "Dados Pessoais";
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
        .content-wrapper { flex-grow: 1; display: flex; flex-direction: column; justify-content: center; text-align: left; width: 100%; }
        .page-title { font-size: 32px; font-weight: 700; margin-bottom: 10px; line-height: 1.2; }
        .page-subtitle { color: var(--text-secondary); font-size: 1rem; margin-bottom: 40px; font-weight: 400; }
        .form-group { margin-bottom: 20px; position: relative; }
        .form-control { width: 100%; padding: 16px 20px; font-size: 1rem; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--glass-border); border-radius: 16px; color: var(--text-primary); outline: none; -webkit-appearance: none; appearance: none; }
        .form-control::placeholder { color: var(--text-secondary); }
        .form-control:focus { border-color: var(--accent-orange); }
        select.form-control { background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23a3a3a3%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 1.2rem center; background-size: .65em auto; padding-right: 2.5rem; }
        .btn-primary { background-image: var(--primary-orange-gradient); color: var(--text-primary); border: none; border-radius: 16px; padding: 16px 24px; font-size: 1.1rem; font-weight: 600; cursor: pointer; width: 100%; }
        .error-message { color: var(--danger-color); font-size: 0.85rem; margin-top: 8px; text-align: left; display: block; }
        .form-group small { color: var(--text-secondary); font-size: 0.8em; margin-top: 5px; display: block; }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="header-nav">
            <a href="<?php echo BASE_APP_URL . '/onboarding/step2_register_details.php'; ?>" class="back-button"><i class="fa-solid fa-arrow-left"></i></a>
        </div>
        <div class="content-wrapper">
            <h1 class="page-title">Agora, alguns<br>dados pessoais</h1>
            <p class="page-subtitle">Estas informações são essenciais para calcular sua meta.</p>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <div class="form-group">
                    <input type="date" name="dob" class="form-control" value="<?php echo htmlspecialchars($dob); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                    <?php if (isset($errors['dob'])): ?><span class="error-message"><?php echo htmlspecialchars($errors['dob']); ?></span><?php endif; ?>
                </div>
                <div class="form-group">
                    <select name="gender" class="form-control" required>
                        <option value="" disabled <?php if(empty($gender)) echo 'selected';?>>Sexo</option>
                        <option value="male" <?php if($gender == 'male') echo 'selected';?>>Masculino</option>
                        <option value="female" <?php if($gender == 'female') echo 'selected';?>>Feminino</option>
                        <option value="other" <?php if($gender == 'other') echo 'selected';?>>Outro</option>
                    </select>
                    <?php if (isset($errors['gender'])): ?><span class="error-message"><?php echo htmlspecialchars($errors['gender']); ?></span><?php endif; ?>
                </div>
                <div class="form-group">
                    <input type="number" name="height_cm" class="form-control" placeholder="Sua altura (cm)" required min="50" max="300" value="<?php echo htmlspecialchars($height_cm); ?>">
                    <?php if (isset($errors['height_cm'])): ?><span class="error-message"><?php echo htmlspecialchars($errors['height_cm']); ?></span><?php endif; ?>
                </div>
                <div class="form-group">
                    <input type="text" name="weight_kg" class="form-control" placeholder="Seu peso (kg)" required pattern="[0-9]+([,\.][0-9]{1,2})?" value="<?php echo htmlspecialchars($weight_display); ?>">
                    <small>Use vírgula para decimais, ex: 70,5</small>
                    <?php if (isset($errors['weight_kg'])): ?><span class="error-message"><?php echo htmlspecialchars($errors['weight_kg']); ?></span><?php endif; ?>
                </div>
                <button type="submit" class="btn-primary">Finalizar</button>
            </form>
        </div>
    </div>
    <script>
        function setRealViewportHeight() { const vh = window.innerHeight * 0.01; document.documentElement.style.setProperty('--vh', `${vh}px`); }
        window.addEventListener('resize', setRealViewportHeight); setRealViewportHeight();
        document.body.addEventListener('touchmove', function(event) { event.preventDefault(); }, { passive: false });
        (function() { const i=document.querySelectorAll('input,select,button'); i.forEach(i=>{i.addEventListener('focusin',()=>{setTimeout(()=>{window.scrollTo(0,0)},0)}),i.addEventListener('blur',()=>{window.scrollTo(0,0)})})})();
    </script>
</body>
</html>