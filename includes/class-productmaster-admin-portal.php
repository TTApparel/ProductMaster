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

        wp_enqueue_script(
            'productmaster-admin',
            PRODUCTMASTER_URL . 'assets/js/admin.js',
            array('jquery'),
            PRODUCTMASTER_VERSION,
            true
        );

        wp_localize_script(
            'productmaster-admin',
            'productmasterAdmin',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('productmaster_update_stock'),
                'savingText' => __('Saving...', 'productmaster'),
                'savedText' => __('Saved', 'productmaster'),
                'errorText' => __('Unable to save inventory.', 'productmaster'),
            )
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
                echo $this->render_inventory_editor($row['variation_id'], $row['qty'], $row['managing_stock'], $inventory_label);
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
                    $size_comparison = $this->compare_size_labels($a['size'], $b['size']);

                    if (0 !== $size_comparison) {
                        return $size_comparison;
                    }

                    return $a['variation_id'] <=> $b['variation_id'];
                }
            );

            $grouped[$color] = $rows;
        }

        return $grouped;
    }

    private function compare_size_labels($left_size, $right_size)
    {
        $left_rank = $this->get_size_rank($left_size);
        $right_rank = $this->get_size_rank($right_size);

        if ($left_rank === $right_rank) {
            return strcmp((string) $left_size, (string) $right_size);
        }

        return $left_rank <=> $right_rank;
    }

    private function get_size_rank($size_label)
    {
        $normalized_label = strtolower(trim((string) $size_label));
        $normalized_label = str_replace(array('-', '_'), ' ', $normalized_label);
        $normalized_label = preg_replace('/\s+/', ' ', $normalized_label);

        $size_order = array(
            'extra small' => 10,
            'x small' => 10,
            'xs' => 10,
            'small' => 20,
            's' => 20,
            'medium' => 30,
            'm' => 30,
            'large' => 40,
            'l' => 40,
            'medium large' => 40,
            'extra large' => 50,
            'x large' => 50,
            'xl' => 50,
            '2x large' => 60,
            '2xl' => 60,
            'xxl' => 60,
            '3x large' => 70,
            '3xl' => 70,
            'xxxl' => 70,
            '4xl' => 80,
            '4x large' => 80,
            '5xl' => 90,
            '5x large' => 90,
        );

        if (isset($size_order[$normalized_label])) {
            return $size_order[$normalized_label];
        }

        return 999;
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

    private function render_inventory_editor($variation_id, $qty, $managing_stock, $inventory_label)
    {
        if (!$managing_stock) {
            return '<p class="productmaster-stock-qty"><strong>' . esc_html__('Inventory Value:', 'productmaster') . '</strong> ' . esc_html($inventory_label) . '</p>';
        }

        $qty_value = null === $qty ? '' : (string) $qty;

        $output = '<div class="productmaster-stock-editor">';
        $output .= '<label for="productmaster-stock-' . esc_attr((string) $variation_id) . '"><strong>' . esc_html__('Inventory Value:', 'productmaster') . '</strong></label>';
        $output .= '<div class="productmaster-stock-controls">';
        $output .= '<input id="productmaster-stock-' . esc_attr((string) $variation_id) . '" type="number" min="0" step="1" class="productmaster-stock-input" value="' . esc_attr($qty_value) . '" data-variation-id="' . esc_attr((string) $variation_id) . '" />';
        $output .= '<button type="button" class="button button-secondary productmaster-save-stock" data-variation-id="' . esc_attr((string) $variation_id) . '">' . esc_html__('Update', 'productmaster') . '</button>';
        $output .= '</div>';
        $output .= '<p class="productmaster-stock-feedback" data-variation-id="' . esc_attr((string) $variation_id) . '" aria-live="polite"></p>';
        $output .= '</div>';

        return $output;
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

    public function ajax_update_variation_stock()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'productmaster')), 403);
        }

        check_ajax_referer('productmaster_update_stock', 'nonce');

        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
        $qty = isset($_POST['qty']) ? wc_stock_amount(wp_unslash($_POST['qty'])) : null;

        if ($variation_id <= 0 || null === $qty || $qty < 0) {
            wp_send_json_error(array('message' => __('Invalid inventory value.', 'productmaster')), 400);
        }

        $variation = wc_get_product($variation_id);

        if (!$variation instanceof WC_Product_Variation) {
            wp_send_json_error(array('message' => __('Variation not found.', 'productmaster')), 404);
        }

        if (!$variation->managing_stock()) {
            wp_send_json_error(array('message' => __('Stock management is disabled for this variation.', 'productmaster')), 400);
        }

        $variation->set_stock_quantity($qty);
        $variation->save();

        wp_send_json_success(
            array(
                'qty' => $variation->get_stock_quantity(),
                'message' => __('Inventory updated.', 'productmaster'),
            )
        );
    }
}
