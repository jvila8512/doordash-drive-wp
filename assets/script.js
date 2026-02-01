/**
 * Administration Script - DoorDash Drive Integration
 * Handles AJAX requests for Settings and History.
 */
jQuery(document).ready(function($) {

    // === 0. SAVE NOTICE — mostrar mensaje de guardado si existe ===
    const feedback = $('#dd-ajax-notice');

    if (ajax_object.dd_msg) {
        let typeClass, message;

        if (ajax_object.dd_msg === 'saved') {
            typeClass = 'notice-success';
            message = 'Settings saved and encrypted successfully.';
        } else if (ajax_object.dd_msg === 'no_changes') {
            typeClass = 'notice-info';
            message = 'No changes detected.';
        }

        if (message) {
            feedback
                .addClass('notice is-dismissible ' + typeClass)
                .html(
                    '<p>' + message + '</p>' +
                    '<button type="button" class="notice-dismiss" onclick="jQuery(this).parent().hide();"><span class="screen-reader-text">Dismiss</span></button>'
                )
                .show();

            // Desaparece automáticamente después de 5 segundos
            setTimeout(function() { feedback.fadeOut(); }, 5000);
        }

        // Limpiar el parámetro de la URL sin recargar
        const url = new URL(window.location.href);
        url.searchParams.delete('dd_msg');
        window.history.replaceState({}, '', url.toString());
    }

    // === 1. SAVE BUTTON — habilitar solo cuando hay cambios ===
    const saveBtn = $('#btn-guardar');
    const form = $('#dd-settings-form');

    // Guardar los valores originales cuando carga la página
    const originalValues = {};
    form.find('input[name], select[name]').each(function() {
        originalValues[$(this).attr('name')] = $(this).val();
    });

    // Comparar valores actuales con los originales
    function checkForChanges() {
        let changed = false;
        form.find('input[name], select[name]').each(function() {
            const name = $(this).attr('name');
            if (name && originalValues[name] !== undefined) {
                if ($(this).val() !== originalValues[name]) {
                    changed = true;
                    return false; // break del each
                }
            }
        });
        saveBtn.prop('disabled', !changed);
    }

    // Escuchar cualquier cambio en los inputs y selects del formulario
    form.on('input change', 'input[name], select[name]', function() {
        checkForChanges();
    });

    // Al hacer submit: deshabilitar botón y girar icono (después ya hace redirect el servidor)
    form.on('submit', function() {
        saveBtn.prop('disabled', true);
        saveBtn.find('.dashicons').removeClass('dashicons-saved').addClass('dashicons-update spin');
    });
    $('#btn-verificar-conexion').on('click', function(e) {
        e.preventDefault();

        const btn = $(this);
        const icon = btn.find('.dashicons');

        // Deshabilitar botón y girar icono
        btn.prop('disabled', true);
        icon.addClass('spin');

        // Limpiar todas las clases previas del notice
        feedback.removeClass('notice notice-success notice-error notice-info updated is-dismissible');

        const data = {
            action: 'dd_test_api_connection',
            _ajax_nonce: $('#dd_test_nonce').val(),
            dev_id: $('input[name="dev_id"]').val(),
            key_id: $('input[name="key_id"]').val(),
            secret: $('input[name="secret"]').val(),
            api_mode: $('select[name="api_mode"]').val()
        };

        $.post(ajax_object.ajax_url, data, function(response) {
            const isSuccess = response.success;
            const message = response.data ? response.data.message : 'Unknown response from server';
            const typeClass = isSuccess ? 'notice-success' : 'notice-error';

            feedback
                .addClass('notice is-dismissible ' + typeClass)
                .html(
                    '<p>' + message + '</p>' +
                    '<button type="button" class="notice-dismiss" onclick="jQuery(this).parent().hide();"><span class="screen-reader-text">Dismiss</span></button>'
                )
                .show();

            // Si es exitoso, lo cerramos automáticamente tras 5 segundos
            if (isSuccess) {
                setTimeout(function() { feedback.fadeOut(); }, 5000);
            }
        })
        .fail(function() {
            feedback
                .addClass('notice is-dismissible notice-error')
                .html('<p>Network error while attempting to connect.</p>')
                .show();
        })
        .always(function() {
            btn.prop('disabled', false);
            icon.removeClass('spin');
        });
    });

    // === 2. VIEW JSON DETAILS (History Tab) ===
    $('.btn-view-json').on('click', function() {
        const externalId = $(this).data('id');
        const modal = $('#dd-modal-json');
        const viewer = $('#dd-json-viewer');

        // Abrir modal y mostrar carga
        modal.fadeIn();
        viewer.html('<span class="dashicons dashicons-update spin"></span> Fetching real-time data from DoorDash...');

        const data = {
            action: 'dd_get_order_details',
            order_id: externalId,
            _ajax_nonce: $('#dd_test_nonce').val()
        };

        $.post(ajax_object.ajax_url, data, function(response) {
            if (response.success) {
                const jsonPretty = JSON.stringify(response.data, null, 4);
                viewer.text(jsonPretty);
            } else {
                const errorMsg = response.data ? response.data.message : 'Error retrieving data';
                viewer.html('<span style="color:#ff6b6b;">Error: ' + errorMsg + '</span>');
            }
        }).fail(function() {
            viewer.html('<span style="color:#ff6b6b;">Error connecting to the local server.</span>');
        });
    });

    // === 3. CLOSE MODAL ===
    $('#close-dd-modal, #dd-modal-json').on('click', function(e) {
        if (e.target !== this && e.target.id !== 'close-dd-modal') return;
        $('#dd-modal-json').fadeOut();
    });

    // === 4. REFRESH HISTORY ===
    $('#btn-refresh-history').on('click', function() {
        const icon = $(this).find('.dashicons');
        icon.addClass('spin');
        location.reload();
    });
});