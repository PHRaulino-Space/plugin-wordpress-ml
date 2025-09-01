<?php
/**
 * Plugin Name: Mercado Livre Integration - Minimal
 * Description: Versão mínima e segura do plugin Mercado Livre para teste
 * Version: 1.0.0
 * Author: Paulo Raulino
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MercadoLivreIntegrationMinimal {
    
    public function __construct() {
        // Hook bem tarde para evitar conflitos
        add_action('wp_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Verificações de segurança
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Adicionar menu admin apenas
        add_action('admin_menu', array($this, 'admin_menu'));
        
        // Log
        error_log('ML Minimal Plugin: Init OK');
    }
    
    public function activate() {
        error_log('ML Minimal Plugin: Activation started');
        
        try {
            // Criar apenas uma tabela simples para teste
            global $wpdb;
            
            if (!$wpdb) {
                throw new Exception('No database connection');
            }
            
            $table_name = $wpdb->prefix . 'ml_test';
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id int(11) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;";
            
            if (!function_exists('dbDelta')) {
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            }
            
            dbDelta($sql);
            
            // Verificar se a tabela foi criada
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                throw new Exception('Failed to create test table');
            }
            
            error_log('ML Minimal Plugin: Activation successful');
            
        } catch (Exception $e) {
            error_log('ML Minimal Plugin Activation Error: ' . $e->getMessage());
            
            // Mostrar erro amigável
            wp_die('Erro na ativação do plugin Mercado Livre Minimal: ' . $e->getMessage() . 
                   '<br><br><a href="' . admin_url('plugins.php') . '">&laquo; Voltar aos plugins</a>');
        }
    }
    
    public function deactivate() {
        error_log('ML Minimal Plugin: Deactivated');
    }
    
    public function admin_menu() {
        add_menu_page(
            'ML Minimal',
            'ML Minimal', 
            'manage_options',
            'ml-minimal',
            array($this, 'admin_page'),
            'dashicons-store',
            30
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Mercado Livre - Minimal Test</h1>
            <p>Plugin ativado com sucesso!</p>
            
            <div class="notice notice-success">
                <p>✅ Plugin funcionando corretamente</p>
            </div>
            
            <h2>Status do Sistema</h2>
            <ul>
                <li><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></li>
                <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
                <li><strong>Database:</strong> <?php global $wpdb; echo $wpdb->db_version(); ?></li>
                <li><strong>Plugin Path:</strong> <?php echo plugin_dir_path(__FILE__); ?></li>
            </ul>
            
            <h2>Teste de Banco</h2>
            <?php
            global $wpdb;
            $table_name = $wpdb->prefix . 'ml_test';
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            echo "<p>Registros na tabela de teste: $count</p>";
            
            // Inserir registro de teste
            if (isset($_POST['test_insert'])) {
                $wpdb->insert($table_name, array('name' => 'Teste ' . date('Y-m-d H:i:s')));
                echo '<div class="notice notice-success"><p>Registro inserido com sucesso!</p></div>';
                echo '<script>window.location.reload();</script>';
            }
            ?>
            
            <form method="post">
                <input type="submit" name="test_insert" value="Inserir Registro de Teste" class="button button-primary" />
            </form>
        </div>
        <?php
    }
}

// Inicializar apenas se não houver conflitos
if (!class_exists('MercadoLivreIntegration')) {
    new MercadoLivreIntegrationMinimal();
    error_log('ML Minimal Plugin: Instance created');
} else {
    error_log('ML Minimal Plugin: Conflict detected with main plugin');
}