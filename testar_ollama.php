<?php
// Script para testar se o Ollama está funcionando
// Execute: php testar_ollama.php

echo "🔍 Testando conexão com Ollama...\n\n";

$ollama_url = 'http://localhost:11434/api/chat';
$model = 'llama3.1:8b'; // Tente primeiro com 8B, se não funcionar, tente 'llama3.1'

// Teste simples
$ch = curl_init($ollama_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => $model,
    'messages' => [
        [
            'role' => 'user',
            'content' => 'Olá! Você está funcionando? Responda apenas "Sim, estou funcionando!"'
        ]
    ],
    'stream' => false
]));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code === 0 || !empty($curl_error)) {
    echo "❌ ERRO: Ollama não está rodando ou não está acessível!\n";
    echo "   Erro: " . $curl_error . "\n\n";
    echo "📋 SOLUÇÃO:\n";
    echo "   1. Instale o Ollama: https://ollama.com/download\n";
    echo "   2. Execute: ollama serve\n";
    echo "   3. Baixe o modelo: ollama pull llama3.1:8b\n";
    echo "   4. Execute este teste novamente\n";
    exit(1);
}

if ($http_code === 200 && !empty($response)) {
    $result = json_decode($response, true);
    
    if (isset($result['message']['content'])) {
        echo "✅ SUCESSO! Ollama está funcionando!\n";
        echo "   Modelo: " . $model . "\n";
        echo "   Resposta: " . trim($result['message']['content']) . "\n\n";
        echo "🎉 Tudo configurado! O sistema vai usar o Ollama para gerar resumos.\n";
        exit(0);
    } else {
        echo "⚠️ AVISO: Ollama respondeu, mas formato inesperado.\n";
        echo "   Resposta: " . substr($response, 0, 200) . "...\n";
        echo "   Tente baixar o modelo: ollama pull " . $model . "\n";
        exit(1);
    }
} else {
    echo "❌ ERRO: Resposta HTTP " . $http_code . "\n";
    echo "   Resposta: " . substr($response, 0, 200) . "...\n\n";
    
    if ($http_code === 404) {
        echo "📋 O modelo '" . $model . "' não foi encontrado.\n";
        echo "   Execute: ollama pull " . $model . "\n";
    } else {
        echo "📋 Verifique se o Ollama está rodando: ollama serve\n";
    }
    exit(1);
}
?>

