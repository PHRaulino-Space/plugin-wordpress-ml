<?php

namespace MercadoLivre\WordPress;

use MercadoLivre\Core\API\MercadoLivreClient;
use MercadoLivre\Core\Database\WordPress\ProductRepository;
use MercadoLivre\Core\Database\WordPress\ConfigRepository;
use MercadoLivre\Core\Services\ProductService;
use MercadoLivre\Core\Services\CategoryService;
use MercadoLivre\Core\Services\AuthenticationService;

class PluginBootstrap
{
    private static ?self $instance = null;
    
    private ProductService $productService;
    private CategoryService $categoryService;
    private AuthenticationService $authService;
    private ConfigRepository $configRepository;
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    private function __construct()
    {
        $this->initializeServices();
    }
    
    public function initialize(): void
    {
        // Hook into WordPress
        add_action('init', [$this, 'onInit']);
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
        
        // AJAX handlers
        add_action('wp_ajax_ml_search_products', [$this, 'ajaxSearchProducts']);
        add_action('wp_ajax_ml_import_product', [$this, 'ajaxImportProduct']);
        add_action('wp_ajax_ml_sync_products', [$this, 'ajaxSyncProducts']);
        add_action('wp_ajax_ml_toggle_visibility', [$this, 'ajaxToggleVisibility']);
        
        // Activation/Deactivation hooks
        register_activation_hook(ML_PLUGIN_FILE, [$this, 'onActivation']);
        register_deactivation_hook(ML_PLUGIN_FILE, [$this, 'onDeactivation']);
    }
    
    private function initializeServices(): void
    {
        // Initialize repositories
        $this->configRepository = new ConfigRepository();
        $productRepository = new ProductRepository();
        
        // Initialize API client
        $appId = $this->configRepository->get('app_id', '');
        $secretKey = $this->configRepository->get('secret_key', '');
        $apiClient = new MercadoLivreClient($appId, $secretKey);
        
        // Set access token if available
        $accessToken = $this->configRepository->get('access_token', '');
        if ($accessToken) {
            $apiClient->setAccessToken($accessToken);
        }
        
        // Initialize services
        $this->productService = new ProductService($apiClient, $productRepository);
        $this->categoryService = new CategoryService($apiClient, new \MercadoLivre\Core\Database\WordPress\CategoryRepository());
        $this->authService = new AuthenticationService($apiClient, $this->configRepository);
    }
    
    public function onInit(): void
    {
        // Load plugin textdomain for translations
        load_plugin_textdomain('mercadolivre-integration', false, dirname(plugin_basename(ML_PLUGIN_FILE)) . '/languages/');
    }
    
    public function addAdminMenu(): void
    {
        add_menu_page(
            __('Mercado Livre', 'mercadolivre-integration'),
            __('Mercado Livre', 'mercadolivre-integration'),
            'manage_options',
            'mercadolivre-integration',
            [$this, 'renderDashboard'],
            'dashicons-store',
            25
        );
        
        add_submenu_page(
            'mercadolivre-integration',
            __('Dashboard', 'mercadolivre-integration'),
            __('Dashboard', 'mercadolivre-integration'),
            'manage_options',
            'mercadolivre-integration',
            [$this, 'renderDashboard']
        );
        
        add_submenu_page(
            'mercadolivre-integration',
            __('Products', 'mercadolivre-integration'),
            __('Products', 'mercadolivre-integration'),
            'manage_options',
            'ml-products',
            [$this, 'renderProducts']
        );
        
        add_submenu_page(
            'mercadolivre-integration',
            __('Import', 'mercadolivre-integration'),
            __('Import', 'mercadolivre-integration'),
            'manage_options',
            'ml-import',
            [$this, 'renderImport']
        );
        
        add_submenu_page(
            'mercadolivre-integration',
            __('Settings', 'mercadolivre-integration'),
            __('Settings', 'mercadolivre-integration'),
            'manage_options',
            'ml-settings',
            [$this, 'renderSettings']
        );
    }
    
