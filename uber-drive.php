<?php
/**
 * Plugin Name:       Uber Direct Integration
 * Description:       Professional integration for managing deliveries via Uber Direct API.
 * Version:           1.0.4
 * Author:            Javy Vila Labrada
 */

if (!defined('ABSPATH')) exit;

define('UB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UB_PLUGIN_URL', plugin_dir_url(__FILE__));

// 1. Load classes
require_once UB_PLUGIN_DIR . 'includes/class-db-uber.php'; 
require_once UB_PLUGIN_DIR . 'includes/class-uber-api.php';
require_once UB_PLUGIN_DIR . 'includes/class-logger.php';

// 2. Admin Menu
add_action('admin_menu', 'ub_crear_menu');
function ub_crear_menu() {
    add_menu_page('Uber Direct', 'Uber Direct', 'manage_options', 'uber-settings', 'ub_mostrar_configuracion', 'dashicons-car', 25);
    add_submenu_page('uber-settings', 'History', 'History', 'manage_options', 'uber-history', 'ub_mostrar_historial');
}

function ub_mostrar_configuracion() {
    $db = new Uber_Database();
    include UB_PLUGIN_DIR . 'admin/uber-settings-view.php';
}

function ub_mostrar_historial() {
    include UB_PLUGIN_DIR . 'admin/history-view.php';
}

// 3. Scripts & AJAX
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'uber') === false && $hook !== 'post.php') return;
    wp_enqueue_script('uber-script', UB_PLUGIN_URL . 'assets/script.js', array('jquery'), '1.0.1', true);
    wp_localize_script('uber-script', 'uber_ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
});

// AJAX: API Connection Test
add_action('wp_ajax_ub_test_api_connection', 'ub_ajax_test_handler');
function ub_ajax_test_handler() {
    // 1. Verificación de seguridad
    check_ajax_referer('ub_test_api_nonce', 'ub_test_api_nonce');

    $api = new Uber_API();
    
    // 2. Obtenemos el token usando los datos que vienen del formulario
    $client_id     = sanitize_text_field($_POST['client_id']);
    $client_secret = $_POST['client_secret']; // Sin sanitizar para no romper caracteres especiales
    
    $token = $api->get_access_token_test($client_id, $client_secret);

    // 3. Enviamos la respuesta estructurada para que el JS NO de error "Done"
    if (!is_wp_error($token)) {
        wp_send_json_success([
            'message' => 'Connection successful! Token generated.',
            'token'   => $token // Opcional, para debug
        ]);
    } else {
        wp_send_json_error([
            'message' => 'Authentication failed: ' . $token->get_error_message()
        ]);
    }
}

// AJAX: Manual Order Send
add_action('wp_ajax_enviar_pedido_a_uber', 'ub_ajax_enviar_manual');
function ub_ajax_enviar_manual() {
    $order_id = intval($_POST['order_id']);
    if (!$order_id) wp_send_json_error('Invalid Order ID.');
    
    $resultado = ub_disparar_entrega_uber($order_id);
    
    if ($resultado === true) {
        wp_send_json_success('Order sent to Uber successfully!');
    } else {
        wp_send_json_error('Error sending order: ' . $resultado);
    }
}

// 4. Activation & WooCommerce Hooks
register_activation_hook(__FILE__, 'ub_crear_tabla_pedidos');
function ub_crear_tabla_pedidos() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'uber_direct_orders';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        uber_id varchar(100) NOT NULL,
        external_id varchar(100) NOT NULL,
        order_status varchar(50) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

add_action('woocommerce_shipping_init', 'ub_init_shipping_method');
function ub_init_shipping_method() {
    if (!class_exists('WC_Uber_Shipping_Method')) {
        require_once UB_PLUGIN_DIR . 'includes/class-shipping-uber.php';
    }
}

add_filter('woocommerce_shipping_methods', function($methods) {
    $methods['uber_shipping'] = 'WC_Uber_Shipping_Method';
    return $methods;
});

// 5. Order Metabox
add_action('add_meta_boxes', function() {
    add_meta_box('uber_direct_box', 'Uber Direct Delivery', 'ub_render_uber_box', 'shop_order', 'side', 'default');
});

