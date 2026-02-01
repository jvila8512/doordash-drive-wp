<?php
/**
 * Plugin Name:       DoorDash Drive Integration
 * Description:       Integración profesional para gestión de entregas mediante DoorDash Drive API v2. Incluye cifrado AES-256 para credenciales y panel de historial.
 * Version:           1.0.0
 * Author:            Javy Vila Labrada
 * Author URI:        https://javy.com
 * License:           GPL-2.0+
 */

if (!defined('ABSPATH')) exit;

// 1. Definir constantes primero
define('DD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DD_ENCRYPTION_KEY', '7db8a9c2f5e14d3b8a0c9e8f7a6b5c4d'); // CAMBIA ESTO

// 2. Cargar las clases
require_once DD_PLUGIN_DIR . 'includes/class-cipher.php';
require_once DD_PLUGIN_DIR . 'includes/class-db.php';
require_once DD_PLUGIN_DIR . 'includes/class-api.php';
require_once DD_PLUGIN_DIR . 'includes/class-logger.php';

// 3. Función Global para otros plugins
function DD_API() {
    return new DD_API();
}

// 4. Menú de Administración
add_action('admin_menu', 'dd_crear_menu');
function dd_crear_menu() {
    add_menu_page(
        'DoorDash Drive',
        'DoorDash',
        'manage_options',
        'doordash-settings',
        'dd_mostrar_configuracion',
        'dashicons-unarchive',
        25
    );
    
    add_submenu_page(
        'doordash-settings',
        'Historial de Entregas',
        'Historial',
        'manage_options',
        'doordash-history',
        'dd_mostrar_historial'
    );
}

// 5. Guardar credenciales en admin_init (se ejecuta ANTES de que WordPress envíe headers)
add_action('admin_init', 'dd_guardar_credenciales');
function dd_guardar_credenciales() {
    // Solo actuar si estamos en nuestra página y hay un POST
    if (!isset($_POST['dd_save_action'])) return;
    if (!isset($_GET['page']) || $_GET['page'] !== 'doordash-settings') return;
    if (!check_admin_referer('dd_save_creds')) return;

    $db = new DD_Database();
    $resultado = $db->save_credentials($_POST);

    // Pasar el resultado como parámetro en la URL del redirect
    $msg = $resultado ? 'saved' : 'no_changes';

    wp_redirect(admin_url('admin.php?page=doordash-settings&dd_msg=' . $msg));
    exit;
}





// 6. Renderizar Vista (solo HTML, sin lógica de guardado)
function dd_mostrar_configuracion() {
    $db = new DD_Database();
    $creds = $db->get_credentials();
    $current_mode = $db->get_mode();

    include DD_PLUGIN_DIR . 'admin/settings-view.php';
}

// 7. Renderizar Vista de Historial
function dd_mostrar_historial() {
    include DD_PLUGIN_DIR . 'admin/history-view.php';
}

// 8. Actuación: Crear Tabla
register_activation_hook(__FILE__, 'dd_crear_tabla_pedidos');
function dd_crear_tabla_pedidos() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'doordash_orders';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        external_id varchar(100) NOT NULL,
        order_status varchar(50) NOT NULL,
        fee int(10) DEFAULT 0,
        tracking_url text NOT NULL,
        customer_name varchar(255) DEFAULT '',
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// 9. Encolar Scripts
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'doordash') === false) return;
    
    wp_enqueue_script('dd-admin-js', DD_PLUGIN_URL . 'assets/script.js', array('jquery'), '1.1', true);
    
    wp_localize_script('dd-admin-js', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'dd_msg'   => isset($_GET['dd_msg']) ? sanitize_text_field($_GET['dd_msg']) : ''
    ));
});

// 10. Respuestas AJAX
add_action('wp_ajax_dd_test_api_connection', 'dd_ajax_test_handler');
function dd_ajax_test_handler() {
    check_ajax_referer('dd_test_api_nonce');
    $api = new DD_API();
    $resultado = $api->test_conexion_directa(
        sanitize_text_field($_POST['dev_id']),
        sanitize_text_field($_POST['key_id']),
        $_POST['secret'],
        sanitize_text_field($_POST['api_mode'])
    );
    if ($resultado['success']) {
        wp_send_json_success(['message' => 'Connection successful! Your credentials are valid.']);
    } else {
        wp_send_json_error(['message' => 'Invalid credentials or API error. ' . $resultado['message']]);
    }
}

add_action('wp_ajax_dd_get_order_details', 'dd_ajax_get_order_details_handler');
function dd_ajax_get_order_details_handler() {
    check_ajax_referer('dd_test_api_nonce');
    $api = new DD_API();
    $resultado = $api->obtener_detalle_entrega(sanitize_text_field($_POST['order_id']));
    if ($resultado['success']) {
        wp_send_json_success($resultado['data']);
    } else {
        wp_send_json_error(['message' => $resultado['message']]);
    }
}