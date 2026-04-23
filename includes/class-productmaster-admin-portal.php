<?php

if (!defined('ABSPATH')) {
    exit;
}

class ProductMaster_Admin_Portal
{
    const PAGE_SLUG = 'productmaster-portal';
    const REVIEW_TOOLS_SLUG = 'productmaster-review-tools';
    const TAXONOMY_FILTERS_SLUG = 'productmaster-taxonomy-filters';
    const REVIEW_BUILDER_SLUG = 'productmaster-review-builder';
    const PER_PAGE = 20;

    public function register_menu()
    {
        add_menu_page(
            __('ProductMaster', 'productmaster'),
            __('ProductMaster', 'productmaster'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            array($this, 'render_inventory_page'),
            'dashicons-products',
            56
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('Inventory', 'productmaster'),
            __('Inventory', 'productmaster'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            array($this, 'render_inventory_page')
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('Review Tools', 'productmaster'),
            __('Review Tools', 'productmaster'),
            'manage_woocommerce',
            self::REVIEW_TOOLS_SLUG,
            array($this, 'render_review_tools_page')
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('Taxonomy Filters', 'productmaster'),
            __('Taxonomy Filters', 'productmaster'),
            'manage_woocommerce',
            self::TAXONOMY_FILTERS_SLUG,
            array($this, 'render_taxonomy_filters_page')
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('Review Builder', 'productmaster'),
            __('Review Builder', 'productmaster'),
            'manage_woocommerce',
            self::REVIEW_BUILDER_SLUG,
            array($this, 'render_review_builder_page')
        );
    }

    public function enqueue_assets($hook)
    {
        $allowed_hooks = array(
            'toplevel_page_' . self::PAGE_SLUG,
            'productmaster_page_' . self::REVIEW_TOOLS_SLUG,
            'productmaster_page_' . self::TAXONOMY_FILTERS_SLUG,
            'productmaster_page_' . self::REVIEW_BUILDER_SLUG,
        );

        if (!in_array($hook, $allowed_hooks, true)) {
            return;
        }

        wp_enqueue_style(
            'productmaster-admin',
            PRODUCTMASTER_URL . 'assets/css/admin.css',
            array(),
            PRODUCTMASTER_VERSION
        );
    }

    public function render_inventory_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'productmaster'));
        }

        if (!class_exists('WooCommerce')) {
            echo '<div class="notice notice-error"><p>' . esc_html__('ProductMaster requires WooCommerce to be active.', 'productmaster') . '</p></div>';
            return;
        }

        $current_page = $this->get_current_page();
        $query_result = $this->get_variable_products($current_page);
        $products = $query_result['products'];
        $total_pages = $query_result['total_pages'];

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

        $this->render_pagination($current_page, $total_pages);

        echo '</div>';
    }

    public function render_review_tools_page()
    {
        $this->render_placeholder_page(
            __('Review Tools', 'productmaster'),
            __('This workspace is reserved for review import and moderation workflows.', 'productmaster')
        );
    }

    public function render_taxonomy_filters_page()
    {
        $this->render_placeholder_page(
            __('Taxonomy Filters', 'productmaster'),
            __('This workspace will host shortcode-driven taxonomy filter tools for archive pages.', 'productmaster')
        );
    }

    public function render_review_builder_page()
    {
        $this->render_placeholder_page(
            __('Review Builder', 'productmaster'),
            __('This workspace will provide visual controls for review display on single product pages.', 'productmaster')
        );
    }

    private function get_variable_products($page)
    {
        $result = wc_get_products(
            array(
                'status' => array('publish'),
                'limit' => self::PER_PAGE,
                'page' => $page,
                'paginate' => true,
                'return' => 'objects',
                'orderby' => 'title',
                'order' => 'ASC',
                'type' => 'variable',
            )
        );

        if (!isset($result->products) || empty($result->products)) {
            return array(
                'products' => array(),
                'total_pages' => 0,
            );
        }

        $products = array();

        foreach ($result->products as $product) {
            if (!$product instanceof WC_Product) {
                continue;
            }

            if (!$product->is_type('variable')) {
                continue;
            }

            $products[] = $product;
        }

        return array(
            'products' => $products,
            'total_pages' => isset($result->max_num_pages) ? (int) $result->max_num_pages : 0,
        );
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

    private function get_current_page()
    {
        $page = isset($_GET['pm_page']) ? absint($_GET['pm_page']) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return max(1, $page);
    }

    private function render_pagination($current_page, $total_pages)
    {
        if ($total_pages <= 1) {
            return;
        }

        $base_url = add_query_arg(
            array(
                'page' => self::PAGE_SLUG,
            ),
            admin_url('admin.php')
        );

        $prev_page = max(1, $current_page - 1);
        $next_page = min($total_pages, $current_page + 1);

        echo '<nav class="productmaster-pagination" aria-label="' . esc_attr__('Product pagination', 'productmaster') . '">';
        echo '<a class="button" href="' . esc_url(add_query_arg('pm_page', $prev_page, $base_url)) . '">' . esc_html__('Previous', 'productmaster') . '</a>';
        echo '<span>' . sprintf(esc_html__('Page %1$d of %2$d', 'productmaster'), (int) $current_page, (int) $total_pages) . '</span>';
        echo '<a class="button" href="' . esc_url(add_query_arg('pm_page', $next_page, $base_url)) . '">' . esc_html__('Next', 'productmaster') . '</a>';
        echo '</nav>';
    }

    private function render_placeholder_page($title, $description)
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'productmaster'));
        }

        echo '<div class="wrap productmaster-wrap">';
        echo '<h1>' . esc_html($title) . '</h1>';
        echo '<p>' . esc_html($description) . '</p>';
        echo '</div>';
    }
}
