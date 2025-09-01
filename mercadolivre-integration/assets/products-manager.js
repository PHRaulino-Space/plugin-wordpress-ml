jQuery(document).ready(function($) {
    'use strict';

    const ProductsManager = {
        currentPage: 1,
        totalPages: 1,
        productsPerPage: mlProductsAjax.products_per_page || 20,
        currentFilters: {},
        currentProductId: null,

        init: function() {
            this.bindEvents();
            this.loadProducts();
        },

        bindEvents: function() {
            // Authentication button
            $('#ml-auth-btn').on('click', this.authenticate.bind(this));
            
            // Sync button
            $('#ml-sync-btn').on('click', this.syncProducts.bind(this));
            
            // Filter buttons
            $('#ml-apply-filters').on('click', this.applyFilters.bind(this));
            $('#ml-clear-filters').on('click', this.clearFilters.bind(this));
            
            // Pagination
            $(document).on('click', '.ml-page-number', this.changePage.bind(this));
            $('#ml-prev-page').on('click', this.prevPage.bind(this));
            $('#ml-next-page').on('click', this.nextPage.bind(this));
            
            // Product interactions
            $(document).on('click', '.ml-category-manage', this.openCategoryModal.bind(this));
            
            // Modal interactions
            $('.ml-modal-close').on('click', this.closeModal.bind(this));
            $(window).on('click', this.closeModalOutside.bind(this));
            $('#ml-add-category').on('click', this.addCategory.bind(this));
            $('#ml-save-categories').on('click', this.saveCategories.bind(this));
            
            // Remove category button (delegated event for dynamically created elements)
            $(document).on('click', '.ml-remove-category', this.removeCategory.bind(this));
            
            // Enter key for filters
            $('.ml-filters input').on('keypress', function(e) {
                if (e.which === 13) {
                    ProductsManager.applyFilters();
                }
            });
        },

        showMessage: function(message, type = 'info') {
            const $messages = $('#ml-messages');
            const messageHtml = `
                <div class="ml-message ${type}">
                    <span class="dashicons dashicons-${this.getMessageIcon(type)}"></span>
                    ${message}
                </div>
            `;
            $messages.html(messageHtml);
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(() => {
                    $messages.fadeOut();
                }, 5000);
            }
        },

        getMessageIcon: function(type) {
            const icons = {
                success: 'yes-alt',
                error: 'warning',
                info: 'info'
            };
            return icons[type] || 'info';
        },

        authenticate: function() {
            this.showMessage('Redirecionando para autenticação...', 'info');
            window.location.href = mlProductsAjax.ajaxurl + '?action=ml_authenticate';
        },

        syncProducts: function() {
            const $btn = $('#ml-sync-btn');
            const originalText = $btn.html();
            
            $btn.prop('disabled', true)
                .html('<span class="dashicons dashicons-update"></span> Sincronizando...');
            
            this.showMessage('Iniciando sincronização com Mercado Livre...', 'info');
            
            $.ajax({
                url: mlProductsAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ml_sync_products',
                    nonce: mlProductsAjax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showMessage(
                            `✅ Sincronização concluída! ${response.data.count} produtos sincronizados.`, 
                            'success'
                        );
                        this.loadProducts();
                    } else {
                        this.showMessage('❌ Erro na sincronização: ' + response.data, 'error');
                    }
                },
                error: () => {
                    this.showMessage('❌ Erro de conexão durante a sincronização.', 'error');
                },
                complete: () => {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        loadProducts: function() {
            const $tbody = $('#ml-products-tbody');
            const $count = $('#ml-products-count');
            const $paginationInfo = $('#ml-pagination-info');
            
            $tbody.html('<tr><td colspan="8" class="ml-loading"><div class="ml-spinner"></div></td></tr>');
            $count.text('Carregando...');
            
            const requestData = {
                action: 'ml_get_products',
                nonce: mlProductsAjax.nonce,
                page: this.currentPage,
                per_page: this.productsPerPage,
                ...this.currentFilters
            };
            
            $.ajax({
                url: mlProductsAjax.ajaxurl,
                type: 'POST',
                data: requestData,
                success: (response) => {
                    if (response.success) {
                        this.renderProducts(response.data.products);
                        this.updatePagination(response.data.pagination);
                        $count.text(`${response.data.pagination.total} produtos encontrados`);
                    } else {
                        $tbody.html(`<tr><td colspan="8" class="text-center">Erro ao carregar produtos: ${response.data}</td></tr>`);
                    }
                },
                error: () => {
                    $tbody.html('<tr><td colspan="8" class="text-center">Erro de conexão</td></tr>');
                }
            });
        },

        renderProducts: function(products) {
            const $tbody = $('#ml-products-tbody');
            
            if (products.length === 0) {
                $tbody.html('<tr><td colspan="8" class="text-center">Nenhum produto encontrado</td></tr>');
                return;
            }
            
            let html = '';
            products.forEach((product) => {
                const isVisible = product.visible ? 'checked' : '';
                const categoriesTags = product.categories.map(cat => 
                    `<span class="ml-category-tag">${cat.name}</span>`
                ).join('');
                
                html += `
                    <tr data-product-id="${product.id}" data-ml-id="${product.ml_id}">
                        <td>
                            <img src="${product.thumbnail_url}" alt="${product.title}" class="ml-product-image" />
                        </td>
                        <td><code>${product.ml_id}</code></td>
                        <td>
                            <div class="ml-product-name" title="${product.title}">
                                ${product.title}
                            </div>
                        </td>
                        <td>
                            <div class="ml-product-description" title="${product.description || ''}">
                                ${product.description || 'Sem descrição'}
                            </div>
                        </td>
                        <td class="ml-product-price">
                            R$ ${parseFloat(product.price).toLocaleString('pt-BR', {minimumFractionDigits: 2})}
                        </td>
                        <td class="ml-categories-cell">
                            <div>${categoriesTags}</div>
                            <a href="#" class="ml-category-manage" data-product-id="${product.id}">
                                Gerenciar
                            </a>
                        </td>
                        <td>
                            <a href="${product.permalink}" target="_blank" class="ml-btn ml-btn-outline" style="font-size: 12px; padding: 5px 10px;">
                                Ver no ML
                            </a>
                        </td>
                    </tr>
                `;
            });
            
            $tbody.html(html);
        },

        updatePagination: function(pagination) {
            this.currentPage = pagination.current_page;
            this.totalPages = pagination.total_pages;
            
            const $info = $('#ml-pagination-info');
            const $pageNumbers = $('#ml-page-numbers');
            const $prevBtn = $('#ml-prev-page');
            const $nextBtn = $('#ml-next-page');
            
            // Update info
            const start = ((pagination.current_page - 1) * pagination.per_page) + 1;
            const end = Math.min(start + pagination.per_page - 1, pagination.total);
            $info.text(`Mostrando ${start}-${end} de ${pagination.total} produtos`);
            
            // Update buttons
            $prevBtn.prop('disabled', pagination.current_page <= 1);
            $nextBtn.prop('disabled', pagination.current_page >= pagination.total_pages);
            
            // Update page numbers
            let pageNumbersHtml = '';
            const maxVisible = 5;
            let startPage = Math.max(1, pagination.current_page - Math.floor(maxVisible / 2));
            let endPage = Math.min(pagination.total_pages, startPage + maxVisible - 1);
            
            if (endPage - startPage < maxVisible - 1) {
                startPage = Math.max(1, endPage - maxVisible + 1);
            }
            
            if (startPage > 1) {
                pageNumbersHtml += '<a href="#" class="ml-page-number" data-page="1">1</a>';
                if (startPage > 2) {
                    pageNumbersHtml += '<span>...</span>';
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === pagination.current_page ? 'active' : '';
                pageNumbersHtml += `<a href="#" class="ml-page-number ${activeClass}" data-page="${i}">${i}</a>`;
            }
            
            if (endPage < pagination.total_pages) {
                if (endPage < pagination.total_pages - 1) {
                    pageNumbersHtml += '<span>...</span>';
                }
                pageNumbersHtml += `<a href="#" class="ml-page-number" data-page="${pagination.total_pages}">${pagination.total_pages}</a>`;
            }
            
            $pageNumbers.html(pageNumbersHtml);
        },

        applyFilters: function() {
            this.currentFilters = {
                search_name: $('#ml-search-name').val(),
                search_description: $('#ml-search-description').val(),
                category: $('#ml-filter-category').val()
            };
            
            // Remove empty filters
            Object.keys(this.currentFilters).forEach(key => {
                if (!this.currentFilters[key]) {
                    delete this.currentFilters[key];
                }
            });
            
            this.currentPage = 1;
            this.loadProducts();
        },

        clearFilters: function() {
            $('#ml-search-name, #ml-search-description').val('');
            $('#ml-filter-category').val('');
            this.currentFilters = {};
            this.currentPage = 1;
            this.loadProducts();
        },

        changePage: function(e) {
            e.preventDefault();
            const page = parseInt($(e.target).data('page'));
            if (page && page !== this.currentPage) {
                this.currentPage = page;
                this.loadProducts();
            }
        },

        prevPage: function() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.loadProducts();
            }
        },

        nextPage: function() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.loadProducts();
            }
        },

        // toggleVisibility function removed - using category-based visibility

        openCategoryModal: function(e) {
            e.preventDefault();
            this.currentProductId = $(e.target).data('product-id');
            this.loadProductCategories();
            this.loadAvailableCategories(); // Load available categories for select
            $('#ml-category-modal').show();
        },

        closeModal: function() {
            $('#ml-category-modal').hide();
            this.currentProductId = null;
        },

        closeModalOutside: function(e) {
            if ($(e.target).is('#ml-category-modal')) {
                this.closeModal();
            }
        },

        loadProductCategories: function() {
            $('#ml-current-categories').html('Carregando...');
            
            $.ajax({
                url: mlProductsAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ml_get_product_categories',
                    nonce: mlProductsAjax.nonce,
                    product_id: this.currentProductId
                },
                success: (response) => {
                    if (response.success) {
                        let html = '';
                        if (response.data.length === 0) {
                            html = '<p>Nenhuma categoria associada</p>';
                        } else {
                            response.data.forEach(category => {
                                html += `
                                    <span class="ml-category-tag">
                                        ${category.name}
                                        <button type="button" class="ml-remove-category" data-category-id="${category.id}">×</button>
                                    </span>
                                `;
                            });
                        }
                        $('#ml-current-categories').html(html);
                    }
                }
            });
        },

        addCategory: function() {
            const selectedCategory = $('#ml-available-categories').val();
            const newCategoryName = $('#ml-new-category-name').val().trim();
            
            if (!selectedCategory && !newCategoryName) {
                this.showMessage('Selecione uma categoria ou digite um nome', 'error');
                return;
            }
            
            const categoryData = {
                action: 'ml_add_product_category',
                nonce: mlProductsAjax.nonce,
                product_id: this.currentProductId
            };
            
            if (selectedCategory) {
                categoryData.category_id = selectedCategory;
            } else {
                categoryData.category_name = newCategoryName;
            }
            
            $.ajax({
                url: mlProductsAjax.ajaxurl,
                type: 'POST',
                data: categoryData,
                success: (response) => {
                    if (response.success) {
                        this.loadProductCategories();
                        this.loadAvailableCategories(); // Update category select
                        $('#ml-available-categories').val('');
                        $('#ml-new-category-name').val('');
                        this.showMessage('Categoria adicionada!', 'success');
                    } else {
                        this.showMessage(response.data || 'Erro ao adicionar categoria', 'error');
                    }
                }
            });
        },

        removeCategory: function(e) {
            e.preventDefault();
            const $button = $(e.target);
            const categoryId = $button.data('category-id');
            
            if (!confirm('Tem certeza que deseja remover esta categoria do produto?')) {
                return;
            }
            
            $.ajax({
                url: mlProductsAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ml_remove_product_category',
                    nonce: mlProductsAjax.nonce,
                    product_id: this.currentProductId,
                    category_id: categoryId
                },
                success: (response) => {
                    if (response.success) {
                        this.loadProductCategories(); // Reload categories
                        this.showMessage('Categoria removida!', 'success');
                    } else {
                        this.showMessage('Erro ao remover categoria', 'error');
                    }
                },
                error: () => {
                    this.showMessage('Erro de conexão', 'error');
                }
            });
        },

        loadAvailableCategories: function() {
            $.ajax({
                url: mlProductsAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ml_get_categories',
                    nonce: mlProductsAjax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const $select = $('#ml-available-categories');
                        $select.find('option:not(:first)').remove(); // Keep first option
                        
                        response.data.forEach(category => {
                            $select.append(`<option value="${category.id}">${category.name}</option>`);
                        });
                    }
                }
            });
        },

        saveCategories: function() {
            this.closeModal();
            this.loadProducts(); // Reload to show updated categories
            this.showMessage('Categorias salvas!', 'success');
        }
    };

    // Initialize
    ProductsManager.init();

    // Make it globally accessible for debugging
    window.MLProductsManager = ProductsManager;
});