<?php
/**
 * Plugin Name: User Verification Map
 * Description: A plugin for collecting user information, admin verification, and displaying verified user data on a map.
 * Version: 1.1
 * Author:Dimitrios Leka / jimboA8
 */

// Enqueue Leaflet.js scripts and styles
function uvmp_enqueue_scripts() {
    // Enqueue Leaflet CSS
    wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.css');
    
    // Enqueue Leaflet JavaScript
    wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.7.1/dist/leaflet.js', array(), null, true);

    // Enqueue custom map script
    wp_enqueue_script('uvmp-map-script', plugin_dir_url(__FILE__) . 'map-script.js', array('leaflet-js'), null, true);
    
    wp_enqueue_style('uvmp-custom-style', plugin_dir_url(__FILE__) . 'address-map-style.css');

    // Pass AJAX URL to JavaScript
    wp_localize_script('uvmp-map-script', 'uvmp_ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}
add_action('wp_enqueue_scripts', 'uvmp_enqueue_scripts');

// Create database table on plugin activation
register_activation_hook(__FILE__, 'uvmp_create_table');
function uvmp_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'form_submissions';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        address varchar(255) NOT NULL,
        phone varchar(255),
        latitude decimal(10, 8) NOT NULL,
        longitude decimal(11, 8) NOT NULL,
        verified tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Add code for form shortcode handler and form submission processing here
function uvmp_render_verification_form() {
    ob_start();
    ?>
    <!-- HTML form -->
    <form id="verification-form" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" method="post">
        <label for="name">Company Name:</label>
        <input type="text" id="name" name="name" required><br>
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required><br>
        <label for="address">Address:</label>
        <input type="text" id="address" name="address" required><br>
        <label for="phone">Website:</label>
        <input type="url" id="phone" name="phone"><br>
        <input type="submit" name="submit" value="Submit">
    </form>

    <script>
        // Initialize Google Places Autocomplete
        function initializeAutocomplete() {
            var input = document.getElementById('address');
            var autocomplete = new google.maps.places.Autocomplete(input);
        }

        // Load Google Maps JavaScript API with Autocomplete library
        function loadGoogleMaps() {
            var script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?key=YOUR-API-KEY-&libraries=places&callback=initializeAutocomplete';
            document.body.appendChild(script);
        }

        // Call loadGoogleMaps function when DOM content is loaded
        document.addEventListener('DOMContentLoaded', loadGoogleMaps);
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('verification_form', 'uvmp_render_verification_form');

function uvmp_process_verification_form() {
    if (isset($_POST['submit'])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'form_submissions';
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $address = sanitize_text_field($_POST['address']);
        $phone = isset($_POST['phone']) ? sanitize_url($_POST['phone']) : ''; // Note: Changed to sanitize_url for website/phone field based on your form
        
        // Geocode the address to get latitude and longitude
        $geocoded_address = uvmp_geocode_address($address);
        if ($geocoded_address) {
            $latitude = $geocoded_address['latitude'];
            $longitude = $geocoded_address['longitude'];
            $wpdb->insert(
                $table_name,
                array(
                    'name' => $name,
                    'email' => $email,
                    'address' => $address,
                    'phone' => $phone,
                    'latitude' => $latitude,
                    'longitude' => $longitude
                )
            );

            // Send email to the user after form submission
            $to = $email; // User's email address
            $subject = 'Form Submission Received';
            $message = 'Your form has been submitted successfully ';
            wp_mail($to, $subject, $message);

            // Redirect after successful form submission
            echo '<script>alert("Form submitted successfully! You will receive an email shortly. Please check your spam folder.");</script>';
            echo '<script>window.location.href = window.location.href.split("?")[0];</script>';
            exit;
        } else {
            wp_die('Failed to geocode address. Please try again later.', 'Error', array('response' => 400));
        }
    }
}
add_action('init', 'uvmp_process_verification_form');


// Geocode address to get latitude and longitude
function uvmp_geocode_address($address) {
    $address = urlencode($address);
    $url = "https://nominatim.openstreetmap.org/search?format=json&q={$address}";
    $response = wp_remote_get($url);
    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        if (!empty($data)) {
            $latitude = $data[0]->lat;
            $longitude = $data[0]->lon;
            return array('latitude' => $latitude, 'longitude' => $longitude);
        }
    }
    return false;
}

// Create admin page for managing form submissions
function uvmp_create_admin_page() {
    add_menu_page(
        'Form Submissions',
        'Form Submissions',
        'manage_options',
        'form-submissions',
        'uvmp_render_admin_page'
    );
}
add_action('admin_menu', 'uvmp_create_admin_page');

// Render admin page to display form submissions
function uvmp_render_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'form_submissions';

    // Retrieve all form submissions
    $submissions = $wpdb->get_results("SELECT * FROM $table_name");

    echo '<div class="wrap">';
    echo '<h2>Form Submissions</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Compnay Name</th><th>Email</th><th>Address</th><th>Website</th><th>Verified</th><th>Action</th></tr></thead>';
    echo '<tbody>';
    foreach ($submissions as $submission) {
        echo '<tr>';
        echo '<td>' . $submission->name . '</td>';
        echo '<td>' . $submission->email . '</td>';
        echo '<td>' . $submission->address . '</td>';
        echo '<td>' . $submission->phone . '</td>';
        echo '<td>' . ($submission->verified ? 'Yes' : 'No') . '</td>';
        echo '<td>';
        if ($submission->verified) {
            echo '<a href="?page=form-submissions&action=unverify&id=' . $submission->id . '">Unverify</a> | ';
        } else {
            echo '<a href="?page=form-submissions&action=verify&id=' . $submission->id . '">Verify</a> | ';
        }
        echo '<a href="?page=form-submissions&action=delete&id=' . $submission->id . '">Delete</a>';
		
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
}

// Handle form submission actions
function uvmp_handle_form_actions() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'form_submissions';
    
    if (!isset($_GET['page'], $_GET['action'])) {
        return;
    }

    if ('form-submissions' !== $_GET['page']) {
        return;
    }

    $submission_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$submission_id) {
        return;
    }

    if (!current_user_can('manage_options')) {
        wp_die('You are not allowed to perform this action.');
    }

    if ($_GET['action'] === 'verify') {
        // Verify submission
        $wpdb->update($table_name, ['verified' => 1], ['id' => $submission_id]);

        // Retrieve user's email to send verification notice
        $user_info = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $submission_id));
        if ($user_info) {
            $to = $user_info->email;
            $subject = 'Form Submission Verified';
            $message = "Congratulations, your form submission has been verified. " . site_url('/map-page/');
            wp_mail($to, $subject, $message);
        }
        
        wp_redirect(admin_url('admin.php?page=form-submissions'));
        exit;
    } elseif ($_GET['action'] === 'unverify') {
        // Unverify submission
        $wpdb->update($table_name, ['verified' => 0], ['id' => $submission_id]);
        
        wp_redirect(admin_url('admin.php?page=form-submissions'));
        exit;
    } elseif ($_GET['action'] === 'delete') {
        // Delete submission
        $wpdb->delete($table_name, ['id' => $submission_id]);
        
        wp_redirect(admin_url('admin.php?page=form-submissions'));
        exit;
    }
}
add_action('admin_init', 'uvmp_handle_form_actions');


