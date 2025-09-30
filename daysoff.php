<?php
/**
 * Plugin Name: WooCommerce Delivery Date Manager
 * Plugin URI: https://sajidkhan.me
 * Description: Manage delivery dates with custom day-off settings, weekend restrictions, and minimum processing days
 * Version: 1.0.0
 * Author: Sajid Khan
 * Author URI: https://sajidkhan.me
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

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

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
        
        // Enqueue inline JavaScript instead of external file
        wp_enqueue_script('jquery');
        
        $inline_js = "
        jQuery(document).ready(function($) {
            // Add day-off
            $('#add_dayoff_btn').on('click', function() {
                var date = $('#new_dayoff_date').val();
                var reason = $('#new_dayoff_reason').val();
                
                if (!date) {
                    alert('Please select a date');
                    return;
                }
                
                // Show loading state
                var btn = $(this);
                var originalText = btn.text();
                btn.prop('disabled', true).text('Adding...');
                
                $.ajax({
                    url: '" . admin_url('admin-ajax.php') . "',
                    type: 'POST',
                    data: {
                        action: 'wdm_add_dayoff',
                        nonce: '" . wp_create_nonce('wdm_nonce') . "',
                        date: date,
                        reason: reason
                    },
                    success: function(response) {
                        btn.prop('disabled', false).text(originalText);
                        
                        if (response.success) {
                            var data = response.data;
                            var row = '<tr data-date=\"' + data.date + '\">' +
                                '<td><strong>' + data.formatted_date + '</strong></td>' +
                                '<td>' + data.day_name + '</td>' +
                                '<td>' + data.reason + '</td>' +
                                '<td><button type=\"button\" class=\"button button-small delete-dayoff\" data-date=\"' + data.date + '\">Remove</button></td>' +
                                '</tr>';
                            
                            // Remove empty message if exists
                            $('#dayoff_list tr:contains(\"No custom day-offs\")').remove();
                            
                            $('#dayoff_list').append(row);
                            $('#new_dayoff_date').val('');
                            $('#new_dayoff_reason').val('');
                            
                            // Show success message
                            var successMsg = $('<div class=\"notice notice-success is-dismissible\" style=\"margin: 10px 0;\"><p>Day-off added successfully!</p></div>');
                            $('.wdm-add-dayoff').after(successMsg);
                            setTimeout(function() {
                                successMsg.fadeOut(function() { $(this).remove(); });
                            }, 3000);
                        } else {
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        btn.prop('disabled', false).text(originalText);
                        alert('AJAX Error: ' + error);
                        console.log(xhr.responseText);
                    }
                });
            });
            
            // Remove day-off
            $(document).on('click', '.delete-dayoff', function() {
                if (!confirm('Are you sure you want to remove this day-off?')) {
                    return;
                }
                
                var date = $(this).data('date');
                var row = $(this).closest('tr');
                var btn = $(this);
                
                btn.prop('disabled', true).text('Removing...');
                
                $.ajax({
                    url: '" . admin_url('admin-ajax.php') . "',
                    type: 'POST',
                    data: {
                        action: 'wdm_remove_dayoff',
                        nonce: '" . wp_create_nonce('wdm_nonce') . "',
                        date: date
                    },
                    success: function(response) {
                        if (response.success) {
                            row.fadeOut(function() {
                                $(this).remove();
                                
                                // Add \"no day-offs\" message if list is empty
                                if ($('#dayoff_list tr').length === 0) {
                                    $('#dayoff_list').html('<tr><td colspan=\"4\" style=\"text-align: center; color: #999;\">No custom day-offs scheduled</td></tr>');
                                }
                            });
                        } else {
                            btn.prop('disabled', false).text('Remove');
                            alert('Error: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        btn.prop('disabled', false).text('Remove');
                        alert('AJAX Error: ' + error);
                        console.log(xhr.responseText);
                    }
                });
            });
        });
        ";
        
        wp_add_inline_script('jquery', $inline_js);
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
            
            // Calculate minimum date (accounting for disabled days)
            var today = new Date();
            var minDate = new Date(today);
            var daysAdded = 0;
            
            // Add processing days, skipping disabled days
            while (daysAdded < minDays) {
                minDate.setDate(minDate.getDate() + 1);
                // Only count this day if it's not disabled
                if (!disabledDays.includes(minDate.getDay()) && 
                    !disabledDates.includes(minDate.toISOString().split('T')[0])) {
                    daysAdded++;
                }
            }
            
            var minDateString = minDate.toISOString().split('T')[0];
            dateField.attr('min', minDateString);
            dateField.attr('placeholder', 'Select delivery date (min. ' + minDays + ' day' + (minDays != 1 ? 's' : '') + ' processing)');
            
            // Validation
            dateField.on('change input blur', function() {
                var selectedValue = $(this).val();
                
                if (!selectedValue) {
                    return;
                }
                
                var selectedDate = new Date(selectedValue + 'T00:00:00');
                var currentDate = new Date();
                currentDate.setHours(0,0,0,0);
                
                // Check if date is in the past or today
                if (selectedDate <= currentDate) {
                    showDateError('Please select a future date. Orders require ' + minDays + ' day(s) to process.');
                    $(this).val('');
                    return false;
                }
                
                // Check if selected date meets minimum processing days (counting only available days)
                var checkDate = new Date(currentDate);
                var availableDays = 0;
                
                while (checkDate < selectedDate) {
                    checkDate.setDate(checkDate.getDate() + 1);
                    var dateString = checkDate.toISOString().split('T')[0];
                    
                    // Count this day if it's not disabled
                    if (!disabledDays.includes(checkDate.getDay()) && 
                        !disabledDates.includes(dateString)) {
                        availableDays++;
                    }
                }
                
                if (availableDays < minDays) {
                    showDateError('Orders require ' + minDays + ' available day(s) to process. Please select a later date (excluding disabled days).');
                    $(this).val('');
                    return false;
                }
                
                // Check disabled days of week
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
        
        // Count available days between today and selected date
        $available_days = 0;
        $check_date = $current_timestamp;
        
        while ($check_date < $selected_timestamp) {
            $check_date = strtotime('+1 day', $check_date);
            $check_day = date('w', $check_date);
            $check_date_string = date('Y-m-d', $check_date);
            
            // Count this day if it's not disabled
            if (!in_array($check_day, $disabled['days']) && 
                !in_array($check_date_string, $disabled['dates'])) {
                $available_days++;
            }
        }
        
        if ($available_days < $settings['minimum_days']) {
            wc_add_notice('Delivery date must have at least ' . $settings['minimum_days'] . ' available day(s) for processing (excluding disabled days).', 'error');
            return;
        }
        
        // Check if selected date itself is disabled
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
     * Save delivery date to order (HPOS Compatible)
     */
    public function save_delivery_date($order_id) {
        if (isset($_POST['billing_date']) && !empty($_POST['billing_date'])) {
            $billing_date = sanitize_text_field($_POST['billing_date']);
            
            // Get order object (HPOS compatible)
            $order = wc_get_order($order_id);
            
            if ($order) {
                // Use HPOS-compatible meta data methods
                $order->update_meta_data('_billing_date', $billing_date);
                $order->update_meta_data('_delivery_date', $billing_date);
                $order->save();
            }
        }
    }
    
    /**
     * Display in admin (HPOS Compatible)
     */
    public function display_in_admin($order) {
        // Get order object (already passed as parameter, but ensure it's the right type)
        if (!is_a($order, 'WC_Order')) {
            $order = wc_get_order($order);
        }
        
        if ($order) {
            $delivery_date = $order->get_meta('_billing_date', true);
            
            if ($delivery_date) {
                $formatted_date = date('l, F j, Y', strtotime($delivery_date));
                echo '<p><strong>Delivery Date:</strong> ' . esc_html($formatted_date) . '</p>';
            }
        }
    }
    
    /**
     * Add to emails (HPOS Compatible)
     */
    public function add_to_emails($order, $sent_to_admin, $plain_text, $email) {
        // Ensure we have an order object
        if (!is_a($order, 'WC_Order')) {
            $order = wc_get_order($order);
        }
        
        if ($order) {
            $delivery_date = $order->get_meta('_billing_date', true);
            
            if ($delivery_date) {
                $formatted_date = date('l, F j, Y', strtotime($delivery_date));
                
                if ($plain_text) {
                    echo "\nDelivery Date: " . $formatted_date . "\n";
                } else {
                    echo '<p><strong>Delivery Date:</strong> ' . esc_html($formatted_date) . '</p>';
                }
            }
        }
    }
    
    /**
     * Display on thank you page (HPOS Compatible)
     */
    public function display_on_thankyou($order_id) {
        $order = wc_get_order($order_id);
        
        if ($order) {
            $delivery_date = $order->get_meta('_billing_date', true);
            
            if ($delivery_date) {
                $formatted_date = date('l, F j, Y', strtotime($delivery_date));
                echo '<p><strong>Your selected delivery date:</strong> ' . esc_html($formatted_date) . '</p>';
            }
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