    public function enqueueAdminScripts($hook): void
    {
        // Only load on our plugin pages
        if (strpos($hook, 'mercadolivre') === false && strpos($hook, 'ml-') === false) {
            return;
        }
        
        wp_enqueue_script(
            'ml-admin-js',
            plugin_dir_url(ML_PLUGIN_FILE) . 'admin/js/admin.js',
            ['jquery'],
            ML_VERSION,
            true
        );
        
        wp_enqueue_style(
            'ml-admin-css',
            plugin_dir_url(ML_PLUGIN_FILE) . 'admin/css/admin.css',
            [],
            ML_VERSION
        );
        
        // Localize script for AJAX
        wp_localize_script('ml-admin-js', 'mlAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ml_nonce'),
            'strings' => [
                'importing' => __('Importing...', 'mercadolivre-integration'),
                'success' => __('Success!', 'mercadolivre-integration'),
                'error' => __('Error occurred', 'mercadolivre-integration')
            ]
        ]);
    }
    
    public function renderDashboard(): void
    {
        $stats = $this->getStats();
        include ML_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    public function renderProducts(): void
    {
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        
        $filters = [
            'limit' => 20,
            'offset' => ($page - 1) * 20,
            'search' => $search
        ];
        
        $products = $this->productService->getProducts($filters);
        $total = $this->productService->getProductsCount($filters);
        
        include ML_PLUGIN_DIR . 'admin/views/products.php';
    }
    
    public function renderImport(): void
    {
        include ML_PLUGIN_DIR . 'admin/views/import.php';
    }
    
    public function renderSettings(): void
    {
        if (isset($_POST['save_settings'])) {
            $this->saveSettings();
        }
        
        $settings = [
            'app_id' => $this->configRepository->get('app_id', ''),
            'secret_key' => $this->configRepository->get('secret_key', ''),
            'site_id' => $this->configRepository->get('site_id', 'MLB'),
            'redirect_uri' => $this->configRepository->get('redirect_uri', ''),
            'is_authenticated' => $this->authService->isAuthenticated()
        ];
        
        include ML_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    public function ajaxSearchProducts(): void
    {
        check_ajax_referer('ml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        $limit = min(50, max(1, intval($_POST['limit'] ?? 10)));
        
        try {
            $results = $this->productService->searchProducts($query, '', $limit);
            wp_send_json_success($results);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    public function ajaxImportProduct(): void
    {
        check_ajax_referer('ml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $productId = sanitize_text_field($_POST['product_id'] ?? '');
        $userId = get_current_user_id();
        
        if (!$productId) {
            wp_send_json_error(['message' => 'Product ID required']);
        }
        
        try {
            $product = $this->productService->importProduct($productId, $userId);
            wp_send_json_success([
                'id' => $product->getId(),
                'title' => $product->getTitle(),
                'price' => $product->getPrice()
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    public function ajaxSyncProducts(): void
    {
        check_ajax_referer('ml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            $products = $this->productService->getProducts(['limit' => 50]);
            $results = $this->productService->syncProducts($products);
            wp_send_json_success($results);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    public function ajaxToggleVisibility(): void
    {
        check_ajax_referer('ml_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $productId = intval($_POST['product_id'] ?? 0);
        $visible = filter_var($_POST['visible'] ?? false, FILTER_VALIDATE_BOOLEAN);
        
        if (!$productId) {
            wp_send_json_error(['message' => 'Product ID required']);
        }
        
        try {
            $result = $this->productService->updateProductVisibility($productId, $visible);
            wp_send_json_success(['success' => $result]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    public function onActivation(): void
    {
        $this->createTables();
        $this->scheduleEvents();
        flush_rewrite_rules();
    }
    
    public function onDeactivation(): void
    {
        $this->clearScheduledEvents();
        flush_rewrite_rules();
    }
    
    private function createTables(): void
    {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Products table
        $products_table = $wpdb->prefix . 'ml_products';
        $products_sql = "CREATE TABLE $products_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) DEFAULT NULL,
            ml_id varchar(255) NOT NULL,
            title text NOT NULL,
            description longtext,
            price decimal(10,2) NOT NULL,
            original_price decimal(10,2) DEFAULT NULL,
            currency_id varchar(10) DEFAULT 'BRL',
            available_quantity int(11) DEFAULT 0,
            sold_quantity int(11) DEFAULT 0,
            condition_type varchar(10) DEFAULT 'new',
            permalink varchar(500) DEFAULT NULL,
            thumbnail varchar(500) DEFAULT NULL,
            category_id varchar(255) DEFAULT NULL,
            seller_id varchar(255) DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            is_visible tinyint(1) DEFAULT 1,
            wp_category_id int(11) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_sync datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_ml_id (ml_id),
            KEY idx_user_ml (user_id, ml_id),
            KEY idx_status (status),
            KEY idx_visible (is_visible)
        ) $charset_collate;";
        
        // Product images table
        $images_table = $wpdb->prefix . 'ml_product_images';
        $images_sql = "CREATE TABLE $images_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            product_id int(11) NOT NULL,
            image_url varchar(500) NOT NULL,
            wp_attachment_id int(11) DEFAULT NULL,
            image_order int(11) DEFAULT 0,
            is_primary tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_product_id (product_id),
            KEY idx_order (image_order)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($products_sql);
        dbDelta($images_sql);
    }
    
    private function scheduleEvents(): void
    {
        if (!wp_next_scheduled('ml_sync_products')) {
            wp_schedule_event(time(), 'hourly', 'ml_sync_products');
        }
    }
    
    private function clearScheduledEvents(): void
    {
        wp_clear_scheduled_hook('ml_sync_products');
    }
    
    private function saveSettings(): void
    {
        check_admin_referer('ml_settings');
        
        $this->configRepository->set('app_id', sanitize_text_field($_POST['app_id'] ?? ''));
        $this->configRepository->set('secret_key', sanitize_text_field($_POST['secret_key'] ?? ''));
        $this->configRepository->set('site_id', sanitize_text_field($_POST['site_id'] ?? 'MLB'));
        $this->configRepository->set('redirect_uri', esc_url_raw($_POST['redirect_uri'] ?? ''));
        
        add_settings_error('ml_settings', 'settings_saved', __('Settings saved.', 'mercadolivre-integration'), 'success');
    }
    
    private function getStats(): array
    {
        return [
            'total_products' => $this->productService->getProductsCount(),
            'visible_products' => $this->productService->getProductsCount(['visible_only' => true]),
            'is_authenticated' => $this->authService->isAuthenticated()
        ];
    }
    
    // Public getters for services
    public function getProductService(): ProductService
    {
        return $this->productService;
    }
    
    public function getCategoryService(): CategoryService
    {
        return $this->categoryService;
    }
    
    public function getAuthService(): AuthenticationService
    {
        return $this->authService;
    }
}