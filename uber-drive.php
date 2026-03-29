<?php
/**
 * Plugin Name:       Uber Direct Integration
 * Description:       Professional integration for managing deliveries via Uber Direct API.
 * Version:           1.0.4
 * Author:            Ermis Media Production
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
// 3. Scripts & AJAX
add_action('admin_enqueue_scripts', function($hook) {
    // Si no es una página de Uber o de edición de pedido, no cargamos nada
    if (strpos($hook, 'uber') === false && $hook !== 'post.php') return;

    // 1. Cargamos tu script principal (el que ya tenías para settings)
    wp_enqueue_script('uber-script', UB_PLUGIN_URL . 'assets/script.js', array('jquery'), '1.0.1', true);

    // 2. Cargamos el nuevo script de historial SOLO en la página de historial
    if (strpos($hook, 'history') !== false) {
        wp_enqueue_script('uber-history-js', UB_PLUGIN_URL . 'assets/history.js', array('jquery'), '1.0.1', true);
    }

    // 3. DEFINIMOS LAS VARIABLES PARA AMBOS SCRIPTS
    // Esto asegura que tu script.js (viejo) y history.js (nuevo) funcionen.
    $ajax_data = array(
        'ajaxurl'  => admin_url('admin-ajax.php'), // Para history.js
        'ajax_url' => admin_url('admin-ajax.php'), // Para script.js viejo
        'nonce'    => wp_create_nonce('ub_history_nonce')
    );

    // Pasamos los datos al script bajo ambos nombres por compatibilidad
    wp_localize_script('uber-script', 'ub_vars', $ajax_data);
    wp_localize_script('uber-script', 'uber_ajax_object', $ajax_data);
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
    $api_mode      = sanitize_text_field($_POST['api_mode']); // ← AGREGAR ESTA LÍNEA
    $token = $api->get_access_token_test($client_id, $client_secret, $api_mode);

    // 3. Enviamos la respuesta estructurada para que el JS NO de error "Done"
    if (!is_wp_error($token)) {
    // Sobreescribe siempre el token anterior
    update_option('ub_access_token', $token);
    update_option('ub_api_mode', $api_mode);

    wp_send_json_success([
        'message' => 'Connection successful! Token generated.',
        'token'   => $token
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

    // Hemos añadido las columnas: customer_name, delivery_fee, plugin_commission, billing_status y tracking_url
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        uber_id varchar(100) NOT NULL,
        external_id varchar(100) NOT NULL,
        customer_name varchar(255) DEFAULT '',
        order_status varchar(50) NOT NULL,
        delivery_fee decimal(10,2) DEFAULT 0.00,
        plugin_commission decimal(10,2) DEFAULT 0.00,
        billing_status varchar(20) DEFAULT 'unpaid',
        tracking_url text,
        raw_json longtext,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // dbDelta es genial porque si la tabla ya existe, 
    // solo añade las columnas nuevas sin borrar nada.
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
   // error_log('=== ub_check_and_dispatch_uber TRIGGERED for order: ' . $order_id . ' ===');
    
    $order = wc_get_order($order_id);
    
    // 1. VALIDACIÓN: ¿El cliente eligió Uber como envío?
    $shipping_methods = $order->get_shipping_methods();
   // error_log('Total shipping methods: ' . count($shipping_methods));
    
    $shipping_method = reset($shipping_methods);
    $method_id = $shipping_method ? $shipping_method->get_method_id() : '';
    
   // error_log('Shipping method ID detected: ' . $method_id);

    // Solo continuamos si el ID del método contiene la palabra 'uber'
    if (strpos($method_id, 'uber') !== false) {
      //  error_log('Method contains "uber", calling ub_disparar_entrega_uber()...');
        return ub_disparar_entrega_uber($order_id);
    }
    
    //error_log('Method does NOT contain "uber", skipping Uber delivery.');
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
    if (get_post_meta($order_id, '_uber_delivery_id', true)) {
        return true;
    }

    $api = new Uber_API();
    $db  = new Uber_Database();
    $settings = $db->get_credentials();

    // Limpiamos la dirección para evitar duplicados como vimos en los logs
    $address_parts = array_filter([
        $order->get_shipping_address_1(),
        $order->get_shipping_city(),
        $order->get_shipping_state(),
        $order->get_shipping_postcode(),
        'US'
    ]);
    $full_dropoff_address = implode(', ', $address_parts);

    $delivery_data = [
        'external_id'          => (string)$order_id, // CRÍTICO: Uber lo necesita para devolver el ID
        'pickup_name'          => !empty($settings['pickup_name']) ? $settings['pickup_name'] : get_bloginfo('name'),
        'pickup_address'       => trim($settings['pickup_address']),
        'pickup_phone_number'  => $settings['pickup_phone'],
        'dropoff_name'         => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
        'dropoff_address'      => $full_dropoff_address,
        'dropoff_phone_number' => $order->get_shipping_phone() ?: $order->get_billing_phone(),
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
        // GUARDAR DATOS PARA LA COLUMNA
        update_post_meta($order_id, '_uber_delivery_id', $result['id']);
        update_post_meta($order_id, '_uber_order_status', $result['status'] ?? 'pending');
        
        if (isset($result['tracking_url'])) {
            update_post_meta($order_id, '_uber_tracking_url', $result['tracking_url']);
        }

        $order->add_order_note('UBER DIRECT: Sent successfully. Status: ' . ($result['status'] ?? 'pending'));
        return true;
    } else {
        $msg = is_wp_error($result) ? $result->get_error_message() : ($result['message'] ?? 'Error');
        $order->add_order_note('UBER DIRECT FAILED: ' . $msg);
        return $msg;
    }
}

/**
 * Añade el link de seguimiento de Uber al correo electrónico de WooCommerce
 */
