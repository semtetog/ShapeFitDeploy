<?php
// ARQUIVO: account_deleted.php (NOVO ARQUIVO)

require_once 'includes/config.php';
// Incluímos o header para que a página tenha o mesmo fundo e estilo base do seu app
require_once APP_ROOT_PATH . '/includes/layout_header.php'; 
?>

<!-- Usamos a mesma classe .container do seu app para manter o layout -->
<div class="container" style="display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 80vh;">

    <!-- Caixa de Sucesso -->
    <div class="success-box" style="text-align: center; color: var(--primary-text-color);">
        
        <!-- Ícone de Check para reforçar o sucesso -->
        <i class="fas fa-check-circle" style="font-size: 60px; color: var(--success-color); margin-bottom: 25px;"></i>
        
        <h1 class="page-title" style="margin-bottom: 15px;">Conta Excluída com Sucesso</h1>
        
        <p class="page-subtitle" style="line-height: 1.6;">
            Todos os seus dados foram removidos permanentemente. Sentiremos sua falta!
        </p>

        <p class="page-subtitle" style="font-size: 0.9em;">
            Você será redirecionado para a página de login em alguns segundos...
        </p>

    </div>

</div>

<!-- O JavaScript que fará o redirecionamento automático -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Define o tempo de espera em milissegundos (4000ms = 4 segundos)
        const redirectDelay = 4000;

        setTimeout(function() {
            // Redireciona para a página de login
            window.location.href = '<?php echo BASE_APP_URL; ?>/auth/login.php';
        }, redirectDelay);
    });
</script>


<?php
// Incluímos o footer para fechar as tags HTML corretamente
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>
