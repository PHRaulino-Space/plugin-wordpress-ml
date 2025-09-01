<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ML_Categoria_Produtos_Elementor_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'ml_categoria_produtos';
    }

    public function get_title() {
        return 'Mercado Livre - Produtos por Categoria';
    }

    public function get_icon() {
        return 'eicon-gallery-group';
    }

    public function get_categories() {
        return ['mercadolivre'];
    }

    public function get_style_depends() {
        return ['ml-categoria-produtos-style'];
    }

    public function get_script_depends() {
        return ['ml-categoria-produtos-script'];
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
            'titulo',
            [
                'label' => 'Título do Widget',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Nossos Produtos',
            ]
        );

        $this->add_control(
            'produtos_por_pagina',
            [
                'label' => 'Produtos por página',
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 20,
                'min' => 5,
                'max' => 100,
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $widget_id = $this->get_id();

        $widget_settings = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'produtos_por_pagina' => (int)$settings['produtos_por_pagina'],
        ];
        
        wp_add_inline_script(
            'ml-categoria-produtos-script',
            sprintf(
                'jQuery(function() { new MLCategoriaWidget("%s", %s); });',
                $widget_id,
                wp_json_encode($widget_settings)
            )
        );

        ?>
        <div id="ml-widget-<?php echo esc_attr($widget_id); ?>" class="ml-elementor-widget-container">
            <?php if (!empty($settings['titulo'])) : ?>
                <h2 class="ml-widget-title"><?php echo esc_html($settings['titulo']); ?></h2>
            <?php endif; ?>
            
            <div class="ml-widget-body">
                <div class="ml-loading-indicator ml-loading-initial">
                    <div class="ml-spinner"></div>
                    <p>Carregando categorias...</p>
                </div>
                
                <div class="ml-tabs-wrapper" style="display: none;">
                    <div class="ml-tabs-container"></div>
                </div>
                
                <div class="ml-products-container">
                    <div class="ml-loading-indicator ml-loading-products" style="display: none;">
                        <div class="ml-spinner"></div>
                        <p>Carregando produtos...</p>
                    </div>
                    <div class="ml-products-grid"></div>
                    <div class="ml-no-products-found" style="display: none;">
                        <p>Nenhum produto encontrado nesta categoria.</p>
                    </div>
                </div>
            </div>

            <div class="ml-modal-overlay" style="display: none;">
                <div class="ml-modal-container">
                    <button class="ml-modal-close">&times;</button>
                    <div class="ml-modal-content">
                        <div class="ml-modal-gallery">
                            <div class="ml-carousel">
                                <img class="ml-carousel-main-image" src="" alt="Imagem principal do produto">
                                <button class="ml-carousel-nav prev">&lt;</button>
                                <button class="ml-carousel-nav next">&gt;</button>
                            </div>
                            <div class="ml-thumbnails-container"></div>
                        </div>
                        <div class="ml-modal-details">
                            <h3 class="ml-modal-title"></h3>
                            <div class="ml-modal-price"></div>
                            <div class="ml-modal-description"></div>
                            <div class="ml-modal-categories-wrapper">
                                <strong>Categorias:</strong>
                                <div class="ml-modal-categories"></div>
                            </div>
                            <a href="#" target="_blank" class="ml-modal-permalink-button">Ver no Mercado Livre</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

add_action('elementor/widgets/widgets_registered', function() {
    if (did_action('elementor/loaded')) {
        \Elementor\Plugin::instance()->widgets_manager->register_widget_type(new ML_Categoria_Produtos_Elementor_Widget());
    }
});
