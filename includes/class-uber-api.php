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
    // CORRECCIÓN: En producción necesitamos AMBOS scopes. 
    // En sandbox, usualmente con eats.deliveries basta, pero no sobra poner ambos.
    $scope = ($mode === 'sandbox') 
        ? 'eats.deliveries' 
        : 'eats.deliveries direct.organizations'; // Espacio entre ellos

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
        return $body->access_token;
    }

    // Tip: Uber suele devolver el error en $body->error o $body->message
    $error_msg = $body->error ?? $body->message ?? 'Unknown Error';
    return new WP_Error('uber_error', $error_msg);
}

    /**
     * Obtiene el Token de acceso (con caché de WordPress)
     */
   private function get_token() {
    // 1. Intentar obtener el token guardado
    $token = get_transient('uber_access_token');
    
    if ($token) {
        error_log("UBER DEBUG: Token recuperado de TRANSIENT. Primeros 15: " . substr($token, 0, 15));
        return $token;
    }

    error_log("UBER DEBUG: No hay token en transient. Solicitando uno nuevo...");

    // 2. Si no hay token, lo solicitamos
    $creds = $this->db->get_credentials();
    
    error_log("UBER DEBUG: Usando Client ID: " . $creds['client_id'] . " en modo: " . $creds['api_mode']);

    $token_result = $this->get_access_token_test(
        $creds['client_id'], 
        $creds['client_secret'], 
        $creds['api_mode']
    );

    if (!is_wp_error($token_result)) {
        error_log("UBER DEBUG: Token nuevo generado con ÉXITO.");
        
        // Guardar por 7 días
        set_transient('uber_access_token', $token_result, 7 * DAY_IN_SECONDS);
        return $token_result;
    }

    error_log("UBER DEBUG: ERROR al generar token: " . $token_result->get_error_message());
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

    if (is_wp_error($token)) {
       // error_log("UBER DEBUG: Error obteniendo token: " . $token->get_error_message());
        return $token;
    }

    $url = "{$this->base_url}/customers/{$creds['customer_id']}/delivery_quotes";
    
    // Preparamos el Body
    $payload = [
        'pickup_address'  => $creds['pickup_address'], 
        'dropoff_address' => $dropoff_address_string,
        'pickup_times'    => [0]
    ];

    $json_payload = json_encode($payload);

    // --- LOGS DE SALIDA ---
    //error_log("UBER DEBUG: URL -> " . $url);
   // error_log("UBER DEBUG: TOKEN (primeros 15) -> " . substr($token, 0, 15) . "...");
   // error_log("UBER DEBUG: PAYLOAD ENVIADO -> " . $json_payload);

    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ],
        'timeout' => 20,
        'body'    => $json_payload,
    ]);

    // --- LOGS DE RESPUESTA ---
    if (is_wp_error($response)) {
       // error_log("UBER DEBUG: WP_ERROR -> " . $response->get_error_message());
        return $response;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

   // error_log("UBER DEBUG: HTTP STATUS -> " . $status_code);
   // error_log("UBER DEBUG: RESPONSE BODY -> " . $response_body);

    $result = json_decode($response_body, true);

    if (isset($result['code']) || isset($result['error']) || $status_code >= 400) {
        $error_message = $result['message'] ?? 'Error en la API de Uber.';
        return new WP_Error('uber_api_error', $error_message, $result);
    }

    return $result;
}

/**
 * Crear Entrega Real (Create Delivery) - También con String limpio
 */
public function create_delivery($order_data) {
    $creds = $this->db->get_credentials();
    $token = $this->get_token();
    
    if (is_wp_error($token)) {
        return $token;
    }

    // 1. Asegurar que las direcciones sean JSON Strings
    // Si ya vienen como string desde el quote, perfecto. Si son arrays, hay que encodearlos.
    if (is_array($creds['pickup_address'])) {
        $order_data['pickup_address'] = json_encode($creds['pickup_address']);
    } else {
        $order_data['pickup_address'] = $creds['pickup_address'];
    }

    if (is_array($order_data['dropoff_address'])) {
        $order_data['dropoff_address'] = json_encode($order_data['dropoff_address']);
    }

    // 2. Manejo del ID externo
    if (isset($order_data['order_id'])) {
        $order_data['external_id'] = (string) $order_data['order_id'];
    }

    // 3. Configuración de Sandbox
    if ($creds['api_mode'] === 'sandbox') {
        $order_data['test_specifications'] = [
            'robo_courier_specification' => ['mode' => 'auto']
        ];
    }

    $url = "{$this->base_url}/customers/{$creds['customer_id']}/deliveries";

    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'timeout' => 30, // Crear una entrega puede tardar un poco más
        'body'    => json_encode($order_data),
    ]);

    if (is_wp_error($response)) return $response;

    $result = json_decode(wp_remote_retrieve_body($response), true);

    // 4. Verificación de éxito de Uber
    if (isset($result['id'])) {
        $result['external_id'] = $order_data['external_id'] ?? '';
        $this->db->guardar_pedido_en_historial($result, $order_data['dropoff_name'] ?? 'Cliente');
        return $result;
    }

    // Manejo de errores de la API
    $error_msg = $result['message'] ?? 'Error desconocido al crear la entrega.';
    return new WP_Error('uber_delivery_failed', $error_msg, $result);
}
}