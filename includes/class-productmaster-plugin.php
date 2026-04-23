<?php

if (!defined('ABSPATH')) {
    exit;
}

class ProductMaster_Plugin
{
    /**
     * @var ProductMaster_Plugin|null
     */
    private static $instance = null;

    /**
     * @var ProductMaster_Admin_Portal
     */
    private $admin_portal;

    /**
     * @return ProductMaster_Plugin
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->load_dependencies();
        $this->register_hooks();
    }

    private function load_dependencies()
    {
        require_once PRODUCTMASTER_PATH . 'includes/class-productmaster-admin-portal.php';
        $this->admin_portal = new ProductMaster_Admin_Portal();
    }

    private function register_hooks()
    {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this->admin_portal, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this->admin_portal, 'enqueue_assets'));
        add_action('wp_ajax_productmaster_update_variation_stock', array($this->admin_portal, 'ajax_update_variation_stock'));
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('productmaster', false, dirname(plugin_basename(PRODUCTMASTER_FILE)) . '/languages');
    }
}
