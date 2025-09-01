<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Mercado Livre - Products', 'mercadolivre-integration'); ?></h1>
    
    <?php settings_errors('ml_settings'); ?>
    
    <!-- Search and Filters -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" action="">
                <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
                <input type="search" name="search" value="<?php echo esc_attr($search ?? ''); ?>" 
                       placeholder="<?php _e('Search products...', 'mercadolivre-integration'); ?>" />
                <input type="submit" class="button" value="<?php _e('Search', 'mercadolivre-integration'); ?>" />
                
                <?php if (!empty($search)): ?>
                    <a href="<?php echo admin_url('admin.php?page=ml-products'); ?>" class="button">
                        <?php _e('Clear', 'mercadolivre-integration'); ?>
                    </a>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="alignright actions">
            <a href="<?php echo admin_url('admin.php?page=ml-import'); ?>" class="button button-primary">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Import Products', 'mercadolivre-integration'); ?>
            </a>
        </div>
    </div>
    
    <?php if (empty($products)): ?>
        <div class="notice notice-info">
            <p>
                <?php if (!empty($search)): ?>
                    <?php _e('No products found matching your search.', 'mercadolivre-integration'); ?>
                <?php else: ?>
                    <?php _e('No products found. Import some products to get started!', 'mercadolivre-integration'); ?>
                    <a href="<?php echo admin_url('admin.php?page=ml-import'); ?>" class="button button-primary">
                        <?php _e('Import Products', 'mercadolivre-integration'); ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        
        <!-- Products Table -->
        <table class="wp-list-table widefat fixed striped posts">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-1" />
                    </th>
                    <th scope="col" class="manage-column column-image">
                        <?php _e('Image', 'mercadolivre-integration'); ?>
                    </th>
                    <th scope="col" class="manage-column column-title">
                        <?php _e('Title', 'mercadolivre-integration'); ?>
                    </th>
                    <th scope="col" class="manage-column column-price">
                        <?php _e('Price', 'mercadolivre-integration'); ?>
                    </th>
                    <th scope="col" class="manage-column column-quantity">
                        <?php _e('Stock', 'mercadolivre-integration'); ?>
                    </th>
                    <th scope="col" class="manage-column column-status">
                        <?php _e('Status', 'mercadolivre-integration'); ?>
                    </th>
                    <th scope="col" class="manage-column column-visibility">
                        <?php _e('Visible', 'mercadolivre-integration'); ?>
                    </th>
                    <th scope="col" class="manage-column column-date">
                        <?php _e('Last Sync', 'mercadolivre-integration'); ?>
                    </th>
                    <th scope="col" class="manage-column column-actions">
                        <?php _e('Actions', 'mercadolivre-integration'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <th scope="row" class="check-column">
                        <input type="checkbox" name="product[]" value="<?php echo esc_attr($product->getId()); ?>" />
                    </th>
                    
                    <td class="column-image">
                        <?php if ($product->getThumbnail()): ?>
                            <img src="<?php echo esc_url($product->getThumbnail()); ?>" 
                                 alt="<?php echo esc_attr($product->getTitle()); ?>" 
                                 style="width: 60px; height: 60px; object-fit: cover;" />
                        <?php else: ?>
                            <div style="width: 60px; height: 60px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                <span class="dashicons dashicons-format-image" style="color: #ccc;"></span>
                            </div>
                        <?php endif; ?>
                    </td>
                    
                    <td class="column-title">
                        <strong>
                            <a href="<?php echo esc_url($product->getPermalink()); ?>" target="_blank">
                                <?php echo esc_html($product->getTitle()); ?>
                            </a>
                        </strong>
                        <div class="row-actions">
                            <span class="ml-id">ML ID: <?php echo esc_html($product->getMlId()); ?></span>
                        </div>
                    </td>
                    
                    <td class="column-price">
                        <strong><?php echo esc_html(number_format($product->getPrice(), 2)); ?> <?php echo esc_html($product->getCurrencyId()); ?></strong>
                        <?php if ($product->getOriginalPrice() && $product->getOriginalPrice() != $product->getPrice()): ?>
                            <br><del style="color: #999;"><?php echo esc_html(number_format($product->getOriginalPrice(), 2)); ?></del>
                        <?php endif; ?>
                    </td>
                    
                    <td class="column-quantity">
                        <span class="ml-stock-<?php echo $product->getAvailableQuantity() > 0 ? 'available' : 'unavailable'; ?>">
                            <?php echo esc_html($product->getAvailableQuantity()); ?>
                        </span>
                        <?php if ($product->getSoldQuantity() > 0): ?>
                            <br><small><?php printf(__('%d sold', 'mercadolivre-integration'), $product->getSoldQuantity()); ?></small>
                        <?php endif; ?>
                    </td>
                    
                    <td class="column-status">
                        <span class="ml-status ml-status-<?php echo esc_attr($product->getStatus()); ?>">
                            <?php echo esc_html(ucfirst($product->getStatus())); ?>
                        </span>
                    </td>
                    
                    <td class="column-visibility">
                        <label class="ml-toggle">
                            <input type="checkbox" 
                                   class="ml-visibility-toggle" 
                                   data-product-id="<?php echo esc_attr($product->getId()); ?>"
                                   <?php checked($product->isVisible()); ?> />
                            <span class="ml-toggle-slider"></span>
                        </label>
                    </td>
                    
                    <td class="column-date">
                        <?php 
                        $lastSync = $product->getLastSync();
                        if ($lastSync) {
                            echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($lastSync)));
                        } else {
                            echo '<span style="color: #999;">' . __('Never', 'mercadolivre-integration') . '</span>';
                        }
                        ?>
                    </td>
                    
                    <td class="column-actions">
                        <button type="button" class="button button-small ml-sync-single" 
                                data-product-id="<?php echo esc_attr($product->getId()); ?>">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Sync', 'mercadolivre-integration'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($total > 20): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $total_pages = ceil($total / 20);
                $current_page = $page ?? 1;
                $base_url = admin_url('admin.php?page=ml-products');
                if (!empty($search)) {
                    $base_url .= '&search=' . urlencode($search);
                }
                
                echo paginate_links([
                    'base' => $base_url . '%_%',
                    'format' => '&paged=%#%',
                    'current' => $current_page,
                    'total' => $total_pages,
                    'prev_text' => __('&laquo; Previous', 'mercadolivre-integration'),
                    'next_text' => __('Next &raquo;', 'mercadolivre-integration')
                ]);
                ?>
            </div>
        </div>
        <?php endif; ?>
        
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle visibility
    $('.ml-visibility-toggle').on('change', function() {
        var $toggle = $(this);
        var productId = $toggle.data('product-id');
        var visible = $toggle.is(':checked');
        
        $.ajax({
            url: mlAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'ml_toggle_visibility',
                nonce: mlAjax.nonce,
                product_id: productId,
                visible: visible
            },
            success: function(response) {
                if (!response.success) {
                    alert('Error: ' + (response.data.message || 'Unknown error'));
                    $toggle.prop('checked', !visible);
                }
            },
            error: function() {
                alert('AJAX error occurred');
                $toggle.prop('checked', !visible);
            }
        });
    });
    
    // Sync single product
    $('.ml-sync-single').on('click', function() {
        var $button = $(this);
        var productId = $button.data('product-id');
        var originalHtml = $button.html();
        
        $button.prop('disabled', true)
               .html('<span class="dashicons dashicons-update spin"></span> ' + mlAjax.strings.syncing);
        
        $.ajax({
            url: mlAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'ml_sync_single_product',
                nonce: mlAjax.nonce,
                product_id: productId
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('AJAX error occurred');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalHtml);
            }
        });
    });
});
</script>