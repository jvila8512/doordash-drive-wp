<?php
/**
 * Settings View - DoorDash Drive Integration
 */
if (!defined('ABSPATH')) exit;
$plugin_version = "1.0"; 
?>

<style>
    .dd-settings-wrapper {
        max-width: 800px;
        margin: 60px auto;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    }

    .dd-notices-area { margin: 0 0 20px 0; }

    .dd-notices-area .notice {
        width: 100% !important;
        max-width: 800px !important;
        margin: 0 0 10px 0 !important;
        box-sizing: border-box;
        border-left-width: 4px !important;
    }

    /* Notice del AJAX — separado, fuera de dd-notices-area */
    #dd-ajax-notice {
        display: none;
        width: 100% !important;
        max-width: 800px !important;
        margin: 0 0 20px 0 !important;
        box-sizing: border-box;
        border-left-width: 4px !important;
    }

    .dd-settings-wrapper .regular-text, .dd-settings-wrapper select { 
        width: 100%;
        max-width: 450px; 
    }

    .postbox-header { border-bottom: 1px solid #eee; background: #fcfcfc; }

    /* Estilo para los Logs */
    .dd-log-container {
        background: #1e1e1e;
        color: #00ff00;
        font-family: 'Courier New', Courier, monospace;
        padding: 15px;
        border-radius: 4px;
        height: 250px;
        overflow-y: scroll;
        font-size: 13px;
        line-height: 1.5;
        border: 1px solid #333;
    }
    .dd-log-entry { margin-bottom: 5px; border-bottom: 1px solid #2a2a2a; padding-bottom: 2px; }
    .dd-log-date { color: #888; margin-right: 10px; }
    .dd-log-error { color: #ff5555; }
    .dd-log-success { color: #55ff55; }

    .btn-save-main:hover { background: #e02a07 !important; cursor: pointer; }

    /* Alinear iconos dentro de botones */
    .dd-btn-icon {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .dd-btn-icon .dashicons {
        display: inline-block;
        vertical-align: middle;
        margin: 0;
        font-size: 18px;
        width: 18px;
        height: 18px;
    }

    /* Botón deshabilitado */
    .btn-save-main:disabled,
    .btn-save-main[disabled] {
        background: #e85d45 !important;
        border-color: #d44d36 !important;
        color: rgba(255, 255, 255, 0.7) !important;
        cursor: not-allowed !important;
        opacity: 1 !important;
        box-shadow: none !important;
    }

    /* Spinner para botones */
    .dd-btn-icon .dashicons.spin {
        display: inline-block;
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>

<div class="wrap">
    <div class="dd-settings-wrapper">
        
        <!-- HEADER -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
            <div style="display: flex; align-items: center;">
                <div style="background: #ff3008; padding: 10px; border-radius: 10px; margin-right: 15px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(255, 48, 8, 0.2);">
                    <span class="dashicons dashicons-location-alt" style="color: white; font-size: 32px; width: 32px; height: 32px;"></span>
                </div>
                <div>
                    <h1 style="margin: 0; padding: 0; line-height: 1; font-size: 24px;">DoorDash Drive Settings</h1>
                    <p style="margin: 5px 0 0; color: #666;">Configure your automated delivery infrastructure.</p>
                </div>
            </div>
            <div style="background: #2d2d2d; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: bold; color: #fff;">
                v<?php echo $plugin_version; ?>
            </div>
        </div>

        <!-- NOTICE DEL AJAX (test connection) -->
        <div id="dd-ajax-notice"></div>

        <!-- FORMULARIO -->
        <form id="dd-settings-form" method="POST" action="">
            <?php wp_nonce_field('dd_save_creds'); ?>
            <input type="hidden" name="dd_save_action" value="1">

            <div class="metabox-holder">
                
                <!-- 1. Store Pickup -->
                <div class="postbox" style="border-left: 4px solid #ff3008; margin-bottom: 25px;">
                    <div class="postbox-header"><h2 class="hndle" style="padding: 12px; margin: 0;">1. Store Pickup Configuration</h2></div>
                    <div class="inside" style="padding: 20px;">
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr><th>Store Name</th><td><input name="pickup_name" type="text" value="<?php echo esc_attr($creds['pickup_name'] ?? ''); ?>" class="regular-text"></td></tr>
                                <tr><th>Physical Address</th><td><input name="pickup_address" type="text" value="<?php echo esc_attr($creds['pickup_address'] ?? ''); ?>" class="regular-text"></td></tr>
                                <tr><th>Contact Phone</th><td><input name="pickup_phone" type="text" value="<?php echo esc_attr($creds['pickup_phone'] ?? ''); ?>" class="regular-text"></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 2. API -->
                <div class="postbox" style="border-left: 4px solid #2d2d2d; margin-bottom: 25px;">
                    <div class="postbox-header"><h2 class="hndle" style="padding: 12px; margin: 0;">2. API Connectivity</h2></div>
                    <div class="inside" style="padding: 20px;">
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr><th>Developer ID</th><td><input name="dev_id" type="text" value="<?php echo esc_attr($creds['dev_id'] ?? ''); ?>" class="regular-text code"></td></tr>
                                <tr><th>Key ID</th><td><input name="key_id" type="text" value="<?php echo esc_attr($creds['key_id'] ?? ''); ?>" class="regular-text code"></td></tr>
                                <tr><th>Signing Secret</th><td><input name="secret" type="password" value="<?php echo esc_attr($creds['secret'] ?? ''); ?>" class="regular-text"></td></tr>
                                <tr><th>Environment</th><td>
                                    <select name="api_mode">
                                        <option value="sandbox" <?php selected($current_mode ?? 'sandbox', 'sandbox'); ?>>Sandbox</option>
                                        <option value="production" <?php selected($current_mode ?? 'sandbox', 'production'); ?>>Production</option>
                                    </select>
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Botones -->
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 25px; background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; margin-bottom: 25px;">
                    <button type="submit" id="btn-guardar" class="button button-primary button-large btn-save-main dd-btn-icon" disabled style="background: #ff3008; border-color: #e02a07; padding: 0 30px; height: 46px;">
                        <span class="dashicons dashicons-saved"></span> Save Configuration
                    </button>
                    <div style="display: flex; gap: 10px;">
                        <input type="hidden" id="dd_test_nonce" value="<?php echo wp_create_nonce('dd_test_api_nonce'); ?>">
                        <button type="button" id="btn-verificar-conexion" class="button button-large dd-btn-icon" style="height: 46px;">
                            <span class="dashicons dashicons-update"></span> Test Connection
                        </button>
                    </div>
                </div>

                <!-- 3. Logs -->
                <div class="postbox" style="border-left: 4px solid #00ff00; margin-bottom: 25px;">
                    <div class="postbox-header" style="display: flex; justify-content: space-between; align-items: center; padding-right: 12px;">
                        <h2 class="hndle" style="padding: 12px; margin: 0;">3. System Logs & Activity</h2>
                        <button type="button" class="button button-small" onclick="location.reload();">Refresh Logs</button>
                    </div>
                    <div class="inside" style="padding: 20px;">
                        <div class="dd-log-container">
                            <?php 
                            $logs = get_option('dd_system_logs', []);
                            if (empty($logs)) {
                                echo '<div class="dd-log-entry">Waiting for activity...</div>';
                            } else {
                                foreach (array_reverse((array)$logs) as $log) {
                                    $type_class = (strpos(strtolower($log['message']), 'error') !== false) ? 'dd-log-error' : 'dd-log-success';
                                    echo '<div class="dd-log-entry">';
                                    echo '<span class="dd-log-date">[' . esc_html($log['date'] ?? date('Y-m-d H:i:s')) . ']</span>';
                                    echo '<span class="' . $type_class . '">' . esc_html($log['message']) . '</span>';
                                    echo '</div>';
                                }
                            }
                            ?>
                        </div>
                        <p class="description" style="margin-top: 10px;">Showing the last 50 delivery events and API interactions.</p>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>