jQuery(document).ready(function($) {
    const form = $('#uber-settings-form');
    const saveBtn = $('#btn-save-uber');
    const testBtn = $('#btn-test-uber');
    const responseDiv = $('#ub-ajax-response');

    // Estado inicial para detectar cambios y habilitar/deshabilitar botones
    let originalData = form.serialize();

    // 1. MONITOR DE CAMBIOS
    form.on('input change', 'input, select, textarea', function() {
        const currentData = form.serialize();
        const hasChanges = (currentData !== originalData);
        saveBtn.prop('disabled', !hasChanges);
        testBtn.prop('disabled', hasChanges);
    });

    // 2. GUARDAR CONFIGURACIÓN (SAVE SETTINGS)
    form.on('submit', function(e) {
        e.preventDefault();
        
        const originalHtml = saveBtn.html(); // Guardamos icono y texto original
        
        // Cambiamos a estado de carga sin borrar el icono
        saveBtn.prop('disabled', true)
               .html('<span class="dashicons dashicons-update spin"></span> Saving...');
        
        const securityNonce = $('#ub_save_nonce').val() || $('input[name="security"]').val() || $('input[name="_wpnonce"]').val();
        const formData = form.serialize() + '&action=ub_save_settings&security=' + securityNonce;

        $.ajax({
            url: uber_ajax_object.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                const typeClass = response.success ? 'ub-success' : 'ub-error';
                const msg = (response.data && response.data.message) ? response.data.message : (response.success ? 'Settings saved successfully' : 'Error saving settings');
                
                responseDiv.hide().removeClass('ub-success ub-error')
                           .addClass(typeClass).html('<p>' + msg + '</p>').fadeIn();

                if (response.success) {
                    originalData = form.serialize();
                    saveBtn.html(originalHtml).prop('disabled', true);
                    testBtn.prop('disabled', false);
                } else {
                    saveBtn.html(originalHtml).prop('disabled', false);
                }
            },
            error: function() {
                responseDiv.addClass('ub-error').html('<p>Server Connection Error</p>').fadeIn();
                saveBtn.html(originalHtml).prop('disabled', false);
            },
            complete: function() {
                setTimeout(() => { responseDiv.fadeOut(); }, 4000);
            }
        });
    });

    // 3. PROBAR CONEXIÓN (TEST CONNECTION)
    testBtn.on('click', function(e) {
        e.preventDefault();
        const originalHtml = $(this).html();
        
        testBtn.prop('disabled', true)
               .html('<span class="dashicons dashicons-admin-network spin"></span> Testing...');
        
        $.post(uber_ajax_object.ajax_url, {
            action: 'ub_test_api_connection',
            ub_test_api_nonce: $('#uber_test_nonce').val(),
            client_id: $('input[name="uber_client_id"]').val(),
            client_secret: $('input[name="uber_client_secret"]').val(),
            pickup_address: $('textarea[name="uber_pickup_address"]').val() || $('#pickup_address').val()
        }, function(response) {
            const typeClass = response.success ? 'ub-success' : 'ub-error';
            const msg = (response.data && response.data.message) ? response.data.message : 'Test complete';
            
            responseDiv.hide().removeClass('ub-success ub-error')
                       .addClass(typeClass).html('<p>' + msg + '</p>').fadeIn();
        }).always(function() {
            testBtn.prop('disabled', false).html(originalHtml);
            setTimeout(() => { responseDiv.fadeOut(); }, 4000);
        });
    });

    // 4. ACTUALIZACIÓN AUTOMÁTICA DEL CHECKOUT (Tu código completo)
    $('form.checkout').on('change', 'select#billing_state, input#billing_city, input#billing_postcode, select#shipping_state, input#shipping_city, input#shipping_postcode', function() {
        $('body').trigger('update_checkout');
    });
});