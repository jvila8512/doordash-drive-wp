<?php
/**
 * Delivery History & Billing View - Uber Direct Integration
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'uber_direct_orders';

// 1. Lógica inicial de carga (PHP)
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Obtener totales para balance global
$total_commissions_unpaid = $wpdb->get_var("SELECT SUM(plugin_commission) FROM $table_name WHERE billing_status = 'unpaid'");
$total_commissions_unpaid = $total_commissions_unpaid ? (float)$total_commissions_unpaid : 0;

$total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
$total_pages = ceil($total_items / $per_page);

// Consulta inicial
$resultados = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time DESC LIMIT $per_page OFFSET $offset");

$db_manager = new Uber_Database();
$settings = $db_manager->get_credentials();
$payment_url = !empty($settings['payment_link']) ? $settings['payment_link'] : '#';
?>

<style>
    .ub-status-pill { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
    .status-completed { background: #d4edda; color: #155724; }
    .status-pending { background: #fff3cd; color: #856404; }
    .status-cancelled { background: #f8d7da; color: #721c24; }
    .status-pickup { background: #d1ecf1; color: #0c5460; }
    
    .filter-box { background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #ccd0d4; margin-bottom: 20px; display: flex; align-items: flex-end; gap: 15px; flex-wrap: wrap; }
    .filter-group { display: flex; flex-direction: column; gap: 5px; }
    .filter-group label { font-weight: 600; color: #1d2327; }
    .filter-group input { padding: 5px; border-radius: 4px; border: 1px solid #8c8f94; }
    
    .stats-container { display: flex; gap: 20px; margin-bottom: 20px; }
    .stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); flex: 1; border-left: 4px solid #000; }
    
    #uber-history-body { transition: opacity 0.3s ease; }
</style>

<div class="wrap">
    <h1 class="wp-heading-inline">Delivery History & Billing</h1>
    <hr class="wp-header-end">

   <div class="filter-box">
    <div class="filter-group">
        <label>From:</label>
        <input type="date" id="start_date">
    </div>
    <div class="filter-group">
        <label>To:</label>
        <input type="date" id="end_date">
    </div>
    <button type="button" id="btn-filter-ajax" class="button button-primary">
        <span class="dashicons dashicons-filter" style="margin-top:4px;"></span> Filter
    </button>
    <button type="button" id="btn-reset-ajax" class="button">Clear</button>
    
    <button type="button" id="btn-export-pdf" class="button" style="background: #d63638; color: white; border: none; margin-left: auto;">
        <span class="dashicons dashicons-pdf" style="margin-top:4px;"></span> Export PDF Report
    </button>
</div>

    <div class="stats-container">
        <div class="stat-card" style="border-left-color: #2271b1;">
            <span style="display:block; font-size: 12px; color: #646970; font-weight: bold; text-transform: uppercase;">Total Orders</span>
            <strong id="stat-total-orders" style="font-size: 24px;"><?php echo $total_items; ?></strong>
        </div>

        <div class="stat-card" style="border-left-color: #d63638; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span style="display:block; font-size: 12px; color: #646970; font-weight: bold; text-transform: uppercase;">Unpaid Balance</span>
                <strong id="stat-unpaid-balance" style="font-size: 24px; color: #d63638;">$<?php echo number_format($total_commissions_unpaid, 2); ?></strong>
            </div>
            
        </div>
    </div>

    <div class="uber-card" style="background:#fff; border: 1px solid #ccd0d4; border-radius:8px;">
        <table class="wp-list-table widefat fixed striped table-view-list">
            <thead>
                <tr>
                    <th style="width: 15%;">Date</th>
                    <th>Customer / Reference</th>
                    <th style="width: 12%;">Status</th>
                    <th style="width: 12%;">Delivery Fee</th>
                    <th style="width: 12%;">Commission</th>
                    <th style="width: 15%;">Actions</th>
                </tr>
            </thead>
            <tbody id="uber-history-body">
                <?php include 'history-table-partial.php'; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="ub-modal-json" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8);">
    <div style="background:#fff; margin:5% auto; padding:20px; width:70%; max-height:80vh; border-radius:8px; overflow:hidden; position:relative;">
        <span id="close-ub-modal" style="position:absolute; right:20px; top:15px; cursor:pointer; font-size:24px;">&times;</span>
        <h3>Technical Details (Uber API Response)</h3>
        <hr>
        <pre id="ub-json-viewer" style="background:#272822; color:#f8f8f2; padding:15px; border-radius:5px; overflow-y:auto; max-height:60vh; font-family: monospace;"></pre>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    
// Evento para Exportar PDF
$('#btn-export-pdf').on('click', function() {
    const start = $('#start_date').val();
    const end = $('#end_date').val();
    
    // Redirigimos a una URL que genera el PDF
    // Usamos window.open para que se descargue en una pestaña nueva
    const url = ajaxurl + '?action=export_uber_pdf&start_date=' + start + '&end_date=' + end + '&_wpnonce=<?php echo wp_create_nonce("uber_pdf_nonce"); ?>';
    window.open(url, '_blank');
});



    // Esta es la función principal que hace la magia
    function loadHistory(page = 1) {
        const $body = $('#uber-history-body');
        $body.css('opacity', '0.5'); // Efecto visual de carga

        const data = {
            action: 'filter_uber_history',
            paged: page,
            start_date: $('#start_date').val(),
            end_date: $('#end_date').val(),
            _ajax_nonce: '<?php echo wp_create_nonce("uber_history_nonce"); ?>'
        };

        $.post(ajaxurl, data, function(response) {
            if(response.success) {
                // 1. Actualizamos el HTML de la tabla
                $body.html(response.data.html);
                
                // 2. Actualizamos los números de las tarjetas de arriba
                $('#stat-total-orders').text(response.data.total_orders);
                $('#stat-unpaid-balance').text(response.data.unpaid_balance);
            } else {
                alert('Error al filtrar los datos');
            }
            $body.css('opacity', '1');
        }).fail(function() {
            alert('Error de conexión con el servidor');
            $body.css('opacity', '1');
        });
    }

    // Evento para el botón "Filtrar"
    $('#btn-filter-ajax').on('click', function(e) {
        e.preventDefault();
        loadHistory(1); // Siempre vuelve a la página 1 al filtrar
    });

    // Evento para los botones de Paginación (Anterior/Siguiente)
    // Usamos $(document).on porque estos botones se crean dinámicamente
    $(document).on('click', '.prev-page-ajax, .next-page-ajax', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        loadHistory(page);
    });

    // Evento para el botón "Limpiar"
    $('#btn-reset-ajax').on('click', function() {
        $('#start_date, #end_date').val('');
        loadHistory(1);
    });
    
    // Evento para el botón "Mark as Delivered"
    $(document).on('click', '.btn-mark-delivered', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const id = $btn.data('id');
        const externalId = $btn.data('external');
        
        if (!confirm('Are you sure you want to mark this order as DELIVERED?')) {
            return;
        }
        
        $btn.prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ub_mark_delivered',
                nonce: '<?php echo wp_create_nonce("uber_admin_nonce"); ?>',
                id: id,
                external_id: externalId
            },
            success: function(response) {
                if (response.success) {
                    alert('Order marked as DELIVERED successfully!');
                    loadHistory(); // Refresh the table
                } else {
                    alert('Error: ' + (response.data?.message || 'Unknown error'));
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt" style="font-size:14px; width:14px; height:14px; margin-right:4px;"></span> Delivered');
                }
            },
            error: function() {
                alert('Connection error');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt" style="font-size:14px; width:14px; height:14px; margin-right:4px;"></span> Delivered');
            }
        });
    });

});
</script>