<?php
/**
 * Uber Direct API Engine
 * Location: includes/class-uber-api.php
 */

if (!defined('ABSPATH')) exit;

class Uber_API {
    private $db;
    private $base_url = 'https://api.uber.com/v1';

    public function __construct() {
        $this->db = new Uber_Database();
    }

    /**
     * MÉTODO PARA TEST: Valida credenciales enviadas desde el formulario AJAX
     */
    public function get_access_token_test($client_id, $client_secret) {
        $response = wp_remote_post('https://auth.uber.com/oauth/v2/token', [
            'body' => [
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'grant_type'    => 'client_credentials',
                'scope'         => 'eats.deliveries',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response));

        if (isset($body->access_token)) {
            return $body->access_token;
        }

        // Si Uber devuelve un error estructurado (invalid_client, etc)
        $error_msg = isset($body->error) ? $body->error : 'Credenciales inválidas';
        return new WP_Error('uber_auth_error', $error_msg);
    }

    /**
     * Obtiene el Token de acceso (con caché de WordPress)
     */
    private function get_token() {
        $creds = $this->db->get_credentials();
        
        $token = get_transient('uber_access_token');
        if ($token) return $token;

        $token_result = $this->get_access_token_test($creds['client_id'], $creds['client_secret']);

        if (!is_wp_error($token_result)) {
            set_transient('uber_access_token', $token_result, 25 * DAY_IN_SECONDS);
            return $token_result;
        }

        return $token_result;
    }

   /**
 * Obtener Cotización de Envío (Quote)
 */
/**
 * Obtener Cotización (Quote) - Usa el String de los ajustes
 */
public function get_delivery_quote($dropoff_address_string) {
    $creds = $this->db->get_credentials();
    $token = $this->get_token();

    if (is_wp_error($token)) return $token;

    // Limpiamos el string de la dirección del negocio (Pickup)
    $pickup_address = trim(stripslashes($creds['pickup_address']));

    $url = "{$this->base_url}/customers/{$creds['customer_id']}/delivery_quotes";

    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'timeout' => 15,
        'body'    => json_encode([
            'pickup_address'  => $pickup_address, // String limpio
            'dropoff_address' => $dropoff_address_string,
        ]),
    ]);

    if (is_wp_error($response)) return $response;

    $result = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($result['code'])) {
        return new WP_Error('uber_api_error', $result['message'] ?? 'Error en cotización');
    }

    return $result;
}

/**
 * Crear Entrega Real (Create Delivery) - También con String limpio
 */
public function create_delivery($order_data) {
    $creds = $this->db->get_credentials();
    $token = $this->get_token();

    if (is_wp_error($token)) return $token;

    // Aseguramos que la dirección de recogida sea el string limpio de los ajustes
    $order_data['pickup_address'] = trim(stripslashes($creds['pickup_address']));

    $url = "{$this->base_url}/customers/{$creds['customer_id']}/deliveries";

    if ($creds['api_mode'] === 'sandbox') {
        $order_data['test_specifications'] = [
            'robo_courier_specification' => ['mode' => 'auto']
        ];
    }

    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body' => json_encode($order_data),
    ]);

    $result = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($result['id'])) {
        $this->db->guardar_pedido_en_historial($result, $order_data['dropoff_name'] ?? 'Cliente');
    }

    return $result;
}
}