<?php
/**
 * Class to handle communication with the DoorDash Drive API.
 * Location: includes/class-api.php
 */

if (!defined('ABSPATH')) exit;

class DD_API {
    private $db;

    public function __construct() {
        $this->db = new DD_Database();
    }

    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Genera el JWT usando credenciales de la DB
     */
    public function generar_jwt() {
        $creds = $this->db->get_credentials();
        if (empty($creds['dev_id'])) return new WP_Error('missing_creds', 'No credentials found.');
        
        return $this->generar_jwt_manual($creds['dev_id'], $creds['key_id'], $creds['secret']);
    }

    /**
     * Generador base de JWT
     */
    private function generar_jwt_manual($dev_id, $key_id, $secret) {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT', 'dd-ver' => 'DD-JWT-V1']);
        $payload = json_encode([
            'aud' => 'doordash',
            'iss' => $dev_id,
            'kid' => $key_id,
            'exp' => time() + 300,
            'iat' => time()
        ]);

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);
        $secret_decoded = base64_decode(strtr($secret, '-_', '+/'));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret_decoded, true);
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $this->base64UrlEncode($signature);
    }

    /**
     * ESTA ES LA FUNCIÓN QUE FALTABA: El motor de peticiones
     */
private function make_request($method, $endpoint, $body = []) {
    $url = ($this->db->get_mode() === 'production') 
        ? 'https://openapi.doordash.com/drive/v2' 
        : 'https://openapi.doordash.com/drive/v2'; // Ajusta si usas otra URL de sandbox

    // Generar el Token JWT (asumo que ya tienes esta lógica)
    $token = $this->generar_jwt(); 

    $args = [
        'method'  => $method,
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body'    => !empty($body) ? json_encode($body) : null,
    ];

    $response = wp_remote_request($url . $endpoint, $args);

    if (is_wp_error($response)) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code($response);
    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    // SI EL CÓDIGO NO ES EXITOSO (400, 401, 422, etc.)
    if ($code < 200 || $code >= 300) {
        $error_msg = $response_body['message'] ?? 'Unknown Error';
        
        // Si DoorDash nos da detalles de qué campo falló (field_errors)
        if (!empty($response_body['field_errors'])) {
            $detalles = [];
            foreach ($response_body['field_errors'] as $error) {
                $detalles[] = $error['field'] . ': ' . $error['error'];
            }
            $error_msg .= ' (' . implode(' | ', $detalles) . ')';
        }

        return new WP_Error('api_error', $error_msg, $response_body);
    }

    return $response_body;
}

    private function procesar_respuesta($response) {
        if (is_wp_error($response)) return $response;

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code >= 200 && $status_code < 300) {
            return $body; // Retornamos los datos directamente
        } else {
            return new WP_Error('api_error', isset($body['message']) ? $body['message'] : 'Error ' . $status_code);
        }
    }

    /**
     * Obtener detalle para el modal del Historial
     */
    public function obtener_detalle_entrega($external_id) {
        $res = $this->make_request('GET', '/deliveries/' . $external_id);
        if (is_wp_error($res)) {
            return ['success' => false, 'message' => $res->get_error_message()];
        }
        return ['success' => true, 'data' => $res];
    }

    /**
     * Test de conexión para el botón de Settings
     */
    public function test_conexion_directa($dev_id, $key_id, $secret, $mode) {
        $jwt = $this->generar_jwt_manual($dev_id, $key_id, $secret);
        $url = "https://openapi.doordash.com/drive/v2/deliveries";

        $response = wp_remote_get($url, [
            'headers' => ['Authorization' => 'Bearer ' . $jwt],
            'timeout' => 15
        ]);

        $code = wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 500) {
            return ['success' => true, 'message' => 'Connection validated.'];
        }
        return ['success' => false, 'message' => 'Invalid credentials or API error.'];
    }
    /**
 * Método Genérico para crear órdenes
 * Incluye campos obligatorios + instrucciones de entrega
 */
public function crear_orden_externa($args) {
    $creds = $this->db->get_credentials();

    $defaults = [
        'external_id'      => 'ORDER-' . uniqid(),
        'customer_name'    => 'Customer',
        'customer_address' => '',
        'customer_phone'   => '',
        'order_value'      => 0,
        'instructions'     => '',
    ];
    $datos = wp_parse_args($args, $defaults);

    // ESTRUCTURA CORREGIDA SEGÚN API V2
  $body = [
    "external_delivery_id"   => $datos['external_id'],
    
    // Datos de la Tienda (Pickup)
    "pickup_address"         => $creds['pickup_address'],
    "pickup_business_name"   => $creds['pickup_name'],
    "pickup_phone_number"    => $creds['pickup_phone'],
    
    // Datos del Cliente (Drop-off) - AJUSTADO SEGÚN ERROR DE VALIDACIÓN
    "dropoff_address"        => $datos['customer_address'], // Prueba con este nombre
    "dropoff_phone_number"   => $datos['customer_phone'],
    "dropoff_contact_given_name" => $datos['customer_name'],
    "dropoff_instructions"   => $datos['instructions'],
    
    "order_value"            => (int)$datos['order_value'],
];

    $res = $this->make_request('POST', '/deliveries', $body);

    if (!is_wp_error($res)) {
        $this->db->guardar_pedido_en_historial($res, $datos['customer_name']);
        return ['success' => true, 'data' => $res];
    }

    // Mejora: Si hay error de validación, intentamos extraer el detalle técnico
    $error_data = $res->get_error_data();
    $msg = $res->get_error_message();
    
    return ['success' => false, 'message' => $msg, 'details' => $error_data];
}
    public function crear_orden_externaP($args) {
    // Definimos los valores por defecto por si falta algo
    $defaults = [
        'name'    => 'Customer',
        'address' => '',
        'phone'   => '',
        'value'   => 0, // Valor en centavos (ej: 2000 = $20.00)
        'items'   => []
    ];
    $datos = wp_parse_args($args, $defaults);

    // Estructura que exige DoorDash
    $body = [
        "external_delivery_id" => "ORDER-" . uniqid(),
        "pickup_address"       => "901 Market St, San Francisco, CA 94103", // Tu local
        "pickup_business_name" => get_bloginfo('name'),
        "pickup_phone_number"  => "+16505555555",
        "dropoff_address"      => $datos['address'],
        "dropoff_phone_number" => $datos['phone'],
        "dropoff_contact_given_name" => $datos['name'],
        "order_value"          => $datos['value']
    ];

    // Llamamos a nuestro motor central
    $res = $this->make_request('POST', '/deliveries', $body);

    if (!is_wp_error($res)) {
        // Guardamos en historial automáticamente
        $this->db->guardar_pedido_en_historial($res, $datos['name']);
        return ['success' => true, 'order_id' => $res['external_delivery_id'], 'tracking' => $res['tracking_url']];
    }

    return ['success' => false, 'message' => $res->get_error_message()];
}
}