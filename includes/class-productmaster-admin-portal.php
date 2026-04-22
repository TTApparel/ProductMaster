<?php

if (!defined('ABSPATH')) {
    exit;
}

class ProductMaster_Admin_Portal
{
    const PAGE_SLUG = 'productmaster-portal';

    public function register_menu()
    {
        add_menu_page(
            __('ProductMaster', 'productmaster'),
            __('ProductMaster', 'productmaster'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            array($this, 'render_page'),
            'dashicons-products',
            56
        );
    }

    public function enqueue_assets($hook)
    {
        if ('toplevel_page_' . self::PAGE_SLUG !== $hook) {
            return;
        }

        wp_enqueue_style(
            'productmaster-admin',
            PRODUCTMASTER_URL . 'assets/css/admin.css',
            array(),
            PRODUCTMASTER_VERSION
        );
    }

    public function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'productmaster'));
        }

        if (!class_exists('WooCommerce')) {
            echo '<div class="notice notice-error"><p>' . esc_html__('ProductMaster requires WooCommerce to be active.', 'productmaster') . '</p></div>';
            return;
        }

        $products = $this->get_apparel_products();

        echo '<div class="wrap productmaster-wrap">';
        echo '<h1>' . esc_html__('ProductMaster Inventory Portal', 'productmaster') . '</h1>';
        echo '<p>' . esc_html__('Focused overview of apparel products and their size/color variation inventory.', 'productmaster') . '</p>';

        if (empty($products)) {
            echo '<div class="notice notice-info"><p>' . esc_html__('No apparel products found. Add products to the apparel category to populate this view.', 'productmaster') . '</p></div>';
            echo '</div>';
            return;
        }

        foreach ($products as $product) {
            $this->render_product_card($product);
        }

        echo '</div>';
    }

    private function get_apparel_products()
    {
        $product_ids = wc_get_products(
            array(
                'status' => array('publish'),
                'limit' => 200,
                'category' => array('apparel'),
                'return' => 'ids',
                'orderby' => 'title',
                'order' => 'ASC',
            )
        );

        if (empty($product_ids)) {
            return array();
        }

        $products = array();

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);

            if (!$product instanceof WC_Product) {
                continue;
            }

            if (!$product->is_type('variable')) {
                continue;
            }

            $products[] = $product;
        }

        return $products;
    }

    private function render_product_card($product)
    {
        $variation_ids = $product->get_children();

        echo '<section class="productmaster-card">';
        echo '<h2><a href="' . esc_url(get_edit_post_link($product->get_id())) . '">' . esc_html($product->get_name()) . '</a></h2>';
        echo '<p class="meta">' . esc_html__('SKU:', 'productmaster') . ' ' . esc_html($product->get_sku() ?: '—') . '</p>';

        if (empty($variation_ids)) {
            echo '<p>' . esc_html__('No variations found for this product.', 'productmaster') . '</p>';
            echo '</section>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Variation', 'productmaster') . '</th>';
        echo '<th>' . esc_html__('Size', 'productmaster') . '</th>';
        echo '<th>' . esc_html__('Color', 'productmaster') . '</th>';
        echo '<th>' . esc_html__('SKU', 'productmaster') . '</th>';
        echo '<th>' . esc_html__('Stock Status', 'productmaster') . '</th>';
        echo '<th>' . esc_html__('Inventory Qty', 'productmaster') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($variation_ids as $variation_id) {
            $variation = wc_get_product($variation_id);

            if (!$variation instanceof WC_Product_Variation) {
                continue;
            }

            $attributes = $variation->get_attributes();
            $size = $this->get_attribute_label($attributes, array('pa_size', 'size'));
            $color = $this->get_attribute_label($attributes, array('pa_color', 'color'));

            echo '<tr>';
            echo '<td><a href="' . esc_url(get_edit_post_link($variation->get_id())) . '">#' . esc_html((string) $variation->get_id()) . '</a></td>';
            echo '<td>' . esc_html($size) . '</td>';
            echo '<td>' . esc_html($color) . '</td>';
            echo '<td>' . esc_html($variation->get_sku() ?: '—') . '</td>';
            echo '<td>' . esc_html(wc_get_product_stock_status_options()[$variation->get_stock_status()] ?? $variation->get_stock_status()) . '</td>';
            echo '<td>' . esc_html($this->format_stock_qty($variation)) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</section>';
    }

    private function get_attribute_label($attributes, $attribute_keys)
    {
        foreach ($attribute_keys as $key) {
            if (empty($attributes[$key])) {
                continue;
            }

            $value = (string) $attributes[$key];

            if (taxonomy_exists($key)) {
                $term = get_term_by('slug', $value, $key);

                if ($term && !is_wp_error($term)) {
                    return $term->name;
                }
            }

            return wc_clean($value);
        }

        return '—';
    }

    private function format_stock_qty($variation)
    {
        if (!$variation->managing_stock()) {
            return __('Not managed', 'productmaster');
        }

        $qty = $variation->get_stock_quantity();

        return null === $qty ? __('N/A', 'productmaster') : (string) $qty;
    }
}
