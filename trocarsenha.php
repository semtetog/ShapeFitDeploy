<?php
// #####################################################################
// #                                                                   #
// #         ATENÇÃO: DELETE ESTE ARQUIVO DO SERVIDOR                  #
// #              IMEDIATAMENTE APÓS O USO!                            #
// #                                                                   #
// #####################################################################

// Inclui os arquivos de configuração e banco de dados
require_once __DIR__ . '/includes/config.php';
$conn = require __DIR__ . '/includes/db.php';

// ===================================================================
//                 CONFIGURAÇÃO - EDITE AS 3 LINHAS ABAIXO
// ===================================================================

// 1. Defina o nome de usuário do admin que terá a senha alterada.
$adminUsernameAlvo = 'nutri_vitor';

// 2. Defina a NOVA senha que você deseja usar.
$novaSenha = 'vitoradmin';

// 3. Defina uma chave de segurança para proteger este script.
$chaveDeSeguranca = 'mudarSenhaAdminAgora456';

// ===================================================================
//                 FIM DA CONFIGURAÇÃO - NÃO EDITE ABAIXO
// ===================================================================


// Verificação de segurança para impedir o uso não autorizado
$chaveDeSegurancaNaURL = $_GET['key'] ?? '';
if ($chaveDeSegurancaNaURL !== $chaveDeSeguranca) {
    header("HTTP/1.1 403 Forbidden");
    die("<h1>Acesso Negado</h1><p>Chave de segurança inválida ou não fornecida.</p>");
}

echo "<h1>Processando Alteração de Senha...</h1>";

// Gera o hash da nova senha usando o mesmo método do seu app
$novoPasswordHash = password_hash($novaSenha, PASSWORD_DEFAULT);

if (!$novoPasswordHash) {
    die("<p style='color:red;'>Erro crítico: A função password_hash() não está funcionando no servidor.</p>");
}

// Prepara a query para atualizar a senha no banco de dados
$sql = "UPDATE sf_admins SET password_hash = ? WHERE username = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die("<p style='color:red;'>Erro ao preparar a query: " . htmlspecialchars($conn->error) . "</p>");
}

// Associa os parâmetros e executa
$stmt->bind_param("ss", $novoPasswordHash, $adminUsernameAlvo);

if ($stmt->execute()) {
    // Verifica se alguma linha foi realmente alterada
    if ($stmt->affected_rows > 0) {
        echo "<p style='color:green; font-weight:bold;'>SUCESSO!</p>";
        echo "<p>A senha do usuário '<strong>" . htmlspecialchars($adminUsernameAlvo) . "</strong>' foi alterada com sucesso.</p>";
        echo "<p>Você já pode fazer login com a nova senha.</p>";
        echo "<hr>";
        echo "<h2 style='color:red;'>AÇÃO URGENTE: DELETE ESTE ARQUIVO ('mudar_senha_admin.php') DO SEU SERVIDOR AGORA MESMO!</h2>";
    } else {
        echo "<p style='color:orange; font-weight:bold;'>AVISO!</p>";
        echo "<p>Nenhum usuário foi encontrado com o nome '<strong>" . htmlspecialchars($adminUsernameAlvo) . "</strong>'. Nenhuma senha foi alterada.</p>";
        echo "<p>Verifique se o nome de usuário na variável \$adminUsernameAlvo está correto.</p>";
    }
} else {
    echo "<p style='color:red; font-weight:bold;'>ERRO!</p>";
    echo "<p>Falha ao executar a atualização no banco de dados: " . htmlspecialchars($stmt->error) . "</p>";
}

// Fecha a conexão
$stmt->close();
$conn->close();
?>