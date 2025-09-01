<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Mercado Livre - Dashboard', 'mercadolivre-integration'); ?></h1>
    
    <?php settings_errors('ml_settings'); ?>
    
    <div class="ml-dashboard">
        <!-- Stats Cards -->
        <div class="ml-stats-grid">
            <div class="ml-stat-card">
                <div class="ml-stat-number"><?php echo esc_html($stats['total_products']); ?></div>
                <div class="ml-stat-label"><?php _e('Total Products', 'mercadolivre-integration'); ?></div>
            </div>
            
            <div class="ml-stat-card">
                <div class="ml-stat-number"><?php echo esc_html($stats['visible_products']); ?></div>
                <div class="ml-stat-label"><?php _e('Visible Products', 'mercadolivre-integration'); ?></div>
            </div>
            
            <div class="ml-stat-card ml-stat-<?php echo $stats['is_authenticated'] ? 'success' : 'error'; ?>">
                <div class="ml-stat-icon">
                    <?php if ($stats['is_authenticated']): ?>
                        <span class="dashicons dashicons-yes-alt"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning"></span>
                    <?php endif; ?>
                </div>
                <div class="ml-stat-label">
                    <?php echo $stats['is_authenticated'] 
                        ? __('Connected', 'mercadolivre-integration')
                        : __('Not Connected', 'mercadolivre-integration'); ?>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="ml-quick-actions">
            <h2><?php _e('Quick Actions', 'mercadolivre-integration'); ?></h2>
            
            <div class="ml-action-buttons">
                <a href="<?php echo admin_url('admin.php?page=ml-import'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Import Products', 'mercadolivre-integration'); ?>
                </a>
                
                <button type="button" id="ml-sync-products" class="button button-secondary">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Sync Products', 'mercadolivre-integration'); ?>
                </button>
                
                <a href="<?php echo admin_url('admin.php?page=ml-products'); ?>" class="button">
                    <span class="dashicons dashicons-products"></span>
                    <?php _e('Manage Products', 'mercadolivre-integration'); ?>
                </a>
                
                <?php if (!$stats['is_authenticated']): ?>
                <a href="<?php echo admin_url('admin.php?page=ml-settings'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Configure API', 'mercadolivre-integration'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Status Info -->
        <div class="ml-status-info">
            <h2><?php _e('System Status', 'mercadolivre-integration'); ?></h2>
            
            <?php if ($stats['is_authenticated']): ?>
                <div class="notice notice-success inline">
                    <p>
                        <strong><?php _e('API Connected', 'mercadolivre-integration'); ?></strong><br>
                        <?php _e('Your Mercado Livre API is properly configured and authenticated.', 'mercadolivre-integration'); ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="notice notice-error inline">
                    <p>
                        <strong><?php _e('API Not Connected', 'mercadolivre-integration'); ?></strong><br>
                        <?php _e('Please configure your Mercado Livre API credentials in Settings.', 'mercadolivre-integration'); ?>
                        <a href="<?php echo admin_url('admin.php?page=ml-settings'); ?>">
                            <?php _e('Go to Settings', 'mercadolivre-integration'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
            
            <div class="ml-system-info">
                <h3><?php _e('System Information', 'mercadolivre-integration'); ?></h3>
                <ul>
                    <li><strong><?php _e('Plugin Version:', 'mercadolivre-integration'); ?></strong> <?php echo ML_VERSION; ?></li>
                    <li><strong><?php _e('Core Architecture:', 'mercadolivre-integration'); ?></strong> <?php _e('Separated & Testable', 'mercadolivre-integration'); ?></li>
                    <li><strong><?php _e('Database Tables:', 'mercadolivre-integration'); ?></strong> 
                        <?php 
                        global $wpdb;
                        $tables_exist = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ml_products'");
                        echo $tables_exist ? __('Created', 'mercadolivre-integration') : __('Missing', 'mercadolivre-integration');
                        ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#ml-sync-products').on('click', function() {
        var $button = $(this);
        var originalText = $button.html();
        
        $button.prop('disabled', true)
               .html('<span class="dashicons dashicons-update spin"></span> ' + mlAjax.strings.importing);
        
        $.ajax({
            url: mlAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'ml_sync_products',
                nonce: mlAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    alert('Sync completed!\nSynced: ' + data.synced + '\nFailed: ' + data.failed);
                    location.reload();
                } else {
                    alert('Error: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('AJAX error occurred');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>