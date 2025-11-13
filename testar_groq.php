<?php
// Script para testar se a Groq API está configurada e funcionando
// Execute: php testar_groq.php

require_once __DIR__ . '/includes/config.php';

echo "🔍 Testando configuração da Groq API...\n\n";

// Verificar se API key está configurada
$api_key = defined('GROQ_API_KEY') ? GROQ_API_KEY : '';

if (empty($api_key)) {
    echo "❌ ERRO: API key não configurada!\n";
    echo "\n";
    echo "📋 SOLUÇÃO:\n";
    echo "   1. Obtenha sua API key em: https://console.groq.com\n";
    echo "   2. Edite includes/config.php\n";
    echo "   3. Altere a linha: define('GROQ_API_KEY', '');\n";
    echo "   4. Para: define('GROQ_API_KEY', 'sua-chave-aqui');\n";
    exit(1);
}

echo "✅ API key configurada!\n";
echo "   Chave: " . substr($api_key, 0, 10) . "...\n\n";

// Testar conexão
echo "🌐 Testando conexão com Groq API...\n";

$api_url = 'https://api.groq.com/openai/v1/chat/completions';
$model = defined('GROQ_MODEL') ? GROQ_MODEL : 'llama-3.1-70b-versatile';

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $api_key
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => $model,
    'messages' => [
        [
            'role' => 'user',
            'content' => 'Responda apenas "Sim, estou funcionando!"'
        ]
    ],
    'max_tokens' => 50
]));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code === 0 || !empty($curl_error)) {
    echo "❌ ERRO: Não foi possível conectar!\n";
    echo "   Erro: " . $curl_error . "\n";
    exit(1);
}

if ($http_code !== 200) {
    echo "❌ ERRO: HTTP Code $http_code\n";
    echo "   Resposta: " . substr($response, 0, 200) . "\n";
    
    if ($http_code === 401) {
        echo "\n   ⚠️ API key inválida! Verifique se copiou corretamente.\n";
    }
    exit(1);
}

$result = json_decode($response, true);

if (isset($result['error'])) {
    echo "❌ ERRO da API: " . json_encode($result['error']) . "\n";
    exit(1);
}

if (isset($result['choices'][0]['message']['content'])) {
    $response_text = trim($result['choices'][0]['message']['content']);
    echo "✅ SUCESSO! Groq API está funcionando!\n";
    echo "   Modelo: $model\n";
    echo "   Resposta: $response_text\n\n";
    echo "🎉 Tudo configurado! O sistema vai usar Groq API automaticamente.\n";
    exit(0);
} else {
    echo "❌ ERRO: Resposta inesperada da API\n";
    echo "   Response: " . substr(json_encode($result), 0, 200) . "\n";
    exit(1);
}
?>

