<?php
// Script para verificar qual modelo do Ollama está instalado
// Execute: php verificar_modelo_ollama.php

echo "🔍 Verificando modelos instalados no Ollama...\n\n";

$ollama_url = 'http://localhost:11434/api/tags';

$ch = curl_init($ollama_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code === 0 || !empty($curl_error)) {
    echo "❌ ERRO: Ollama não está acessível!\n";
    echo "   Erro: " . $curl_error . "\n";
    echo "   Execute: ollama serve\n";
    exit(1);
}

if ($http_code !== 200) {
    echo "❌ ERRO: HTTP Code $http_code\n";
    exit(1);
}

$result = json_decode($response, true);

if (!isset($result['models']) || empty($result['models'])) {
    echo "⚠️ Nenhum modelo encontrado!\n";
    echo "   Execute: ollama pull llama3.1:8b\n";
    exit(1);
}

echo "✅ Modelos instalados:\n\n";
foreach ($result['models'] as $model) {
    $name = $model['name'] ?? 'Desconhecido';
    $size = isset($model['size']) ? number_format($model['size'] / 1024 / 1024 / 1024, 2) . ' GB' : 'N/A';
    echo "   📦 $name ($size)\n";
}

echo "\n💡 Dica: O sistema vai tentar usar 'llama3.1:8b' primeiro, depois 'llama3.1'\n";
?>





