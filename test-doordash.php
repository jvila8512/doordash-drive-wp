<?php
// --- CONFIGURACIÓN ---
$dev_id = "e1d72127-f723-49fb-aa4a-d1b8e5cb505e";
$key_id = "cb4334a8-547f-4e37-afbc-0517d5297ffe";
$secret = "anldCdg0BnU6l9jChFD6ga59tp-BkOPc-7WM6VuEmQA";

// --- 1. FUNCIÓN GENERAR TOKEN JWT ---
function generar_jwt_simple($dev_id, $key_id, $secret) {
    $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT', 'dd-ver' => 'DD-JWT-V1']);
    $payload = json_encode([
        'aud' => 'doordash',
        'iss' => $dev_id,
        'kid' => $key_id,
        'exp' => time() + 300,
        'iat' => time()
    ]);

    $f = function($data) { return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); };
    
    $b64Header = $f($header);
    $b64Payload = $f($payload);
    
    // Decodificar el secreto antes de firmar
    $decoded_secret = base64_decode(strtr($secret, '-_', '+/'));
    $signature = hash_hmac('sha256', $b64Header . "." . $b64Payload, $decoded_secret, true);
    
    return $b64Header . "." . $b64Payload . "." . $f($signature);
}

// --- 2. PREPARAR LA ORDEN ---
$jwt = generar_jwt_simple($dev_id, $key_id, $secret);
$external_id = "PRUEBA-" . uniqid();

$body = json_encode([
    "external_delivery_id" => $external_id,
    "pickup_address" => "901 Market Street, San Francisco, CA 94103",
    "pickup_business_name" => "Mi Restaurante Local",
    "pickup_phone_number" => "+16505555555",
    "dropoff_address" => "901 Market Street, San Francisco, CA 94103",
    "dropoff_phone_number" => "+16505555555",
    "order_value" => 1500
]);

// --- 3. ENVIAR CON CURL ---
$ch = curl_init("https://openapi.doordash.com/drive/v2/deliveries/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-type: application/json",
    "Authorization: Bearer " . $jwt
]);

$response = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

$data = json_decode($response, true);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <style>
        body { font-family: sans-serif; background: #f4f4f9; display: flex; justify-content: center; padding: 50px; }
        .card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); max-width: 600px; }
        .status { color: #ff3008; font-weight: bold; font-size: 1.2em; }
        .link-rastreo { display: inline-block; margin-top: 20px; padding: 15px 25px; background: #ff3008; color: white; text-decoration: none; border-radius: 8px; }
        pre { background: #272822; color: #f8f8f2; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Resultado de la Petición</h2>
        <p>Código HTTP: <strong><?php echo $info['http_code']; ?></strong></p>
        
        <?php if ($info['http_code'] == 200 || $info['http_code'] == 201): ?>
            <p class="status">✅ ¡Éxito! Orden Creada</p>
            <p>ID Externo: <?php echo $data['external_delivery_id']; ?></p>
            <p>Tarifa: $<?php echo $data['fee'] / 100; ?> USD</p>
            
            <a href="<?php echo $data['url_de_seguimiento']; ?>" target="_blank" class="link-rastreo">
                🚀 Rastrear Entrega en Vivo
            </a>
        <?php else: ?>
            <p style="color:red;">❌ Error en la petición</p>
        <?php endif; ?>

        <h3>Respuesta JSON Completa:</h3>
        <pre><?php echo json_encode($data, JSON_PRETTY_PRINT); ?></pre>
    </div>
    
</body>
</html>