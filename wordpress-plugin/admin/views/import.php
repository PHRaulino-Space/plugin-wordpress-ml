<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Mercado Livre - Import Products', 'mercadolivre-integration'); ?></h1>
    
    <?php settings_errors('ml_settings'); ?>
    
    <div class="ml-import-container">
        <!-- Search and Import Section -->
        <div class="ml-import-search">
            <h2><?php _e('Search and Import Products', 'mercadolivre-integration'); ?></h2>
            <p><?php _e('Search for products on Mercado Livre and import them to your database.', 'mercadolivre-integration'); ?></p>
            
            <div class="ml-search-form">
                <div class="ml-search-input">
                    <input type="text" id="ml-search-query" placeholder="<?php _e('Enter product name or keywords...', 'mercadolivre-integration'); ?>" class="regular-text" />
                    <button type="button" id="ml-search-btn" class="button button-primary">
                        <span class="dashicons dashicons-search"></span>
                        <?php _e('Search', 'mercadolivre-integration'); ?>
                    </button>
                </div>
                
                <div class="ml-search-filters">
                    <select id="ml-search-limit">
                        <option value="10"><?php _e('10 results', 'mercadolivre-integration'); ?></option>
                        <option value="20" selected><?php _e('20 results', 'mercadolivre-integration'); ?></option>
                        <option value="50"><?php _e('50 results', 'mercadolivre-integration'); ?></option>
                    </select>
                </div>
            </div>
            
            <div id="ml-search-results" class="ml-search-results" style="display: none;">
                <h3><?php _e('Search Results', 'mercadolivre-integration'); ?></h3>
                <div id="ml-results-grid" class="ml-results-grid"></div>
            </div>
        </div>
        
        <!-- Direct Import Section -->
        <div class="ml-import-direct">
            <h2><?php _e('Direct Product Import', 'mercadolivre-integration'); ?></h2>
            <p><?php _e('If you already know the Mercado Livre product ID, you can import it directly.', 'mercadolivre-integration'); ?></p>
            
            <div class="ml-direct-form">
                <input type="text" id="ml-direct-id" placeholder="<?php _e('MLB1234567890', 'mercadolivre-integration'); ?>" class="regular-text" />
                <button type="button" id="ml-direct-import" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Import Product', 'mercadolivre-integration'); ?>
                </button>
            </div>
            
            <p class="description">
                <?php _e('Example: MLB1234567890 (the product ID from the Mercado Livre URL)', 'mercadolivre-integration'); ?>
            </p>
        </div>
        
        <!-- Bulk Import Section -->
        <div class="ml-import-bulk">
            <h2><?php _e('Bulk Import', 'mercadolivre-integration'); ?></h2>
            <p><?php _e('Import multiple products at once by providing a list of product IDs.', 'mercadolivre-integration'); ?></p>
            
            <div class="ml-bulk-form">
                <textarea id="ml-bulk-ids" rows="5" cols="50" placeholder="MLB1234567890&#10;MLB0987654321&#10;MLB1111111111" class="large-text"></textarea>
                <br><br>
                <button type="button" id="ml-bulk-import" class="button button-secondary">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Import All Products', 'mercadolivre-integration'); ?>
                </button>
            </div>
            
            <p class="description">
                <?php _e('Enter one product ID per line. Processing will be done in batches to avoid timeouts.', 'mercadolivre-integration'); ?>
            </p>
            
            <div id="ml-bulk-progress" class="ml-bulk-progress" style="display: none;">
                <h4><?php _e('Import Progress', 'mercadolivre-integration'); ?></h4>
                <div class="ml-progress-bar">
                    <div id="ml-progress-fill" class="ml-progress-fill" style="width: 0%;"></div>
                </div>
                <div id="ml-progress-text" class="ml-progress-text">0 / 0</div>
                <div id="ml-progress-results" class="ml-progress-results"></div>
            </div>
        </div>
        
        <!-- Import History -->
        <div class="ml-import-history">
            <h2><?php _e('Recent Imports', 'mercadolivre-integration'); ?></h2>
            <div id="ml-import-log">
                <p class="description"><?php _e('Your recent imports will appear here.', 'mercadolivre-integration'); ?></p>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var importLog = [];
    
    // Search products
    $('#ml-search-btn').on('click', function() {
        searchProducts();
    });
    
    $('#ml-search-query').on('keypress', function(e) {
        if (e.which === 13) {
            searchProducts();
        }
    });
    
    function searchProducts() {
        var query = $('#ml-search-query').val().trim();
        var limit = $('#ml-search-limit').val();
        
        if (!query) {
            alert('<?php _e('Please enter a search query', 'mercadolivre-integration'); ?>');
            return;
        }
        
        var $button = $('#ml-search-btn');
        var originalText = $button.html();
        
        $button.prop('disabled', true)
               .html('<span class="dashicons dashicons-update spin"></span> <?php _e('Searching...', 'mercadolivre-integration'); ?>');
        
        $.ajax({
            url: mlAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'ml_search_products',
                nonce: mlAjax.nonce,
                query: query,
                limit: limit
            },
            success: function(response) {
                if (response.success) {
                    displaySearchResults(response.data);
                    $('#ml-search-results').show();
                } else {
                    alert('Error: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('AJAX error occurred');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    }
    
    function displaySearchResults(results) {
        var $grid = $('#ml-results-grid');
        $grid.empty();
        
        if (!results || results.length === 0) {
            $grid.html('<p><?php _e('No products found.', 'mercadolivre-integration'); ?></p>');
            return;
        }
        
        $.each(results, function(index, product) {
            var $item = $('<div class="ml-result-item">');
            
            var imageHtml = product.thumbnail ? 
                '<img src="' + product.thumbnail + '" alt="' + product.title + '" />' :
                '<div class="ml-no-image"><span class="dashicons dashicons-format-image"></span></div>';
            
            var priceHtml = product.price ? 
                '<span class="ml-price">' + parseFloat(product.price).toFixed(2) + ' ' + (product.currency_id || 'BRL') + '</span>' :
                '<span class="ml-price-na"><?php _e('Price not available', 'mercadolivre-integration'); ?></span>';
            
            $item.html(
                '<div class="ml-result-image">' + imageHtml + '</div>' +
                '<div class="ml-result-content">' +
                    '<h4 class="ml-result-title">' + product.title + '</h4>' +
                    '<div class="ml-result-price">' + priceHtml + '</div>' +
                    '<div class="ml-result-id">ID: ' + product.id + '</div>' +
                    '<div class="ml-result-actions">' +
                        '<button type="button" class="button button-primary ml-import-single" data-id="' + product.id + '">' +
                            '<span class="dashicons dashicons-download"></span> <?php _e('Import', 'mercadolivre-integration'); ?>' +
                        '</button>' +
                        '<a href="' + product.permalink + '" target="_blank" class="button"><?php _e('View on ML', 'mercadolivre-integration'); ?></a>' +
                    '</div>' +
                '</div>'
            );
            
            $grid.append($item);
        });
        
        // Bind import buttons
        $('.ml-import-single').on('click', function() {
            var productId = $(this).data('id');
            importSingleProduct(productId, $(this));
        });
    }
    
    // Direct import
    $('#ml-direct-import').on('click', function() {
        var productId = $('#ml-direct-id').val().trim();
        if (!productId) {
            alert('<?php _e('Please enter a product ID', 'mercadolivre-integration'); ?>');
            return;
        }
        
        importSingleProduct(productId, $(this));
    });
    
    // Bulk import
    $('#ml-bulk-import').on('click', function() {
        var ids = $('#ml-bulk-ids').val().trim().split('\n').filter(function(id) {
            return id.trim() !== '';
        });
        
        if (ids.length === 0) {
            alert('<?php _e('Please enter at least one product ID', 'mercadolivre-integration'); ?>');
            return;
        }
        
        importBulkProducts(ids);
    });
    
    function importSingleProduct(productId, $button) {
        var originalText = $button.html();
        
        $button.prop('disabled', true)
               .html('<span class="dashicons dashicons-update spin"></span> <?php _e('Importing...', 'mercadolivre-integration'); ?>');
        
        $.ajax({
            url: mlAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'ml_import_product',
                nonce: mlAjax.nonce,
                product_id: productId
            },
            success: function(response) {
                if (response.success) {
                    var product = response.data;
                    addToImportLog('success', 'Imported: ' + product.title + ' (ID: ' + productId + ')');
                    $button.html('<span class="dashicons dashicons-yes"></span> <?php _e('Imported!', 'mercadolivre-integration'); ?>');
                    
                    setTimeout(function() {
                        $button.html(originalText).prop('disabled', false);
                    }, 2000);
                } else {
                    addToImportLog('error', 'Failed to import ' + productId + ': ' + (response.data.message || 'Unknown error'));
                    alert('Error: ' + (response.data.message || 'Unknown error'));
                    $button.prop('disabled', false).html(originalText);
                }
            },
            error: function() {
                addToImportLog('error', 'AJAX error importing ' + productId);
                alert('AJAX error occurred');
                $button.prop('disabled', false).html(originalText);
            }
        });
    }
    
    function importBulkProducts(ids) {
        var $progress = $('#ml-bulk-progress');
        var $progressFill = $('#ml-progress-fill');
        var $progressText = $('#ml-progress-text');
        var $results = $('#ml-progress-results');
        
        $progress.show();
        $progressText.text('0 / ' + ids.length);
        $results.empty();
        
        var completed = 0;
        var successful = 0;
        var failed = 0;
        
        function processNext() {
            if (completed >= ids.length) {
                $progressText.text('Completed: ' + successful + ' successful, ' + failed + ' failed');
                addToImportLog('info', 'Bulk import completed: ' + successful + ' successful, ' + failed + ' failed');
                return;
            }
            
            var productId = ids[completed];
            
            $.ajax({
                url: mlAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'ml_import_product',
                    nonce: mlAjax.nonce,
                    product_id: productId
                },
                success: function(response) {
                    if (response.success) {
                        successful++;
                        $results.append('<div class="ml-import-success">✓ ' + response.data.title + '</div>');
                    } else {
                        failed++;
                        $results.append('<div class="ml-import-error">✗ ' + productId + ': ' + (response.data.message || 'Unknown error') + '</div>');
                    }
                },
                error: function() {
                    failed++;
                    $results.append('<div class="ml-import-error">✗ ' + productId + ': AJAX error</div>');
                },
                complete: function() {
                    completed++;
                    var percentage = (completed / ids.length) * 100;
                    $progressFill.css('width', percentage + '%');
                    $progressText.text(completed + ' / ' + ids.length);
                    
                    // Process next with a small delay
                    setTimeout(processNext, 500);
                }
            });
        }
        
        processNext();
    }
    
    function addToImportLog(type, message) {
        var timestamp = new Date().toLocaleString();
        importLog.unshift({
            type: type,
            message: message,
            timestamp: timestamp
        });
        
        updateImportLogDisplay();
    }
    
    function updateImportLogDisplay() {
        var $log = $('#ml-import-log');
        
        if (importLog.length === 0) {
            $log.html('<p class="description"><?php _e('Your recent imports will appear here.', 'mercadolivre-integration'); ?></p>');
            return;
        }
        
        var html = '<ul class="ml-log-list">';
        $.each(importLog.slice(0, 10), function(index, entry) {
            html += '<li class="ml-log-' + entry.type + '">';
            html += '<span class="ml-log-time">' + entry.timestamp + '</span> ';
            html += '<span class="ml-log-message">' + entry.message + '</span>';
            html += '</li>';
        });
        html += '</ul>';
        
        $log.html(html);
    }
});
</script>