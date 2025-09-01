class MLCategoriaWidget {
    constructor(widgetId, settings) {
        this.widgetId = widgetId;
        this.settings = settings;
        this.ajaxUrl = settings.ajax_url;
        this.widgetEl = jQuery('#ml-widget-' + widgetId);
        this.init();
    }

    init() {
        if (this.widgetEl.length === 0) { return; }
        this.bindEvents();
        this.loadCategories();
    }

    bindEvents() {
        this.widgetEl.on('click', '.ml-tab', (e) => this.handleTabClick(e));
        this.widgetEl.on('click', '.ml-produto-card', (e) => this.handleProductClick(e));
        this.widgetEl.on('click', '.ml-modal-close', () => this.closeModal());
        this.widgetEl.on('click', '.ml-modal-overlay', (e) => { if (jQuery(e.target).is('.ml-modal-overlay')) this.closeModal(); });
        this.widgetEl.on('click', '.ml-carousel-nav.next', () => this.nextImage());
        this.widgetEl.on('click', '.ml-carousel-nav.prev', () => this.prevImage());
        this.widgetEl.on('click', '.ml-thumbnail', (e) => this.handleThumbnailClick(e));
    }

    showLoading(section, show) { this.widgetEl.find('.ml-loading-' + section).toggle(show); }

    loadCategories() {
        this.showLoading('initial', true);
        jQuery.post(this.ajaxUrl, { action: 'ml_get_categories' })
            .done(response => {
                if (response.success) {
                    this.renderTabs(response.data);
                    this.loadProducts('todos');
                }
            })
            .always(() => {
                this.showLoading('initial', false);
                this.widgetEl.find('.ml-tabs-wrapper').show();
            });
    }

    renderTabs(categories) {
        const tabsContainer = this.widgetEl.find('.ml-tabs-container');
        tabsContainer.empty().append('<button class="ml-tab active" data-category="todos">Todos</button>');
        categories.forEach(cat => {
            tabsContainer.append('<button class="ml-tab" data-category="' + cat.id + '">' + cat.name + '</button>');
        });
    }

    handleTabClick(e) {
        const tab = jQuery(e.currentTarget);
        if (tab.hasClass('active')) return;
        this.widgetEl.find('.ml-tab').removeClass('active');
        tab.addClass('active');
        const categoryId = tab.data('category');
        this.loadProducts(categoryId);
    }

    loadProducts(categoryId) {
        this.showLoading('products', true);
        this.widgetEl.find('.ml-products-grid').hide();
        this.widgetEl.find('.ml-no-products-found').hide();
        jQuery.post(this.ajaxUrl, { action: 'ml_get_products', category: categoryId === 'todos' ? '' : categoryId, per_page: this.settings.produtos_por_pagina, require_category: true })
            .done(response => {
                if (response.success && response.data.products.length > 0) {
                    this.renderProducts(response.data.products);
                    this.widgetEl.find('.ml-products-grid').show();
                } else {
                    this.widgetEl.find('.ml-no-products-found').show();
                }
            }).always(() => this.showLoading('products', false));
    }

    renderProducts(products) {
        const grid = this.widgetEl.find('.ml-products-grid');
        grid.empty();
        products.forEach(product => {
            const price = parseFloat(product.price).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            grid.append(
                '<div class="ml-produto-card" data-id="' + product.id + '">' +
                    '<img src="' + product.thumbnail_url + '" alt="' + product.title + '" class="ml-produto-image"/>' +
                    '<div class="ml-produto-info">' +
                        '<h4 class="ml-produto-title">' + product.title + '</h4>' +
                        '<p class="ml-produto-price">' + price + '</p>' +
                    '</div>' +
                '</div>'
            );
        });
    }

    handleProductClick(e) { const productId = jQuery(e.currentTarget).data('id'); this.openModalWithProduct(productId); }

    openModalWithProduct(productId) {
        this.widgetEl.find('.ml-modal-overlay').show();
        jQuery.post(this.ajaxUrl, { action: 'ml_get_product_details', product_id: productId })
            .done(response => { if (response.success) { this.populateModal(response.data); } });
    }

    populateModal(product) {
        const modal = this.widgetEl.find('.ml-modal-container');
        modal.find('.ml-modal-title').text(product.title);
        modal.find('.ml-modal-price').text(parseFloat(product.price).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }));
        modal.find('.ml-modal-description').text(product.description || 'Descrição não disponível.');
        modal.find('.ml-modal-permalink-button').attr('href', product.permalink);
        const categoriesHtml = product.categories.map(function(cat) { return '<span class="ml-categoria-tag">' + cat.name + '</span>'; }).join('');
        modal.find('.ml-modal-categories').html(categoriesHtml);
        this.productImages = (product.images || []).map(function(img) { return img.url; });
        if (this.productImages.length === 0 && product.thumbnail_url) { this.productImages.push(product.thumbnail_url); }
        this.currentImageIndex = 0;
        this.updateCarousel();
    }

    updateCarousel() {
        const hasImages = this.productImages.length > 0;
        this.widgetEl.find('.ml-carousel-main-image').attr('src', hasImages ? this.productImages[this.currentImageIndex] : '');
        this.widgetEl.find('.ml-carousel-nav').toggle(this.productImages.length > 1);
        this.renderThumbnails();
    }

    renderThumbnails() {
        const thumbsContainer = this.widgetEl.find('.ml-thumbnails-container');
        thumbsContainer.empty();
        this.productImages.forEach((url, index) => {
            thumbsContainer.append('<img src="' + url + '" class="ml-thumbnail ' + (index === this.currentImageIndex ? 'active' : '') + '" data-index="' + index + '" />');
        });
    }

    nextImage() { this.currentImageIndex = (this.currentImageIndex + 1) % this.productImages.length; this.updateCarousel(); }

    prevImage() { this.currentImageIndex = (this.currentImageIndex - 1 + this.productImages.length) % this.productImages.length; this.updateCarousel(); }

    handleThumbnailClick(e) { this.currentImageIndex = jQuery(e.currentTarget).data('index'); this.updateCarousel(); }

    closeModal() { this.widgetEl.find('.ml-modal-overlay').hide(); }
}

window.MLWidgets = window.MLWidgets || {};
window.MLWidgets.initCategoria = function(widgetId, settings) {
    if (!window.MLWidgets[widgetId]) {
        window.MLWidgets[widgetId] = new MLCategoriaWidget(widgetId, settings);
    }
};