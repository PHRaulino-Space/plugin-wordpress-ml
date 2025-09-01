<?php
/**
 * Plugin Name: Mercado Livre Integration - No Assets
 * Description: Versão sem assets para teste de funcionalidade básica
 * Version: 1.0.0
 * Author: Paulo Raulino
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MercadoLivreNoAssets {
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function init() {
        // Hook apenas admin
        if (is_admin()) {
            add_action('admin_menu', array($this, 'admin_menu'));
        }
        
        // AJAX handlers
        add_action('wp_ajax_ml_authenticate', array($this, 'handle_authentication'));
        add_action('wp_ajax_ml_sync_products', array($this, 'sync_products'));
        
        // Shortcode
        add_shortcode('ml_products_basic', array($this, 'products_shortcode'));
    }
    
    public function activate() {
        global $wpdb;
        
        // Criar tabela básica de produtos
        $table_name = $wpdb->prefix . 'ml_products_basic';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            ml_id varchar(255) NOT NULL,
            title text NOT NULL,
            price decimal(10,2) NOT NULL,
            thumbnail_url varchar(500),
            permalink varchar(500),
            visible tinyint(1) DEFAULT 1,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ml_id (ml_id)
        ) $charset_collate;";
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        
        dbDelta($sql);
    }
    
    public function admin_menu() {
        add_menu_page(
            'ML No Assets',
            'ML No Assets',
            'manage_options',
            'ml-no-assets',
            array($this, 'admin_page'),
            'dashicons-store',
            30
        );
    }
    
    public function admin_page() {
        $access_token = get_user_meta(get_current_user_id(), 'ml_access_token', true);
        
        ?>
        <div class="wrap">
            <h1>Mercado Livre - No Assets Version</h1>
            
            <?php if (!$access_token): ?>
                <div class="notice notice-warning">
                    <p><strong>Não autenticado:</strong> Configure suas credenciais primeiro.</p>
                </div>
                
                <h2>Configurações</h2>
                <form method="post">
                    <table class="form-table">
                        <tr>
                            <th>App ID</th>
                            <td><input type="text" name="app_id" required /></td>
                        </tr>
                        <tr>
                            <th>Secret Key</th>
                            <td><input type="text" name="secret_key" required /></td>
                        </tr>
                        <tr>
                            <th>Redirect URL</th>
                            <td><input type="url" name="redirect_url" required /></td>
                        </tr>
                    </table>
                    <?php submit_button('Salvar Configurações'); ?>
                </form>
                
                <?php
                if (isset($_POST['app_id'])) {
                    update_option('ml_app_id', sanitize_text_field($_POST['app_id']));
                    update_option('ml_secret_key', sanitize_text_field($_POST['secret_key']));
                    update_option('ml_redirect_url', esc_url_raw($_POST['redirect_url']));
                    echo '<div class="notice notice-success"><p>Configurações salvas!</p></div>';
                }
                ?>
                
                <h2>Autenticação</h2>
                <p>Após salvar as configurações, use este link para autenticar:</p>
                <a href="<?php echo admin_url('admin-ajax.php?action=ml_authenticate'); ?>" class="button button-primary">
                    Autenticar com Mercado Livre
                </a>
                
            <?php else: ?>
                <div class="notice notice-success">
                    <p><strong>✅ Conectado!</strong> Você está autenticado com o Mercado Livre.</p>
                </div>
                
                <h2>Sincronização</h2>
                <form method="post">
                    <input type="hidden" name="sync_products" value="1" />
                    <?php submit_button('Sincronizar Produtos'); ?>
                </form>
                
                <?php
                if (isset($_POST['sync_products'])) {
                    $result = $this->sync_products_simple();
                    if ($result) {
                        echo '<div class="notice notice-success"><p>Produtos sincronizados com sucesso!</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>Erro na sincronização.</p></div>';
                    }
                }
                ?>
                
                <h2>Produtos</h2>
                <?php $this->display_products_table(); ?>
                
            <?php endif; ?>
            
            <h2>Shortcode</h2>
            <p>Use o shortcode <code>[ml_products_basic]</code> em qualquer página.</p>
        </div>
        <?php
    }
    
    public function handle_authentication() {
        if (isset($_GET['code'])) {
            // Processar código de retorno
            $code = sanitize_text_field($_GET['code']);
            $this->exchange_code_for_token($code);
        } else {
            // Redirecionar para ML
            $this->redirect_to_auth();
        }
    }
    
    private function redirect_to_auth() {
        $app_id = get_option('ml_app_id');
        $redirect_url = get_option('ml_redirect_url');
        
        if (!$app_id || !$redirect_url) {
            wp_die('Configure as credenciais primeiro.');
        }
        
        $auth_url = 'https://auth.mercadolibre.com.br/authorization?' . http_build_query(array(
            'response_type' => 'code',
            'client_id' => $app_id,
            'redirect_uri' => $redirect_url
        ));
        
        wp_redirect($auth_url);
        exit;
    }
    
    private function exchange_code_for_token($code) {
        $app_id = get_option('ml_app_id');
        $secret_key = get_option('ml_secret_key');
        $redirect_url = get_option('ml_redirect_url');
        
        $response = wp_remote_post('https://api.mercadolibre.com/oauth/token', array(
            'body' => array(
                'grant_type' => 'authorization_code',
                'client_id' => $app_id,
                'client_secret' => $secret_key,
                'code' => $code,
                'redirect_uri' => $redirect_url
            )
        ));
        
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['access_token'])) {
                update_user_meta(get_current_user_id(), 'ml_access_token', $body['access_token']);
                wp_redirect(admin_url('admin.php?page=ml-no-assets'));
                exit;
            }
        }
        
        wp_die('Erro na autenticação');
    }
    
    public function sync_products() {
        wp_send_json_success(array('message' => 'Sincronização via AJAX funcionando!'));
    }
    
    private function sync_products_simple() {
        $access_token = get_user_meta(get_current_user_id(), 'ml_access_token', true);
        if (!$access_token) {
            return false;
        }
        
        // Simular sincronização
        global $wpdb;
        $table_name = $wpdb->prefix . 'ml_products_basic';
        
        $wpdb->insert($table_name, array(
            'ml_id' => 'TESTE_' . time(),
            'title' => 'Produto de Teste - ' . date('Y-m-d H:i:s'),
            'price' => rand(10, 1000),
            'thumbnail_url' => 'https://via.placeholder.com/150',
            'permalink' => 'https://mercadolibre.com',
            'visible' => 1
        ));
        
        return true;
    }
    
    private function display_products_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ml_products_basic';
        $products = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 10");
        
        if (!$products) {
            echo '<p>Nenhum produto encontrado. Execute a sincronização.</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID ML</th>
                    <th>Título</th>
                    <th>Preço</th>
                    <th>Visível</th>
                    <th>Criado em</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo esc_html($product->ml_id); ?></td>
                    <td><?php echo esc_html($product->title); ?></td>
                    <td>R$ <?php echo number_format($product->price, 2, ',', '.'); ?></td>
                    <td><?php echo $product->visible ? '✅' : '❌'; ?></td>
                    <td><?php echo esc_html($product->created_at); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    public function products_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Faça login para ver os produtos.</p>';
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ml_products_basic';
        $products = $wpdb->get_results("SELECT * FROM $table_name WHERE visible = 1 ORDER BY created_at DESC LIMIT 5");
        
        if (!$products) {
            return '<p>Nenhum produto encontrado.</p>';
        }
        
        $output = '<div class="ml-products-basic">';
        $output .= '<h3>Produtos do Mercado Livre</h3>';
        
        foreach ($products as $product) {
            $output .= '<div style="border: 1px solid #ddd; padding: 10px; margin: 10px 0;">';
            $output .= '<h4>' . esc_html($product->title) . '</h4>';
            $output .= '<p>Preço: R$ ' . number_format($product->price, 2, ',', '.') . '</p>';
            if ($product->thumbnail_url) {
                $output .= '<img src="' . esc_url($product->thumbnail_url) . '" style="max-width: 150px;" />';
            }
            $output .= '</div>';
        }
        
        $output .= '</div>';
        return $output;
    }
}

new MercadoLivreNoAssets();