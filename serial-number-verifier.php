<?php
/*
Plugin Name: Serial Checker Plugin
Description: A plugin to verify serial numbers and track visits.
Version: 1.1
Author: Donia Alhosin
*/


date_default_timezone_set('Africa/Cairo'); 
// Activation Hook: Create Database Table
function create_serial_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'serial_numbers';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        serial_number varchar(50) NOT NULL UNIQUE,
        status varchar(20) DEFAULT 'unverified',
        date_of_verification datetime DEFAULT NULL,
        last_visit datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'create_serial_table');

// Add Admin Menu
function serial_checker_admin_menu() {
    add_menu_page(
        'Serial Checker',              
        'Serial Checker',              
        'manage_options',              
        'serial-checker',              
        'serial_checker_admin_page'    
    );
}
add_action('admin_menu', 'serial_checker_admin_menu');

// Admin Page Content
function serial_checker_admin_page() {
    ?>
    <div class="wrap">
        <h1>Serial Checker Import/Export</h1>
        
        <!-- Import Section -->
        <h2>Import Serial Numbers</h2>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('import_serial_action', 'import_serial_nonce'); ?>
            <input type="file" name="import_file" accept=".csv" required>
            <input type="submit" name="import_serials" class="button button-primary" value="Import">
        </form>

        <!-- Export Section -->
        <h2>Export Serial Numbers</h2>
        <form method="post">
            <?php wp_nonce_field('export_serial_action', 'export_serial_nonce'); ?>
            <input type="submit" name="export_serials" class="button button-secondary" value="Export">
        </form>
    </div>
    <?php

    // Handle Import
    if (isset($_POST['import_serials'])) {
        check_admin_referer('import_serial_action', 'import_serial_nonce');
        import_serial_numbers();
    }

    // Handle Export
    if (isset($_POST['export_serials'])) {
        check_admin_referer('export_serial_action', 'export_serial_nonce');
        export_serial_numbers();
    }
}

// Import Functionality
function import_serial_numbers() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'serial_numbers';

    if ($_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
        $file = fopen($_FILES['import_file']['tmp_name'], 'r');
        while (($data = fgetcsv($file, 1000, ',')) !== FALSE) {
            $serial_number = sanitize_text_field($data[0]);
            if (!empty($serial_number)) {
                $wpdb->insert($table_name, array(
                    'serial_number' => $serial_number,
                    'status' => 'unverified'
                ), array('%s', '%s'));
            }
        }
        fclose($file);
        echo "<div class='updated'><p>Serial numbers imported successfully.</p></div>";
    } else {
        echo "<div class='error'><p>Error uploading file.</p></div>";
    }
}

// Export Functionality
function export_serial_numbers() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'serial_numbers';
    $results = $wpdb->get_results("SELECT * FROM $table_name");


    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="serial_numbers.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

  
    $output = fopen('php://output', 'w');

    
    fputcsv($output, array('ID', 'Serial Number', 'Status', 'Date of Verification', 'Last Visit'));


    foreach ($results as $row) {
        fputcsv($output, array(
            $row->id,
            $row->serial_number,
            $row->status,
            $row->date_of_verification,
            $row->last_visit
        ));
    }

    fclose($output); 
    exit(); 
}

// Shortcode to Display Serial Verification Form
function serial_checker_form() {
    ob_start(); ?>
    <div style="text-align: center; margin-top: 20px;">
        <a >
            <img src="data:image/png;base64,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" alt="Logo" style="max-width: 100px; display: block; margin: 0 auto;">
        </a>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" />
    

    <form method="post" class="serial-checker-form">
        <?php wp_nonce_field('verify_serial_action', 'verify_serial_nonce'); ?>
        <input type="text" name="serial_code" placeholder="Enter Serial Number" required>
        <input type="submit" name="check_serial" value="Verify">
    </form>

    <?php
    if (isset($_POST['check_serial']) && check_admin_referer('verify_serial_action', 'verify_serial_nonce')) {
        global $wpdb;
        $serial = sanitize_text_field($_POST['serial_code']);
        $table_name = $wpdb->prefix . 'serial_numbers';

        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE serial_number = %s", $serial));

        echo '<div class="serial-checker-message">';
        if ($result) {
            // If the serial is not verified, verify it now
            if ($result->status === 'unverified') {
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'verified',
                        'date_of_verification' => current_time('mysql')
                    ),
                    array('serial_number' => $serial)
                );
                echo '<div class="updated"><p>Serial number verified successfully!</p></div>';
            } else {
                echo '<div class="error"><p>This serial number has already been verified.</p></div>';
                echo '<div class="status-info">';
            echo '<p><strong>Date of Verification:</strong> ' .  date_i18n('Y-m-d H:i:s', strtotime($result->date_of_verification))  . '</p>';
            echo '<p><strong>Last Visit:</strong> ' . date_i18n('Y-m-d H:i:s', strtotime($result->last_visit))  . '</p>';
            echo '</div>';
            }

                 // Update last visit
            $wpdb->update($table_name, array('last_visit' => current_time('mysql')), array('serial_number' => $serial));
        } else {
            echo '<div class="error"><p>Invalid serial number.</p></div>';
        }
    }

    return ob_get_clean();
}
add_shortcode('serial_checker', 'serial_checker_form');
?>
