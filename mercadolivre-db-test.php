<?php
/**
 * Plugin Name: ML DB Test
 * Description: Testa apenas criação de tabelas
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

function ml_db_test_activate() {
    error_log('ML DB Test: Starting activation');
    
    try {
        global $wpdb;
        
        if (!$wpdb) {
            throw new Exception('No database connection available');
        }
        
        error_log('ML DB Test: Database connection OK');
        
        // Get charset
        $charset_collate = $wpdb->get_charset_collate();
        error_log('ML DB Test: Charset: ' . $charset_collate);
        
        // Simple table
        $table_name = $wpdb->prefix . 'ml_simple_test';
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            test_data varchar(255) NOT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        error_log('ML DB Test: SQL prepared: ' . $sql);
        
        // Load dbDelta
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            error_log('ML DB Test: dbDelta loaded from upgrade.php');
        }
        
        // Execute
        $result = dbDelta($sql);
        error_log('ML DB Test: dbDelta result: ' . print_r($result, true));
        
        // Check if table was created
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if ($table_exists == $table_name) {
            error_log('ML DB Test: Table created successfully');
            
            // Insert test data
            $insert_result = $wpdb->insert(
                $table_name,
                array('test_data' => 'Activation test data'),
                array('%s')
            );
            
            if ($insert_result !== false) {
                error_log('ML DB Test: Test data inserted successfully');
            } else {
                error_log('ML DB Test: Failed to insert test data: ' . $wpdb->last_error);
            }
            
        } else {
            throw new Exception('Table was not created: ' . $table_name);
        }
        
        error_log('ML DB Test: Activation completed successfully');
        
    } catch (Exception $e) {
        error_log('ML DB Test: Activation error: ' . $e->getMessage());
        
        // Don't use wp_die during activation, just log and deactivate
        deactivate_plugins(plugin_basename(__FILE__));
        return;
    }
}

function ml_db_test_deactivate() {
    error_log('ML DB Test: Deactivation started');
    
    // Optionally drop the test table
    global $wpdb;
    $table_name = $wpdb->prefix . 'ml_simple_test';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    error_log('ML DB Test: Test table dropped');
}

function ml_db_test_init() {
    if (is_admin()) {
        add_action('admin_menu', 'ml_db_test_menu');
    }
}

function ml_db_test_menu() {
    add_menu_page(
        'ML DB Test',
        'ML DB Test',
        'manage_options',
        'ml-db-test',
        'ml_db_test_page'
    );
}

function ml_db_test_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ml_simple_test';
    
    ?>
    <div class="wrap">
        <h1>ML Database Test</h1>
        
        <?php
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if ($table_exists == $table_name) {
            echo '<div class="notice notice-success"><p><strong>✅ SUCCESS!</strong> Tabela criada com sucesso.</p></div>';
            
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            echo '<p>Registros na tabela: ' . $count . '</p>';
            
            // Show data
            $data = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id DESC LIMIT 10");
            if ($data) {
                echo '<h3>Dados na tabela:</h3>';
                echo '<table class="wp-list-table widefat">';
                echo '<thead><tr><th>ID</th><th>Data</th><th>Created At</th></tr></thead>';
                echo '<tbody>';
                foreach ($data as $row) {
                    echo '<tr>';
                    echo '<td>' . $row->id . '</td>';
                    echo '<td>' . esc_html($row->test_data) . '</td>';
                    echo '<td>' . $row->created_at . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
            
            // Add test data form
            if (isset($_POST['add_test_data'])) {
                $test_data = 'Test data ' . date('Y-m-d H:i:s');
                $wpdb->insert($table_name, array('test_data' => $test_data));
                echo '<div class="notice notice-info"><p>Dados de teste adicionados!</p></div>';
                echo '<script>window.location.reload();</script>';
            }
            
            echo '<form method="post" style="margin-top: 20px;">';
            echo '<input type="submit" name="add_test_data" value="Adicionar Dados de Teste" class="button button-primary" />';
            echo '</form>';
            
        } else {
            echo '<div class="notice notice-error"><p><strong>❌ ERROR!</strong> Tabela não encontrada.</p></div>';
            echo '<p>A tabela <code>' . $table_name . '</code> não existe.</p>';
        }
        ?>
        
        <h2>Informações de Debug</h2>
        <ul>
            <li><strong>Table name:</strong> <?php echo $table_name; ?></li>
            <li><strong>WordPress DB Version:</strong> <?php echo $wpdb->db_version(); ?></li>
            <li><strong>MySQL Version:</strong> <?php echo $wpdb->get_var('SELECT VERSION()'); ?></li>
            <li><strong>Charset Collate:</strong> <?php echo $wpdb->get_charset_collate(); ?></li>
        </ul>
        
        <h3>Verificar logs de erro do servidor para mais detalhes</h3>
    </div>
    <?php
}

// Register hooks
register_activation_hook(__FILE__, 'ml_db_test_activate');
register_deactivation_hook(__FILE__, 'ml_db_test_deactivate');
add_action('init', 'ml_db_test_init');

error_log('ML DB Test: Plugin loaded');