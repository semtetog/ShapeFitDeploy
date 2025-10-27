<?php
// --- INÍCIO DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---
require_once 'includes/config.php'; // Para BASE_APP_URL
require_once 'includes/auth.php';   // auth.php deve incluir config.php se precisar

if (isLoggedIn()) {
    require_once 'includes/db.php'; // Necessário para checar onboarding_complete
    $stmt_onboarding_check_idx = $conn->prepare("SELECT onboarding_complete FROM sf_users WHERE id = ?");
    if ($stmt_onboarding_check_idx) {
        $stmt_onboarding_check_idx->bind_param("i", $_SESSION['user_id']);
        $stmt_onboarding_check_idx->execute();
        $result_onboarding_check_idx = $stmt_onboarding_check_idx->get_result();
        $user_status_idx = $result_onboarding_check_idx->fetch_assoc();
        $stmt_onboarding_check_idx->close();

        if ($user_status_idx && $user_status_idx['onboarding_complete']) {
            header("Location: " . BASE_APP_URL . "/main_app.php");
        } else {
            header("Location: " . BASE_APP_URL . "/onboarding/step1_intro.php");
        }
    } else {
        // Erro ao preparar a consulta, talvez redirecionar para erro ou login
        error_log("Index.php - Prepare sf_users failed: " . $conn->error);
        header("Location: " . BASE_APP_URL . "/auth/login.php?error=db_check_failed");
    }
    exit();
} else {
    header("Location: " . BASE_APP_URL . "/auth/login.php");
    exit();
}
// --- FIM DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---

// Esta página é puramente de redirecionamento, então não precisa de $page_title,
// layout_header.php ou layout_footer.php.
// O código HTML nunca será alcançado se a lógica acima funcionar.
?>