<?php
if (!defined('ABSPATH')) exit;

class WC_Uber_Shipping_Method extends WC_Shipping_Method {

    // Declare properties to prevent PHP 8+ "Deprecated" errors
    public $id;
    public $instance_id;
    public $method_title;
    public $method_description;
    public $supports;
    public $title;
    public $enabled;
    public $form_fields; // Fixed variable name for WooCommerce standards

    public function __construct($instance_id = 0) {
        $this->id                 = 'uber_shipping';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('Uber Direct', 'uber-direct');
        $this->method_description = __('Real-time delivery quotes via Uber Direct', 'uber-direct');
        $this->supports           = array('shipping-zones', 'instance-settings');
        
        $this->init();
    }

    function init() {
        // Load the form fields
        $this->init_form_fields(); 
        $this->init_settings();

        // Define the Title seen by the customer in English
        $this->title   = $this->get_option('title', 'Uber Delivery');
        $this->enabled = $this->get_option('enabled', 'yes');

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Admin Settings Configuration
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'uber-direct'),
                'type'    => 'checkbox',
                'label'   => __('Enable Uber Direct', 'uber-direct'),
                'default' => 'yes',
            ),
            'title' => array(
                'title'       => __('Method Title', 'uber-direct'),
                'type'        => 'text',
                'description' => __('This is the delivery option name the customer sees during checkout.', 'uber-direct'),
                'default'     => __('Uber Delivery', 'uber-direct'),
            ),
            'markup' => array(
                'title'       => __('Fee Markup (Optional)', 'uber-direct'),
                'type'        => 'number',
                'description' => __('Additional amount to add to the Uber quote (e.g. 5.00).', 'uber-direct'),
                'default'     => '0',
            )
        );
    }

    /**
     * Shipping Calculation Logic
     */
  public function calculate_shipping($package = array()) {
 $api = new Uber_API();

    $address_1 = $package['destination']['address_1'] ?? '';
    $address_2 = $package['destination']['address_2'] ?? ''; // Agregamos dirección 2 por si acaso
    $city      = $package['destination']['city'] ?? '';
    $state     = $package['destination']['state'] ?? '';
    $postcode  = $package['destination']['postcode'] ?? '';
    $country   = $package['destination']['country'] ?? 'US';

    if (empty($address_1) || empty($postcode)) return;

    // CREAR DIRECCIÓN LIMPIA
    // Usamos un array y eliminamos duplicados o partes vacías
    $address_parts = array_filter([$address_1, $address_2, $city, $state, $postcode, $country]);
    
    // Unimos con coma y espacio. 
    // Esto evita el formato "Calle, , Ciudad" si falta algún dato.
    $full_destination = implode(', ', $address_parts);

    
    $quote = $api->get_delivery_quote($full_destination);

    // FIX START: Check if the API returned a WP_Error object
    if (is_wp_error($quote)) {
        $error_message = "Uber Direct Error: " . $quote->get_error_message();
        
        if (!wc_has_notice($error_message, 'error')) {
            wc_add_notice($error_message, 'error');
        }
        return; // Exit safely
    }

    // Now it is safe to check if it's an array with a fee
    if (isset($quote['fee'])) {
       // wc_clear_notices();
        $this->add_rate([
            'id'    => $this->get_rate_id(),
            'label' => $this->title,
            'cost'  => (float) $quote['fee'] / 100,
        ]);
    } 
    // Handle specific Uber error codes inside the array
    elseif (isset($quote['code'])) {
        $msg = "Delivery unavailable: ";
        
        if ($quote['code'] === 'address_undeliverable') {
            $msg .= $quote['metadata']['details'] ?? "Address is outside delivery radius.";
        } else {
            $msg .= $quote['message'] ?? "Unknown API error.";
        }

        if (!wc_has_notice($msg, 'error')) {
            wc_add_notice($msg, 'error');
        }
    }
}
}