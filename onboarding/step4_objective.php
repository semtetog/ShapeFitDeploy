<?php
// --- INÍCIO DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Usar um nome de variável bem específico para esta página
$step4_objective_error_message = ''; // Inicialização EXPLÍCITA e ÚNICA
$step4_objective_to_display = $_SESSION['onboarding_data']['objective'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submitted_objective = trim($_POST['objective'] ?? '');
    $step4_objective_to_display = $submitted_objective;

    if (empty($submitted_objective) || !in_array($submitted_objective, ['lose_fat', 'maintain_weight', 'gain_muscle'])) {
        $step4_objective_error_message = "Por favor, selecione um objetivo."; // Definida APENAS AQUI
    } else {
        $_SESSION['onboarding_data']['objective'] = $submitted_objective;
        header("Location: " . BASE_APP_URL . "/onboarding/step5_activity.php");
        exit();
    }
}
// --- FIM DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---

$page_title = "Seu Objetivo";
require_once '../includes/layout_header.php';
?>

    <div class="container">
        <div class="header-nav">
            <a href="<?php echo BASE_ASSET_URL; ?>/onboarding/step1_intro.php" class="back-button"><</a>
            <a href="<?php echo BASE_ASSET_URL; ?>/onboarding/step1_intro.php" class="back-button-text">Voltar</a>
        </div>
        <h1 class="page-title">Qual é o seu<br>objetivo?</h1>
        <p class="page-subtitle">Todos os meus cardápios são baseados em refeições saudáveis e balanceadas.</p>

        <form action="<?php echo BASE_ASSET_URL; ?>/onboarding/step4_objective.php" method="POST">
            <?php
            if (!empty($step4_objective_error_message)) {
                echo '<p class="error-message text-center mb-2">' . htmlspecialchars($step4_objective_error_message) . '</p>';
            }
            ?>

            <label class="selectable-option <?php if($step4_objective_to_display == 'lose_fat') echo 'selected'; ?>">
                <input type="radio" name="objective" value="lose_fat" <?php if($step4_objective_to_display == 'lose_fat') echo 'checked'; ?> required>
                <span class="option-text">Perder gordura</span>
            </label>

            <label class="selectable-option <?php if($step4_objective_to_display == 'maintain_weight') echo 'selected'; ?>">
                <input type="radio" name="objective" value="maintain_weight" <?php if($step4_objective_to_display == 'maintain_weight') echo 'checked'; ?> required>
                <span class="option-text">Manter peso e melhorar alimentação</span>
            </label>

            <label class="selectable-option <?php if($step4_objective_to_display == 'gain_muscle') echo 'selected'; ?>">
                <input type="radio" name="objective" value="gain_muscle" <?php if($step4_objective_to_display == 'gain_muscle') echo 'checked'; ?> required>
                <span class="option-text">Ganhar massa muscular</span>
            </label>

            <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Continuar</button>
        </form>
    </div>

<?php
require_once '../includes/layout_footer.php';
?>