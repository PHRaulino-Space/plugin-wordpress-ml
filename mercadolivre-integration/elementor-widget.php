<?php
if (!defined('ABSPATH')) {
    exit;
}

class ML_Products_Elementor_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'ml_products_manager';
    }

    public function get_title() {
        return 'Mercado Livre - Gerenciador de Produtos';
    }

    public function get_icon() {
        return 'eicon-gallery-grid';
    }

    public function get_categories() {
        return ['mercadolivre'];
    }

    public function get_style_depends() {
        return ['ml-products-manager-style'];
    }

    public function get_script_depends() {
        return ['ml-products-manager-script'];
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
            echo '<div class="ml-login-required"><p>Você precisa estar logado para usar este widget.</p></div>';
            return;
        }

        $settings = $this->get_settings_for_display();

        // Passar dados para o JavaScript
        wp_localize_script('ml-products-manager-script', 'mlProductsAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ml_products_nonce'),
            'products_per_page' => (int)$settings['products_per_page']
        ));

        $this->render_products_manager_html($settings['show_filters'] === 'yes');
    }

    private function render_products_manager_html($show_filters) {
        global $wpdb;
        $categories_table = $wpdb->prefix . 'ml_categories';
        $categories = $wpdb->get_results("SELECT * FROM $categories_table ORDER BY name");
        $access_token = get_user_meta(get_current_user_id(), 'ml_access_token', true);
        ?>
        <div id="ml-products-manager" class="ml-products-container">
            <div class="ml-header">
                <h2>Gerenciador de Produtos</h2>
                <div class="ml-action-buttons">
                    <?php if (!$access_token): ?>
                        <button id="ml-auth-btn" class="ml-btn ml-btn-primary">Conectar com Mercado Livre</button>
                    <?php else: ?>
                        <button id="ml-sync-btn" class="ml-btn ml-btn-success">Sincronizar Produtos</button>
                    <?php endif; ?>
                </div>
            </div>

            <div id="ml-messages" class="ml-messages"></div>

            <?php if ($show_filters && $access_token): ?>
            <div class="ml-filters-section">
                <div class="ml-filters">
                    <div class="ml-filter-group-main">
                        <input type="text" id="ml-search-name" placeholder="Buscar por nome..." />
                        <select id="ml-filter-category">
                            <option value="">Todas as categorias</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category->id); ?>"><?php echo esc_html($category->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button id="ml-apply-filters" class="ml-btn ml-btn-secondary">Filtrar</button>
                    <button id="ml-clear-filters" class="ml-btn ml-btn-outline">Limpar</button>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($access_token): ?>
            <div class="ml-products-section">
                <div class="ml-table-header">
                    <span id="ml-products-count">Carregando...</span>
                </div>
                <div class="ml-table-container">
                    <table id="ml-products-table" class="ml-table">
                        <thead>
                            <tr><th>Imagem</th><th>ID ML</th><th>Nome</th><th>Descrição</th><th>Preço</th><th>Categorias</th><th>Ações</th></tr>
                        </thead>
                        <tbody id="ml-products-tbody"></tbody>
                    </table>
                </div>
                <div class="ml-pagination"></div>
            </div>
            <?php else: ?>
            <div class="ml-not-connected"><p>Conecte-se ao Mercado Livre para gerenciar seus produtos.</p></div>
            <?php endif; ?>
        </div>

        <div id="ml-category-modal" class="ml-modal" style="display: none;">
            <div class="ml-modal-content">
                 <div class="ml-modal-header"><h3>Gerenciar Categorias</h3><span class="ml-modal-close">&times;</span></div>
                 <div class="ml-modal-body">
                    <div id="ml-current-categories"></div>
                    <div class="ml-category-form">
                        <select id="ml-available-categories"><option value="">Selecione...</option></select>
                        <input type="text" id="ml-new-category-name" placeholder="Ou crie uma nova..." />
                        <button id="ml-add-category" class="ml-btn ml-btn-primary">Adicionar</button>
                    </div>
                 </div>
                 <div class="ml-modal-footer"><button id="ml-save-categories" class="ml-btn ml-btn-success">Fechar</button></div>
            </div>
        </div>
        <?php
    }
}

add_action('elementor/widgets/widgets_registered', function() {
    if (did_action('elementor/loaded')) {
        \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new ML_Products_Elementor_Widget());
    }
});