add_action('woocommerce_email_before_order_table', 'ub_añadir_tracking_email', 10, 4);

function ub_añadir_tracking_email($order, $sent_to_admin, $plain_text, $email) {
    // Solo queremos mostrarlo al cliente, no al administrador
    if ($sent_to_admin) return;

    $tracking_url = get_post_meta($order->get_id(), '_uber_tracking_url', true);

    if ($tracking_url) {
        echo '<h2>Track Your Delivery</h2>';
        echo '<p>Great news! Your delivery is being handled by Uber Direct. You can track your driver in real-time by clicking the link below:</p>';
        echo '<p><a href="' . esc_url($tracking_url) . '" style="background-color: #000; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">Track My Order</a></p>';
        echo '<hr style="border: 1px solid #eee; margin: 20px 0;">';
    }
}

/**
 * Display the tracking button on the "Thank You" page after payment
 */
add_action('woocommerce_thankyou', 'ub_display_tracking_on_thankyou_page', 20);

function ub_display_tracking_on_thankyou_page($order_id) {
    $tracking_url = get_post_meta($order_id, '_uber_tracking_url', true);

    if ($tracking_url) {
        ?>
        <div class="uber-tracking-box" style="background: #686262; color: #fff; padding: 25px; border-radius: 12px; margin: 20px 0; text-align: center; border: 2px solid #2ecc71;">
            <h2 style="color: #fff; margin-top: 0;">Your Delivery is on its way!</h2>
            <p style="font-size: 16px;">Track your Uber Direct driver in real-time below:</p>
            <a href="<?php echo esc_url($tracking_url); ?>" target="_blank" class="button" style="background-color: #2ecc71; color: #fff; font-weight: bold; padding: 15px 30px; border-radius: 8px; text-decoration: none; display: inline-block; font-size: 18px;">
                Track My Order Now
            </a>
        </div>
        <?php
    }
}

// En tu constructor o archivo de hooks:
add_action('wp_ajax_ub_get_order_json', 'ub_get_order_json_callback');

function ub_get_order_json_callback() {
    global $wpdb;
    $order_id = intval($_POST['order_id']);
    $table_name = $wpdb->prefix . 'uber_direct_orders';

    $raw_json = $wpdb->get_var($wpdb->prepare("SELECT raw_json FROM $table_name WHERE id = %d", $order_id));

    if ($raw_json) {
        wp_send_json_success($raw_json);
    } else {
        wp_send_json_error('No data found');
    }
}


/**
 * Procesa la filtración de historial mediante AJAX
 */
/**
 * Procesa la filtración de historial mediante AJAX
 */
add_action('wp_ajax_filter_uber_history', 'ub_ajax_filter_history');

