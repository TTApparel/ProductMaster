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

        $products = $this->get_variable_products();

        echo '<div class="wrap productmaster-wrap">';
        echo '<h1>' . esc_html__('ProductMaster Inventory Portal', 'productmaster') . '</h1>';
        echo '<p>' . esc_html__('Focused overview of variable products and their size/color variation inventory.', 'productmaster') . '</p>';

        if (empty($products)) {
            echo '<div class="notice notice-info"><p>' . esc_html__('No variable products found. Create variable products to populate this view.', 'productmaster') . '</p></div>';
            echo '</div>';
            return;
        }

        foreach ($products as $product) {
            $this->render_product_card($product);
        }

        echo '</div>';
    }

    private function get_variable_products()
    {
        $product_ids = wc_get_products(
            array(
                'status' => array('publish'),
                'limit' => 200,
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
        $grouped_inventory = $this->group_variations_by_color($variation_ids);

        echo '<section class="productmaster-card">';
        echo '<h2><a href="' . esc_url(get_edit_post_link($product->get_id())) . '">' . esc_html($product->get_name()) . '</a></h2>';
        echo '<p class="meta">' . esc_html__('SKU:', 'productmaster') . ' ' . esc_html($product->get_sku() ?: '—') . '</p>';

        if (empty($variation_ids) || empty($grouped_inventory)) {
            echo '<p>' . esc_html__('No variations found for this product.', 'productmaster') . '</p>';
            echo '</section>';
            return;
        }

        echo '<details class="productmaster-variations-toggle">';
        echo '<summary>' . esc_html__('View variations by color', 'productmaster') . '</summary>';

        foreach ($grouped_inventory as $color => $size_rows) {
            echo '<details class="productmaster-color-group">';
            echo '<summary>' . sprintf(esc_html__('Color: %s', 'productmaster'), esc_html($color)) . '</summary>';
            echo '<div class="productmaster-size-grid">';

            foreach ($size_rows as $row) {
                $stock_status = wc_get_product_stock_status_options()[$row['stock_status']] ?? $row['stock_status'];
                $progress = $this->calculate_stock_progress($row['qty']);
                $inventory_label = $this->format_stock_qty($row['qty'], $row['managing_stock']);

                echo '<article class="productmaster-size-card">';
                echo '<div class="productmaster-size-header">';
                echo '<span class="productmaster-size-name">' . esc_html($row['size']) . '</span>';
                echo '<a href="' . esc_url(get_edit_post_link($row['variation_id'])) . '">#' . esc_html((string) $row['variation_id']) . '</a>';
                echo '</div>';
                echo '<p class="productmaster-size-meta">' . esc_html__('SKU:', 'productmaster') . ' ' . esc_html($row['sku']) . ' · ' . esc_html($stock_status) . '</p>';
                echo '<div class="productmaster-stock-track" role="img" aria-label="' . esc_attr(sprintf(__('Inventory level for color %1$s size %2$s', 'productmaster'), $color, $row['size'])) . '">';
                echo '<span class="productmaster-stock-fill" style="width:' . esc_attr((string) $progress) . '%;"></span>';
                echo '</div>';
                echo '<p class="productmaster-stock-qty">' . esc_html($inventory_label) . '</p>';
                echo '</article>';
            }

            echo '</div>';
            echo '</details>';
        }

        echo '</details>';
        echo '</section>';
    }

    private function group_variations_by_color($variation_ids)
    {
        $grouped = array();

        foreach ($variation_ids as $variation_id) {
            $variation = wc_get_product($variation_id);

            if (!$variation instanceof WC_Product_Variation) {
                continue;
            }

            $attributes = $variation->get_attributes();
            $size = $this->get_attribute_label($attributes, array('pa_size', 'size'));
            $color = $this->get_attribute_label($attributes, array('pa_color', 'color'));

            if (!isset($grouped[$color])) {
                $grouped[$color] = array();
            }

            $grouped[$color][] = array(
                'variation_id' => $variation->get_id(),
                'size' => $size,
                'sku' => $variation->get_sku() ?: '—',
                'stock_status' => $variation->get_stock_status(),
                'qty' => $variation->get_stock_quantity(),
                'managing_stock' => $variation->managing_stock(),
            );
        }

        ksort($grouped);

        foreach ($grouped as $color => $rows) {
            usort(
                $rows,
                function ($a, $b) {
                    return strcmp($a['size'], $b['size']);
                }
            );

            $grouped[$color] = $rows;
        }

        return $grouped;
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

    private function format_stock_qty($qty, $managing_stock)
    {
        if (!$managing_stock) {
            return __('Not managed', 'productmaster');
        }

        return null === $qty ? __('N/A', 'productmaster') : (string) $qty;
    }

    private function calculate_stock_progress($qty)
    {
        if (null === $qty || $qty <= 0) {
            return 0;
        }

        if ($qty >= 50) {
            return 100;
        }

        return (int) round(($qty / 50) * 100);
    }
}
