<?php
/**
 * Plugin Name: Mercado Livre Safe
 * Description: Versão segura sem funções que podem causar erros
 * Version: 1.0.0
 */

// Basic security
if (!defined('ABSPATH')) {
    exit;
}

// Simple class without complex functionality
class MercadoLivreSafe {
    
    public function __construct() {
        // Hook later to avoid conflicts
        add_action('wp_loaded', array($this, 'init'));
        
        // Safe activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function activate() {
        // Minimal activation - just log
        error_log('ML Safe: Plugin activated successfully');
        
        // Set a simple option to indicate activation
        update_option('ml_safe_activated', current_time('mysql'));
    }
    
    public function deactivate() {
        error_log('ML Safe: Plugin deactivated');
        delete_option('ml_safe_activated');
    }
    
    public function init() {
        // Only run if we're in admin and user can manage options
        if (is_admin() && current_user_can('manage_options')) {
            add_action('admin_menu', array($this, 'add_menu'));
        }
    }
    
    public function add_menu() {
        add_menu_page(
            'ML Safe',
            'ML Safe',
            'manage_options',
            'ml-safe',
            array($this, 'admin_page'),
            'dashicons-store',
            30
        );
    }
    
    public function admin_page() {
        $activated_time = get_option('ml_safe_activated');
        ?>
        <div class="wrap">
            <h1>Mercado Livre Safe</h1>
            
            <div class="notice notice-success">
                <p><strong>🎉 SUCCESS!</strong> Plugin ativado sem quebrar o site!</p>
            </div>
            
            <?php if ($activated_time): ?>
            <p><strong>Ativado em:</strong> <?php echo $activated_time; ?></p>
            <?php endif; ?>
            
            <div class="card">
                <h2>Status do Sistema</h2>
                <table class="form-table">
                    <tr>
                        <th>WordPress Version</th>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <th>PHP Version</th>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th>Memory Limit</th>
                        <td><?php echo ini_get('memory_limit'); ?></td>
                    </tr>
                    <tr>
                        <th>Upload Max Size</th>
                        <td><?php echo ini_get('upload_max_filesize'); ?></td>
                    </tr>
                    <tr>
                        <th>Current User ID</th>
                        <td><?php echo get_current_user_id(); ?></td>
                    </tr>
                    <tr>
                        <th>Plugin Directory</th>
                        <td><?php echo plugin_dir_path(__FILE__); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h2>Próximos Passos</h2>
                <p>Se este plugin funcionou, podemos começar a adicionar funcionalidades gradualmente:</p>
                <ol>
                    <li>✅ Ativação básica - <strong>OK</strong></li>
                    <li>⏳ Criação de tabelas</li>
                    <li>⏳ Autenticação OAuth</li>
                    <li>⏳ Sincronização de produtos</li>
                    <li>⏳ Interface do usuário</li>
                </ol>
            </div>
            
            <div class="card">
                <h2>Teste de Funções WordPress</h2>
                <ul>
                    <li>current_user_can: <?php echo function_exists('current_user_can') ? '✅' : '❌'; ?></li>
                    <li>update_option: <?php echo function_exists('update_option') ? '✅' : '❌'; ?></li>
                    <li>wp_remote_post: <?php echo function_exists('wp_remote_post') ? '✅' : '❌'; ?></li>
                    <li>add_menu_page: <?php echo function_exists('add_menu_page') ? '✅' : '❌'; ?></li>
                </ul>
            </div>
            
            <p><em>Plugin criado para diagnóstico. Se você vê esta página, a ativação funcionou!</em></p>
        </div>
        
        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        .card h2 {
            margin-top: 0;
        }
        </style>
        <?php
    }
}

// Initialize only if no conflicts
if (!class_exists('MercadoLivreIntegration') && !class_exists('MercadoLivreNoAssets')) {
    new MercadoLivreSafe();
    error_log('ML Safe: Instance created successfully');
} else {
    error_log('ML Safe: Skipping initialization due to conflict with other ML plugins');
}