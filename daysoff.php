<?php
/**
 * Plugin Name: WooCommerce Delivery Date Manager
 * Plugin URI: https://yourwebsite.com
 * Description: Manage delivery dates with custom day-off settings, weekend restrictions, and minimum processing days
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: woo-delivery-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WooCommerce_Delivery_Date_Manager {
    
    private $option_name = 'wdm_settings';
    
    public function __construct() {
        // Admin menu and settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Checkout modifications
        add_action('wp_footer', array($this, 'checkout_date_restrictions'));
        add_action('woocommerce_checkout_process', array($this, 'validate_billing_date'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_delivery_date'));
        
        // Display delivery date
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_in_admin'));
        add_action('woocommerce_email_customer_details', array($this, 'add_to_emails'), 20, 4);
        add_action('woocommerce_thankyou', array($this, 'display_on_thankyou'));
        
        // AJAX handlers
        add_action('wp_ajax_wdm_add_dayoff', array($this, 'ajax_add_dayoff'));
        add_action('wp_ajax_wdm_remove_dayoff', array($this, 'ajax_remove_dayoff'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Delivery Date Settings',
            'Delivery Dates',
            'manage_woocommerce',
            'woo-delivery-manager',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('wdm_settings_group', $this->option_name);
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'woocommerce_page_woo-delivery-manager') {
            return;
        }
        
        wp_enqueue_style('wdm-admin-css', plugin_dir_url(__FILE__) . 'assets/admin.css', array(), '1.0.0');
        wp_enqueue_script('wdm-admin-js', plugin_dir_url(__FILE__) . 'assets/admin.js', array('jquery'), '1.0.0', true);
        
        wp_localize_script('wdm-admin-js', 'wdmAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wdm_nonce')
        ));
    }
    
    /**
     * Get plugin settings
     */
    private function get_settings() {
        $defaults = array(
            'minimum_days' => 2,
            'disable_sunday' => true,
            'disable_saturday' => false,
            'disable_monday' => false,
            'disable_tuesday' => false,
            'disable_wednesday' => false,
            'disable_thursday' => false,
            'disable_friday' => false,
            'custom_dayoffs' => array(),
            'auto_weekend_disable' => false
        );
        
        $settings = get_option($this->option_name, $defaults);
        return wp_parse_args($settings, $defaults);
    }
    
    /**
     * Admin settings page
     */
    public function settings_page() {
        if (isset($_POST['wdm_save_settings']) && check_admin_referer('wdm_settings_nonce')) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        $settings = $this->get_settings();
        ?>
        <div class="wrap wdm-settings-wrap">
            <h1>🗓️ Delivery Date Manager Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('wdm_settings_nonce'); ?>
                
                <table class="form-table">
                    <!-- Minimum Processing Days -->
                    <tr>
                        <th scope="row">
                            <label for="minimum_days">Minimum Processing Days</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="minimum_days" 
                                   name="minimum_days" 
                                   value="<?php echo esc_attr($settings['minimum_days']); ?>" 
                                   min="0" 
                                   max="30" 
                                   class="small-text">
                            <p class="description">Orders require this many days to process (e.g., 2 = customers can only select dates 2+ days from today)</p>
                        </td>
                    </tr>
                    
                    <!-- Quick Weekend Disable -->
                    <tr>
                        <th scope="row">
                            <label for="auto_weekend_disable">Quick Weekend Disable</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="auto_weekend_disable" 
                                       name="auto_weekend_disable" 
                                       value="1" 
                                       <?php checked($settings['auto_weekend_disable'], true); ?>>
                                <strong>Disable Saturday & Sunday deliveries</strong>
                            </label>
                            <p class="description">Quick toggle to disable weekend deliveries. This will override individual day settings below.</p>
                        </td>
                    </tr>
                    
                    <!-- Individual Day Settings -->
                    <tr>
                        <th scope="row">Disable Specific Days</th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">Select days to disable</legend>
                                
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="disable_sunday" value="1" <?php checked($settings['disable_sunday'], true); ?>>
                                    <strong>Sunday</strong>
                                </label>
                                
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="disable_saturday" value="1" <?php checked($settings['disable_saturday'], true); ?>>
                                    <strong>Saturday</strong>
                                </label>
                                
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="disable_monday" value="1" <?php checked($settings['disable_monday'], true); ?>>
                                    Monday
                                </label>
                                
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="disable_tuesday" value="1" <?php checked($settings['disable_tuesday'], true); ?>>
                                    Tuesday
                                </label>
                                
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="disable_wednesday" value="1" <?php checked($settings['disable_wednesday'], true); ?>>
                                    Wednesday
                                </label>
                                
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="disable_thursday" value="1" <?php checked($settings['disable_thursday'], true); ?>>
                                    Thursday
                                </label>
                                
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="disable_friday" value="1" <?php checked($settings['disable_friday'], true); ?>>
                                    Friday
                                </label>
                            </fieldset>
                            <p class="description">Check any days you want to disable for deliveries. Users will not be able to select these days.</p>
                        </td>
                    </tr>
                    
                    <!-- Custom Day-Off Dates -->
                    <tr>
                        <th scope="row">Custom Day-Off Dates</th>
                        <td>
                            <div class="wdm-dayoff-manager">
                                <div class="wdm-add-dayoff">
                                    <input type="date" id="new_dayoff_date" class="regular-text">
                                    <input type="text" id="new_dayoff_reason" placeholder="Reason (optional)" class="regular-text">
                                    <button type="button" class="button button-secondary" id="add_dayoff_btn">Add Day Off</button>
                                </div>
                                
                                <div class="wdm-dayoff-list">
                                    <h3>Scheduled Days Off</h3>
                                    <table class="wp-list-table widefat fixed striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Day</th>
                                                <th>Reason</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="dayoff_list">
                                            <?php
                                            if (!empty($settings['custom_dayoffs'])) {
                                                usort($settings['custom_dayoffs'], function($a, $b) {
                                                    return strtotime($a['date']) - strtotime($b['date']);
                                                });
                                                
                                                foreach ($settings['custom_dayoffs'] as $index => $dayoff) {
                                                    $date = $dayoff['date'];
                                                    $reason = isset($dayoff['reason']) ? $dayoff['reason'] : '';
                                                    $day_name = date('l', strtotime($date));
                                                    $formatted_date = date('F j, Y', strtotime($date));
                                                    
                                                    echo '<tr data-date="' . esc_attr($date) . '">';
                                                    echo '<td><strong>' . esc_html($formatted_date) . '</strong></td>';
                                                    echo '<td>' . esc_html($day_name) . '</td>';
                                                    echo '<td>' . esc_html($reason) . '</td>';
                                                    echo '<td><button type="button" class="button button-small delete-dayoff" data-date="' . esc_attr($date) . '">Remove</button></td>';
                                                    echo '</tr>';
                                                }
                                            } else {
                                                echo '<tr><td colspan="4" style="text-align: center; color: #999;">No custom day-offs scheduled</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <p class="description">Add specific dates when deliveries should not be available (holidays, maintenance days, etc.)</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Settings', 'primary', 'wdm_save_settings'); ?>
            </form>
        </div>
        
        <style>
        .wdm-settings-wrap {
            max-width: 1200px;
        }
        .wdm-dayoff-manager {
            background: #fff;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 4px;
        }
        .wdm-add-dayoff {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .wdm-add-dayoff input {
            margin-right: 10px;
        }
        .wdm-dayoff-list h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        .delete-dayoff {
            color: #a00;
        }
        .delete-dayoff:hover {
            color: #dc3232;
            border-color: #dc3232;
        }
        </style>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        $settings = array(
            'minimum_days' => isset($_POST['minimum_days']) ? intval($_POST['minimum_days']) : 2,
            'disable_sunday' => isset($_POST['disable_sunday']),
            'disable_saturday' => isset($_POST['disable_saturday']),
            'disable_monday' => isset($_POST['disable_monday']),
            'disable_tuesday' => isset($_POST['disable_tuesday']),
            'disable_wednesday' => isset($_POST['disable_wednesday']),
            'disable_thursday' => isset($_POST['disable_thursday']),
            'disable_friday' => isset($_POST['disable_friday']),
            'auto_weekend_disable' => isset($_POST['auto_weekend_disable']),
            'custom_dayoffs' => $this->get_settings()['custom_dayoffs'] // Preserve existing day-offs
        );
        
        // If auto weekend disable is checked, force Saturday and Sunday to be disabled
        if ($settings['auto_weekend_disable']) {
            $settings['disable_saturday'] = true;
            $settings['disable_sunday'] = true;
        }
        
        update_option($this->option_name, $settings);
    }
    
    /**
     * AJAX: Add day-off
     */
    public function ajax_add_dayoff() {
        check_ajax_referer('wdm_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Unauthorized');
        }
        
        $date = sanitize_text_field($_POST['date']);
        $reason = sanitize_text_field($_POST['reason']);
        
        if (empty($date)) {
            wp_send_json_error('Date is required');
        }
        
        $settings = $this->get_settings();
        
        // Check if date already exists
        foreach ($settings['custom_dayoffs'] as $dayoff) {
            if ($dayoff['date'] === $date) {
                wp_send_json_error('This date is already added');
            }
        }
        
        $settings['custom_dayoffs'][] = array(
            'date' => $date,
            'reason' => $reason
        );
        
        update_option($this->option_name, $settings);
        
        $day_name = date('l', strtotime($date));
        $formatted_date = date('F j, Y', strtotime($date));
        
        wp_send_json_success(array(
            'date' => $date,
            'formatted_date' => $formatted_date,
            'day_name' => $day_name,
            'reason' => $reason
        ));
    }
    
    /**
     * AJAX: Remove day-off
     */
    public function ajax_remove_dayoff() {
        check_ajax_referer('wdm_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Unauthorized');
        }
        
        $date = sanitize_text_field($_POST['date']);
        $settings = $this->get_settings();
        
        $settings['custom_dayoffs'] = array_filter($settings['custom_dayoffs'], function($dayoff) use ($date) {
            return $dayoff['date'] !== $date;
        });
        
        $settings['custom_dayoffs'] = array_values($settings['custom_dayoffs']); // Re-index array
        
        update_option($this->option_name, $settings);
        
        wp_send_json_success();
    }
    
    /**
     * Get disabled dates for frontend
     */
    private function get_disabled_dates() {
        $settings = $this->get_settings();
        $disabled = array(
            'days' => array(),
            'dates' => array()
        );
        
        // Handle auto weekend disable
        if ($settings['auto_weekend_disable']) {
            $disabled['days'][] = 0; // Sunday
            $disabled['days'][] = 6; // Saturday
        } else {
            // Individual day settings
            if ($settings['disable_sunday']) $disabled['days'][] = 0;
            if ($settings['disable_monday']) $disabled['days'][] = 1;
            if ($settings['disable_tuesday']) $disabled['days'][] = 2;
            if ($settings['disable_wednesday']) $disabled['days'][] = 3;
            if ($settings['disable_thursday']) $disabled['days'][] = 4;
            if ($settings['disable_friday']) $disabled['days'][] = 5;
            if ($settings['disable_saturday']) $disabled['days'][] = 6;
        }
        
        // Custom day-offs
        foreach ($settings['custom_dayoffs'] as $dayoff) {
            $disabled['dates'][] = $dayoff['date'];
        }
        
        return $disabled;
    }
    
    /**
     * Frontend date restrictions
     */
    public function checkout_date_restrictions() {
        if (!is_checkout()) {
            return;
        }
        
        $settings = $this->get_settings();
        $disabled = $this->get_disabled_dates();
        ?>
        <script>
        jQuery(document).ready(function($) {
            var dateField = $('input[name="billing_date"]');
            
            if (dateField.length) {
                var minDays = <?php echo intval($settings['minimum_days']); ?>;
                var disabledDays = <?php echo json_encode($disabled['days']); ?>;
                var disabledDates = <?php echo json_encode($disabled['dates']); ?>;
                
                // Calculate minimum date
                var today = new Date();
                var minDate = new Date(today);
                minDate.setDate(today.getDate() + minDays);
                
                // Skip disabled days for minimum date
                while (disabledDays.includes(minDate.getDay())) {
                    minDate.setDate(minDate.getDate() + 1);
                }
                
                var minDateString = minDate.toISOString().split('T')[0];
                dateField.attr('min', minDateString);
                dateField.attr('placeholder', 'Select delivery date');
                
                // Validation
                dateField.on('change input blur', function() {
                    var selectedValue = $(this).val();
                    
                    if (!selectedValue) {
                        return;
                    }
                    
                    var selectedDate = new Date(selectedValue);
                    var currentDate = new Date();
                    currentDate.setHours(0,0,0,0);
                    
                    // Check past dates
                    if (selectedDate <= currentDate) {
                        showDateError('Please select a future date. Orders require ' + minDays + ' day(s) to process.');
                        $(this).val('');
                        return false;
                    }
                    
                    // Check minimum processing days
                    var minProcessDate = new Date();
                    minProcessDate.setDate(minProcessDate.getDate() + minDays);
                    minProcessDate.setHours(0,0,0,0);
                    
                    if (selectedDate < minProcessDate) {
                        showDateError('Orders require ' + minDays + ' day(s) to process. Please select a later date.');
                        $(this).val('');
                        return false;
                    }
                    
                    // Check disabled days
                    if (disabledDays.includes(selectedDate.getDay())) {
                        var dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        showDateError(dayNames[selectedDate.getDay()] + ' delivery is not available. Please select another date.');
                        $(this).val('');
                        return false;
                    }
                    
                    // Check custom disabled dates
                    if (disabledDates.includes(selectedValue)) {
                        showDateError('Delivery is not available on this date. Please select another date.');
                        $(this).val('');
                        return false;
                    }
                    
                    $(this).removeClass('date-error');
                    $('.date-error-message').remove();
                });
                
                function showDateError(message) {
                    dateField.addClass('date-error');
                    $('.date-error-message').remove();
                    dateField.after('<div class="date-error-message" style="color: #e74c3c; font-size: 12px; margin-top: 5px; background: #fdf2f2; border: 1px solid #e74c3c; padding: 8px; border-radius: 3px;">' + message + '</div>');
                    setTimeout(function() {
                        $('.date-error-message').fadeOut();
                    }, 5000);
                }
            }
        });
        </script>
        
        <style>
        input[name="billing_date"].date-error {
            border-color: #e74c3c !important;
            background-color: #fdf2f2 !important;
        }
        </style>
        <?php
    }
    
    /**
     * Server-side validation
     */
    public function validate_billing_date() {
        if (isset($_POST['billing_date']) && !empty($_POST['billing_date'])) {
            $selected_date = sanitize_text_field($_POST['billing_date']);
            $selected_timestamp = strtotime($selected_date);
            $settings = $this->get_settings();
            $disabled = $this->get_disabled_dates();
            
            if (!$selected_timestamp) {
                wc_add_notice('Please enter a valid delivery date.', 'error');
                return;
            }
            
            $current_timestamp = strtotime('today');
            if ($selected_timestamp <= $current_timestamp) {
                wc_add_notice('Delivery date cannot be today or in the past.', 'error');
                return;
            }
            
            $min_timestamp = strtotime('+' . $settings['minimum_days'] . ' days');
            if ($selected_timestamp < $min_timestamp) {
                wc_add_notice('Delivery date must be at least ' . $settings['minimum_days'] . ' day(s) from today.', 'error');
                return;
            }
            
            $selected_day = date('w', $selected_timestamp);
            if (in_array($selected_day, $disabled['days'])) {
                wc_add_notice('Delivery is not available on the selected day of the week.', 'error');
                return;
            }
            
            if (in_array($selected_date, $disabled['dates'])) {
                wc_add_notice('Delivery is not available on this date.', 'error');
                return;
            }
        }
    }
    
    /**
     * Save delivery date to order
     */
    public function save_delivery_date($order_id) {
        if (isset($_POST['billing_date']) && !empty($_POST['billing_date'])) {
            $billing_date = sanitize_text_field($_POST['billing_date']);
            update_post_meta($order_id, '_billing_date', $billing_date);
            update_post_meta($order_id, '_delivery_date', $billing_date);
        }
    }
    
    /**
     * Display in admin
     */
    public function display_in_admin($order) {
        $delivery_date = get_post_meta($order->get_id(), '_billing_date', true);
        if ($delivery_date) {
            $formatted_date = date('l, F j, Y', strtotime($delivery_date));
            echo '<p><strong>Delivery Date:</strong> ' . esc_html($formatted_date) . '</p>';
        }
    }
    
    /**
     * Add to emails
     */
    public function add_to_emails($order, $sent_to_admin, $plain_text, $email) {
        $delivery_date = get_post_meta($order->get_id(), '_billing_date', true);
        if ($delivery_date) {
            $formatted_date = date('l, F j, Y', strtotime($delivery_date));
            if ($plain_text) {
                echo "\nDelivery Date: " . $formatted_date . "\n";
            } else {
                echo '<p><strong>Delivery Date:</strong> ' . esc_html($formatted_date) . '</p>';
            }
        }
    }
    
    /**
     * Display on thank you page
     */
    public function display_on_thankyou($order_id) {
        $delivery_date = get_post_meta($order_id, '_billing_date', true);
        if ($delivery_date) {
            $formatted_date = date('l, F j, Y', strtotime($delivery_date));
            echo '<p><strong>Your selected delivery date:</strong> ' . esc_html($formatted_date) . '</p>';
        }
    }
}

