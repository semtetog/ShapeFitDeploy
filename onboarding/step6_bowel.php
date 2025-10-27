<?php
// --- INÍCIO DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$bowel_movement_to_display = $_SESSION['onboarding_data']['bowel_movement'] ?? '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submitted_bowel_movement = trim($_POST['bowel_movement'] ?? '');
    $bowel_movement_to_display = $submitted_bowel_movement;

    if (empty($submitted_bowel_movement) || !in_array($submitted_bowel_movement, ['daily', 'alternate_days', 'every_3_plus_days'])) {
        $error_message = "Por favor, selecione uma opção.";
    } else {
        $_SESSION['onboarding_data']['bowel_movement'] = $submitted_bowel_movement;
        // Próximo passo na NOVA ORDEM: step6_bowel -> step7_restrictions_ask
        header("Location: " . BASE_APP_URL . "/onboarding/step7_restrictions_ask.php");
        exit();
    }
}
// --- FIM DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---

$page_title = "Seu Intestino";
require_once '../includes/layout_header.php';
?>

    <div class="container">
        <div class="header-nav">
            <!-- Voltar para o passo anterior na NOVA ORDEM: step5_activity -->
            <a href="<?php echo BASE_ASSET_URL; ?>/onboarding/step5_activity.php" class="back-button"><</a>
            <a href="<?php echo BASE_ASSET_URL; ?>/onboarding/step5_activity.php" class="back-button-text">Voltar</a>
        </div>
        <h1 class="page-title">Como anda o<br>seu intestino?</h1>
        <p class="page-subtitle">Uma pergunta indiscreta, eu sei, mas acredite, ela é importante.</p>

        <form action="<?php echo BASE_ASSET_URL; ?>/onboarding/step6_bowel.php" method="POST">
            <?php if (!empty($error_message)): ?><p class="error-message text-center mb-2"><?php echo htmlspecialchars($error_message); ?></p><?php endif; ?>

            <label class="selectable-option <?php if($bowel_movement_to_display == 'daily') echo 'selected'; ?>">
                <input type="radio" name="bowel_movement" value="daily" <?php if($bowel_movement_to_display == 'daily') echo 'checked'; ?> required>
                <span class="option-text">Funciona diariamente</span>
            </label>

            <label class="selectable-option <?php if($bowel_movement_to_display == 'alternate_days') echo 'selected'; ?>">
                <input type="radio" name="bowel_movement" value="alternate_days" <?php if($bowel_movement_to_display == 'alternate_days') echo 'checked'; ?> required>
                <span class="option-text">Funciona dia sim, dia não.</span>
            </label>

            <label class="selectable-option <?php if($bowel_movement_to_display == 'every_3_plus_days') echo 'selected'; ?>">
                <input type="radio" name="bowel_movement" value="every_3_plus_days" <?php if($bowel_movement_to_display == 'every_3_plus_days') echo 'checked'; ?> required>
                <span class="option-text">Funciona a cada 3 dias ou mais</span>
            </label>

            <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Continuar</button>
        </form>
    </div>

<?php
require_once '../includes/layout_footer.php';
?>