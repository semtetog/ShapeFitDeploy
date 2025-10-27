<?php
// Arquivo: test_db_search.php (para diagnóstico)

// Configurações básicas de erro para vermos tudo que acontece
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8'); // Usar texto puro para facilitar a leitura

echo "--- INICIANDO TESTE DE BUSCA NO BANCO DE DADOS ---\n\n";

// Incluir arquivos de configuração
require_once 'includes/config.php';
echo "config.php carregado.\n";

require_once APP_ROOT_PATH . '/includes/db.php';
echo "db.php carregado. A conexão deve estar ativa.\n";

if ($conn) {
    echo "Variável \$conn existe. Charset da conexão: " . $conn->character_set_name() . "\n\n";
} else {
    die("ERRO FATAL: Variável \$conn não existe após incluir db.php.\n");
}

// O termo que estamos testando
$term = 'carne';
echo "Termo de busca: '{$term}'\n\n";

$results = [];

try {
    $local_term = '%' . $term . '%';
    $start_term = $term . '%';

    // A mesma query do ajax_search_food.php
    $sql = "
        SELECT taco_id, name_pt, energy_kcal_100g 
        FROM sf_food_items 
        WHERE name_pt LIKE ? 
        ORDER BY CASE WHEN name_pt LIKE ? THEN 1 ELSE 2 END
        LIMIT 15
    ";
    echo "SQL a ser executado:\n{$sql}\n\n";

    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        // Se a preparação da query falhar, o script vai parar aqui e mostrar o erro
        die("ERRO na preparação da query: " . $conn->error . "\n");
    }
    echo "Query preparada com sucesso.\n";

    $stmt->bind_param("ss", $local_term, $start_term);
    echo "Parâmetros ('{$local_term}', '{$start_term}') associados.\n";

    $stmt->execute();
    echo "Query executada.\n";
    
    // Verificando se houve erros na execução
    if ($stmt->error) {
        die("ERRO na execução da query: " . $stmt->error . "\n");
    }

    $result_obj = $stmt->get_result(); // Vamos tentar get_result() aqui. Se falhar, veremos o erro.
    $num_rows = $result_obj->num_rows;
    echo "Número de linhas encontradas: {$num_rows}\n\n";

    if ($num_rows > 0) {
        echo "--- RESULTADOS ENCONTRADOS ---\n";
        while ($row = $result_obj->fetch_assoc()) {
            // Imprime cada resultado
            echo "ID: " . $row['taco_id'] . " | Nome: " . $row['name_pt'] . " | Kcal: " . $row['energy_kcal_100g'] . "\n";
        }
    } else {
        echo "Nenhum resultado encontrado no banco de dados para o termo '{$term}'.\n";
    }

    $stmt->close();
    echo "\nStatement fechado.\n";

} catch (Exception $e) {
    echo "\n\n--- OCORREU UMA EXCEÇÃO ---\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
}

$conn->close();
echo "Conexão fechada.\n";
echo "\n--- TESTE CONCLUÍDO ---";

?>