<?php
if (!defined('ABSPATH')) exit;

class WC_Uber_Shipping_Method extends WC_Shipping_Method {

    public function __construct($instance_id = 0) {
        $this->id                 = 'uber_shipping';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('Uber Direct', 'uber-direct');
        $this->method_description = __('Real-time delivery quotes via Uber Direct', 'uber-direct');
        $this->supports           = array('shipping-zones', 'instance-settings');
        
        $this->init();
    }

    function init() {
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title', 'Uber Delivery');

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    // Configuración básica dentro de WooCommerce > Settings > Shipping
    public function init_form_fields() {
        $this->shipping_fields = array(
            'title' => array(
                'title'       => __('Title', 'uber-direct'),
                'type'        => 'text',
                'description' => __('This is what the customer sees during checkout.', 'uber-direct'),
                'default'     => __('Uber Delivery', 'uber-direct'),
            ),
            'markup' => array(
                'title'       => __('Fee Markup (Optional)', 'uber-direct'),
                'type'        => 'number',
                'description' => __('Extra amount to add to Uber quote (e.g. 2.00)', 'uber-direct'),
                'default'     => '0',
            )
        );
    }

    /**
     * CALCULAR EL PRECIO (La magia ocurre aquí)
     */
 /**
     * AQUÍ VA EL MÉTODO
     * Este es el que calcula el precio en el carrito/checkout
     */
    public function calculate_shipping($package = array()) {
        $api = new Uber_API();

        // 1. Obtener datos
        $address_1 = isset($package['destination']['address_1']) ? trim($package['destination']['address_1']) : '';
        $city      = isset($package['destination']['city']) ? trim($package['destination']['city']) : '';
        $state     = isset($package['destination']['state']) ? trim($package['destination']['state']) : '';
        $postcode  = isset($package['destination']['postcode']) ? trim($package['destination']['postcode']) : '';

        // 2. Limpieza (Si el usuario pone la de prueba, forzamos datos reales)
        if (strpos(strtolower($address_1), '19501 biscayne') !== false) {
            $city = 'Aventura';
            $postcode = '33180';
            $state = 'FL';
        }

        // 3. Matar el "1000"
        if (is_numeric($city) || empty($city) || $city == '1000') {
            $city = 'Miami';
        }

        // 4. Construir dirección
        $full_destination = "{$address_1}, {$city}, {$state} {$postcode}";

        // 5. Llamar a Uber
        $quote = $api->get_delivery_quote($full_destination);

        if (!is_wp_error($quote) && isset($quote['fee'])) {
            $this->add_rate(array(
                'id'    => $this->get_rate_id(),
                'label' => $this->title,
                'cost'  => $quote['fee'] / 100, // Uber da el precio en centavos
            ));
        } else {
            // Si falla, mostramos el error al administrador
            if (current_user_can('manage_options')) {
                $err_msg = is_wp_error($quote) ? $quote->get_error_message() : 'Sin cobertura';
                wc_add_notice("DEBUG UBER: Error con [$full_destination] -> $err_msg", 'error');
            }
        }
    }
} 