jQuery(document).ready(function($) {
    const form = $('#uber-settings-form');
    const saveBtn = $('#btn-save-uber');
    const testBtn = $('#btn-test-uber');
    const responseDiv = $('#ub-ajax-response');

    // Mantenemos el estado inicial de los datos
    let originalData = form.serialize();

    // 1. MONITOR DE CAMBIOS
    // Habilita/deshabilita botones según si hay cambios sin guardar
    form.on('input change', 'input, select, textarea', function() {
        const currentData = form.serialize();
        const hasChanges = (currentData !== originalData);
        saveBtn.prop('disabled', !hasChanges);
        testBtn.prop('disabled', hasChanges); // No permite testear si hay cambios sin guardar
    });

    // 2. GUARDAR CONFIGURACIÓN (SAVE SETTINGS)
    form.on('submit', function(e) {
        e.preventDefault();
        
        const icon = saveBtn.find('.dashicons');
        saveBtn.prop('disabled', true).text('Saving...');
        icon.addClass('spin');
        
        // Búsqueda de Nonce de seguridad
        const securityNonce = $('#ub_save_nonce').val() || $('input[name="security"]').val() || $('input[name="_wpnonce"]').val();

        const formData = form.serialize() + '&action=ub_save_settings&security=' + securityNonce;

        $.ajax({
            url: uber_ajax_object.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                const typeClass = response.success ? 'ub-success' : 'ub-error';
                // Evitamos el error "Done" poniendo un mensaje por defecto en inglés
                const msg = (response.data && response.data.message) ? response.data.message : (response.success ? 'Settings saved successfully' : 'Error saving settings');
                
                responseDiv.hide().removeClass('ub-success ub-error')
                           .addClass(typeClass).html('<p>' + msg + '</p>').fadeIn();

                if (response.success) {
                    originalData = form.serialize(); // Sincronizamos los datos originales
                    saveBtn.prop('disabled', true).text('Save Settings');
                    testBtn.prop('disabled', false);
                } else {
                    saveBtn.prop('disabled', false).text('Save Settings');
                }
            },
            error: function() {
                responseDiv.addClass('ub-error').html('<p>Server Connection Error</p>').fadeIn();
                saveBtn.prop('disabled', false).text('Save Settings');
            },
            complete: function() {
                icon.removeClass('spin');
                setTimeout(() => { responseDiv.fadeOut(); }, 4000);
            }
        });
    });

    // 3. PROBAR CONEXIÓN (TEST CONNECTION)
    testBtn.on('click', function(e) {
        e.preventDefault();
        const icon = $(this).find('.dashicons');
        testBtn.prop('disabled', true).text('Testing...');
        icon.addClass('spin');
        
        $.post(uber_ajax_object.ajax_url, {
            action: 'ub_test_api_connection',
            ub_test_api_nonce: $('#uber_test_nonce').val(),
            client_id: $('input[name="uber_client_id"]').val(),
            client_secret: $('input[name="uber_client_secret"]').val()
        }, function(response) {
            const typeClass = response.success ? 'ub-success' : 'ub-error';
            const msg = (response.data && response.data.message) ? response.data.message : 'Test complete';
            
            responseDiv.hide().removeClass('ub-success ub-error')
                       .addClass(typeClass).html('<p>' + msg + '</p>').fadeIn();
        }).always(function() {
            testBtn.prop('disabled', false).text('Test Connection');
            icon.removeClass('spin');
            setTimeout(() => { responseDiv.fadeOut(); }, 4000);
        });
    });

    // 4. ACTUALIZACIÓN AUTOMÁTICA DEL CHECKOUT
    // Fuerza a WooCommerce a recalcular el envío cuando cambian los campos de dirección
    $('form.checkout').on('change', 'select#billing_state, input#billing_city, input#billing_postcode, select#shipping_state, input#shipping_city, input#shipping_postcode', function() {
        $('body').trigger('update_checkout');
    });
});