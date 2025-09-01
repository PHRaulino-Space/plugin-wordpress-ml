<?php
/**
 * Plugin Name: Mercado Livre Integration
 * Plugin URI: https://example.com
 * Description: Plugin para sincronizar produtos do Mercado Livre com WordPress
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('MERCADOLIVRE_PLUGIN_URL', plugins_url('/', __FILE__));
define('MERCADOLIVRE_PLUGIN_PATH', plugin_dir_path(__FILE__));

class MercadoLivreIntegration {
    
    public function __construct() {
        // Hook init later to avoid conflicts
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Only init if WordPress is properly loaded
        if (!function_exists('add_action') || !function_exists('wp_get_current_user')) {
            return;
        }
        
        try {
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('wp_ajax_ml_authenticate', array($this, 'handle_authentication'));
            add_action('wp_ajax_ml_sync_products', array($this, 'sync_products'));
            // add_category function was removed - using ajax_add_product_category
            add_action('wp_ajax_ml_get_products', array($this, 'ajax_get_products'));
            add_action('wp_ajax_nopriv_ml_get_products', array($this, 'ajax_get_products'));
            add_action('wp_ajax_ml_get_product_categories', array($this, 'ajax_get_product_categories'));
            add_action('wp_ajax_ml_add_product_category', array($this, 'ajax_add_product_category'));
            add_action('wp_ajax_ml_remove_product_category', array($this, 'ajax_remove_product_category'));
            add_action('wp_ajax_ml_get_categories', array($this, 'ajax_get_categories'));
            add_action('wp_ajax_nopriv_ml_get_categories', array($this, 'ajax_get_categories'));
            add_action('wp_ajax_ml_get_product_details', array($this, 'ajax_get_product_details'));
            add_action('wp_ajax_nopriv_ml_get_product_details', array($this, 'ajax_get_product_details'));
            add_action('wp_ajax_ml_disconnect', array($this, 'handle_disconnect'));
            add_shortcode('ml_products', array($this, 'products_shortcode'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
            
            // Log successful initialization
            error_log('Mercado Livre Plugin: Initialized successfully');
            
        } catch (Exception $e) {
            error_log('Mercado Livre Plugin Init Error: ' . $e->getMessage());
        }
    }
    
    public function activate() {
        try {
            // Verificar se o WordPress está totalmente carregado
            if (!function_exists('wp_get_current_user')) {
                require_once(ABSPATH . 'wp-includes/pluggable.php');
            }
            
            // Criar tabelas de forma segura
            $this->create_tables();
            
            flush_rewrite_rules();
            
            // Log de sucesso
            error_log('Mercado Livre Plugin: Ativado com sucesso');
            
        } catch (Exception $e) {
            // Log do erro
            error_log('Mercado Livre Plugin Activation Error: ' . $e->getMessage());
            
            // Desativar plugin em caso de erro
            deactivate_plugins(plugin_basename(__FILE__));
            
            // Mostrar erro para o usuário
            wp_die('Erro na ativação do plugin Mercado Livre: ' . $e->getMessage());
        }
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    public function create_tables() {
        global $wpdb;
        
        if (!$wpdb) {
            throw new Exception('Database connection not available');
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Ensure dbDelta is available
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        
        // Tabela de produtos (sem campo visible - será separado)
        $table_products = $wpdb->prefix . 'ml_products';
        $sql_products = "CREATE TABLE $table_products (
            id int(11) NOT NULL AUTO_INCREMENT,
            ml_id varchar(50) NOT NULL,
            title text NOT NULL,
            price decimal(10,2) NOT NULL,
            thumbnail_url varchar(500),
            permalink varchar(500),
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY ml_id (ml_id)
        ) $charset_collate;";
        
        // Tabela de imagens
        $table_images = $wpdb->prefix . 'ml_product_images';
        $sql_images = "CREATE TABLE $table_images (
            id int(11) NOT NULL AUTO_INCREMENT,
            product_id int(11) NOT NULL,
            url varchar(500) NOT NULL,
            PRIMARY KEY (id),
            KEY product_id (product_id)
        ) $charset_collate;";
        
        // Tabela de categorias (apenas categorias manuais/customizadas)
        $table_categories = $wpdb->prefix . 'ml_categories';
        $sql_categories = "CREATE TABLE $table_categories (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) $charset_collate;";
        
        // Tabela de relacionamento produtos-categorias (ML product ID + category ID)
        $table_product_categories = $wpdb->prefix . 'ml_product_categories';
        $sql_product_categories = "CREATE TABLE $table_product_categories (
            ml_product_id varchar(50) NOT NULL,
            category_id int(11) NOT NULL,
            PRIMARY KEY (ml_product_id, category_id),
            KEY ml_product_id (ml_product_id),
            KEY category_id (category_id)
        ) $charset_collate;";
        
        // Execute table creation with error handling
        $result1 = dbDelta($sql_products);
        $result2 = dbDelta($sql_images);
        $result3 = dbDelta($sql_categories);
        $result4 = dbDelta($sql_product_categories);
        
        // Log results for debugging
        error_log('Mercado Livre Tables Created: ' . print_r(array(
            'products' => $result1,
            'images' => $result2, 
            'categories' => $result3,
            'product_categories' => $result4
        ), true));
        
        // Check if tables were created
        $tables_created = array(
            $wpdb->prefix . 'ml_products',
            $wpdb->prefix . 'ml_product_images',
            $wpdb->prefix . 'ml_categories',
            $wpdb->prefix . 'ml_product_categories'
        );
        
        foreach ($tables_created as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                throw new Exception("Failed to create table: $table");
            }
        }
        
        // Create default categories if they don't exist
        $this->create_default_categories();
    }
    
    private function create_default_categories() {
        global $wpdb;
        $table_categories = $wpdb->prefix . 'ml_categories';
        
        // Check if categories already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_categories");
        
        if ($count == 0) {
            // Insert default categories
            $default_categories = array(
                array('name' => 'Eletrônicos', 'description' => 'Produtos eletrônicos e tecnologia'),
                array('name' => 'Casa e Jardim', 'description' => 'Produtos para casa e jardim'),
                array('name' => 'Moda', 'description' => 'Roupas e acessórios'),
                array('name' => 'Esportes', 'description' => 'Artigos esportivos'),
                array('name' => 'Livros', 'description' => 'Livros e materiais educativos'),
                array('name' => 'Outros', 'description' => 'Outros produtos')
            );
            
            foreach ($default_categories as $category) {
                $wpdb->insert($table_categories, $category);
            }
            
            error_log('Mercado Livre Plugin: Created ' . count($default_categories) . ' default categories');
        }
    }
    
    public function admin_menu() {
        add_menu_page(
            'Mercado Livre',
            'Mercado Livre',
            'manage_options',
            'mercadolivre-config',
            array($this, 'admin_page'),
            'dashicons-store',
            30
        );
    }
    
    public function admin_page() {
        // Handle form submissions
        if (isset($_POST['submit'])) {
            $app_id = sanitize_text_field($_POST['app_id']);
            $secret_key = sanitize_text_field($_POST['secret_key']);
            $redirect_url = esc_url_raw($_POST['redirect_url']);
            
            update_option('ml_app_id', $app_id);
            update_option('ml_secret_key', $secret_key);
            update_option('ml_redirect_url', $redirect_url);
            
            echo '<div class="notice notice-success"><p>Configurações salvas!</p></div>';
        }
        
        // Handle disconnect action
        if (isset($_POST['disconnect_ml'])) {
            // Add nonce verification for form-based disconnect
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'ml_disconnect_form')) {
                echo '<div class="notice notice-error"><p>Erro de segurança: Nonce inválido.</p></div>';
            } else {
                try {
                    $this->disconnect_mercado_livre();
                    echo '<div class="notice notice-info"><p>Desconectado do Mercado Livre com sucesso!</p></div>';
                } catch (Exception $e) {
                    echo '<div class="notice notice-error"><p>Erro ao desconectar: ' . esc_html($e->getMessage()) . '</p></div>';
                    error_log('Mercado Livre Disconnect Error: ' . $e->getMessage());
                }
            }
        }
        
        $app_id = get_option('ml_app_id', '');
        $secret_key = get_option('ml_secret_key', '');
        $redirect_url = get_option('ml_redirect_url', '');
        
        // Check authentication status
        $access_token = get_user_meta(get_current_user_id(), 'ml_access_token', true);
        $user_info = null;
        $auth_status = 'Não autenticado';
        $auth_status_class = 'notice-error';
        
        if ($access_token) {
            $user_info = $this->get_user_info($access_token);
            if ($user_info && !isset($user_info['error'])) {
                $auth_status = 'Autenticado com sucesso';
                $auth_status_class = 'notice-success';
            } else {
                $auth_status = 'Token inválido ou expirado';
                $auth_status_class = 'notice-warning';
            }
        }
        ?>
        <div class="wrap">
            <h1>Configurações Mercado Livre</h1>
            
            <!-- Authentication Status Section -->
            <div class="notice <?php echo $auth_status_class; ?> inline">
                <h3>Status da Autenticação</h3>
                <p><strong><?php echo esc_html($auth_status); ?></strong></p>
                
                <?php if ($user_info && !isset($user_info['error'])): ?>
                    <p><strong>Usuário logado:</strong> <?php echo esc_html($user_info['first_name'] . ' ' . $user_info['last_name']); ?> 
                       (ID: <?php echo esc_html($user_info['id']); ?>)</p>
                    <p><strong>Email:</strong> <?php echo esc_html($user_info['email']); ?></p>
                    <p><strong>Nickname:</strong> <?php echo esc_html($user_info['nickname']); ?></p>
                <?php else: ?>
                    <?php if ($access_token): ?>
                        <p><em>Token encontrado mas inválido. Reconecte para atualizar.</em></p>
                    <?php else: ?>
                        <p><em>Nenhum token de acesso encontrado. Configure suas credenciais e conecte.</em></p>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Authentication Buttons -->
                <div class="ml-auth-buttons" style="margin-top: 15px;">
                    <!-- Always show connect button -->
                    <a href="<?php echo admin_url('admin-ajax.php?action=ml_authenticate'); ?>" class="button button-primary">
                        🔗 Conectar com Mercado Livre
                    </a>
                    
                    <?php if ($access_token): ?>
                        <form method="post" style="display: inline-block; margin-left: 10px;">
                            <?php wp_nonce_field('ml_disconnect_form'); ?>
                            <input type="hidden" name="disconnect_ml" value="1" />
                            <input type="submit" class="button button-secondary" value="🔌 Desconectar" 
                                   onclick="return confirm('Tem certeza que deseja desconectar do Mercado Livre?');" />
                        </form>
                    <?php endif; ?>
                    
                    <button type="button" id="ml-refresh-status" class="button" style="margin-left: 10px;">
                        🔄 Atualizar Status
                    </button>
                </div>
            </div>

            <!-- Product Statistics Section -->
            <?php if ($user_info && !isset($user_info['error'])): 
                global $wpdb;
                $products_table = $wpdb->prefix . 'ml_products';
                $product_categories_table = $wpdb->prefix . 'ml_product_categories';
                $total_products = $wpdb->get_var("SELECT COUNT(*) FROM $products_table");
                // Visible products are those with categories
                $visible_products = $wpdb->get_var(
                    "SELECT COUNT(DISTINCT p.id) FROM $products_table p 
                     INNER JOIN $product_categories_table pc ON p.ml_id = pc.ml_product_id"
                );
                $last_sync = $wpdb->get_var("SELECT MAX(created_at) FROM $products_table");
            ?>
            <div class="notice notice-info inline">
                <h3>Estatísticas dos Produtos</h3>
                <p><strong>Total de produtos sincronizados:</strong> <?php echo intval($total_products); ?></p>
                <p><strong>Produtos visíveis (com categorias):</strong> <?php echo intval($visible_products); ?></p>
                <p><strong>Última sincronização:</strong> 
                   <?php echo $last_sync ? date('d/m/Y H:i:s', strtotime($last_sync)) : 'Nunca'; ?>
                </p>
                <p>
                    <button type="button" id="ml-sync-now" class="button button-primary">
                        Sincronizar Agora
                    </button>
                    <span id="ml-sync-status" style="margin-left: 10px;"></span>
                </p>
            </div>

            <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#ml-sync-now').on('click', function() {
                    var $btn = $(this);
                    var $status = $('#ml-sync-status');
                    
                    $btn.prop('disabled', true).text('Sincronizando...');
                    $status.html('<span style="color: #0073aa;">Iniciando sincronização...</span>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'ml_sync_products'
                        },
                        success: function(response) {
                            if (response.success) {
                                $status.html('<span style="color: #46b450;">✓ Sincronização concluída! Produtos: ' + response.data.count + '</span>');
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                $status.html('<span style="color: #dc3232;">✗ Erro: ' + (response.data || 'Erro desconhecido') + '</span>');
                            }
                        },
                        error: function() {
                            $status.html('<span style="color: #dc3232;">✗ Erro de conexão</span>');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text('Sincronizar Agora');
                        }
                    });
                });
                
                // Refresh status button
                $('#ml-refresh-status').on('click', function() {
                    location.reload();
                });
            });
            </script>
            <?php endif; ?>
            
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">App ID</th>
                        <td><input type="text" name="app_id" value="<?php echo esc_attr($app_id); ?>" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th scope="row">Secret Key</th>
                        <td><input type="text" name="secret_key" value="<?php echo esc_attr($secret_key); ?>" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th scope="row">Redirect URL</th>
                        <td><input type="url" name="redirect_url" value="<?php echo esc_attr($redirect_url); ?>" class="regular-text" required /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <!-- Categories Management Section -->
            <h2>Gerenciar Categorias</h2>
            <?php $this->render_categories_section(); ?>
        </div>
        <?php
    }
    
    private function render_categories_section() {
        global $wpdb;
        $table_categories = $wpdb->prefix . 'ml_categories';
        
        // Handle category creation
        if (isset($_POST['create_category'])) {
            $category_name = sanitize_text_field($_POST['category_name']);
            $category_description = sanitize_textarea_field($_POST['category_description']);
            
            if ($category_name) {
                $result = $wpdb->insert($table_categories, array(
                    'name' => ucwords(strtolower(trim($category_name))),
                    'description' => $category_description
                ));
                
                if ($result) {
                    echo '<div class="notice notice-success"><p>Categoria criada com sucesso!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Erro ao criar categoria.</p></div>';
                }
            }
        }
        
        // Handle category deletion
        if (isset($_POST['delete_category'])) {
            $category_id = intval($_POST['category_id']);
            
            // First remove all product-category relationships
            $wpdb->delete($wpdb->prefix . 'ml_product_categories', array('category_id' => $category_id));
            
            // Then delete the category
            $result = $wpdb->delete($table_categories, array('id' => $category_id));
            
            if ($result) {
                echo '<div class="notice notice-success"><p>Categoria removida com sucesso!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Erro ao remover categoria.</p></div>';
            }
        }
        
        $categories = $wpdb->get_results("SELECT * FROM $table_categories ORDER BY name");
        ?>
        
        <!-- Create new category -->
        <div class="card">
            <h3>Criar Nova Categoria</h3>
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row">Nome da Categoria</th>
                        <td><input type="text" name="category_name" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th scope="row">Descrição</th>
                        <td><textarea name="category_description" class="large-text" rows="3"></textarea></td>
                    </tr>
                </table>
                <input type="hidden" name="create_category" value="1" />
                <?php submit_button('Criar Categoria', 'secondary'); ?>
            </form>
        </div>
        
        <!-- List existing categories -->
        <div class="card" style="margin-top: 20px;">
            <h3>Categorias Existentes</h3>
            <?php if ($categories): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th>Data de Criação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo esc_html($category->id); ?></td>
                                <td><strong><?php echo esc_html($category->name); ?></strong></td>
                                <td><?php echo esc_html($category->description); ?></td>
                                <td><?php echo esc_html($category->created_at); ?></td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="category_id" value="<?php echo $category->id; ?>" />
                                        <input type="hidden" name="delete_category" value="1" />
                                        <input type="submit" class="button button-small" value="Remover" 
                                               onclick="return confirm('Tem certeza que deseja remover esta categoria? Isso também removerá todas as associações com produtos.');" />
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Nenhuma categoria encontrada. <a href="#" onclick="document.forms[0].scrollIntoView()">Criar a primeira categoria</a>.</p>
            <?php endif; ?>
        </div>
        
        <?php
    }
    
    public function handle_authentication() {
        if (!is_user_logged_in()) {
            wp_die('Unauthorized');
        }
        
        if (isset($_GET['code'])) {
            $code = sanitize_text_field($_GET['code']);
            $this->exchange_code_for_token($code);
        } else {
            $this->redirect_to_auth();
        }
    }
    
    private function redirect_to_auth() {
        $app_id = get_option('ml_app_id');
        $redirect_url = get_option('ml_redirect_url');
        
        if (!$app_id || !$redirect_url) {
            wp_die('Configurações do Mercado Livre não encontradas');
        }
        
        $auth_url = 'https://auth.mercadolivre.com.br/authorization?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $app_id,
            'redirect_uri' => $redirect_url
        ]);
        
        wp_redirect($auth_url);
        exit;
    }
    
    private function exchange_code_for_token($code) {
        $app_id = get_option('ml_app_id');
        $secret_key = get_option('ml_secret_key');
        $redirect_url = get_option('ml_redirect_url');
        
        $response = wp_remote_post('https://api.mercadolibre.com/oauth/token', [
            'body' => [
                'grant_type' => 'authorization_code',
                'client_id' => $app_id,
                'client_secret' => $secret_key,
                'code' => $code,
                'redirect_uri' => $redirect_url
            ]
        ]);
        
        if (is_wp_error($response)) {
            wp_die('Erro na autenticação');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            update_user_meta(get_current_user_id(), 'ml_access_token', $body['access_token']);
            update_user_meta(get_current_user_id(), 'ml_refresh_token', $body['refresh_token']);
            
            wp_redirect(site_url('/ml-produtos/'));
            exit;
        }
        
        wp_die('Erro ao obter token');
    }
    
    public function sync_products() {
        if (!is_user_logged_in()) {
            wp_die('Unauthorized');
        }
        
        $access_token = get_user_meta(get_current_user_id(), 'ml_access_token', true);
        
        if (!$access_token) {
            wp_send_json_error('Token não encontrado');
        }
        
        $user_info = $this->get_user_info($access_token);
        
        if (!$user_info) {
            wp_send_json_error('Erro ao obter informações do usuário');
        }
        
        $products = $this->get_user_products($user_info['id'], $access_token);
        
        if (!$products) {
            wp_send_json_error('Erro ao obter produtos');
        }
        
        // PASSO 1: Limpar todos os dados existentes (exceto visibilidade e categorias)
        $this->clear_sync_data();
        
        $synced_count = 0;
        $ml_product_ids = array();
        
        // PASSO 2: Sincronizar todos os produtos do ML
        foreach ($products['results'] as $product_id) {
            $product_details = $this->get_product_details($product_id, $access_token);
            
            if ($product_details) {
                $this->save_product_full($product_details);
                $ml_product_ids[] = $product_details['id'];
                $synced_count++;
            }
        }
        
        // Limpar produtos órfãos (que não estão mais no ML)
        $this->cleanup_orphaned_products($ml_product_ids);
        
        wp_send_json_success(array(
            'message' => "Sincronizados {$synced_count} produtos",
            'count' => $synced_count
        ));
    }
    
    private function cleanup_orphaned_products($current_ml_ids) {
        if (empty($current_ml_ids)) {
            return; // Se não há produtos no ML, não remover nada
        }
        
        global $wpdb;
        
        $table_products = $wpdb->prefix . 'ml_products';
        $table_images = $wpdb->prefix . 'ml_product_images';
        $table_product_categories = $wpdb->prefix . 'ml_product_categories';
        
        // Criar lista segura para SQL IN clause
        $placeholders = implode(',', array_fill(0, count($current_ml_ids), '%s'));
        
        // Produtos órfãos são aqueles que estão no banco mas NÃO estão na lista atual do ML
        $orphaned_products = $wpdb->get_results($wpdb->prepare(
            "SELECT id, ml_id FROM $table_products WHERE ml_id NOT IN ($placeholders)",
            $current_ml_ids
        ));
        
        foreach ($orphaned_products as $product) {
            // Limpar imagens
            $wpdb->delete($table_images, array('product_id' => $product->id));
            
            // Limpar associações de categoria 
            $wpdb->delete($table_product_categories, array('ml_product_id' => $product->ml_id));
            
            // Remover produto
            $wpdb->delete($table_products, array('id' => $product->id));
        }
        
        if (count($orphaned_products) > 0) {
            error_log('Mercado Livre Plugin: Removed ' . count($orphaned_products) . ' orphaned products');
        }
    }
    
    private function get_user_info($access_token) {
        $response = wp_remote_get('https://api.mercadolibre.com/users/me?access_token=' . $access_token);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    private function get_user_products($user_id, $access_token) {
        $response = wp_remote_get("https://api.mercadolibre.com/users/{$user_id}/items/search?access_token=" . $access_token);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    private function get_product_details($product_id, $access_token) {
        $response = wp_remote_get("https://api.mercadolibre.com/items/{$product_id}?access_token=" . $access_token);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        return json_decode(wp_remote_retrieve_body($response), true);
    }
    
    private function clear_sync_data() {
        global $wpdb;
        
        $table_images = $wpdb->prefix . 'ml_product_images';
        
        // Limpar apenas imagens (serão reinsertadas)
        $wpdb->query("DELETE FROM $table_images");
        
        // NÃO limpar nada mais - produtos serão atualizados via UPSERT
        // Produtos órfãos serão identificados e removidos no final
        
        // NÃO limpar: 
        // - ml_categories (categorias manuais)
        // - ml_product_categories (associações manuais produto-categoria) 
        // - ml_products (será atualizado via UPSERT, órfãos removidos no final)
    }
    
    private function save_product_full($product) {
        global $wpdb;
        
        $table_products = $wpdb->prefix . 'ml_products';
        $table_images = $wpdb->prefix . 'ml_product_images';
        $table_categories = $wpdb->prefix . 'ml_categories';
        $table_product_categories = $wpdb->prefix . 'ml_product_categories';
        
        // Inserir ou atualizar produto usando REPLACE (preserva associações)
        $product_data = array(
            'ml_id' => $product['id'],
            'title' => $product['title'],
            'price' => $product['price'],
            'thumbnail_url' => $product['thumbnail'],
            'permalink' => $product['permalink']
        );
        
        // Use INSERT ... ON DUPLICATE KEY UPDATE para preservar o ID sequencial
        $wpdb->query($wpdb->prepare("
            INSERT INTO $table_products (ml_id, title, price, thumbnail_url, permalink, created_at, updated_at) 
            VALUES (%s, %s, %s, %s, %s, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE 
                title = VALUES(title),
                price = VALUES(price),
                thumbnail_url = VALUES(thumbnail_url),
                permalink = VALUES(permalink),
                updated_at = CURRENT_TIMESTAMP
        ", 
            $product['id'],
            $product['title'],
            $product['price'],
            $product['thumbnail'],
            $product['permalink']
        ));
        
        // Get the product DB ID (preserved if it already existed)
        $product_db_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_products WHERE ml_id = %s", 
            $product['id']
        ));
        
        // Limpar imagens antigas deste produto e inserir novas
        $wpdb->delete($table_images, array('product_id' => $product_db_id));
        
        if (isset($product['pictures']) && is_array($product['pictures'])) {
            foreach ($product['pictures'] as $picture) {
                $wpdb->insert($table_images, array(
                    'product_id' => $product_db_id,
                    'url' => $picture['url']
                ));
            }
        }
        
        // NÃO sincronizar categorias do ML - apenas produtos
        // Categorias serão criadas/gerenciadas manualmente pelo usuário
    }
    
    // get_category_details removed - not syncing ML categories anymore
    
    
    // Function removed - using ajax_add_product_category instead
    
    public function ajax_get_products() {
        global $wpdb;

        // Parâmetros da requisição
        $page = max(1, intval(isset($_POST['page']) ? $_POST['page'] : 1));
        $per_page = max(5, min(100, intval(isset($_POST['per_page']) ? $_POST['per_page'] : 20)));
        $category = sanitize_text_field(isset($_POST['category']) ? $_POST['category'] : '');
        // Novo parâmetro para controlar o filtro de categoria
        $require_category = isset($_POST['require_category']) ? rest_sanitize_boolean($_POST['require_category']) : false;

        $offset = ($page - 1) * $per_page;

        // Tabelas do banco
        $products_table = $wpdb->prefix . 'ml_products';
        $product_categories_table = $wpdb->prefix . 'ml_product_categories';
        $categories_table = $wpdb->prefix . 'ml_categories';

        // Montagem da Query
        $join_type = $require_category ? 'INNER JOIN' : 'LEFT JOIN';
        $base_query = "FROM $products_table p $join_type $product_categories_table pc ON p.ml_id = pc.ml_product_id";
        
        $where_conditions = array();
        $query_params = array();

        if ($category) {
            $where_conditions[] = "pc.category_id = %d";
            $query_params[] = intval($category);
        } else if ($require_category) {
            // Se requer categoria mas nenhuma específica foi passada, garante que a associação exista.
            $where_conditions[] = "pc.category_id IS NOT NULL";
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = ' WHERE ' . implode(' AND ', $where_conditions);
        }

        // Query para contagem total de produtos
        $count_query = "SELECT COUNT(DISTINCT p.id) $base_query $where_clause";
        $total = $wpdb->get_var($wpdb->prepare($count_query, $query_params));

        // Query para buscar os produtos
        $product_query = "SELECT DISTINCT p.* $base_query $where_clause ORDER BY p.created_at DESC LIMIT %d OFFSET %d";
        array_push($query_params, $per_page, $offset);
        $products = $wpdb->get_results($wpdb->prepare($product_query, $query_params));

        // Buscar categorias para cada produto
        foreach ($products as &$product) {
            $product->description = $product->title; // Usar o título como descrição base
            $product_cats = $wpdb->get_results($wpdb->prepare(
                "SELECT c.* FROM $categories_table c JOIN $product_categories_table pc ON c.id = pc.category_id WHERE pc.ml_product_id = %s",
                $product->ml_id
            ));
            $product->categories = $product_cats;
        }

        wp_send_json_success(array(
            'products' => $products,
            'pagination' => array(
                'current_page' => $page,
                'per_page' => $per_page,
                'total' => intval($total),
                'total_pages' => ceil($total / $per_page)
            )
        ));
    }

    public function ajax_get_product_details() {
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        if (empty($product_id)) {
            wp_send_json_error('Product ID is required.');
        }

        global $wpdb;
        $products_table = $wpdb->prefix . 'ml_products';
        $images_table = $wpdb->prefix . 'ml_product_images';
        $categories_table = $wpdb->prefix . 'ml_categories';
        $product_categories_table = $wpdb->prefix . 'ml_product_categories';

        // Get main product data
        $product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $products_table WHERE id = %d", $product_id));

        if (!$product) {
            wp_send_json_error('Product not found.');
        }

        // Get all images
        $images = $wpdb->get_results($wpdb->prepare("SELECT url FROM $images_table WHERE product_id = %d", $product_id));
        $product->images = $images;

        // Get all categories
        $categories = $wpdb->get_results($wpdb->prepare(
            "SELECT c.name FROM $categories_table c 
             INNER JOIN $product_categories_table pc ON c.id = pc.category_id 
             WHERE pc.ml_product_id = %s",
            $product->ml_id
        ));
        $product->categories = $categories;
        
        // A description field could be added to the database later.
        $product->description = $product->title;

        wp_send_json_success($product);
    }
    
    public function ajax_get_product_categories() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        $product_id = intval($_POST['product_id']);
        
        global $wpdb;
        $categories_table = $wpdb->prefix . 'ml_categories';
        $product_categories_table = $wpdb->prefix . 'ml_product_categories';
        
        // Get product by DB id to find ML id
        $products_table = $wpdb->prefix . 'ml_products';
        $product = $wpdb->get_row($wpdb->prepare("SELECT ml_id FROM $products_table WHERE id = %d", $product_id));
        
        if (!$product) {
            wp_send_json_success(array());
            return;
        }
        
        $categories = $wpdb->get_results($wpdb->prepare(
            "SELECT c.* FROM $categories_table c 
             INNER JOIN $product_categories_table pc ON c.id = pc.category_id 
             WHERE pc.ml_product_id = %s",
            $product->ml_id
        ));
        
        wp_send_json_success($categories);
    }
    
    public function ajax_add_product_category() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        $product_id = intval($_POST['product_id']);
        $category_id = sanitize_text_field(isset($_POST['category_id']) ? $_POST['category_id'] : '');
        $category_name = sanitize_text_field(isset($_POST['category_name']) ? $_POST['category_name'] : '');
        
        global $wpdb;
        $categories_table = $wpdb->prefix . 'ml_categories';
        $product_categories_table = $wpdb->prefix . 'ml_product_categories';
        
        // Create new category if name provided
        if ($category_name && !$category_id) {
            $normalized_name = ucwords(strtolower(trim($category_name)));
            
            // Check if category already exists
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $categories_table WHERE name = %s",
                $normalized_name
            ));
            
            if ($existing) {
                wp_send_json_error('Uma categoria com este nome já existe');
                return;
            }
            
            $result = $wpdb->insert($categories_table, array(
                'name' => $normalized_name,
                'description' => ''
            ));
            
            if ($result) {
                $category_id = $wpdb->insert_id;
            } else {
                wp_send_json_error('Erro ao criar categoria');
                return;
            }
        }
        
        if (!$category_id) {
            wp_send_json_error('Category ID or name required');
        }
        
        // Get product ML ID from DB ID
        $products_table = $wpdb->prefix . 'ml_products';
        $product = $wpdb->get_row($wpdb->prepare("SELECT ml_id FROM $products_table WHERE id = %d", $product_id));
        
        if (!$product) {
            wp_send_json_error('Product not found');
            return;
        }
        
        // Check if relationship already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $product_categories_table WHERE ml_product_id = %s AND category_id = %d",
            $product->ml_id, $category_id
        ));
        
        if (!$existing) {
            $wpdb->insert($product_categories_table, array(
                'ml_product_id' => $product->ml_id,
                'category_id' => $category_id
            ));
        }
        
        wp_send_json_success();
    }
    
    public function ajax_remove_product_category() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
        }
        
        $product_id = intval($_POST['product_id']);
        $category_id = intval($_POST['category_id']);
        
        global $wpdb;
        $product_categories_table = $wpdb->prefix . 'ml_product_categories';
        $products_table = $wpdb->prefix . 'ml_products';
        
        // Get product ML ID from DB ID
        $product = $wpdb->get_row($wpdb->prepare("SELECT ml_id FROM $products_table WHERE id = %d", $product_id));
        
        if (!$product) {
            wp_send_json_error('Product not found');
            return;
        }
        
        // Remove the relationship using ML product ID and category ID
        $result = $wpdb->delete($product_categories_table, array(
            'ml_product_id' => $product->ml_id,
            'category_id' => $category_id
        ));
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to remove category');
        }
    }
    
    public function ajax_get_categories() {
        global $wpdb;
        $categories_table = $wpdb->prefix . 'ml_categories';
        
        $categories = $wpdb->get_results("SELECT * FROM $categories_table ORDER BY name");
        
        wp_send_json_success($categories);
    }
    
    public function handle_disconnect() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        // Check nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'ml_disconnect_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $this->disconnect_mercado_livre();
        wp_send_json_success(array('message' => 'Desconectado com sucesso'));
    }
    
    private function disconnect_mercado_livre() {
        $user_id = get_current_user_id();
        
        // Remove tokens do usuário atual
        delete_user_meta($user_id, 'ml_access_token');
        delete_user_meta($user_id, 'ml_refresh_token');
        
        // Log da ação
        error_log("Mercado Livre: User $user_id disconnected");
    }
    
    public function products_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Você precisa estar logado para ver os produtos.</p>';
        }
        
        // Enqueue styles e scripts apenas se existirem
        $js_path = MERCADOLIVRE_PLUGIN_PATH . 'assets/products-manager.js';
        $css_path = MERCADOLIVRE_PLUGIN_PATH . 'assets/products-manager.css';
        
        if (file_exists($js_path)) {
            wp_enqueue_script('ml-products-manager', MERCADOLIVRE_PLUGIN_URL . 'assets/products-manager.js', array('jquery'), '1.0.0', true);
        }
        
        if (file_exists($css_path)) {
            wp_enqueue_style('ml-products-manager', MERCADOLIVRE_PLUGIN_URL . 'assets/products-manager.css', array(), '1.0.0');
        }
        
        // Localizar script apenas se foi carregado
        if (file_exists($js_path)) {
            wp_localize_script('ml-products-manager', 'mlProductsAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ml_products_nonce'),
                'products_per_page' => 20
            ));
        }
        
        ob_start();
        $this->render_products_manager_simple();
        return ob_get_clean();
    }
    
    private function render_products_manager_simple() {
        global $wpdb;
        
        // Get authentication status
        $access_token = get_user_meta(get_current_user_id(), 'ml_access_token', true);
        $user_id = get_current_user_id();
        
        // Get categories for filters
        $categories_table = $wpdb->prefix . 'ml_categories';
        $categories = $wpdb->get_results("SELECT * FROM $categories_table ORDER BY name");
        ?>
        <div id="ml-products-manager" class="ml-products-container">
            <!-- Header with Action Buttons -->
            <div class="ml-header">
                <h2>Gerenciador de Produtos - Mercado Livre</h2>
                
                <div class="ml-action-buttons">
                    <!-- Always show connect button -->
                    <a href="<?php echo admin_url('admin-ajax.php?action=ml_authenticate'); ?>" class="ml-btn ml-btn-primary">
                        🔗 Conectar com Mercado Livre
                    </a>
                    
                    <?php if ($access_token): ?>
                        <button id="ml-sync-btn" class="ml-btn ml-btn-success">
                            Sincronizar Produtos
                        </button>
                        <button id="ml-disconnect-btn" class="ml-btn ml-btn-secondary" style="margin-left: 10px;">
                            🔌 Desconectar
                        </button>
                        <span class="ml-auth-status">✅ Conectado ao Mercado Livre</span>
                    <?php else: ?>
                        <span class="ml-auth-status" style="color: #dc3232;">❌ Não conectado</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status Messages -->
            <div id="ml-messages" class="ml-messages"></div>

            <!-- Filters Section -->
            <?php if ($access_token): ?>
            <div class="ml-filters-section">
                <h3>Filtros</h3>
                <div class="ml-filters">
                    <div class="ml-filter-row">
                        <div class="ml-filter-group">
                            <label for="ml-search-name">Nome do Produto:</label>
                            <input type="text" id="ml-search-name" placeholder="Digite o nome..." />
                        </div>
                        
                        <div class="ml-filter-group">
                            <label for="ml-search-description">Descrição:</label>
                            <input type="text" id="ml-search-description" placeholder="Buscar na descrição..." />
                        </div>
                    </div>
                    
                    <div class="ml-filter-row">
                        <div class="ml-filter-group">
                            <label for="ml-filter-category">Categoria:</label>
                            <select id="ml-filter-category">
                                <option value="">Todas as categorias</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo esc_attr($category->ml_category_id); ?>">
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="ml-filter-group">
                            <label>Status:</label>
                            <span style="font-size: 12px; color: #666;">
                                Produtos sem categoria são automaticamente ocultos
                            </span>
                        </div>
                    </div>
                    
                    <div class="ml-filter-actions">
                        <button id="ml-apply-filters" class="ml-btn ml-btn-secondary">Aplicar Filtros</button>
                        <button id="ml-clear-filters" class="ml-btn ml-btn-outline">Limpar</button>
                    </div>
                </div>
            </div>
            
            <!-- Products Table -->
            <div class="ml-products-section">
                <div class="ml-table-header">
                    <h3>Produtos</h3>
                    <div class="ml-table-stats">
                        <span id="ml-products-count">Carregando...</span>
                    </div>
                </div>
                
                <div class="ml-table-container">
                    <table id="ml-products-table" class="ml-table">
                        <thead>
                            <tr>
                                <th>Imagem</th>
                                <th>ID ML</th>
                                <th>Nome</th>
                                <th>Descrição</th>
                                <th>Valor</th>
                                    <th>Categorias</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody id="ml-products-tbody">
                            <!-- Products will be loaded here -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="ml-pagination">
                    <div class="ml-pagination-info">
                        <span id="ml-pagination-info">Carregando...</span>
                    </div>
                    <div class="ml-pagination-controls">
                        <button id="ml-prev-page" class="ml-btn ml-btn-outline" disabled>← Anterior</button>
                        <span id="ml-page-numbers"></span>
                        <button id="ml-next-page" class="ml-btn ml-btn-outline" disabled>Próxima →</button>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="ml-not-connected">
                <p>Conecte-se ao Mercado Livre para gerenciar seus produtos.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_products_page() {
        global $wpdb;
        
        $table_products = $wpdb->prefix . 'ml_products';
        $table_categories = $wpdb->prefix . 'ml_categories';
        $table_product_categories = $wpdb->prefix . 'ml_product_categories';
        
        $access_token = get_user_meta(get_current_user_id(), 'ml_access_token', true);
        
        $products = $wpdb->get_results("SELECT * FROM $table_products ORDER BY created_at DESC");
        $categories = $wpdb->get_results("SELECT * FROM $table_categories ORDER BY name");
        ?>
        <div id="ml-products-container">
            <h2>Produtos do Mercado Livre</h2>
            
            <?php if (!$access_token): ?>
                <button id="ml-auth-btn" class="button button-primary">Autenticar com Mercado Livre</button>
            <?php else: ?>
                <button id="ml-sync-btn" class="button button-primary">Sincronizar Produtos</button>
            <?php endif; ?>
            
            <div id="ml-messages"></div>
            
            <?php if ($products): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Imagem</th>
                            <th>Título</th>
                            <th>Preço</th>
                            <th>Categorias</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <?php
                            $product_categories = $wpdb->get_results($wpdb->prepare("
                                SELECT c.name FROM $table_categories c 
                                INNER JOIN $table_product_categories pc ON c.id = pc.category_id 
                                WHERE pc.ml_product_id = %s
                            ", $product->ml_id));
                            ?>
                            <tr>
                                <td>
                                    <?php if ($product->thumbnail_url): ?>
                                        <img src="<?php echo esc_url($product->thumbnail_url); ?>" width="50" height="50" />
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($product->title); ?></td>
                                <td>R$ <?php echo number_format($product->price, 2, ',', '.'); ?></td>
                                <td>
                                    <?php foreach ($product_categories as $cat): ?>
                                        <span class="ml-category-tag"><?php echo esc_html($cat->name); ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <button class="button ml-add-category-btn" data-product-id="<?php echo $product->id; ?>">
                                        Adicionar Categoria
                                    </button>
                                    <?php if ($product->permalink): ?>
                                        <a href="<?php echo esc_url($product->permalink); ?>" target="_blank" class="button">Ver no ML</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Nenhum produto encontrado. Sincronize seus produtos primeiro.</p>
            <?php endif; ?>
        </div>
        
        <div id="ml-category-modal" style="display: none;">
            <div class="ml-modal-content">
                <h3>Adicionar Categoria</h3>
                <input type="text" id="ml-category-input" placeholder="Nome da categoria" />
                <button id="ml-save-category" class="button button-primary">Salvar</button>
                <button id="ml-cancel-category" class="button">Cancelar</button>
            </div>
        </div>
        <?php
    }
    
    public function enqueue_scripts() {
        $plugin_url = plugins_url('/', __FILE__);

        // Registrar assets do widget de Categoria de Produtos
        wp_register_style('ml-categoria-produtos-style', $plugin_url . 'assets/categoria-produtos.css', [], '1.0.3');
        wp_register_script('ml-categoria-produtos-script', $plugin_url . 'assets/categoria-produtos.js', ['jquery'], '1.0.3', true);

        // Registrar assets do widget de Gerenciador de Produtos
        wp_register_style('ml-products-manager-style', $plugin_url . 'assets/products-manager.css', [], '1.0.1');
        wp_register_script('ml-products-manager-script', $plugin_url . 'assets/products-manager.js', ['jquery'], '1.0.1', true);

        // Assets do shortcode (se houver)
        $js_path = MERCADOLIVRE_PLUGIN_PATH . 'assets/frontend.js';
        $css_path = MERCADOLIVRE_PLUGIN_PATH . 'assets/frontend.css';
        
        if (file_exists($js_path)) {
            wp_enqueue_script('ml-frontend', MERCADOLIVRE_PLUGIN_URL . 'assets/frontend.js', array('jquery'), '1.0.0', true);
        }
        
        if (file_exists($css_path)) {
            wp_enqueue_style('ml-frontend', MERCADOLIVRE_PLUGIN_URL . 'assets/frontend.css', array(), '1.0.0');
        }
        
        if (file_exists($js_path)) {
            wp_localize_script('ml-frontend', 'ml_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ml_nonce')
            ));
        }
    }
    
    public function admin_enqueue_scripts() {
        $css_path = MERCADOLIVRE_PLUGIN_PATH . 'assets/admin.css';
        if (file_exists($css_path)) {
            wp_enqueue_style('ml-admin', MERCADOLIVRE_PLUGIN_URL . 'assets/admin.css', array(), '1.0.0');
        }
    }
}

add_action('elementor/elements/categories_registered', function($elements_manager) {
    $elements_manager->add_category(
        'mercadolivre',
        [
            'title' => 'Mercado Livre',
            'icon' => 'fa fa-plug',
        ]
    );
});

// Load Elementor widget only if Elementor is active and after init
function ml_load_elementor_widget() {
    $plugin_path = MERCADOLIVRE_PLUGIN_PATH;
    $widget_files = [
        $plugin_path . 'elementor-widget.php',
        $plugin_path . 'elementor-categoria-widget.php'
    ];

    foreach ($widget_files as $file) {
        if (file_exists($file)) {
            require_once $file;
        }
    }
}
add_action('init', 'ml_load_elementor_widget', 20);

// Initialize plugin with error handling
try {
    new MercadoLivreIntegration();
} catch (Exception $e) {
    error_log('Mercado Livre Plugin Fatal Error: ' . $e->getMessage());
    add_action('admin_notices', function() use ($e) {
        echo '<div class="notice notice-error"><p><strong>Erro no plugin Mercado Livre:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
    });
}