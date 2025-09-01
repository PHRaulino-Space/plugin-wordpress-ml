<?php
/**
 * Plugin Name: Mercado Livre Integration
 * Description: Plugin para integração com a API do Mercado Livre usando arquitetura separada e testável
 * Version: 2.0.0
 * Author: Paulo Raulino
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Text Domain: mercadolivre-integration
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('ML_VERSION', '2.0.0');
define('ML_PLUGIN_FILE', __FILE__);
define('ML_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ML_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load Composer autoloader
if (file_exists(dirname(__FILE__, 2) . '/vendor/autoload.php')) {
    require_once dirname(__FILE__, 2) . '/vendor/autoload.php';
} else {
    // Fallback for production - manual class loading
    spl_autoload_register(function ($class) {
        // Handle our core classes
        if (strpos($class, 'MercadoLivre\\Core\\') === 0) {
            $file = dirname(__FILE__, 2) . '/src/' . str_replace(['MercadoLivre\\Core\\', '\\'], ['', '/'], $class) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
        
        // Handle WordPress plugin classes
        if (strpos($class, 'MercadoLivre\\WordPress\\') === 0) {
            $file = __DIR__ . '/includes/' . str_replace(['MercadoLivre\\WordPress\\', '\\'], ['', '/'], $class) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    });
}

// Also need to create missing CategoryRepository
if (!class_exists('MercadoLivre\\Core\\Database\\WordPress\\CategoryRepository')) {
    class MercadoLivre_WordPress_CategoryRepository implements MercadoLivre\Core\Database\Interfaces\CategoryRepositoryInterface {
        public function save(\MercadoLivre\Core\Models\Category $category): bool { return true; }
        public function findById(int $id): ?\MercadoLivre\Core\Models\Category { return null; }
        public function findByMlId(string $mlCategoryId): ?\MercadoLivre\Core\Models\Category { return null; }
        public function findAll(): array { return []; }
        public function delete(int $id): bool { return true; }
    }
}

/**
 * Initialize the plugin
 */
function ml_initialize_plugin(): void 
{
    try {
        $bootstrap = \MercadoLivre\WordPress\PluginBootstrap::getInstance();
        $bootstrap->initialize();
    } catch (Exception $e) {
        // Log error and show admin notice
        error_log('Mercado Livre Plugin Error: ' . $e->getMessage());
        
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Mercado Livre Integration:</strong> ' . esc_html($e->getMessage());
            echo '</p></div>';
        });
    }
}

/**
 * Plugin activation
 */
function ml_activate_plugin(): void
{
    try {
        $bootstrap = \MercadoLivre\WordPress\PluginBootstrap::getInstance();
        $bootstrap->onActivation();
        
        // Set default options
        add_option('ml_version', ML_VERSION);
        
    } catch (Exception $e) {
        wp_die('Plugin activation failed: ' . $e->getMessage());
    }
}

/**
 * Plugin deactivation  
 */
function ml_deactivate_plugin(): void
{
    try {
        $bootstrap = \MercadoLivre\WordPress\PluginBootstrap::getInstance();
        $bootstrap->onDeactivation();
        
    } catch (Exception $e) {
        error_log('Plugin deactivation error: ' . $e->getMessage());
    }
}

// Hook into WordPress
add_action('plugins_loaded', 'ml_initialize_plugin');
register_activation_hook(__FILE__, 'ml_activate_plugin');
register_deactivation_hook(__FILE__, 'ml_deactivate_plugin');

// Add scheduled event handler
add_action('ml_sync_products', function() {
    try {
        $bootstrap = \MercadoLivre\WordPress\PluginBootstrap::getInstance();
        $productService = $bootstrap->getProductService();
        
        // Sync up to 50 products per run
        $products = $productService->getProducts(['limit' => 50]);
        $productService->syncProducts($products);
        
    } catch (Exception $e) {
        error_log('ML Sync Error: ' . $e->getMessage());
    }
});

// Utility functions for theme/other plugins
if (!function_exists('ml_get_product_service')) {
    function ml_get_product_service(): \MercadoLivre\Core\Services\ProductService 
    {
        return \MercadoLivre\WordPress\PluginBootstrap::getInstance()->getProductService();
    }
}

if (!function_exists('ml_get_products')) {
    function ml_get_products(array $filters = []): array 
    {
        return ml_get_product_service()->getProducts($filters);
    }
}

if (!function_exists('ml_import_product')) {
    function ml_import_product(string $productId, ?int $userId = null): \MercadoLivre\Core\Models\Product 
    {
        if ($userId === null) {
            $userId = get_current_user_id();
        }
        return ml_get_product_service()->importProduct($productId, $userId);
    }
}