<?php
/**
 * Settings View - Uber Direct Integration (Final Corrected IDs)
 */
if (!defined('ABSPATH')) exit;

$plugin_version = "1.0.1"; 
$uber_settings = get_option('uber_api_settings', []);
?>

<style>
    .uber-settings-wrapper { max-width: 850px; margin: 40px auto; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    .uber-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; overflow: hidden; }
    .uber-card-header { background: #f8f9fa; padding: 15px 20px; border-bottom: 1px solid #ccd0d4; display: flex; align-items: center; }
    .uber-card-header h2 { margin: 0; font-size: 16px; color: #1d2327; }
    .uber-card-body { padding: 20px; }
    .form-table th { width: 200px; font-weight: 600; }
    .regular-text { width: 100%; border-radius: 4px; border: 1px solid #8c8f94; }
    
    .dd-log-container { background: #1a1a1a; color: #00ff00; padding: 15px; border-radius: 4px; height: 150px; overflow-y: auto; font-family: monospace; font-size: 12px; }

    #ub-ajax-response { padding: 15px 20px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid; font-weight: 500; display: none; }
    .ub-success { background: #d4edda; color: #155724; border-color: #28a745; }
    .ub-error { background: #f8d7da; color: #721c24; border-color: #dc3545; }

    .button-large { display: inline-flex !important; align-items: center !important; justify-content: center !important; height: 50px !important; padding: 0 30px !important; }
    .button .dashicons { margin-right: 8px !important; transform-origin: center center !important; display: inline-block !important; }

    .spin { animation: ub-rotate 1s infinite linear !important; }
    @keyframes ub-rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

    #btn-save-uber:disabled { background: #666 !important; color: #ccc !important; cursor: not-allowed !important; }
    #btn-save-uber:enabled { background: #000 !important; color: #fff !important; cursor: pointer; border: none; }
</style>

<div class="wrap">
    <div class="uber-settings-wrapper">
        
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px;">
            <div style="display: flex; align-items: center;">
                <div style="background: #000; padding: 12px; border-radius: 12px; margin-right: 15px;">
                    <span class="dashicons dashicons-car" style="color: white; font-size: 30px; width: 30px; height: 30px;"></span>
                </div>
                <div>
                    <h1 style="margin: 0; font-size: 28px; font-weight: 800;">Uber Direct</h1>
                    <p style="margin: 0; color: #666;">Control your delivery fleet</p>
                </div>
            </div>
            <div style="background: #eee; padding: 5px 12px; border-radius: 15px; font-size: 11px; font-weight: bold;">V <?php echo $plugin_version; ?></div>
        </div>

        <div id="ub-ajax-response"></div>

        <form id="uber-settings-form" method="POST">
            <input type="hidden" id="ub_save_nonce" value="<?php echo wp_create_nonce('uber_save_creds'); ?>">
            
            <div class="uber-card" style="border-left: 5px solid #000;">
                <div class="uber-card-header"><h2>1. Pickup Location Details</h2></div>
                <div class="uber-card-body">
                    <table class="form-table">
                        <tr>
                            <th>Store Name</th>
                            <td><input name="pickup_name" type="text" value="<?php echo esc_attr($uber_settings['pickup_name'] ?? ''); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th>Address (JSON)</th>
                            <td><textarea name="pickup_address" class="regular-text" style="height:90px;"><?php echo esc_textarea($uber_settings['pickup_address'] ?? ''); ?></textarea></td>
                        </tr>
                        <tr>
                            <th>Phone</th>
                            <td><input name="pickup_phone" type="text" value="<?php echo esc_attr($uber_settings['pickup_phone'] ?? ''); ?>" class="regular-text"></td>
                        </tr>
                                        
                    </table>
                </div>
            </div>

            <div class="uber-card" style="border-left: 5px solid #276ee5;">
                <div class="uber-card-header"><h2>2. API Authentication</h2></div>
                <div class="uber-card-body">
                    <table class="form-table">
                        <tr>
                            <th>Customer ID</th>
                            <td><input name="uber_customer_id" type="text" value="<?php echo esc_attr($uber_settings['customer_id'] ?? ''); ?>" class="regular-text code"></td>
                        </tr>
                        <tr>
                            <th>Client ID</th>
                            <td><input name="uber_client_id" type="text" value="<?php echo esc_attr($uber_settings['client_id'] ?? ''); ?>" class="regular-text code"></td>
                        </tr>
                        <tr>
                            <th>Client Secret</th>
                            <td><input name="uber_client_secret" type="password" value="<?php echo esc_attr($uber_settings['client_secret'] ?? ''); ?>" class="regular-text"></td>
                        </tr>
                                            <tr>
                        <th scope="row"><label for="plugin_commission">Commission per Order ($)</label></th>
                        <td>
                            <input name="plugin_commission" type="number" step="0.01" id="plugin_commission" 
                                value="<?php echo esc_attr($uber_settings['plugin_commission'] ?? '1.00'); ?>" class="regular-text">
                            <p class="description">Amount you will charge the merchant for each successful Uber delivery.</p>
                        </td>
</tr>
                        <tr>
                            <th>Environment</th>
                            <td>
                                <select name="uber_api_mode" style="width: 200px;">
                                    <option value="sandbox" <?php selected($uber_settings['api_mode'] ?? 'sandbox', 'sandbox'); ?>>Sandbox</option>
                                    <option value="production" <?php selected($uber_settings['api_mode'] ?? 'sandbox', 'production'); ?>>Production</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-bottom: 30px;">
                <button type="submit" id="btn-save-uber" class="button button-primary button-large" disabled>
                    <span class="dashicons dashicons-saved"></span> Save Configuration
                </button>
                
                <button type="button" id="btn-test-uber" class="button button-large">
                    <span class="dashicons dashicons-admin-links"></span> Test API Connection
                </button>
            </div>

            <div class="uber-card">
                <div class="uber-card-header"><h2>3. System Logs</h2></div>
                <div class="uber-card-body">
                    <div class="dd-log-container" id="uber-logs">[System] Ready.</div>
                </div>
            </div>
        </form>
    </div>
</div>

<input type="hidden" id="uber_test_nonce" value="<?php echo wp_create_nonce('ub_test_api_nonce'); ?>">