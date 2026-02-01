<?php
/**
 * Database Management - DoorDash Drive Integration
 * Location: includes/class-db.php
 */

if (!defined('ABSPATH')) exit;

class DD_Database {

    // Centralizamos todo en una sola fila de la base de datos
    private $option_name = 'dd_api_settings';

    /**
     * SAVE CREDENTIALS
     * Guarda todos los datos (API + Tienda) en un solo array
     */
    public function save_credentials($data) {
        if (!is_array($data)) return false;

        $creds = [
            'dev_id'         => sanitize_text_field($data['dev_id'] ?? ''),
            'key_id'         => sanitize_text_field($data['key_id'] ?? ''),
            'secret'         => sanitize_text_field($data['secret'] ?? ''), 
            'api_mode'       => sanitize_text_field($data['api_mode'] ?? 'sandbox'),
            'pickup_address' => sanitize_text_field($data['pickup_address'] ?? ''),
            'pickup_phone'   => sanitize_text_field($data['pickup_phone'] ?? ''),
            'pickup_name'    => sanitize_text_field($data['pickup_name'] ?? ''),
        ];

        return update_option($this->option_name, $creds);
    }

    /**
     * Recupera todas las configuraciones
     */
    public function get_credentials() {
        $defaults = [
            'dev_id'         => '',
            'key_id'         => '',
            'secret'         => '',
            'api_mode'       => 'sandbox',
            'pickup_address' => '',
            'pickup_phone'   => '',
            'pickup_name'    => ''
        ];
        
        $saved = get_option($this->option_name, []);
        return wp_parse_args($saved, $defaults);
    }

    /**
     * Helper para obtener el modo actual
     */
    public function get_mode() {
        $creds = $this->get_credentials();
        return $creds['api_mode'];
    }

    /**
     * Guarda el resultado de un pedido en la tabla personalizada
     */
    public function guardar_pedido_en_historial($data, $customer_name) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'doordash_orders';

        $wpdb->insert(
            $table_name,
            [
                'time'          => current_time('mysql'),
                'external_id'   => $data['external_delivery_id'] ?? '',
                'order_status'  => $data['delivery_status'] ?? 'created',
                'fee'           => $data['fee'] ?? 0,
                'tracking_url'  => $data['tracking_url'] ?? '',
                'customer_name' => $customer_name
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s']
        );
    }
}