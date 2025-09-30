# WooCommerce Delivery Date Manager

A comprehensive WordPress plugin that allows customers to select delivery dates during checkout while giving store owners complete control over delivery scheduling through customizable day-off settings, weekend restrictions, and minimum processing time requirements.

## 🚀 Features

### **Delivery Date Selection**
- **Customer-facing date picker** on checkout page
- **Real-time validation** with instant feedback
- **Minimum processing days** configuration (e.g., require 2+ days for order processing)
- **Smart date restrictions** that automatically skip unavailable dates

### **Flexible Day Management**
- **Individual weekday control** - disable any combination of days (Monday through Sunday)
- **Quick weekend toggle** - instantly disable Saturday & Sunday deliveries
- **Custom day-off dates** - add specific dates for holidays, maintenance, or special events
- **Reason tracking** - optional notes for each custom day-off

### **Admin Features**
- **Intuitive settings panel** under WooCommerce menu
- **AJAX-powered day-off management** - add/remove dates without page refresh
- **Visual day-off calendar** with sortable date listing
- **Real-time validation** prevents duplicate dates

### **Order Integration**
- **Delivery date display** in admin order details
- **Email integration** - delivery dates appear in customer and admin emails
- **Thank you page** - confirmation of selected delivery date
- **HPOS compatibility** - fully compatible with WooCommerce High-Performance Order Storage

### **Technical Excellence**
- **Frontend & backend validation** for maximum reliability
- **Responsive design** works on all devices
- **Security-focused** with proper nonce verification and capability checks
- **Performance optimized** with minimal database queries

## 📋 Requirements

- **WordPress:** 5.0 or higher
- **WooCommerce:** 3.0 or higher (tested up to 8.0)
- **PHP:** 7.0 or higher

## 🛠️ Installation

1. **Download** the plugin files
2. **Upload** to your WordPress site via:
   - Admin dashboard: `Plugins > Add New > Upload Plugin`
   - FTP: Upload to `/wp-content/plugins/woocommerce-delivery-date-manager/`
3. **Activate** the plugin through the 'Plugins' menu
4. **Configure** settings under `WooCommerce > Delivery Dates`

## ⚙️ Configuration

### **Basic Settings**

1. Navigate to **WooCommerce > Delivery Dates**
2. Configure your delivery preferences:

#### **Minimum Processing Days**
- Set how many days orders need to process before delivery
- Example: Setting "2" means customers can only select dates 2+ days from today
- Range: 0-30 days

#### **Weekend Management**
- **Quick Toggle:** Enable "Quick Weekend Disable" to block Saturday & Sunday
- **Individual Control:** Manually select specific days to disable

#### **Custom Day-Off Dates**
- Add specific dates when delivery isn't available
- Include optional reasons (holidays, maintenance, etc.)
- Dates are automatically sorted chronologically
- Easy removal with one-click delete

### **Advanced Features**

#### **Smart Date Validation**
The plugin automatically:
- Prevents selection of past dates
- Enforces minimum processing time
- Skips disabled weekdays
- Blocks custom day-off dates
- Provides clear error messages

#### **Order Integration**
Delivery dates automatically appear in:
- Admin order details page
- Customer order emails
- Admin notification emails
- Order confirmation (thank you) page

## 🎯 How It Works

### **For Customers**
1. **Add products** to cart and proceed to checkout
2. **Select delivery date** using the date picker
3. **Real-time validation** ensures only valid dates can be selected
4. **Confirmation** shows selected date on thank you page and in emails

### **For Store Owners**
1. **Set processing time** (how many days you need to prepare orders)
2. **Configure delivery days** (which days of the week you deliver)
3. **Add custom day-offs** for holidays or special circumstances
4. **Monitor orders** with delivery dates clearly displayed in admin

## 🔧 Technical Details

### **Database Storage**
- Settings stored in WordPress options table
- Order delivery dates saved as order meta (HPOS compatible)
- No additional database tables required

### **Security Features**
- Nonce verification for all AJAX requests
- Capability checks for admin functions
- Input sanitization and validation
- XSS protection with proper escaping

### **Performance**
- Minimal database queries
- Inline JavaScript (no external files)
- Efficient AJAX operations
- Optimized for high-traffic sites

## 🎨 Customization

### **Styling**
The plugin includes built-in CSS for:
- Admin interface styling
- Error message formatting
- Date picker enhancements

### **Hooks & Filters**
The plugin uses standard WordPress/WooCommerce hooks:
- `woocommerce_checkout_process` - Date validation
- `woocommerce_checkout_update_order_meta` - Save delivery date
- `woocommerce_admin_order_data_after_billing_address` - Admin display
- `woocommerce_email_customer_details` - Email integration

## 🐛 Troubleshooting

### **Common Issues**

**Date picker not appearing:**
- Ensure WooCommerce is active
- Check if checkout page has proper billing fields
- Verify JavaScript is enabled

**Dates not saving:**
- Confirm proper WordPress permissions
- Check for plugin conflicts
- Verify HPOS compatibility settings

**Validation not working:**
- Clear browser cache
- Check for JavaScript errors in console
- Ensure minimum days setting is configured

## 📝 Changelog

### **Version 1.0.0**
- Initial release
- Core delivery date functionality
- Admin settings panel
- HPOS compatibility
- Email integration
- Custom day-off management

## 👨‍💻 Developer

**Sajid Khan**
- Website: [https://sajidkhan.me](https://sajidkhan.me)
- Plugin URI: [https://sajidkhan.me](https://sajidkhan.me)

## 📄 License

This plugin is licensed under the GPL v2 or later.

## 🤝 Support

For support, feature requests, or bug reports, please contact the developer through the official website.

---

**Made with ❤️ for the WooCommerce community**
