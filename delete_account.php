<?php


require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$page_title = "Excluir Conta";
// Adicionamos o 'style.css' que você forneceu. Ele deve ser carregado pelo seu header.
$extra_css = ['style.css']; 
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<!-- Usamos a classe .container que você definiu no seu CSS para páginas -->
<div class="container" style="padding-top: 0;">

    <header class="header-nav">
        <!-- Adicionamos um botão de voltar para consistência -->
        <a href="javascript:history.back()" class="back-button">
            <i class="fas fa-arrow-left"></i>
        </a>
    </header>

    <h1 class="page-title">Excluir Minha Conta</h1>
    
    <!-- Caixa de Aviso com estilo melhorado -->
    <div class="warning-box" style="background-color: rgba(244, 67, 54, 0.1); border: 1px solid var(--danger-color); color: #ffcdd2; padding: 20px; border-radius: 12px; margin-bottom: 30px;">
        <h3 style="margin-top: 0; color: #ef9a9a; display: flex; align-items: center; gap: 8px;"><i class="fas fa-exclamation-triangle"></i> Atenção! Ação Irreversível</h3>
        <p style="color: #e0e0e0; line-height: 1.6;">Esta ação excluirá permanentemente todos os seus dados da plataforma, incluindo perfil, histórico, diário e progresso. <strong>Uma vez excluídos, estes dados jamais poderão ser recuperados.</strong></p>
    </div>

    <form id="delete-account-form" action="process_delete_account.php" method="POST" class="form-group">
        
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

        <div class="form-group">
            <label for="confirmation-input">Para confirmar, digite <strong>EXCLUIR</strong> no campo abaixo:</label>
            <!-- Usamos a classe .form-control que você definiu -->
            <input type="text" id="confirmation-input" name="confirmation_text" class="form-control" autocomplete="off" placeholder="Digite EXCLUIR aqui">
        </div>

        <!-- Usamos as classes .btn e .btn-danger que definimos -->
        <button type="submit" id="delete-button" class="btn btn-danger" disabled>
            Eu entendo, excluir minha conta
        </button>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const confirmationInput = document.getElementById('confirmation-input');
    const deleteButton = document.getElementById('delete-button');

    if (confirmationInput && deleteButton) {
        confirmationInput.addEventListener('input', function() {
            if (confirmationInput.value.trim() === 'EXCLUIR') {
                deleteButton.disabled = false;
            } else {
                deleteButton.disabled = true;
            }
        });
    }
});
</script>

<?php
// Carrega a navegação inferior para manter a consistência
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>