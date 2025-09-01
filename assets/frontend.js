jQuery(document).ready(function($) {
    // Botão de autenticação
    $('#ml-auth-btn').on('click', function() {
        window.location.href = ml_ajax.ajax_url + '?action=ml_authenticate';
    });
    
    // Botão de sincronização
    $('#ml-sync-btn').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Sincronizando...');
        
        $.ajax({
            url: ml_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ml_sync_products',
                _ajax_nonce: ml_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#ml-messages').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $('#ml-messages').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                }
            },
            error: function() {
                $('#ml-messages').html('<div class="notice notice-error"><p>Erro na sincronização</p></div>');
            },
            complete: function() {
                btn.prop('disabled', false).text('Sincronizar Produtos');
            }
        });
    });
    
    // Toggle visibilidade
    $('.ml-visibility-toggle').on('change', function() {
        var checkbox = $(this);
        var productId = checkbox.data('product-id');
        var visible = checkbox.is(':checked') ? 1 : 0;
        
        $.ajax({
            url: ml_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ml_toggle_visibility',
                product_id: productId,
                visible: visible,
                _ajax_nonce: ml_ajax.nonce
            },
            error: function() {
                checkbox.prop('checked', !checkbox.is(':checked'));
                alert('Erro ao alterar visibilidade');
            }
        });
    });
    
    // Modal de categoria
    var currentProductId = null;
    
    $('.ml-add-category-btn').on('click', function() {
        currentProductId = $(this).data('product-id');
        $('#ml-category-modal').show();
        $('#ml-category-input').focus();
    });
    
    $('#ml-cancel-category').on('click', function() {
        $('#ml-category-modal').hide();
        $('#ml-category-input').val('');
    });
    
    $('#ml-save-category').on('click', function() {
        var categoryName = $('#ml-category-input').val().trim();
        
        if (!categoryName) {
            alert('Digite o nome da categoria');
            return;
        }
        
        $.ajax({
            url: ml_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ml_add_category',
                product_id: currentProductId,
                category_name: categoryName,
                _ajax_nonce: ml_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Erro ao adicionar categoria');
                }
            },
            error: function() {
                alert('Erro ao adicionar categoria');
            }
        });
    });
    
    // Enter para salvar categoria
    $('#ml-category-input').on('keypress', function(e) {
        if (e.which == 13) {
            $('#ml-save-category').click();
        }
    });
});