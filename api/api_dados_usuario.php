<?php
// Arquivo: api_dados_usuario.php

header('Content-Type: application/json'); // Linha importante! Diz que a resposta é em formato JSON.

// Inclua aqui seu arquivo de conexão com o banco de dados
// Ex: require_once('conexao.php');

// 1. Pegar o ID do usuário que a IA quer consultar
// A IA vai chamar: www.seusite.com/api_dados_usuario.php?user_id=123
$userId = $_GET['user_id'];

if (!$userId) {
    echo json_encode(['erro' => 'ID do usuário não fornecido']);
    exit;
}

// 2. Montar a consulta SQL
// Vendo suas tabelas, uma consulta poderia buscar o perfil do usuário e suas refeições.
// Este é SÓ UM EXEMPLO, você precisará adaptar para sua lógica real.
$sql = "SELECT 
            u.user_name, 
            up.user_goal,  -- O objetivo do usuário (da tabela sf_user_profiles)
            GROUP_CONCAT(rm.meal_title SEPARATOR '; ') as plano_alimentar -- Pega o nome de todas as refeições do dia
        FROM 
            sf_users u
        LEFT JOIN 
            sf_user_profiles up ON u.user_id = up.user_id
        LEFT JOIN
            sf_user_routine_log url ON u.user_id = url.user_id -- Supondo que esta tabela liga o usuário à sua rotina
        LEFT JOIN 
            sf_routine_items ri ON url.routine_id = ri.routine_id -- Itens da rotina
        LEFT JOIN
            sf_recipes rm ON ri.item_id = rm.recipe_id -- Nome das receitas/refeições
        WHERE 
            u.user_id = ?  -- Usamos '?' para segurança
        GROUP BY
            u.user_id";

// 3. Executar a consulta com segurança (usando prepared statements)
$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $userId); // 'i' significa que o ID é um inteiro
$stmt->execute();
$result = $stmt->get_result();
$dadosUsuario = $result->fetch_assoc();

// 4. Devolver os dados em formato JSON
if ($dadosUsuario) {
    echo json_encode($dadosUsuario);
} else {
    echo json_encode(['erro' => 'Usuário não encontrado']);
}

$stmt->close();
$conexao->close();

?>