// Initialize the plugin
new WooCommerce_Delivery_Date_Manager();

// Create admin.js file content in plugin folder
function wdm_create_admin_js() {
    $js_content = <<<'JS'
jQuery(document).ready(function($) {
    // Add day-off
    $('#add_dayoff_btn').on('click', function() {
        var date = $('#new_dayoff_date').val();
        var reason = $('#new_dayoff_reason').val();
        
        if (!date) {
            alert('Please select a date');
            return;
        }
        
        $.ajax({
            url: wdmAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'wdm_add_dayoff',
                nonce: wdmAjax.nonce,
                date: date,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    var row = '<tr data-date="' + data.date + '">' +
                        '<td><strong>' + data.formatted_date + '</strong></td>' +
                        '<td>' + data.day_name + '</td>' +
                        '<td>' + data.reason + '</td>' +
                        '<td><button type="button" class="button button-small delete-dayoff" data-date="' + data.date + '">Remove</button></td>' +
                        '</tr>';
                    
                    $('#dayoff_list').append(row);
                    $('#new_dayoff_date').val('');
                    $('#new_dayoff_reason').val('');
                    
                    // Remove "no day-offs" message if exists
                    $('#dayoff_list tr td[colspan="4"]').parent().remove();
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    });
    
    // Remove day-off
    $(document).on('click', '.delete-dayoff', function() {
        if (!confirm('Remove this day-off?')) {
            return;
        }
        
        var date = $(this).data('date');
        var row = $(this).closest('tr');
        
        $.ajax({
            url: wdmAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'wdm_remove_dayoff',
                nonce: wdmAjax.nonce,
                date: date
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(function() {
                        $(this).remove();
                        
                        // Add "no day-offs" message if list is empty
                        if ($('#dayoff_list tr').length === 0) {
                            $('#dayoff_list').html('<tr><td colspan="4" style="text-align: center; color: #999;">No custom day-offs scheduled</td></tr>');
                        }
                    });
                }
            }
        });
    });
});
JS;
    
    // Note: In real implementation, save this to assets/admin.js file
    // For now, it's embedded in the PHP for single-file distribution
}