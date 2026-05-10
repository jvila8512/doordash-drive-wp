<?php
/**
 * Database Management - Uber Direct Integration
 */

if (!defined('ABSPATH')) exit;

class Uber_Database {

    private $option_name = 'uber_api_settings';

    /**
     * SAVE CREDENTIALS
     * Guarda los datos de API y Tienda dinámicamente
     */
    public function save_credentials($data) {
        // Obtenemos los valores actuales para comparar
        $old_settings = $this->get_credentials();

        $settings = array(
            // Limpiamos el string de la dirección para que no tenga barras raras
            'pickup_address' => isset($data['pickup_address']) ? stripslashes(sanitize_text_field($data['pickup_address'])) : $old_settings['pickup_address'],
            'pickup_name'    => isset($data['pickup_name']) ? sanitize_text_field($data['pickup_name']) : $old_settings['pickup_name'],
            'pickup_phone'   => isset($data['pickup_phone']) ? sanitize_text_field($data['pickup_phone']) : $old_settings['pickup_phone'],
            'customer_id'    => isset($data['uber_customer_id']) ? sanitize_text_field($data['uber_customer_id']) : $old_settings['customer_id'],
            'client_id'      => isset($data['uber_client_id']) ? sanitize_text_field($data['uber_client_id']) : $old_settings['client_id'],
            'client_secret'  => isset($data['uber_client_secret']) ? sanitize_text_field($data['uber_client_secret']) : $old_settings['client_secret'],
            'api_mode'       => isset($data['uber_api_mode']) ? sanitize_text_field($data['uber_api_mode']) : $old_settings['api_mode'],
            'plugin_commission' => floatval($data['plugin_commission']), // <--- GUARDAR COMO NÚMERO
            'prep_time'      => isset($data['prep_time']) ? intval($data['prep_time']) : 0, // Minutos de preparación del restaurante
            'webhook_key'    => isset($data['uber_webhook_key']) ? sanitize_text_field($data['uber_webhook_key']) : $old_settings['webhook_key'], // Webhook Signing Key
        );

        update_option($this->option_name, $settings);
        return true; // Siempre retornamos true para evitar el bloqueo del botón
    }

    public function get_credentials() {
        $defaults = [
            'customer_id'    => '',
            'client_id'      => '',
            'client_secret'  => '',
            'api_mode'       => 'sandbox',
            'pickup_address' => '',
            'pickup_phone'   => '',
            'pickup_name'    => '',
            'prep_time'      => 0, // Minutos de preparación - 0 = ASAP
            'webhook_key'    => ''  // Webhook Signing Key para validar requests
        ];
        
        $saved = get_option($this->option_name, []);
        return wp_parse_args($saved, $defaults);
    }
    /**
     * Helper para obtener el modo actual (Sandbox/Production)
     */
    public function get_mode() {
        $creds = $this->get_credentials();
        return $creds['api_mode'];
    }

    /**
     * Guarda el resultado de un pedido de Uber en la tabla de historial
     * He adaptado los nombres de los campos a lo que devuelve el JSON de Uber
     */
   /**
 * Guarda la orden en el historial incluyendo datos para monetización
 */
public function guardar_pedido_en_historial($uber_response, $customer_name) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'uber_direct_orders';

    $settings = $this->get_credentials();
    $mi_comision = !empty($settings['plugin_commission']) ? floatval($settings['plugin_commission']) : 1.00;
    $uber_fee = isset($uber_response['fee']) ? $uber_response['fee'] / 100 : 0;

    // --- MEJORA DE SEGURIDAD PARA EL ID ---
    // Si Uber no devuelve el external_id, intentamos sacarlo de nuestra propia respuesta guardada
    $external_id = !empty($uber_response['external_id']) ? $uber_response['external_id'] : '';
    
    // Si sigue vacío pero tenemos el ID de pedido en el objeto de respuesta que armamos antes
    // (Esto depende de los cambios que hicimos en class-uber-api.php)
    
    $wpdb->insert($table_name, [
        'time'              => current_time('mysql'),
        'uber_id'           => $uber_response['id'],
        'external_id'       => $external_id, // <--- Esto debe coincidir con el ID de WC
        'customer_name'     => $customer_name,
        'order_status'      => $uber_response['status'] ?? 'created',
        'delivery_fee'      => $uber_fee,
        'plugin_commission' => $mi_comision,
        'billing_status'    => 'unpaid',
        'tracking_url'      => $uber_response['tracking_url'] ?? '',
        'raw_json'          => json_encode($uber_response)
    ]);
}
public function get_history($limit = 20, $offset = 0, $start_date = '', $end_date = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'uber_deliveries';
    
    $query = "SELECT * FROM $table_name WHERE 1=1";
    $params = [];

    // Filtro por fechas
    if (!empty($start_date) && !empty($end_date)) {
        $query .= " AND created_at BETWEEN %s AND %s";
        $params[] = $start_date . ' 00:00:00';
        $params[] = $end_date . ' 23:59:59';
    }

    // Paginación
    $query .= " ORDER BY created_at DESC LIMIT %d OFFSET %d";
    $params[] = $limit;
    $params[] = $offset;

    return $wpdb->get_results($wpdb->prepare($query, ...$params));
}


}