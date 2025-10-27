<?php // admin/includes/footer.php (VERSÃO FINAL E CORRIGIDA) ?>
            </div> <!-- fecha .content-wrapper -->
        </main> <!-- fecha .main-content -->
    </div> <!-- fecha .admin-wrapper -->

    <!-- ======================================================= -->
    <!--     1. CARREGA A BIBLIOTECA DO GRÁFICO (O MAIS IMPORTANTE) -->
    <!-- ======================================================= -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


    <!-- ======================================================= -->
    <!--     2. CARREGA SEUS SCRIPTS DO ADMIN                  -->
    <!-- ======================================================= -->

    <!-- Carrega o script principal do admin -->
    <script src="<?php echo BASE_ADMIN_URL; ?>/assets/js/admin_script.js?v=<?php echo time(); ?>"></script>

    <!-- Carrega scripts extras da página (como o user_view_logic.js) -->
    <?php if (isset($extra_js) && is_array($extra_js)): ?>
        <?php foreach ($extra_js as $script_filename): ?>
            <script src="<?php echo BASE_ADMIN_URL; ?>/assets/js/<?php echo $script_filename; ?>?v=<?php echo time(); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>