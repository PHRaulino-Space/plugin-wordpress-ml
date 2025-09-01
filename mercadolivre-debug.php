<?php
/**
 * Plugin Name: ML Debug
 * Description: Versão ultra-minimal para debug
 * Version: 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Log everything
error_log('ML Debug: Plugin file loaded');

// Simple activation function
function ml_debug_activate() {
    error_log('ML Debug: Activation started');
    
    // Don't do anything complex, just log
    error_log('ML Debug: Activation completed successfully');
}

// Simple deactivation
function ml_debug_deactivate() {
    error_log('ML Debug: Deactivation started');
}

// Register hooks
register_activation_hook(__FILE__, 'ml_debug_activate');
register_deactivation_hook(__FILE__, 'ml_debug_deactivate');

// Simple init
function ml_debug_init() {
    error_log('ML Debug: Init called');
    
    // Add simple admin menu
    if (is_admin()) {
        add_action('admin_menu', 'ml_debug_menu');
    }
}
add_action('init', 'ml_debug_init');

// Simple admin menu
function ml_debug_menu() {
    error_log('ML Debug: Adding admin menu');
    
    add_menu_page(
        'ML Debug',
        'ML Debug',
        'manage_options',
        'ml-debug',
        'ml_debug_page',
        'dashicons-hammer',
        30
    );
}

// Simple admin page
function ml_debug_page() {
    error_log('ML Debug: Admin page called');
    ?>
    <div class="wrap">
        <h1>ML Debug Plugin</h1>
        <div class="notice notice-success">
            <p><strong>SUCCESS!</strong> Plugin ativado sem erros.</p>
        </div>
        
        <h2>Informações do Sistema</h2>
        <ul>
            <li><strong>WordPress:</strong> <?php echo get_bloginfo('version'); ?></li>
            <li><strong>PHP:</strong> <?php echo PHP_VERSION; ?></li>
            <li><strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></li>
            <li><strong>Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?></li>
            <li><strong>Time Limit:</strong> <?php echo ini_get('max_execution_time'); ?>s</li>
        </ul>
        
        <h2>Logs</h2>
        <p>Verifique os logs de erro do servidor para ver se há mensagens do "ML Debug".</p>
        
        <h2>Teste de Database</h2>
        <?php
        global $wpdb;
        if ($wpdb) {
            echo '<p style="color: green;">✅ Database connection: OK</p>';
            echo '<p>Database version: ' . $wpdb->db_version() . '</p>';
            echo '<p>Table prefix: ' . $wpdb->prefix . '</p>';
        } else {
            echo '<p style="color: red;">❌ Database connection: FAILED</p>';
        }
        ?>
        
        <h2>Teste de Funções WordPress</h2>
        <ul>
            <li>wp_get_current_user: <?php echo function_exists('wp_get_current_user') ? '✅' : '❌'; ?></li>
            <li>add_action: <?php echo function_exists('add_action') ? '✅' : '❌'; ?></li>
            <li>wp_remote_get: <?php echo function_exists('wp_remote_get') ? '✅' : '❌'; ?></li>
            <li>dbDelta: <?php echo function_exists('dbDelta') ? '✅' : '❌'; ?></li>
        </ul>
        
        <p><strong>Se você vê esta página, o plugin básico está funcionando!</strong></p>
    </div>
    <?php
}

error_log('ML Debug: Plugin file execution completed');
?>