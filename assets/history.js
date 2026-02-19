/**
 * Uber Drive History JS
 */
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('ub-modal-json');
    const closeBtn = document.getElementById('close-ub-modal');
    const jsonViewer = document.getElementById('ub-json-viewer');
    const refreshBtn = document.getElementById('btn-refresh-history');

    // 1. Manejo del Modal para ver JSON
    document.querySelectorAll('.btn-view-json').forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.getAttribute('data-id');
            modal.style.display = 'block';
            jsonViewer.innerHTML = 'Loading technical data...';

            // Aquí llamamos a la base de datos vía AJAX para obtener el raw_json
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ub_get_order_json',
                    order_id: orderId,
                    nonce: ub_vars.nonce // Asegúrate de pasar un nonce por seguridad
                },
                success: function(response) {
                    if (response.success) {
                        // Formateamos el JSON para que se vea bonito
                        const obj = JSON.parse(response.data);
                        jsonViewer.innerHTML = JSON.stringify(obj, null, 4);
                    } else {
                        jsonViewer.innerHTML = 'Error loading data.';
                    }
                }
            });
        });
    });

    // 2. Cerrar Modal
    if (closeBtn) {
        closeBtn.onclick = () => modal.style.display = 'none';
    }

    window.onclick = (event) => {
        if (event.target == modal) modal.style.display = 'none';
    };

    // 3. Botón Refresh con animación
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            const icon = this.querySelector('.dashicons');
            icon.classList.add('spin');
            
            // Recargamos la página para ver nuevos pedidos
            setTimeout(() => {
                location.reload();
            }, 800);
        });
    }
});