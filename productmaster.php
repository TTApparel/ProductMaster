<?php
/**
 * Plugin Name: ProductMaster
 * Description: Backend portal for WooCommerce apparel catalog visibility, with variation and inventory insights.
 * Version: 0.1.0
 * Author: ProductMaster Contributors
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: productmaster
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PRODUCTMASTER_VERSION', '0.1.0');
define('PRODUCTMASTER_FILE', __FILE__);
define('PRODUCTMASTER_PATH', plugin_dir_path(__FILE__));
define('PRODUCTMASTER_URL', plugin_dir_url(__FILE__));

require_once PRODUCTMASTER_PATH . 'includes/class-productmaster-plugin.php';

ProductMaster_Plugin::get_instance();