function ub_render_uber_box($post) {
    $uber_id = get_post_meta($post->ID, '_uber_delivery_id', true);
    if ($uber_id) {
        echo '<p style="color: green; font-weight: bold;"> Sent to Uber</p>';
        echo '<p>ID: <code>' . esc_html($uber_id) . '</code></p>';
    } else {
        echo '<p>This order has not been sent to Uber yet.</p>';
        echo '<button type="button" id="btn-enviar-uber" class="button button-primary" data-order-id="' . $post->ID . '">Send to Uber Now</button>';
        echo '<div id="uber-res-msg" style="margin-top:10px;"></div>';
    }
}

// 6. MAIN FUNCTION: TRIGGER DELIVERY (DYNAMIC DATA)
// Se dispara cuando el pago es aceptado (estado Processing)
add_action('woocommerce_order_status_processing', 'ub_check_and_dispatch_uber', 10, 1);

function ub_check_and_dispatch_uber($order_id) {
    $order = wc_get_order($order_id);
    
    // 1. VALIDACIÓN: ¿El cliente eligió Uber como envío?
    $shipping_methods = $order->get_shipping_methods();
    $shipping_method = reset($shipping_methods);
    $method_id = $shipping_method ? $shipping_method->get_method_id() : '';

    // Solo continuamos si el ID del método contiene la palabra 'uber'
    if (strpos($method_id, 'uber') !== false) {
        return ub_disparar_entrega_uber($order_id);
    }
    
    // Si no fue Uber, no hacemos nada
    return;
}
// AJAX: Save Settings (ESTO ES LO QUE TE FALTABA)
add_action('wp_ajax_ub_save_settings', 'ub_ajax_save_settings_handler');
function ub_ajax_save_settings_handler() {
    // 1. Validar seguridad (debe coincidir con el script.js)
    check_ajax_referer('uber_save_creds', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'You do not have permission to do this.']);
    }

    // 2. Guardar en la base de datos usando tu clase
    $db = new Uber_Database();
    $result = $db->save_credentials($_POST);

    if ($result) {
        wp_send_json_success(['message' => 'Settings saved successfully!']);
    } else {
        wp_send_json_error(['message' => 'Failed to save settings.']);
    }
}

// 7. LA FUNCIÓN QUE REALMENTE ENVÍA A UBER (Esta es la que te faltaba)
function ub_disparar_entrega_uber($order_id) {
    $order = wc_get_order($order_id);
    if (get_post_meta($order_id, '_uber_delivery_id', true)) return true;

    $api = new Uber_API();
    $db  = new Uber_Database();
    $settings = $db->get_credentials();

    // Pickup Data
    $pickup_address = !empty($settings['pickup_address']) ? stripslashes($settings['pickup_address']) : get_option('woocommerce_store_address');
    $pickup_phone = !empty($settings['pickup_phone']) ? $settings['pickup_phone'] : '+13055551234';
    $pickup_name = !empty($settings['pickup_name']) ? $settings['pickup_name'] : get_bloginfo('name');

    // Customer Phone Formatting
    $customer_phone = preg_replace('/[^0-9]/', '', $order->get_shipping_phone() ?: $order->get_billing_phone());
    $customer_phone = (strlen($customer_phone) == 10) ? '+1' . $customer_phone : '+' . ltrim($customer_phone, '+');

    $delivery_data = [
        'pickup_name'          => $pickup_name,
        'pickup_address'       => trim($pickup_address),
        'pickup_phone_number'  => $pickup_phone,
        'dropoff_name'         => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
        'dropoff_address'      => $order->get_shipping_address_1() . ', ' . $order->get_shipping_city() . ', ' . $order->get_shipping_state() . ' ' . $order->get_shipping_postcode() . ', US',
        'dropoff_phone_number' => $customer_phone,
        'manifest_items'       => []
    ];

    foreach ($order->get_items() as $item) {
        $delivery_data['manifest_items'][] = [
            'name'     => $item->get_name(),
            'quantity' => $item->get_quantity(),
        ];
    }

    $result = $api->create_delivery($delivery_data);

    if (!is_wp_error($result) && isset($result['id'])) {
        update_post_meta($order_id, '_uber_delivery_id', $result['id']);
        $order->add_order_note('UBER DIRECT: Success! ID: ' . $result['id']);
        return true;
    } else {
        $msg = is_wp_error($result) ? $result->get_error_message() : ($result['message'] ?? 'Error');
        $order->add_order_note('UBER DIRECT FAILED: ' . $msg);
        return $msg;
    }
}