function ub_ajax_filter_history() {
    // 1. Validar seguridad: Asegúrate que en la vista uses 'uber_history_nonce'
    check_ajax_referer('uber_history_nonce', '_ajax_nonce');

    global $wpdb;
    $table_name = $wpdb->prefix . 'uber_direct_orders';

    // 2. Parámetros
    $paged      = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : 1;
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date   = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
    $per_page   = 20;
    $offset     = ($paged - 1) * $per_page;

    // 3. Construir WHERE para filtros
    $where = " WHERE 1=1";
    if (!empty($start_date) && !empty($end_date)) {
        $where .= $wpdb->prepare(" AND time BETWEEN %s AND %s", $start_date . ' 00:00:00', $end_date . ' 23:59:59');
    }

    // 4. CÁLCULOS EN TIEMPO REAL
    $total_items = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where");
    $total_pages = ceil($total_items / $per_page);
    
    // Sumar balance filtrado
    $unpaid_balance = $wpdb->get_var("SELECT SUM(plugin_commission) FROM $table_name $where AND billing_status = 'unpaid'");
    $unpaid_balance_formatted = '$' . number_format((float)($unpaid_balance ?: 0), 2);

    // 5. Obtener resultados para la tabla
    $resultados = $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY time DESC LIMIT $per_page OFFSET $offset");
    $current_page = $paged;

    // 6. Generar HTML de la tabla
    ob_start();
    $partial_path = UB_PLUGIN_DIR . 'admin/history-table-partial.php';
    if (file_exists($partial_path)) {
        include $partial_path;
    } else {
        echo "<tr><td colspan='6'>Error: partial file not found.</td></tr>";
    }
    $table_html = ob_get_clean();

    // 7. ENVIAR RESPUESTA COMPLETA
    wp_send_json_success([
        'html'           => $table_html,
        'total_orders'   => $total_items,
        'unpaid_balance' => $unpaid_balance_formatted
    ]);
}


add_action('wp_ajax_export_uber_pdf', 'ub_export_history_pdf');

function ub_export_history_pdf() {
    if (!current_user_can('manage_options')) wp_die('No access');
    check_admin_referer('uber_pdf_nonce');

    global $wpdb;
    $table_name = $wpdb->prefix . 'uber_direct_orders';
    
    $start = sanitize_text_field($_GET['start_date']);
    $end = sanitize_text_field($_GET['end_date']);

    $where = " WHERE 1=1";
    if (!empty($start) && !empty($end)) {
        $where .= $wpdb->prepare(" AND time BETWEEN %s AND %s", $start . ' 00:00:00', $end . ' 23:59:59');
    }

    $resultados = $wpdb->get_results("SELECT * FROM $table_name $where ORDER BY time DESC");

    // --- DISEÑO DEL PDF (HTML/CSS) ---
    ob_start();
    ?>
    <style>
        body { font-family: sans-serif; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .stats { margin: 20px 0; display: table; width: 100%; }
        .stat-box { display: table-cell; background: #f4f4f4; padding: 15px; text-align: center; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #a8a5a5; color: #fff; padding: 10px; text-align: left; }
        td { border-bottom: 1px solid #eee; padding: 10px; font-size: 12px; }
        .status-paid { color: green; font-weight: bold; }
        .status-unpaid { color: red; font-weight: bold; }
    </style>

    <div class="header">
        <h1>Uber Direct - Delivery Report</h1>
        <p>Period: <?php echo $start ?: 'All'; ?> to <?php echo $end ?: 'All'; ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Customer</th>
                <th>Status</th>
                <th>Fee</th>
                <th>Commission</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_comm = 0;
            foreach ($resultados as $row) : 
                $total_comm += $row->plugin_commission;
            ?>
                <tr>
                    <td><?php echo date('Y-m-d', strtotime($row->time)); ?></td>
                    <td><?php echo $row->customer_name; ?></td>
                    <td><?php echo strtoupper($row->order_status); ?></td>
                    <td>$<?php echo number_format($row->delivery_fee, 2); ?></td>
                    <td class="status-<?php echo $row->billing_status; ?>">
                        $<?php echo number_format($row->plugin_commission, 2); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 30px; text-align: right; font-size: 18px;">
        <strong>Total Commission: $<?php echo number_format($total_comm, 2); ?></strong>
    </div>
    <?php
    $html = ob_get_clean();

    // --- CONVERSIÓN A PDF ---
    // Si no tienes Dompdf instalado, podemos usar una técnica simple de impresión
    // o descargar Dompdf vía Composer. 
    // Por ahora, para que funcione YA MISMO, forzamos el modo impresión del navegador:
    echo $html;
    echo '<script>window.print();</script>';
    exit;
}


//Ultimos cambios para la actualizacion del estado de la orden..


// --- 8. COLUMNA PERSONALIZADA EN LA LISTA DE PEDIDOS ---

// Registrar la columna
add_filter( 'manage_edit-shop_order_columns', 'ub_add_uber_status_column' );
function ub_add_uber_status_column( $columns ) {
    // Insertamos la columna después de la fecha
    $new_columns = array();
    foreach ( $columns as $key => $name ) {
        $new_columns[ $key ] = $name;
        if ( 'order_date' === $key ) {
            $new_columns['uber_tracking'] = 'Estado Uber';
        }
    }
    return $new_columns;
}

// Llenar la columna con datos
add_action( 'manage_shop_order_custom_column', 'ub_fill_uber_status_column', 10, 2 );
function ub_fill_uber_status_column( $column, $post_id ) {
    if ( 'uber_tracking' === $column ) {
        $status = get_post_meta( $post_id, '_uber_order_status', true ); // Necesitaremos guardar esto en el webhook
        $tracking_url = get_post_meta( $post_id, '_uber_tracking_url', true );

        if ( $tracking_url ) {
            $display_status = $status ? strtoupper($status) : 'ACTIVO';
            echo '<a href="' . esc_url( $tracking_url ) . '" target="_blank" class="button tip" data-tip="Rastrear en Uber" style="background-color:#000; color:#fff; border:none;">' . esc_html($display_status) . '</a>';
        } else {
            echo '<span style="color:#999;">Sin envío</span>';
        }
    }
}
// --- 9. WEBHOOKS DE UBER DIRECT ---

// --- 1. UBER WEBHOOK HANDLER (INGLÉS) ---
add_action( 'rest_api_init', function () {
    register_rest_route( 'uber-direct/v1', '/webhook', array(
        'methods'             => 'POST',
        'callback'            => 'ub_handle_uber_webhook',
        'permission_callback' => '__return_true', 
    ) );
} );

function ub_handle_uber_webhook( $request ) {
    global $wpdb;
    $params = $request->get_json_params();
    
    // Uber sends the order ID in 'external_id'
    $order_id = isset($params['external_id']) ? intval($params['external_id']) : 0;
    $new_status = isset($params['status']) ? sanitize_text_field($params['status']) : '';

    if ( $order_id > 0 && !empty($new_status) ) {
        $table_name = $wpdb->prefix . 'uber_direct_orders';

        // 1. Update your custom History Table
        $wpdb->update(
            $table_name,
            array( 'order_status' => $new_status ),
            array( 'external_id'  => (string)$order_id )
        );

        // 2. Update WooCommerce Order Meta (for the column)
        update_post_meta( $order_id, '_uber_order_status', $new_status );

        // 3. Add Order Note in English
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $order->add_order_note( "Uber Direct Update: Status changed to " . strtoupper($new_status) );
        }

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }
    return new WP_REST_Response( array( 'message' => 'Invalid Data' ), 400 );
}

// --- 2. PROFESSIONAL COLUMN LOGIC (INGLÉS + COLORES) ---
add_filter( 'manage_edit-shop_order_columns', 'ub_register_uber_column', 25 ); 
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'ub_register_uber_column', 25 ); 

function ub_register_uber_column( $columns ) {
    $columns['uber_col'] = 'Delivery Service';
    return $columns;
}

add_action( 'manage_shop_order_custom_column', 'ub_fill_column_fixed', 25, 2 );
add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'ub_fill_column_fixed', 25, 2 );

