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
public function get_access_token_test($client_id, $client_secret, $mode = 'sandbox') {
    // Según tu doc, el scope es siempre este
   $scope = ($mode === 'sandbox') ? 'eats.deliveries' : 'direct.organizations';

    $response = wp_remote_post('https://auth.uber.com/oauth/v2/token', [
        'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        'body' => [
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'grant_type'    => 'client_credentials',
            'scope'         => $scope,
        ],
    ]);

    if (is_wp_error($response)) return $response;

    $body = json_decode(wp_remote_retrieve_body($response));

    if (isset($body->access_token)) {
        return $body->access_token; // Solo devuelve el string, no guarda nada
    }

    return new WP_Error('uber_error', $body->error ?? 'Unknown Error');
}

    /**
     * Obtiene el Token de acceso (con caché de WordPress)
     */
   private function get_token() {
    // Primero revisamos si ya lo tenemos guardado
    $token = get_transient('uber_access_token');
    if ($token) return $token;

    // Si no está, lo pedimos
    $creds = $this->db->get_credentials();
    $token_result = $this->get_access_token_test($creds['client_id'], $creds['client_secret'], $creds['api_mode']);

    // Si Uber nos dio el token, LO GUARDAMOS por 25 días
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
 * Get Delivery Quote - Uses the address string from settings
 */
public function get_delivery_quote($dropoff_address_string) {
    $creds = $this->db->get_credentials();
    $token = $this->get_token();

    // If token generation fails, return the error
    if (is_wp_error($token)) {
        return $token;
    }

    // Clean and sanitize the pickup address (Origin)
    $pickup_address = trim(stripslashes($creds['pickup_address']));
    
    // Clean and sanitize the dropoff address (Destination)
    $dropoff_address = trim(stripslashes($dropoff_address_string));

    $url = "{$this->base_url}/customers/{$creds['customer_id']}/delivery_quotes";

    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ],
        'timeout' => 20, // Increased timeout for external API stability
        'body'    => json_encode([
            'pickup_address'  => $pickup_address,
            'dropoff_address' => $dropoff_address,
        ]),
    ]);

    // Handle connection or WordPress-level errors
    if (is_wp_error($response)) {
        return $response;
    }

    $result = json_decode(wp_remote_retrieve_body($response), true);

    // Handle Uber API specific errors (e.g., unauthorized, distance_too_far)
    if (isset($result['code'])) {
        $error_message = isset($result['message']) ? $result['message'] : 'Error retrieving quote from Uber.';
        return new WP_Error('uber_api_error', $error_message);
    }

    return $result;
}

/**
 * Crear Entrega Real (Create Delivery) - También con String limpio
 */
public function create_delivery($order_data) {
    $creds = $this->db->get_credentials();
    $token = $this->get_token();
    

    if (is_wp_error($token)) 
     {         error_log('Uber create_delivery ERROR: Token failed - ' . $token->get_error_message());

 return $token;


     }  

    // Aseguramos que la dirección de recogida sea el string limpio de los ajustes
    $order_data['pickup_address'] = trim(stripslashes($creds['pickup_address']));

    $url = "{$this->base_url}/customers/{$creds['customer_id']}/deliveries";

    if ($creds['api_mode'] === 'sandbox') {
        $order_data['test_specifications'] = [
            'robo_courier_specification' => ['mode' => 'auto']
        ];
    }

// LOG 1: Qué se va a enviar
    error_log('Uber create_delivery REQUEST:');
    error_log('URL: ' . $url);
    error_log('Data: ' . json_encode($order_data, JSON_PRETTY_PRINT));




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