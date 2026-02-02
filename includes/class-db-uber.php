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
            'pickup_name'    => ''
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
    public function guardar_pedido_en_historial($data, $customer_name) {
        global $wpdb;
        // Puedes decidir si usas la misma tabla o creas una nueva prefix_uber_orders
        $table_name = $wpdb->prefix . 'uber_direct_orders';

        $wpdb->insert(
            $table_name,
            [
                'time'          => current_time('mysql'),
                'uber_id'       => $data['id'] ?? '', // ID que empieza con del_
                'external_id'   => $data['external_id'] ?? '', // Tu ID de WP
                'order_status'  => $data['status'] ?? 'pending',
                'fee'           => $data['fee'] ?? 0,
                'tracking_url'  => $data['tracking_url'] ?? '',
                'customer_name' => $customer_name
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );
    }
}