function ub_fill_column_fixed( $column, $order_or_id ) {
    if ( 'uber_col' === $column ) {
        global $wpdb;
        $order = ( $order_or_id instanceof WC_Order ) ? $order_or_id : wc_get_order( $order_or_id );
        $order_id = $order->get_id();
        
        $is_uber = false;
        foreach ( $order->get_shipping_methods() as $method ) {
            if ( strpos( strtolower( $method->get_method_id() ), 'uber' ) !== false ) {
                $is_uber = true;
                break;
            }
        }

        if ( ! $is_uber ) {
            echo '<span style="color:#777; font-weight:500;">Local Pickup</span>';
            return;
        }

        // Search in history table
        $table_name = $wpdb->prefix . 'uber_direct_orders';
        $uber_data = $wpdb->get_row( $wpdb->prepare( 
            "SELECT order_status, tracking_url FROM $table_name WHERE external_id = %s LIMIT 1", 
            (string)$order_id 
        ));

        if ( $uber_data && !empty($uber_data->tracking_url) ) {
            $blue_color = '#007cba';
            
            // Your Blue Border Button
            echo '<a href="' . esc_url( $uber_data->tracking_url ) . '" target="_blank" 
                     style="background:#fff; color:' . $blue_color . '; border: 2px solid ' . $blue_color . '; 
                            padding:5px 12px; border-radius:50px; text-decoration:none; font-size:10px; 
                            font-weight:bold; display:inline-block; text-transform:uppercase;">
                     See Delivery
                  </a>';
            
            // Colored Status Logic
            $status = strtolower($uber_data->order_status);
            $status_color = '#666'; 

            if (in_array($status, ['pending', 'created'])) $status_color = '#f1c40f'; // Yellow
            elseif (in_array($status, ['pickup', 'en_route'])) $status_color = '#3498db'; // Blue
            elseif (in_array($status, ['delivered', 'completed'])) $status_color = '#27ae60'; // Green

            echo '<div style="font-size:10px; color:' . $status_color . '; margin-top:4px; font-weight:bold; text-transform:uppercase;">' 
                 . esc_html($status) . '</div>';

        } else {
            echo '<span style="color:#d63638; font-weight:bold; font-size:11px;">Not Dispatched</span>';
        }
    }
}


