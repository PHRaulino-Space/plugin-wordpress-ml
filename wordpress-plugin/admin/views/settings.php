<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Mercado Livre - Settings', 'mercadolivre-integration'); ?></h1>
    
    <?php settings_errors('ml_settings'); ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('ml_settings'); ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="app_id"><?php _e('App ID', 'mercadolivre-integration'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="app_id" name="app_id" value="<?php echo esc_attr($settings['app_id']); ?>" 
                               class="regular-text" required />
                        <p class="description">
                            <?php _e('Your Mercado Livre application ID. Get this from', 'mercadolivre-integration'); ?>
                            <a href="https://developers.mercadolivre.com.br/" target="_blank">
                                <?php _e('Mercado Livre Developers', 'mercadolivre-integration'); ?>
                            </a>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="secret_key"><?php _e('Secret Key', 'mercadolivre-integration'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="secret_key" name="secret_key" 
                               value="<?php echo esc_attr($settings['secret_key']); ?>" class="regular-text" required />
                        <p class="description">
                            <?php _e('Your Mercado Livre application secret key. Keep this secure!', 'mercadolivre-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="site_id"><?php _e('Site ID', 'mercadolivre-integration'); ?></label>
                    </th>
                    <td>
                        <select id="site_id" name="site_id" class="regular-text">
                            <option value="MLB" <?php selected($settings['site_id'], 'MLB'); ?>>Brazil (MLB)</option>
                            <option value="MLA" <?php selected($settings['site_id'], 'MLA'); ?>>Argentina (MLA)</option>
                            <option value="MLM" <?php selected($settings['site_id'], 'MLM'); ?>>Mexico (MLM)</option>
                            <option value="MLC" <?php selected($settings['site_id'], 'MLC'); ?>>Chile (MLC)</option>
                            <option value="MLU" <?php selected($settings['site_id'], 'MLU'); ?>>Uruguay (MLU)</option>
                            <option value="MCO" <?php selected($settings['site_id'], 'MCO'); ?>>Colombia (MCO)</option>
                        </select>
                        <p class="description">
                            <?php _e('Select the Mercado Livre site/country you want to integrate with.', 'mercadolivre-integration'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="redirect_uri"><?php _e('Redirect URI', 'mercadolivre-integration'); ?></label>
                    </th>
                    <td>
                        <input type="url" id="redirect_uri" name="redirect_uri" 
                               value="<?php echo esc_attr($settings['redirect_uri']); ?>" class="regular-text" />
                        <p class="description">
                            <?php _e('OAuth callback URL configured in your Mercado Livre app. Example:', 'mercadolivre-integration'); ?>
                            <code><?php echo admin_url('admin.php?page=ml-settings&tab=auth'); ?></code>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <h2><?php _e('Authentication Status', 'mercadolivre-integration'); ?></h2>
        
        <?php if ($settings['is_authenticated']): ?>
            <div class="notice notice-success inline">
                <p>
                    <span class="dashicons dashicons-yes-alt"></span>
                    <strong><?php _e('Authenticated', 'mercadolivre-integration'); ?></strong> - 
                    <?php _e('Your application is connected to Mercado Livre.', 'mercadolivre-integration'); ?>
                </p>
            </div>
        <?php else: ?>
            <div class="notice notice-warning inline">
                <p>
                    <span class="dashicons dashicons-warning"></span>
                    <strong><?php _e('Not Authenticated', 'mercadolivre-integration'); ?></strong> - 
                    <?php _e('You need to authenticate with Mercado Livre to access products.', 'mercadolivre-integration'); ?>
                </p>
            </div>
            
            <div class="ml-auth-help">
                <h3><?php _e('How to Authenticate', 'mercadolivre-integration'); ?></h3>
                <ol>
                    <li><?php _e('Save your App ID and Secret Key above', 'mercadolivre-integration'); ?></li>
                    <li><?php _e('Create a test user on Mercado Livre Developers', 'mercadolivre-integration'); ?></li>
                    <li><?php _e('Get an access token using the OAuth2 flow', 'mercadolivre-integration'); ?></li>
                    <li><?php _e('Add the token to your database:', 'mercadolivre-integration'); ?>
                        <code>wp option update ml_access_token "YOUR_TOKEN_HERE"</code>
                    </li>
                </ol>
                
                <p>
                    <strong><?php _e('For Development:', 'mercadolivre-integration'); ?></strong><br>
                    <?php _e('You can use the core testing scripts to get a token:', 'mercadolivre-integration'); ?>
                    <code>php scripts/get-test-token.php</code>
                </p>
            </div>
        <?php endif; ?>
        
        <h2><?php _e('Advanced Settings', 'mercadolivre-integration'); ?></h2>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php _e('Sync Schedule', 'mercadolivre-integration'); ?></th>
                    <td>
                        <p>
                            <?php 
                            $next_sync = wp_next_scheduled('ml_sync_products');
                            if ($next_sync) {
                                printf(
                                    __('Next automatic sync: %s', 'mercadolivre-integration'),
                                    '<strong>' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_sync) . '</strong>'
                                );
                            } else {
                                _e('Automatic sync not scheduled', 'mercadolivre-integration');
                            }
                            ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Database Tables', 'mercadolivre-integration'); ?></th>
                    <td>
                        <?php
                        global $wpdb;
                        $products_table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ml_products'");
                        $images_table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ml_product_images'");
                        ?>
                        <p>
                            Products table: 
                            <?php if ($products_table): ?>
                                <span class="ml-status-ok">✓ Created</span>
                            <?php else: ?>
                                <span class="ml-status-error">✗ Missing</span>
                            <?php endif; ?>
                        </p>
                        <p>
                            Images table: 
                            <?php if ($images_table): ?>
                                <span class="ml-status-ok">✓ Created</span>
                            <?php else: ?>
                                <span class="ml-status-error">✗ Missing</span>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <input type="submit" name="save_settings" id="submit" class="button-primary" 
                   value="<?php _e('Save Settings', 'mercadolivre-integration'); ?>" />
        </p>
    </form>
    
    <div class="ml-help-section">
        <h2><?php _e('Need Help?', 'mercadolivre-integration'); ?></h2>
        
        <div class="ml-help-grid">
            <div class="ml-help-card">
                <h3><?php _e('Documentation', 'mercadolivre-integration'); ?></h3>
                <p><?php _e('Check the plugin documentation for setup instructions and examples.', 'mercadolivre-integration'); ?></p>
            </div>
            
            <div class="ml-help-card">
                <h3><?php _e('Testing', 'mercadolivre-integration'); ?></h3>
                <p><?php _e('Use the core testing scripts to validate your API connection:', 'mercadolivre-integration'); ?></p>
                <code>php scripts/test-with-your-product.php</code>
            </div>
            
            <div class="ml-help-card">
                <h3><?php _e('Debugging', 'mercadolivre-integration'); ?></h3>
                <p><?php _e('Enable WordPress debug mode to see detailed error messages.', 'mercadolivre-integration'); ?></p>
                <code>define('WP_DEBUG', true);</code>
            </div>
        </div>
    </div>
</div>