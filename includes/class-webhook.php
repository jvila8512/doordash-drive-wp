<?php
/**
 * Webhook — Retroalimentación de DoorDash Drive
 * Ubicación: includes/class-webhook.php
 * 
 * DoorDash llama a esta URL cada vez que el estado de una entrega cambia.
 * Tú defines la URL del webhook en tu dashboard de DoorDash Drive.
 * 
 * Para registrar la URL del webhook en WordPress, agrega esto a tu plugin principal:
 *      add_action('rest_api_init', function () {
 *          require_once DD_PLUGIN_DIR . 'includes/class-webhook.php';
 *          $webhook = new DD_Webhook();
 *          register_rest_route('doordash/v1', '/webhook', [
 *              'methods'             => 'POST',
 *              'callback'            => [$webhook, 'handle'],
 *              'permission_callback' => '__return_true', // DoorDash no manda auth de WordPress
 *          ]);
 *      });
 * 
 * La URL que configuras en DoorDash será:
 *      https://tu-sitio.com/wp-json/doordash/v1/webhook
 */

if (!defined('ABSPATH')) exit;

class DD_Webhook {

    /**
     * Manejador principal — WordPress lo llama cuando DoorDash hace POST
     */
    public function handle(WP_REST_Request $request) {
        $body = $request->get_json_params();

        // 1. Validar que el body no esté vacío
        if (empty($body)) {
            $this->log('Webhook recibido pero el body está vacío.');
            return new WP_REST_Response(['error' => 'Empty body'], 400);
        }

        // 2. Extraer datos clave que envía DoorDash
        $external_id    = $body['external_delivery_id'] ?? null;
        $status         = $body['delivery_status'] ?? null;
        $tracking_url   = $body['tracking_url'] ?? null;
        $event_type     = $body['event_type'] ?? 'status_update';

        // 3. Validar campos obligatorios
        if (!$external_id || !$status) {
            $this->log('Webhook incompleto — falta external_delivery_id o delivery_status.');
            return new WP_REST_Response(['error' => 'Missing required fields'], 400);
        }

        // 4. Logear la recepción
        $this->log("Webhook recibido | ID: {$external_id} | Status: {$status} | Event: {$event_type}");

        // 5. Actualizar el estado en la base de datos
        $db = new DD_Database();
        $db->actualizar_estado_pedido($external_id, $status, $tracking_url);

        // 6. Ejecutar acciones según el estado
        $this->procesar_estado($external_id, $status, $body);

        // 7. Retornar 200 a DoorDash (si no responde 200, DoorDash va a reintentar)
        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    /**
     * Procesar según el estado recibido
     * 
     * Estados que envía DoorDash:
     *   - created          → Orden creada, esperando que el restaurante la confirme
     *   - confirmed        → El restaurante confirmó la orden
     *   - pickup_eta_updated → Se actualizó la hora estimada de recogida
     *   - picked_up        → El delivery ya recogió la orden en tu tienda
     *   - dropoff_eta_updated → Se actualizó la hora estimada de entrega
     *   - delivered        → ¡La orden fue entregada al cliente!
     *   - cancelled        → La orden fue cancelada
     *   - failed           → Algo salió mal
     */
    private function procesar_estado($external_id, $status, $body) {
        switch ($status) {
            case 'created':
                $this->log("Order {$external_id}: Creada, pendiente confirmación.");
                break;

            case 'confirmed':
                $this->log("Order {$external_id}: Confirmada por el restaurante.");
                break;

            case 'picked_up':
                $this->log("Order {$external_id}: Recogida por el delivery.");
                // Aquí podrías enviar un SMS o email al cliente diciendo "tu pedido está en camino"
                do_action('dd_order_picked_up', $external_id, $body);
                break;

            case 'delivered':
                $this->log("Order {$external_id}: ✓ ENTREGADA exitosamente.");
                // Aquí confirmas la entrega, actualizas inventario, envías factura, etc.
                do_action('dd_order_delivered', $external_id, $body);
                break;

            case 'cancelled':
                $this->log("Order {$external_id}: Cancelada. Razón: " . ($body['cancellation_reason'] ?? 'No especificada'));
                do_action('dd_order_cancelled', $external_id, $body);
                break;

            case 'failed':
                $this->log("Order {$external_id}: ✗ FALLÓ. Detalles: " . ($body['failure_reason'] ?? 'Sin detalles'));
                do_action('dd_order_failed', $external_id, $body);
                break;

            default:
                $this->log("Order {$external_id}: Estado recibido: {$status}");
                break;
        }
    }

    /**
     * Guardar log en la opción dd_system_logs (misma que muestra el panel de Settings)
     */
    private function log($message) {
        $logs = get_option('dd_system_logs', []);
        $logs[] = [
            'date'    => date('Y-m-d H:i:s'),
            'message' => $message
        ];

        // Mantener solo los últimos 50 logs
        if (count($logs) > 50) {
            $logs = array_slice($logs, -50);
        }

        update_option('dd_system_logs', $logs);
    }
}