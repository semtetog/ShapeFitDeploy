<?php
// Arquivo: ajax_lookup_barcode.php - VERSÃO SIMPLIFICADA E ROBUSTA

header("Content-Type: application/json; charset=utf-8");

require_once 'includes/config.php';

$barcode = isset($_GET['barcode']) ? trim($_GET['barcode']) : '';
if (empty($barcode) || !is_numeric($barcode)) {
    echo json_encode(['success' => false, 'message' => 'Código de barras inválido fornecido.']);
    exit;
}

$url = "https://world.openfoodfacts.org/api/v2/product/{$barcode}.json?fields=product_name_pt,product_name,brands,nutriments,code";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'ShapeFitApp/1.0 - ' . BASE_APP_URL);
curl_setopt($ch, CURLOPT_TIMEOUT, 20); // Aumenta para 20 segundos
$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    error_log("Erro de cURL em ajax_lookup: " . $error);
    echo json_encode(['success' => false, 'message' => 'Não foi possível conectar à base de dados online.']);
    exit;
}

$data = json_decode($response, true);

if (isset($data['status']) && $data['status'] == 1 && isset($data['product'])) {
    $product = $data['product'];
    $nutriments = $product['nutriments'] ?? [];
    $kcal = $nutriments['energy-kcal_100g'] ?? ($nutriments['energy_100g'] ?? null) / 4.184;

    $formatted_product = [
        'id' => $product['code'] ?? $barcode,
        'name' => $product['product_name_pt'] ?? $product['product_name'] ?? 'Nome Indisponível',
        'brand' => $product['brands'] ?? null,
        'kcal_100g' => $kcal !== null ? round($kcal) : null,
        'protein_100g' => $nutriments['proteins_100g'] ?? null,
        'carbs_100g' => $nutriments['carbohydrates_100g'] ?? null,
        'fat_100g' => $nutriments['fat_100g'] ?? null,
    ];
    echo json_encode(['success' => true, 'data' => $formatted_product], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['success' => false, 'message' => 'Este produto não foi encontrado na base de dados online.']);
}
?>