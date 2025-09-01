<?php
if (!defined('ABSPATH')) {
    exit;
}

// Verificar se o Elementor está ativo
if (!class_exists('\Elementor\Widget_Base')) {
    return;
}

class ML_Products_Elementor_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'ml_products_manager';
    }

    public function get_title() {
        return 'Mercado Livre - Gerenciador de Produtos';
    }

    public function get_icon() {
        return 'eicon-products';
    }

    public function get_categories() {
        return ['general'];
    }

    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => 'Configurações',
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'products_per_page',
            [
                'label' => 'Produtos por página',
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 20,
                'min' => 5,
                'max' => 50,
            ]
        );

        $this->add_control(
            'show_filters',
            [
                'label' => 'Mostrar filtros',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'Sim',
                'label_off' => 'Não',
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (!is_user_logged_in()) {
            echo '<div class="ml-login-required">';
            echo '<p>Você precisa estar logado para acessar o gerenciador de produtos.</p>';
            echo '</div>';
            return;
        }

        $settings = $this->get_settings_for_display();
        $products_per_page = $settings['products_per_page'];
        $show_filters = $settings['show_filters'] === 'yes';

        // Enqueue scripts and styles apenas se existirem
        $plugin_url = plugin_dir_url(__FILE__);
        $plugin_path = plugin_dir_path(__FILE__);
        
        $js_path = $plugin_path . 'assets/products-manager.js';
        $css_path = $plugin_path . 'assets/products-manager.css';
        
        if (file_exists($js_path)) {
            wp_enqueue_script('ml-products-manager', $plugin_url . 'assets/products-manager.js', array('jquery'), '1.0.0', true);
        }
        
        if (file_exists($css_path)) {
            wp_enqueue_style('ml-products-manager', $plugin_url . 'assets/products-manager.css', array(), '1.0.0');
        }
        
        // Localizar script apenas se foi carregado
        if (file_exists($js_path)) {
            wp_localize_script('ml-products-manager', 'mlProductsAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ml_products_nonce'),
                'products_per_page' => $products_per_page
            ));
        }

        $this->render_products_manager($show_filters, $products_per_page);
    }

    private function render_products_manager($show_filters, $products_per_page) {
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
                    <?php if (!$access_token): ?>
                        <button id="ml-auth-btn" class="ml-btn ml-btn-primary">
                            <span class="dashicons dashicons-admin-network"></span>
                            Conectar com Mercado Livre
                        </button>
                    <?php else: ?>
                        <button id="ml-sync-btn" class="ml-btn ml-btn-success">
                            <span class="dashicons dashicons-update"></span>
                            Sincronizar Produtos
                        </button>
                        <span class="ml-auth-status">✅ Conectado ao Mercado Livre</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status Messages -->
            <div id="ml-messages" class="ml-messages"></div>

            <!-- Filters Section -->
            <?php if ($show_filters && $access_token): ?>
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
                                    <option value="<?php echo esc_attr($category->id); ?>">
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
            <?php endif; ?>

            <!-- Products Table -->
            <?php if ($access_token): ?>
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

        <!-- Category Modal -->
        <div id="ml-category-modal" class="ml-modal" style="display: none;">
            <div class="ml-modal-content">
                <div class="ml-modal-header">
                    <h3>Gerenciar Categorias</h3>
                    <span class="ml-modal-close">&times;</span>
                </div>
                <div class="ml-modal-body">
                    <div class="ml-category-current">
                        <h4>Categorias Atuais:</h4>
                        <div id="ml-current-categories"></div>
                    </div>
                    
                    <div class="ml-category-add">
                        <h4>Adicionar Nova Categoria:</h4>
                        <div class="ml-category-form">
                            <select id="ml-available-categories">
                                <option value="">Selecionar categoria existente...</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo esc_attr($category->id); ?>">
                                        <?php echo esc_html($category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="ml-or">OU</span>
                            <input type="text" id="ml-new-category-name" placeholder="Nome da nova categoria..." />
                            <button id="ml-add-category" class="ml-btn ml-btn-primary">Adicionar</button>
                        </div>
                    </div>
                </div>
                <div class="ml-modal-footer">
                    <button id="ml-save-categories" class="ml-btn ml-btn-success">Salvar</button>
                    <button class="ml-btn ml-btn-outline ml-modal-close">Cancelar</button>
                </div>
            </div>
        </div>
        <?php
    }
}

// Register the widget
function register_ml_products_widget() {
    // Verificar se o Elementor está carregado completamente
    if (did_action('elementor/loaded')) {
        \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new ML_Products_Elementor_Widget());
    }
}

// Hook mais tarde para garantir que o Elementor está carregado
add_action('elementor/widgets/widgets_registered', 'register_ml_products_widget');