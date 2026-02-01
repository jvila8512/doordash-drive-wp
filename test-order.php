<?php
/**
 * Herramienta de Prueba - crear_orden_externa
 * Ubicación: includes/test-crear-orden.php
 * 
 * Cómo ejecutarlo:
 *   http://localhost/wordpress/wp-admin/admin.php?page=doordash-settings&action=test_crear_orden
 */

// 1. Cargar WordPress
require_once('../../../wp-load.php');

// Seguridad: Solo administradores
if (!current_user_can('manage_options')) {
    die('Acceso denegado');
}

// 2. Instanciar clases
$api = new DD_API();
$db  = new DD_Database();
$creds = $db->get_credentials();

// 3. Datos de prueba — estos van a crear_orden_externa
$orden_prueba = [
    'external_id'      => 'ORDER-' . time(),
    'customer_name'    => 'Javy Test User',
    'customer_address' => '100 Market St, San Francisco, CA 94105',
    'customer_phone'   => '+16505551111',
    'order_value'      => 2500,  // $25.00 USD en centavos
    'instructions'     => 'Dejar en la puerta. Timbre no funciona.',
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test crear_orden_externa - DoorDash</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f1; padding: 40px; }
        .container { max-width: 650px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .header { border-bottom: 2px solid #ff3008; padding-bottom: 10px; margin-bottom: 20px; }
        .header h2 { margin: 0; }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-err { color: #dc3545; font-weight: bold; }
        .section { margin-bottom: 20px; }
        .section h4 { margin: 0 0 8px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .cred-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 14px; }
        .cred-label { color: #666; }
        .cred-ok { color: #28a745; font-weight: bold; }
        .cred-fail { color: #dc3545; font-weight: bold; }
        table.datos { width: 100%; border-collapse: collapse; font-size: 14px; }
        table.datos td { padding: 5px 0; border-bottom: 1px solid #f0f0f0; }
        table.datos td:first-child { color: #666; width: 160px; }
        pre { background: #2d2d2d; color: #ccc; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 13px; }
        .btn { display: inline-block; background: #ff3008; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin-top: 10px; font-weight: bold; }
        .btn:hover { background: #e02a07; }
        .result-box { padding: 15px; border-radius: 8px; margin-top: 10px; }
        .result-box.ok { background: #d4edda; border: 1px solid #c3e6cb; }
        .result-box.err { background: #f8d7da; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="container">

    <!-- HEADER -->
    <div class="header">
        <h2>🚀 Test — crear_orden_externa()</h2>
        <small style="color:#888;">Prueba directa de la función con datos simulados</small>
    </div>

    <!-- 1. CREDENCIALES -->
    <div class="section">
        <h4>1. Credenciales</h4>
        <div class="cred-row">
            <span class="cred-label">Developer ID</span>
            <span class="<?php echo (!empty($creds['dev_id'])) ? 'cred-ok' : 'cred-fail'; ?>">
                <?php echo (!empty($creds['dev_id'])) ? '✓ OK' : '✗ Falta'; ?>
            </span>
        </div>
        <div class="cred-row">
            <span class="cred-label">Key ID</span>
            <span class="<?php echo (!empty($creds['key_id'])) ? 'cred-ok' : 'cred-fail'; ?>">
                <?php echo (!empty($creds['key_id'])) ? '✓ OK' : '✗ Falta'; ?>
            </span>
        </div>
        <div class="cred-row">
            <span class="cred-label">Secret</span>
            <span class="<?php echo (!empty($creds['secret'])) ? 'cred-ok' : 'cred-fail'; ?>">
                <?php echo (!empty($creds['secret'])) ? '✓ OK' : '✗ Falta'; ?>
            </span>
        </div>
        <div class="cred-row">
            <span class="cred-label">Modo</span>
            <span><strong><?php echo $db->get_mode() ?? 'sandbox'; ?></strong></span>
        </div>
        <div class="cred-row">
            <span class="cred-label">Pickup (Settings)</span>
            <span style="font-size:13px; color:#555;"><?php echo $creds['pickup_address'] ?? '— No configurada —'; ?></span>
        </div>
    </div>

    <!-- 2. DATOS QUE SE ENVIAN -->
    <div class="section">
        <h4>2. Datos de la orden de prueba</h4>
        <table class="datos">
            <?php foreach ($orden_prueba as $key => $value): ?>
            <tr>
                <td><?php echo ucfirst(str_replace('_', ' ', $key)); ?></td>
                <td><?php echo htmlspecialchars($value); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- 3. EJECUTAR crear_orden_externa -->
    <div class="section">
        <h4>3. Enviando orden...</h4>
        <?php
        if (empty($creds['dev_id']) || empty($creds['key_id']) || empty($creds['secret'])) {
            echo '<div class="result-box err"><span class="status-err">✗ No se puede enviar</span> — Falta completar las credenciales en Settings.</div>';
        } else {
            // ← AQUÍ LLAMA A crear_orden_externa
            $resultado = $api->crear_orden_externa($orden_prueba);

            if ($resultado['success']) {
                $data = $resultado['data'];
                echo '<div class="result-box ok">';
                echo '<p class="status-ok">✓ ¡Orden creada exitosamente!</p>';
                echo '<table class="datos">';
                echo '<tr><td>External ID</td><td><strong>' . ($data['external_delivery_id'] ?? 'N/A') . '</strong></td></tr>';
                echo '<tr><td>Status</td><td>' . ($data['delivery_status'] ?? 'N/A') . '</td></tr>';
                echo '<tr><td>Fee</td><td>$' . number_format(($data['fee'] ?? 0) / 100, 2) . ' USD</td></tr>';
                echo '<tr><td>Tracking</td><td><a href="' . ($data['tracking_url'] ?? '#') . '" target="_blank">' . ($data['tracking_url'] ?? 'N/A') . '</a></td></tr>';
                echo '</table>';
                echo '</div>';
                echo '<a href="' . admin_url('admin.php?page=doordash-history') . '" class="btn">Ver en el Historial →</a>';
            } else {
                echo '<div class="result-box err">';
                echo '<p class="status-err">✗ Error al crear la orden</p>';
                echo '<p style="font-size:14px; margin:0;">' . htmlspecialchars($resultado['message']) . '</p>';
                echo '</div>';
            }
        }
        ?>
    </div>

    <!-- 4. RESPUESTA TÉCNICA -->
    <div class="section">
        <h4>4. Respuesta técnica (JSON)</h4>
        <pre><?php
        if (isset($resultado)) {
            echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            echo "— No se ejecutó el envío —";
        }
        ?></pre>
    </div>

</div>
</body>
</html>