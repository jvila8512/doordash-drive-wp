<?php
/**
 * Delivery History View - DoorDash Drive Integration
 * Location: admin/history-view.php
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'doordash_orders';
$resultados = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time DESC LIMIT 50");
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Delivery History</h1>
    
    <button id="btn-refresh-history" class="page-title-action">
        <span class="dashicons dashicons-update" style="margin-top:4px;"></span> Refresh
    </button>
    <hr class="wp-header-end">

    <div id="dd-history-table-container" style="margin-top: 20px;">
        <table class="wp-list-table widefat fixed striped table-view-list">
            <thead>
                <tr>
                    <th style="width: 15%;">Date</th>
                    <th style="width: 15%;">External ID</th>
                    <th>Customer</th>
                    <th style="width: 10%;">Status</th>
                    <th style="width: 10%;">Fee</th>
                    <th style="width: 20%;">Actions</th>
                </tr>
            </thead>
            <tbody id="dd-history-body">
                <?php if (empty($resultados)) : ?>
                    <tr><td colspan="6" style="text-align:center;">No records found.</td></tr>
                <?php else : ?>
                    <?php foreach ($resultados as $orden) : ?>
                        <tr>
                            <td><?php echo date('m/d/Y H:i', strtotime($orden->time)); ?></td>
                            <td><strong><?php echo esc_html($orden->external_id); ?></strong></td>
                            <td><?php echo esc_html($orden->customer_name); ?></td>
                            <td>
                                <span class="dd-status-pill status-<?php echo esc_attr($orden->order_status); ?>">
                                    <?php echo esc_html($orden->order_status); ?>
                                </span>
                            </td>
                            <td>$<?php echo number_format($orden->fee / 100, 2); ?></td>
                            <td>
                                <a href="<?php echo esc_url($orden->tracking_url); ?>" target="_blank" class="button button-small">Track</a>
                                <button type="button" class="button button-small btn-view-json" data-id="<?php echo $orden->external_id; ?>">View JSON</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="dd-modal-json" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8);">
    <div style="background:#fff; margin:5% auto; padding:20px; width:70%; max-height:80vh; border-radius:8px; overflow:hidden; position:relative;">
        <span id="close-dd-modal" style="position:absolute; right:20px; top:15px; cursor:pointer; font-size:24px;">&times;</span>
        <h3>Technical Details (API Response)</h3>
        <hr>
        <div id="dd-json-viewer" style="background:#272822; color:#f8f8f2; padding:15px; border-radius:5px; overflow-y:auto; max-height:60vh; font-family:monospace; white-space:pre-wrap;">
            Loading data...
        </div>
    </div>
</div>

<style>
    /* Status Pill Styles */
    .dd-status-pill {
        padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase;
    }
    .status-created { background: #d1ecf1; color: #0c5460; }
    .status-picked_up { background: #fff3cd; color: #856404; }
    .status-delivered { background: #d4edda; color: #155724; }
    
    /* Animation for the refresh button */
    .spin { animation: dd-spin 1s infinite linear; }
    @keyframes dd-spin { from {transform:rotate(0deg);} to {transform:rotate(360deg);} }
</style>