<?php if (empty($resultados)) : ?>
    <tr><td colspan="6" style="text-align:center; padding: 20px;">No records found.</td></tr>
<?php else : ?>
    <?php foreach ($resultados as $orden) : ?>
        <tr>
            <td><?php echo date('M j, Y H:i', strtotime($orden->time)); ?></td>
            <td>
                <strong><?php echo esc_html($orden->customer_name); ?></strong><br>
                <small style="color:#666;">Ref: <?php echo esc_html($orden->external_id); ?></small>
            </td>
            <td><span class="ub-status-pill status-<?php echo strtolower($orden->order_status); ?>"><?php echo esc_html($orden->order_status); ?></span></td>
            <td>$<?php echo number_format($orden->delivery_fee, 2); ?></td>
            <td style="font-weight: bold; color: <?php echo ($orden->billing_status === 'paid') ? '#2ecc71' : '#d63638'; ?>;">
                $<?php echo number_format($orden->plugin_commission, 2); ?>
            </td>
            <td>
                <div style="display:flex; gap:5px; align-items: center;">
    <?php if (!empty($orden->tracking_url)) : ?>
        <a href="<?php echo esc_url($orden->tracking_url); ?>" 
           target="_blank" 
           class="button button-small" 
           title="Track Live Order"
           style="display: inline-flex; align-items: center; justify-content: center;">
            <span class="dashicons dashicons-location" style="font-size:14px; width:14px; height:14px; margin-right:4px;"></span> 
            Track
        </a>
    <?php endif; ?>

    <button type="button" 
            class="button button-small btn-view-json" 
            data-id="<?php echo $orden->id; ?>"
            style="display: inline-flex; align-items: center; justify-content: center;">
        JSON
    </button>
</div>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>

<tr>
    <td colspan="6" class="tablenav">
        <div class="tablenav-pages" style="float:right; margin: 10px 0;">
            <?php if ($current_page > 1) : ?>
                <a class="button prev-page-ajax" data-page="<?php echo $current_page - 1; ?>">« Prev</a>
            <?php endif; ?>
            <span style="margin: 0 10px;"> Page <strong><?php echo $current_page; ?></strong> of <?php echo $total_pages; ?> </span>
            <?php if ($current_page < $total_pages) : ?>
                <a class="button next-page-ajax" data-page="<?php echo $current_page + 1; ?>">Next »</a>
            <?php endif; ?>
        </div>
    </td>
</tr>