// AJAX endpoint to retrieve verified user addresses
add_action('wp_ajax_get_verified_users', 'uvmp_get_verified_users');
function uvmp_get_verified_users() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'form_submissions';
    $verified_users = $wpdb->get_results("SELECT * FROM $table_name WHERE verified = 1", ARRAY_A);
    wp_send_json($verified_users);
}
// AJAX endpoint to retrieve verified user addresses for logged-in users
add_action('wp_ajax_get_verified_users', 'uvmp_get_verified_users');
// AJAX endpoint for non-logged-in users
add_action('wp_ajax_nopriv_get_verified_users', 'uvmp_get_verified_users');


// Shortcode to display form submissions
function uvmp_display_form_submissions() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'form_submissions';
    $submissions = $wpdb->get_results("SELECT * FROM $table_name");

    $output = '';

    if ($submissions) {
        // Responsive table CSS
        $output .= '<style>';
        $output .= 'table.uvmp-table { width: 100%; border-collapse: collapse; font-size: 14px; }';
        $output .= 'table.uvmp-table, table.uvmp-table th, table.uvmp-table td { border: 1px solid #ddd; }';
        $output .= 'table.uvmp-table th, table.uvmp-table td { text-align: left; padding: 10px; }';
        // Make table responsive
        $output .= '@media screen and (max-width: 760px), (min-device-width: 768px) and (max-device-width: 1024px)  {';
        $output .= 'table.uvmp-table, table.uvmp-table thead, table.uvmp-table tbody, table.uvmp-table th, table.uvmp-table td, table.uvmp-table tr { display: block; }';
        $output .= 'table.uvmp-table thead tr { position: absolute; top: -9999px; left: -9999px; }';
        $output .= 'table.uvmp-table tr { border: 1px solid #ccc; margin-bottom: 5px; }';
        $output .= 'table.uvmp-table td { border: none; border-bottom: 1px solid #eee; position: relative; padding-left: 50%; }';
        $output .= 'table.uvmp-table td:before {';
        $output .= 'position: absolute; top: 6px; left: 6px; width: 45%; padding-right: 10px; white-space: nowrap; font-weight: bold;';
        $output .= '}';
        // Label the data
        $output .= 'table.uvmp-table td:nth-of-type(1):before { content: "Company Name:"; }';
        $output .= 'table.uvmp-table td:nth-of-type(2):before { content: "Email:"; }';
        $output .= 'table.uvmp-table td:nth-of-type(3):before { content: "Address:"; }';
        $output .= 'table.uvmp-table td:nth-of-type(4):before { content: "Website:"; }';
        $output .= '}';
        $output .= '</style>';

        $output .= '<table class="uvmp-table">';
        $output .= '<thead><tr><th>Company Name</th><th>Email</th><th>Address</th><th>Website</th></tr></thead>';
        $output .= '<tbody>';
        foreach ($submissions as $submission) {
            $output .= '<tr>';
            $output .= '<td>' . esc_html($submission->name) . '</td>'; 
            $output .= '<td>' . esc_html($submission->email) . '</td>';
            $output .= '<td>' . esc_html($submission->address) . '</td>';
            $output .= '<td>' . esc_html($submission->phone) . '</td>'; // Assuming phone field is used for the website
            $output .= '</tr>';
        }
        $output .= '</tbody>';
        $output .= '</table>';
    } else {
        $output .= 'No form submissions found.';
    }

    return $output;
}


add_shortcode('display_form_submissions', 'uvmp_display_form_submissions');

// Change the "From" email address
add_filter('wp_mail_from', 'custom_email_from');
function custom_email_from($original_email_address) {
    return 'YOUR-COSTUME-EMAIL FOR EXAMPLE = info@YourCompanyname.com';
}

// Change the "From" name
add_filter('wp_mail_from_name', 'custom_email_from_name');
function custom_email_from_name($original_email_from) {
    return 'YOUR COMPANI NAME THAT THE USER WIILL RESIVE ON THE EMAIL';
}

 