/**
 * 1. Register the Uber Direct Metabox
 */
add_action('add_meta_boxes', 'ub_add_uber_metabox');
function ub_add_uber_metabox() {
    $screens = ['shop_order', 'woocommerce_page_wc-orders']; // Support for both traditional and HPOS
    foreach ($screens as $screen) {
        add_meta_box(
            'uber_direct_metabox',
            'Uber Direct',
            'ub_render_uber_metabox',
            $screen,
            'side', // Right side
            'high'
        );
    }
}

/**
 * 2. Render the Metabox Content
 */
function ub_render_uber_metabox($post_or_order) {
    // Get Order Object
    $order = ($post_or_order instanceof WP_Post) ? wc_get_order($post_or_order->ID) : $post_or_order;
    $order_id = $order->get_id();

    // Check if it is an Uber shipping method
    $is_uber = false;
    foreach ($order->get_shipping_methods() as $method) {
        if (strpos(strtolower($method->get_method_id()), 'uber') !== false) {
            $is_uber = true;
            break;
        }
    }

    if (!$is_uber) {
        echo '<p style="color:#777; font-style:italic;">This order was not placed via Uber Direct.</p>';
        return;
    }

    // Get data from our custom table
    global $wpdb;
    $table_name = $wpdb->prefix . 'uber_direct_orders';
    $uber_data = $wpdb->get_row($wpdb->prepare(
        "SELECT delivery_fee, order_status, tracking_url, time FROM $table_name WHERE external_id = %s LIMIT 1",
        (string)$order_id
    ));

    echo '<div class="ub-metabox-content" style="padding:10px 0;">';

    if ($uber_data) {
        // --- DISPLAY INFO ---
        echo '<p><strong>Shipping Cost:</strong> $' . number_format($uber_data->delivery_fee, 2) . '</p>';
        
        // Formatear fecha para el Delivery Time (o usar el tiempo estimado si lo tienes guardado)
        $delivery_time = date('M j, Y g:i A', strtotime($uber_data->time));
        echo '<p><strong>Request Time:</strong> ' . esc_html($delivery_time) . '</p>';
        
        echo '<p><strong>Status:</strong> <span style="text-transform:uppercase; font-weight:bold; color:#f1c40f;">' . esc_html($uber_data->order_status) . '</span></p>';
        
        if ($uber_data->tracking_url) {
            echo '<a href="' . esc_url($uber_data->tracking_url) . '" target="_blank" class="button button-primary" style="width:100%; text-align:center; margin-top:10px; background:#007cba; border:none;">Track Delivery</a>';
        }
    } else {
        // --- MANUAL DISPATCH BUTTON ---
        echo '<p style="color:#d63638; font-weight:bold;">Order not yet dispatched to Uber.</p>';
        
        // Link to trigger the existing function ub_disparar_entrega_uber
        $dispatch_url = wp_nonce_url(admin_url('admin-ajax.php?action=manual_dispatch_uber&order_id=' . $order_id), 'ub_manual_dispatch_nonce');
        
        echo '<a href="' . esc_url($dispatch_url) . '" class="button button-primary" style="width:100%; text-align:center; margin-top:10px; background:#007cba;">Dispatch to Uber Now</a>';
    }

    echo '</div>';
}

/**
 * 3. AJAX Handler for Manual Dispatch
 */
add_action('wp_ajax_manual_dispatch_uber', 'ub_ajax_manual_dispatch');
function ub_ajax_manual_dispatch() {
    check_admin_referer('ub_manual_dispatch_nonce');

    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    if ($order_id) {
        // Call your existing function
        $result = ub_disparar_entrega_uber($order_id);

        if ($result === true) {
            wp_redirect(admin_url('post.php?post=' . $order_id . '&action=edit&ub_success=1'));
        } else {
            wp_die("Error dispatching to Uber: " . $result);
        }
    }
    exit;
}

