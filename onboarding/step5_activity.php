<?php
// onboarding/step5_activity.php (VERSÃO CORRIGIDA E SINCRONIZADA COM O ADMIN)

require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();
if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Pega o valor da sessão para pré-selecionar
$activity_level_to_display = $_SESSION['onboarding_data']['activity_level'] ?? '';
$error_message = '';

// As chaves agora estão 100% alinhadas com o que o painel admin espera
// ADICIONAMOS 'sedentary' à lista de valores válidos
$valid_activity_levels = ['sedentary', 'sedentary_to_1x', 'light_2_3x', 'moderate_3_5x', 'intense_5x_plus', 'athlete'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submitted_activity_level = trim($_POST['activity_level'] ?? '');
    $activity_level_to_display = $submitted_activity_level;

    if (empty($submitted_activity_level) || !in_array($submitted_activity_level, $valid_activity_levels)) {
        $error_message = "Por favor, selecione seu nível de atividade.";
    } else {
        $_SESSION['onboarding_data']['activity_level'] = $submitted_activity_level;
        header("Location: " . BASE_APP_URL . "/onboarding/step6_bowel.php");
        exit();
    }
}

$page_title = "Atividade Física";
require_once '../includes/layout_header.php';
?>

<div class="container">
    <div class="header-nav">
        <a href="<?php echo BASE_APP_URL; ?>/onboarding/step4_objective.php" class="back-button"><i class="fas fa-chevron-left"></i></a>
    </div>
    <h1 class="page-title">Você pratica<br>atividade física?</h1>
    <p class="page-subtitle">Selecione a opção que melhor descreve sua rotina semanal.</p>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
        <?php if (!empty($error_message)): ?><p class="error-message text-center mb-2"><?php echo htmlspecialchars($error_message); ?></p><?php endif; ?>

        <!-- [ADICIONADO] Nova opção para "Sedentário" -->
        <label class="selectable-option <?php if($activity_level_to_display == 'sedentary') echo 'selected'; ?>">
            <input type="radio" name="activity_level" value="sedentary" <?php if($activity_level_to_display == 'sedentary') echo 'checked'; ?> required>
            <span class="option-text">Sedentário (não pratico exercícios)</span>
        </label>

        <!-- [ALTERADO] O texto da opção antiga para não gerar confusão -->
        <label class="selectable-option <?php if($activity_level_to_display == 'sedentary_to_1x') echo 'selected'; ?>">
            <input type="radio" name="activity_level" value="sedentary_to_1x" <?php if($activity_level_to_display == 'sedentary_to_1x') echo 'checked'; ?> required>
            <span class="option-text">Muito Leve - Treino 1x na semana</span>
        </label>

        <label class="selectable-option <?php if($activity_level_to_display == 'light_2_3x') echo 'selected'; ?>">
            <input type="radio" name="activity_level" value="light_2_3x" <?php if($activity_level_to_display == 'light_2_3x') echo 'checked'; ?> required>
            <span class="option-text">Leve - 2 a 3x na semana</span>
        </label>

        <label class="selectable-option <?php if($activity_level_to_display == 'moderate_3_5x') echo 'selected'; ?>">
            <input type="radio" name="activity_level" value="moderate_3_5x" <?php if($activity_level_to_display == 'moderate_3_5x') echo 'checked'; ?> required>
            <span class="option-text">Moderado - 3 a 5x na semana</span>
        </label>

        <label class="selectable-option <?php if($activity_level_to_display == 'intense_5x_plus') echo 'selected'; ?>">
            <input type="radio" name="activity_level" value="intense_5x_plus" <?php if($activity_level_to_display == 'intense_5x_plus') echo 'checked'; ?> required>
            <span class="option-text">Intenso - 5x ou mais na semana</span>
        </label>
        
        <label class="selectable-option <?php if($activity_level_to_display == 'athlete') echo 'selected'; ?>">
            <input type="radio" name="activity_level" value="athlete" <?php if($activity_level_to_display == 'athlete') echo 'checked'; ?> required>
            <span class="option-text">Atleta / Treino intenso 2x por dia</span>
        </label>

        <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Continuar</button>
    </form>
</div>

<?php
require_once '../includes/layout_footer.php';
?>