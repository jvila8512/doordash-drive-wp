<?php
/**
 * Plugin Name:       Uber Direct Integration
 * Description:       Integración profesional para gestión de entregas mediante Uber Direct API.
 * Version:           1.0.2
 * Author:            Javy Vila Labrada
 */

if (!defined('ABSPATH')) exit;

define('UB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UB_PLUGIN_URL', plugin_dir_url(__FILE__));

// 1. Cargar las clases
require_once UB_PLUGIN_DIR . 'includes/class-db-uber.php'; 
require_once UB_PLUGIN_DIR . 'includes/class-uber-api.php';
require_once UB_PLUGIN_DIR . 'includes/class-logger.php';

// 2. Menú de Administración
add_action('admin_menu', 'ub_crear_menu');
function ub_crear_menu() {
    add_menu_page('Uber Direct', 'Uber Direct', 'manage_options', 'uber-settings', 'ub_mostrar_configuracion', 'dashicons-car', 25);
    add_submenu_page('uber-settings', 'Historial', 'Historial', 'manage_options', 'uber-history', 'ub_mostrar_historial');
}

function ub_mostrar_configuracion() {
    $db = new Uber_Database();
    include UB_PLUGIN_DIR . 'admin/uber-settings-view.php';
}

function ub_mostrar_historial() {
    include UB_PLUGIN_DIR . 'admin/history-view.php';
}

// 3. Scripts y AJAX
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'uber') === false && $hook !== 'post.php') return;
    wp_enqueue_script('uber-script', UB_PLUGIN_URL . 'assets/script.js', array('jquery'), '1.0.1', true);
    wp_localize_script('uber-script', 'uber_ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
});

// AJAX: Test de Conexión
add_action('wp_ajax_ub_test_api_connection', 'ub_ajax_test_handler');
function ub_ajax_test_handler() {
    check_ajax_referer('ub_test_api_nonce', 'ub_test_api_nonce');
    $api = new Uber_API();
    $token = $api->get_access_token_test(sanitize_text_field($_POST['client_id']), $_POST['client_secret']);
    if (!is_wp_error($token)) {
        wp_send_json_success(['message' => 'Connection successful!']);
    } else {
        wp_send_json_error(['message' => $token->get_error_message()]);
    }
}

// AJAX: Enviar Pedido Manual (EL QUE TE FALTABA)
add_action('wp_ajax_enviar_pedido_a_uber', 'ub_ajax_enviar_manual');
function ub_ajax_enviar_manual() {
    $order_id = intval($_POST['order_id']);
    if (!$order_id) wp_send_json_error('ID de pedido no válido.');
    
    $resultado = ub_disparar_entrega_uber($order_id);
    
    if ($resultado === true) {
        wp_send_json_success('¡Pedido enviado a Uber con éxito!');
    } else {
        wp_send_json_error($resultado);
    }
}

// 4. Activación y WooCommerce Hooks
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

// 5. Metabox en el Pedido
add_action('add_meta_boxes', function() {
    add_meta_box('uber_direct_box', 'Uber Direct Delivery', 'ub_render_uber_box', 'shop_order', 'side', 'default');
});

function ub_render_uber_box($post) {
    $uber_id = get_post_meta($post->ID, '_uber_delivery_id', true);
    if ($uber_id) {
        echo '<p style="color: green;">✅ ID: <code>' . esc_html($uber_id) . '</code></p>';
    } else {
        echo '<button type="button" id="btn-enviar-uber" class="button button-primary" data-order-id="' . $post->ID . '">Enviar a Uber Ahora</button>';
        echo '<div id="uber-res-msg"></div>';
    }
}

// 6. FUNCIÓN MAESTRA: DISPARAR ENTREGA
add_action('woocommerce_order_status_processing', 'ub_disparar_entrega_uber', 10, 1);
function ub_disparar_entrega_uber($order_id) {
    $order = wc_get_order($order_id);
    if (get_post_meta($order_id, '_uber_delivery_id', true)) return true;

    $api = new Uber_API();
    $phone = preg_replace('/[^0-9]/', '', $order->get_shipping_phone() ? $order->get_shipping_phone() : $order->get_billing_phone());
    $phone = (strlen($phone) == 10) ? '+1' . $phone : '+' . $phone;

    $delivery_data = [
        'pickup_name'          => get_bloginfo('name'),
        'pickup_address'       => '123 SW 8th St, Miami, FL 33130',
        'pickup_phone_number'  => '+13055551234',
        'dropoff_name'         => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
        'dropoff_address'      => $order->get_shipping_address_1() . ', ' . $order->get_shipping_city() . ', ' . $order->get_shipping_state() . ' ' . $order->get_shipping_postcode(),
        'dropoff_phone_number' => $phone,
        'manifest_items'       => []
    ];

    foreach ($order->get_items() as $item) {
        $delivery_data['manifest_items'][] = [
            'name' => $item->get_name(),
            'quantity' => $item->get_quantity(),
        ];
    }

    $result = $api->create_delivery($delivery_data);

    if (!is_wp_error($result) && isset($result['id'])) {
        update_post_meta($order_id, '_uber_delivery_id', $result['id']);
        $order->add_order_note('🚀 Uber Direct: ¡Éxito! ID: ' . $result['id']);
        return true;
    } else {
        $error = is_wp_error($result) ? $result->get_error_message() : 'Error desconocido';
        $order->add_order_note('❌ Uber Direct Falló: ' . $error);
        return $error;
    }
}