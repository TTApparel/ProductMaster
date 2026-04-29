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
    const FILTER_OPTION_KEY = 'productmaster_taxonomy_filters';

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

        wp_enqueue_media();

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

    public function enqueue_frontend_assets()
    {
        wp_enqueue_style(
            'productmaster-frontend',
            PRODUCTMASTER_URL . 'assets/css/frontend.css',
            array(),
            PRODUCTMASTER_VERSION
        );

        wp_enqueue_script(
            'productmaster-frontend',
            PRODUCTMASTER_URL . 'assets/js/frontend.js',
            array(),
            PRODUCTMASTER_VERSION,
            true
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
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'productmaster'));
        }

        $notice = $this->handle_taxonomy_filter_actions();
        $filters = $this->get_saved_taxonomy_filters();
        $taxonomy_options = $this->get_taxonomy_options();
        $editing_filter = $this->get_editing_filter($filters);

        echo '<div class="wrap productmaster-wrap">';
        echo '<h1>' . esc_html__('Taxonomy Filters', 'productmaster') . '</h1>';
        echo '<p>' . esc_html__('Create shortcode-driven product loop filters using existing product categories and attributes.', 'productmaster') . '</p>';

        if (!empty($notice)) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($notice) . '</p></div>';
        }

        echo '<section class="productmaster-card">';
        echo '<h2>' . esc_html__('Create New Filter', 'productmaster') . '</h2>';
        echo '<form method="post">';
        wp_nonce_field('productmaster_save_tax_filter', 'productmaster_tax_filter_nonce');
        echo '<input type="hidden" name="productmaster_action" value="add_filter" />';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="pm_filter_label">' . esc_html__('Filter Label', 'productmaster') . '</label></th><td><input class="regular-text" id="pm_filter_label" name="filter_label" type="text" required /></td></tr>';
        echo '<tr><th scope="row"><label for="pm_filter_type">' . esc_html__('Filter Type', 'productmaster') . '</label></th><td><select id="pm_filter_type" name="filter_type">';
        foreach ($this->get_supported_filter_types() as $type => $label) {
            echo '<option value="' . esc_attr($type) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th scope="row"><label for="pm_filter_taxonomy">' . esc_html__('Category / Attribute', 'productmaster') . '</label></th><td><select id="pm_filter_taxonomy" name="filter_taxonomy">';
        foreach ($taxonomy_options as $taxonomy => $label) {
            echo '<option value="' . esc_attr($taxonomy) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';
        echo '</tbody></table>';
        submit_button(__('Add Filter', 'productmaster'));
        echo '</form>';
        echo '</section>';

        echo '<section class="productmaster-card">';
        echo '<h2>' . esc_html__('Configured Filters', 'productmaster') . '</h2>';
        echo '<p>' . esc_html__('Use all filters shortcode:', 'productmaster') . ' <code>[productmaster_filters]</code></p>';

        if (empty($filters)) {
            echo '<p>' . esc_html__('No filters configured yet.', 'productmaster') . '</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Label', 'productmaster') . '</th><th>' . esc_html__('Taxonomy', 'productmaster') . '</th><th>' . esc_html__('Type', 'productmaster') . '</th><th>' . esc_html__('Shortcode', 'productmaster') . '</th><th>' . esc_html__('Action', 'productmaster') . '</th></tr></thead><tbody>';
            foreach ($filters as $filter) {
                $type_label = $this->get_supported_filter_types()[$filter['type']] ?? $filter['type'];
                $filter_shortcode = '[productmaster_filter label="' . $filter['label'] . '"]';
                $dynamic_shortcode = '[productmaster_filter_' . sanitize_key($filter['id']) . ']';
                echo '<tr>';
                echo '<td>' . esc_html($filter['label']) . '</td>';
                echo '<td>' . esc_html($taxonomy_options[$filter['taxonomy']] ?? $filter['taxonomy']) . '</td>';
                echo '<td>' . esc_html($type_label) . '</td>';
                echo '<td><code>' . esc_html($filter_shortcode) . '</code><br /><code>' . esc_html($dynamic_shortcode) . '</code></td>';
                echo '<td><form method="post">';
                $edit_url = add_query_arg(
                    array(
                        'page' => self::TAXONOMY_FILTERS_SLUG,
                        'edit_filter' => $filter['id'],
                    ),
                    admin_url('admin.php')
                );
                echo '<a class="button button-small" href="' . esc_url($edit_url) . '">' . esc_html__('Edit', 'productmaster') . '</a> ';
                wp_nonce_field('productmaster_save_tax_filter', 'productmaster_tax_filter_nonce');
                echo '<input type="hidden" name="productmaster_action" value="delete_filter" />';
                echo '<input type="hidden" name="filter_id" value="' . esc_attr($filter['id']) . '" />';
                submit_button(__('Delete', 'productmaster'), 'delete small', 'submit', false);
                echo '</form></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</section>';

        if (!empty($editing_filter)) {
            $this->render_filter_presentation_editor($editing_filter);
        }
        echo '</div>';
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

    public function render_filters_shortcode()
    {
        return $this->render_filters_shortcode_by_args(array());
    }

    public function render_single_filter_shortcode($atts)
    {
        $atts = shortcode_atts(
            array(
                'id' => '',
                'label' => '',
            ),
            $atts,
            'productmaster_filter'
        );

        $filter_id = sanitize_text_field($atts['id']);
        if (empty($filter_id) && !empty($atts['label'])) {
            $filter_id = $this->find_filter_id_by_label(sanitize_text_field($atts['label']));
        }

        return $this->render_filters_shortcode_by_args(
            array(
                'filter_id' => $filter_id,
            )
        );
    }

    public function register_dynamic_filter_shortcodes()
    {
        foreach ($this->get_saved_taxonomy_filters() as $filter) {
            if (empty($filter['id'])) {
                continue;
            }

            $shortcode = 'productmaster_filter_' . sanitize_key($filter['id']);
            add_shortcode(
                $shortcode,
                function () use ($filter) {
                    return $this->render_filters_shortcode_by_args(
                        array(
                            'filter_id' => $filter['id'],
                        )
                    );
                }
            );
        }
    }

    private function render_filters_shortcode_by_args($args)
    {
        $filters = $this->get_saved_taxonomy_filters();
        if (empty($filters)) {
            return '';
        }

        if (!empty($args['filter_id'])) {
            $filters = array_values(
                array_filter(
                    $filters,
                    function ($filter) use ($args) {
                        return isset($filter['id']) && $filter['id'] === $args['filter_id'];
                    }
                )
            );
        }

        if (empty($filters)) {
            return '';
        }

        ob_start();
        echo '<form class="productmaster-filters-form" method="get">';
        $this->render_preserved_filter_query_inputs($filters);
        foreach ($filters as $filter) {
            $this->render_single_filter_input($filter);
        }

        echo '</form>';
        return ob_get_clean();
    }

    private function get_filter_value_match_operator($filter)
    {
        $presentation = isset($filter['presentation']) && is_array($filter['presentation']) ? $filter['presentation'] : array();
        $value_match = isset($presentation['value_match']) ? sanitize_key((string) $presentation['value_match']) : 'or';

        return 'and' === $value_match ? 'AND' : 'IN';
    }

    private function render_preserved_filter_query_inputs($rendered_filters)
    {
        $all_filter_keys = $this->get_filter_query_arg_keys();
        $keys_rendered_in_form = array();

        foreach ((array) $rendered_filters as $filter) {
            $keys_rendered_in_form = array_merge($keys_rendered_in_form, $this->get_filter_query_arg_keys_by_filter($filter));
        }
        $keys_rendered_in_form = array_values(array_unique($keys_rendered_in_form));

        foreach ($_GET as $key => $value) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $key = sanitize_key((string) $key);
            if (!in_array($key, $all_filter_keys, true)) {
                continue;
            }

            if (in_array($key, $keys_rendered_in_form, true)) {
                continue;
            }

            if (is_array($value)) {
                $clean_values = array_values(
                    array_filter(
                        array_map(
                            'sanitize_text_field',
                            wp_unslash($value)
                        )
                    )
                );
                echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr(implode(',', $clean_values)) . '" />';
                continue;
            }

            $clean_value = sanitize_text_field(wp_unslash($value));
            echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($clean_value) . '" />';
        }
    }

    public function apply_filters_to_product_query($query)
    {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        $post_type = $query->get('post_type');
        if ('product' !== $post_type && !is_shop() && !is_product_taxonomy()) {
            return;
        }

        $filters = $this->get_saved_taxonomy_filters();
        if (empty($filters)) {
            return;
        }

        $tax_query = (array) $query->get('tax_query', array());
        $filter_tax_query = array('relation' => 'AND');
        $meta_query = (array) $query->get('meta_query', array());

        foreach ($filters as $filter) {
            $param_key = 'pmf_' . $filter['id'];
            $raw_value = isset($_GET[$param_key]) ? wp_unslash($_GET[$param_key]) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $raw_values = $this->normalize_filter_values($raw_value);
            $allowed_terms = isset($filter['presentation']['allowed_terms']) ? (array) $filter['presentation']['allowed_terms'] : array();
            $manual_hierarchy_terms = $this->get_manual_hierarchy_allowed_terms($filter);
            if (!empty($manual_hierarchy_terms)) {
                $allowed_terms = $manual_hierarchy_terms;
            }

            if (in_array($filter['type'], array('checkboxes', 'image_boxes'), true) && !empty($raw_values)) {
                $terms = array_map('sanitize_title', $raw_values);
                $terms = $this->expand_terms_by_manual_hierarchy($terms, $filter);
                if (!empty($allowed_terms)) {
                    $terms = array_values(array_intersect($terms, $allowed_terms));
                }

                $filter_tax_query[] = array(
                    'taxonomy' => $filter['taxonomy'],
                    'field' => 'slug',
                    'terms' => $terms,
                    'operator' => $this->get_filter_value_match_operator($filter),
                );
            }

            if ('drop_down_selectors' === $filter['type'] && !empty($raw_value)) {
                $dropdown_term = sanitize_title((string) $raw_value);
                $dropdown_terms = $this->expand_terms_by_manual_hierarchy(array($dropdown_term), $filter);
                if (!empty($allowed_terms) && !in_array($dropdown_term, $allowed_terms, true)) {
                    continue;
                }

                $filter_tax_query[] = array(
                    'taxonomy' => $filter['taxonomy'],
                    'field' => 'slug',
                    'terms' => $dropdown_terms,
                    'operator' => $this->get_filter_value_match_operator($filter),
                );
            }

            if ('drop_down_selectors' === $filter['type'] && empty($raw_value)) {
                $parent_value = isset($_GET[$param_key . '_parent']) ? sanitize_title(wp_unslash($_GET[$param_key . '_parent'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $child_value = isset($_GET[$param_key . '_child']) ? sanitize_title(wp_unslash($_GET[$param_key . '_child'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $dropdown_terms = array_filter(array($parent_value, $child_value));
                $dropdown_terms = $this->expand_terms_by_manual_hierarchy($dropdown_terms, $filter);

                if (!empty($allowed_terms)) {
                    $dropdown_terms = array_values(array_intersect($dropdown_terms, $allowed_terms));
                }

                if (!empty($dropdown_terms)) {
                    $filter_tax_query[] = array(
                        'taxonomy' => $filter['taxonomy'],
                        'field' => 'slug',
                        'terms' => $dropdown_terms,
                        'operator' => $this->get_filter_value_match_operator($filter),
                    );
                }
            }

            if ('search_fields' === $filter['type'] && !empty($raw_value)) {
                $query->set('s', sanitize_text_field((string) $raw_value));
            }

            if ('multi_filter' === $filter['type'] && !empty($raw_value)) {
                $selected_pairs = $this->normalize_multi_filter_values($raw_value);
                $selected_by_source = array();
                foreach ($selected_pairs as $pair) {
                    if (false === strpos($pair, ':')) {
                        continue;
                    }
                    list($source_id, $term_slug) = explode(':', $pair, 2);
                    $source_id = sanitize_key($source_id);
                    $term_slug = sanitize_title($term_slug);
                    if ('' === $source_id || '' === $term_slug) {
                        continue;
                    }
                    $selected_by_source[$source_id][] = $term_slug;
                }

                if (empty($selected_by_source)) {
                    continue;
                }

                $filters_by_id = array();
                foreach ($filters as $saved_filter) {
                    if (!empty($saved_filter['id'])) {
                        $filters_by_id[$saved_filter['id']] = $saved_filter;
                    }
                }

                foreach ($selected_by_source as $source_id => $source_terms) {
                    if (empty($filters_by_id[$source_id]['taxonomy'])) {
                        continue;
                    }
                    $source_filter = $filters_by_id[$source_id];
                    $source_terms = $this->expand_terms_by_manual_hierarchy($source_terms, $source_filter);
                    $filter_tax_query[] = array(
                        'taxonomy' => $source_filter['taxonomy'],
                        'field' => 'slug',
                        'terms' => array_values(array_unique(array_filter($source_terms))),
                        'operator' => $this->get_filter_value_match_operator($source_filter),
                    );
                }
            }
        }

        $min_price = isset($_GET['pmf_min_price']) ? wc_format_decimal(wp_unslash($_GET['pmf_min_price'])) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $max_price = isset($_GET['pmf_max_price']) ? wc_format_decimal(wp_unslash($_GET['pmf_max_price'])) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if (null !== $min_price || null !== $max_price) {
            $meta_query[] = array(
                'key' => '_price',
                'value' => array(
                    null !== $min_price ? (float) $min_price : 0,
                    null !== $max_price ? (float) $max_price : 999999,
                ),
                'compare' => 'BETWEEN',
                'type' => 'DECIMAL',
            );
        }

        if (count($filter_tax_query) > 1) {
            $tax_query[] = $filter_tax_query;
        }

        if (!empty($tax_query)) {
            $query->set('tax_query', $tax_query);
        }

        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }
    }

    private function handle_taxonomy_filter_actions()
    {
        if ('POST' !== $_SERVER['REQUEST_METHOD']) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            return '';
        }

        if (!isset($_POST['productmaster_action']) || !isset($_POST['productmaster_tax_filter_nonce'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return '';
        }

        check_admin_referer('productmaster_save_tax_filter', 'productmaster_tax_filter_nonce');

        $action = sanitize_text_field(wp_unslash($_POST['productmaster_action'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $filters = $this->get_saved_taxonomy_filters();

        if ('add_filter' === $action) {
            $label = isset($_POST['filter_label']) ? sanitize_text_field(wp_unslash($_POST['filter_label'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $taxonomy = isset($_POST['filter_taxonomy']) ? sanitize_key(wp_unslash($_POST['filter_taxonomy'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $type = isset($_POST['filter_type']) ? sanitize_key(wp_unslash($_POST['filter_type'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

            if (empty($label) || empty($taxonomy) || !isset($this->get_supported_filter_types()[$type])) {
                return __('Unable to save filter. Check your values and try again.', 'productmaster');
            }

            if ($this->label_exists($label, $filters)) {
                return __('Filter label already exists. Use a unique label.', 'productmaster');
            }

            $filter_id = $this->generate_filter_id_from_label($label);

            $filters[] = array(
                'id' => $filter_id,
                'label' => $label,
                'taxonomy' => $taxonomy,
                'type' => $type,
                'presentation' => $this->get_default_presentation_settings(),
            );

            update_option(self::FILTER_OPTION_KEY, $filters, false);
            return __('Filter added.', 'productmaster');
        }

        if ('delete_filter' === $action) {
            $filter_id = isset($_POST['filter_id']) ? sanitize_text_field(wp_unslash($_POST['filter_id'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $filters = array_values(
                array_filter(
                    $filters,
                    function ($filter) use ($filter_id) {
                        return isset($filter['id']) && $filter['id'] !== $filter_id;
                    }
                )
            );
            update_option(self::FILTER_OPTION_KEY, $filters, false);
            return __('Filter deleted.', 'productmaster');
        }

        if ('update_filter_presentation' === $action) {
            $filter_id = isset($_POST['filter_id']) ? sanitize_text_field(wp_unslash($_POST['filter_id'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $presentation = $this->sanitize_presentation_settings($_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing

            foreach ($filters as $index => $filter) {
                if (!isset($filter['id']) || $filter['id'] !== $filter_id) {
                    continue;
                }

                $filters[$index]['presentation'] = $presentation;
            }

            update_option(self::FILTER_OPTION_KEY, $filters, false);
            return __('Filter presentation updated.', 'productmaster');
        }

        return '';
    }

    private function get_saved_taxonomy_filters()
    {
        $filters = get_option(self::FILTER_OPTION_KEY, array());
        if (!is_array($filters)) {
            return array();
        }

        $normalized_filters = array();
        $used_ids = array();
        foreach ($filters as $filter) {
            if (empty($filter['label'])) {
                continue;
            }

            if (empty($filter['id']) || 0 === strpos((string) $filter['id'], 'f_')) {
                $filter['id'] = $this->generate_filter_id_from_label($filter['label']);
            }

            $base_id = $filter['id'];
            $counter = 2;
            while (in_array($filter['id'], $used_ids, true)) {
                $filter['id'] = $base_id . '-' . $counter;
                $counter++;
            }
            $used_ids[] = $filter['id'];

            $normalized_filters[] = $filter;
        }
        $filters = $normalized_filters;

        foreach ($filters as $index => $filter) {
            if (!isset($filter['presentation']) || !is_array($filter['presentation'])) {
                $filters[$index]['presentation'] = $this->get_default_presentation_settings();
                continue;
            }

            $filters[$index]['presentation'] = array_merge(
                $this->get_default_presentation_settings(),
                $filter['presentation']
            );
        }

        return $filters;
    }

    private function get_taxonomy_options()
    {
        $options = array(
            'product_cat' => __('Product Categories', 'productmaster'),
        );

        $attributes = wc_get_attribute_taxonomies();
        foreach ($attributes as $attribute) {
            $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
            $options[$taxonomy] = sprintf(__('Attribute: %s', 'productmaster'), $attribute->attribute_label);
        }

        return $options;
    }

    private function get_supported_filter_types()
    {
        return array(
            'checkboxes' => __('Checkboxes', 'productmaster'),
            'image_boxes' => __('Image boxes', 'productmaster'),
            'drop_down_selectors' => __('Drop down selectors', 'productmaster'),
            'sliders' => __('Sliders', 'productmaster'),
            'search_fields' => __('Search Fields', 'productmaster'),
            'multi_filter' => __('Multi-Filter', 'productmaster'),
            'currently_selected_filters' => __('Currently Selected Filters', 'productmaster'),
            'reset_button' => __('Reset Products Button', 'productmaster'),
        );
    }

    private function render_single_filter_input($filter)
    {
        $param_key = 'pmf_' . $filter['id'];
        $selected_value = isset($_GET[$param_key]) ? wp_unslash($_GET[$param_key]) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $selected_values = $this->normalize_filter_values($selected_value);
        $terms = get_terms(
            array(
                'taxonomy' => $filter['taxonomy'],
                'hide_empty' => false,
            )
        );
        $presentation = isset($filter['presentation']) ? $filter['presentation'] : $this->get_default_presentation_settings();
        $style = $this->build_filter_inline_style($presentation);
        $instance_class = 'productmaster-filter-instance-' . sanitize_html_class($filter['id']);
        $wrapper_classes = 'productmaster-filter-group ' . $instance_class;
        if ('1' !== $presentation['use_theme_colors']) {
            $wrapper_classes .= ' productmaster-filter-custom';
        }

        echo $this->build_custom_css_output($filter['id'], $presentation['custom_css'] ?? '');

        echo '<fieldset class="' . esc_attr($wrapper_classes) . '" style="' . esc_attr($style) . '">';

        if ('checkboxes' === $filter['type']) {
            if ('enabled' === $presentation['hierarchical_visual'] && is_taxonomy_hierarchical($filter['taxonomy'])) {
                $this->render_hierarchical_checkbox_terms($terms, $filter, $param_key, $selected_values, $presentation);
            } else {
                foreach ($terms as $term) {
                    if (!empty($presentation['allowed_terms']) && !in_array($term->slug, $presentation['allowed_terms'], true)) {
                        continue;
                    }
                    $checked = in_array($term->slug, $selected_values, true);
                    $class = 'image_boxes' === $filter['type'] ? 'productmaster-image-box' : '';
                    echo '<label class="' . esc_attr($class) . '"><span class="productmaster-checkbox-icon">' . esc_html($presentation['checkbox_icon']) . '</span> <input type="checkbox" name="' . esc_attr($param_key) . '" value="' . esc_attr($term->slug) . '" ' . checked($checked, true, false) . ' /> ' . esc_html($term->name) . '</label>';
                }
            }
        } elseif ('image_boxes' === $filter['type']) {
            $this->render_image_box_filter($filter, $terms, $param_key, $selected_values, $presentation);
        } elseif ('drop_down_selectors' === $filter['type']) {
            $extra_class = 'enabled' === $presentation['hierarchical_visual'] ? 'productmaster-hierarchical-enabled' : '';
            $manual_hierarchy = isset($presentation['hierarchy_map']) ? (array) $presentation['hierarchy_map'] : array();
            if ('enabled' === $presentation['hierarchical_visual'] && !empty($manual_hierarchy)) {
                $this->render_hierarchical_dropdown_selects($manual_hierarchy, $terms, $param_key);
            } else {
                echo '<select class="' . esc_attr($extra_class) . '" name="' . esc_attr($param_key) . '"><option value="">' . esc_html__('Any', 'productmaster') . '</option>';
                foreach ($terms as $term) {
                    if (!empty($presentation['allowed_terms']) && !in_array($term->slug, $presentation['allowed_terms'], true)) {
                        continue;
                    }
                    echo '<option value="' . esc_attr($term->slug) . '" ' . selected((string) $selected_value, $term->slug, false) . '>' . esc_html($term->name) . '</option>';
                }
                echo '</select>';
            }
        } elseif ('sliders' === $filter['type']) {
            $min = isset($_GET['pmf_min_price']) ? wp_unslash($_GET['pmf_min_price']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $max = isset($_GET['pmf_max_price']) ? wp_unslash($_GET['pmf_max_price']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            echo '<label>' . esc_html__('Min Price', 'productmaster') . ' <input type="number" min="0" step="0.01" name="pmf_min_price" value="' . esc_attr((string) $min) . '" /></label>';
            echo '<label>' . esc_html__('Max Price', 'productmaster') . ' <input type="number" min="0" step="0.01" name="pmf_max_price" value="' . esc_attr((string) $max) . '" /></label>';
        } elseif ('search_fields' === $filter['type']) {
            echo '<input type="search" name="' . esc_attr($param_key) . '" value="' . esc_attr((string) $selected_value) . '" placeholder="' . esc_attr__('Search products', 'productmaster') . '" />';
        } elseif ('multi_filter' === $filter['type']) {
            $this->render_multi_filter_input($filter);
        } elseif ('currently_selected_filters' === $filter['type']) {
            $this->render_currently_selected_filters($filter);
        } elseif ('reset_button' === $filter['type']) {
            $reset_url = remove_query_arg($this->get_filter_query_arg_keys());
            echo '<a class="button" href="' . esc_url($reset_url) . '">' . esc_html__('Reset Products', 'productmaster') . '</a>';
        }

        echo '</fieldset>';
    }

    private function render_currently_selected_filters($filter)
    {
        $all_trackable_filters = $this->get_trackable_filters(isset($filter['id']) ? $filter['id'] : '');
        $all_trackable_ids = wp_list_pluck($all_trackable_filters, 'id');
        $configured_filter_ids = isset($filter['presentation']['selected_filter_ids']) ? (array) $filter['presentation']['selected_filter_ids'] : array();
        $selected_filter_ids = empty($configured_filter_ids) ? $all_trackable_ids : array_values(array_intersect($configured_filter_ids, $all_trackable_ids));

        $active_selection_rows = array();
        $clear_keys_all = array();

        foreach ($all_trackable_filters as $tracked_filter) {
            if (!in_array($tracked_filter['id'], $selected_filter_ids, true)) {
                continue;
            }

            $param_key = 'pmf_' . $tracked_filter['id'];
            $raw_value = isset($_GET[$param_key]) ? wp_unslash($_GET[$param_key]) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $selected_values = array();

            if ('sliders' === $tracked_filter['type']) {
                $min_price = isset($_GET['pmf_min_price']) ? wc_format_decimal(wp_unslash($_GET['pmf_min_price'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $max_price = isset($_GET['pmf_max_price']) ? wc_format_decimal(wp_unslash($_GET['pmf_max_price'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                if ('' !== $min_price || '' !== $max_price) {
                    $selected_values[] = sprintf(
                        /* translators: 1: min price, 2: max price */
                        __('Min %1$s, Max %2$s', 'productmaster'),
                        '' !== $min_price ? $min_price : __('Any', 'productmaster'),
                        '' !== $max_price ? $max_price : __('Any', 'productmaster')
                    );
                }
            } elseif (!empty($raw_value)) {
                $selected_values = $this->normalize_filter_values($raw_value);
            } elseif ('drop_down_selectors' === $tracked_filter['type']) {
                $parent = isset($_GET[$param_key . '_parent']) ? sanitize_title(wp_unslash($_GET[$param_key . '_parent'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $child = isset($_GET[$param_key . '_child']) ? sanitize_title(wp_unslash($_GET[$param_key . '_child'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $selected_values = array_filter(array($parent, $child));
            }

            if (empty($selected_values)) {
                continue;
            }

            if (!empty($tracked_filter['taxonomy'])) {
                $selected_values = $this->translate_term_slugs_for_filter($tracked_filter, $selected_values);
            } else {
                $selected_values = array_map('sanitize_text_field', $selected_values);
            }

            $clear_keys = $this->get_filter_query_arg_keys_by_filter($tracked_filter);
            $clear_keys_all = array_merge($clear_keys_all, $clear_keys);
            $active_selection_rows[] = array(
                'label' => $tracked_filter['label'],
                'values' => $selected_values,
                'clear_url' => esc_url(remove_query_arg($clear_keys)),
            );
        }

        if (empty($active_selection_rows)) {
            echo '<p>' . esc_html__('No filters selected.', 'productmaster') . '</p>';
            return;
        }

        echo '<ul class="productmaster-active-filter-list">';
        foreach ($active_selection_rows as $row) {
            echo '<li><span class="productmaster-active-filter-label">' . esc_html($row['label']) . ':</span> ' . esc_html(implode(', ', $row['values'])) . ' <a href="' . esc_url($row['clear_url']) . '">' . esc_html__('Clear', 'productmaster') . '</a></li>';
        }
        echo '</ul>';
        $clear_all_url = esc_url(remove_query_arg(array_values(array_unique($clear_keys_all))));
        echo '<p><a class="button button-secondary" href="' . esc_url($clear_all_url) . '">' . esc_html__('Clear all selected filters', 'productmaster') . '</a></p>';
    }

    private function get_filter_query_arg_keys()
    {
        $keys = array('pmf_min_price', 'pmf_max_price');
        foreach ($this->get_saved_taxonomy_filters() as $filter) {
            if (isset($filter['id'])) {
                $keys[] = 'pmf_' . $filter['id'];
                $keys[] = 'pmf_' . $filter['id'] . '_parent';
                $keys[] = 'pmf_' . $filter['id'] . '_child';
            }
        }

        return $keys;
    }

    private function get_filter_query_arg_keys_by_filter($filter)
    {
        if (empty($filter['id'])) {
            return array();
        }

        if (isset($filter['type']) && 'sliders' === $filter['type']) {
            return array('pmf_min_price', 'pmf_max_price');
        }

        $param_key = 'pmf_' . $filter['id'];
        return array($param_key, $param_key . '_parent', $param_key . '_child');
    }

    private function get_trackable_filters($current_filter_id)
    {
        $trackable_filters = array();
        foreach ($this->get_saved_taxonomy_filters() as $saved_filter) {
            if (empty($saved_filter['id']) || empty($saved_filter['label'])) {
                continue;
            }

            if ($saved_filter['id'] === $current_filter_id) {
                continue;
            }

            if (in_array($saved_filter['type'], array('currently_selected_filters', 'reset_button'), true)) {
                continue;
            }

            $trackable_filters[] = $saved_filter;
        }

        return $trackable_filters;
    }

    private function translate_term_slugs_for_filter($filter, $term_slugs)
    {
        if (empty($filter['taxonomy'])) {
            return array_values(array_map('sanitize_text_field', (array) $term_slugs));
        }

        $term_names = array();
        foreach ((array) $term_slugs as $slug) {
            $slug = sanitize_title((string) $slug);
            if ('' === $slug) {
                continue;
            }

            $term = get_term_by('slug', $slug, $filter['taxonomy']);
            if ($term && !is_wp_error($term) && !empty($term->name)) {
                $term_names[] = $term->name;
                continue;
            }

            $term_names[] = $slug;
        }

        return $term_names;
    }

    private function normalize_filter_values($raw_value)
    {
        if (is_array($raw_value)) {
            return array_values(array_filter(array_map('sanitize_title', $raw_value)));
        }

        if (!is_string($raw_value) || '' === trim($raw_value)) {
            return array();
        }

        if (false !== strpos($raw_value, ',')) {
            $parts = explode(',', $raw_value);
            return array_values(array_filter(array_map('sanitize_title', $parts)));
        }

        return array(sanitize_title($raw_value));
    }

    private function normalize_multi_filter_values($raw_value)
    {
        if (is_array($raw_value)) {
            return array_values(array_filter(array_map('sanitize_text_field', $raw_value)));
        }

        if (!is_string($raw_value) || '' === trim($raw_value)) {
            return array();
        }

        return array_values(array_filter(array_map('sanitize_text_field', explode(',', $raw_value))));
    }

    private function get_filter_types_without_taxonomy()
    {
        return array(
            'multi_filter' => true,
            'currently_selected_filters' => true,
            'reset_button' => true,
        );
    }

    private function render_filter_presentation_editor($filter)
    {
        $presentation = isset($filter['presentation']) ? $filter['presentation'] : $this->get_default_presentation_settings();
        $manual_hierarchy_terms = $this->get_manual_hierarchy_allowed_terms(
            array(
                'presentation' => $presentation,
            )
        );
        $effective_allowed_terms = !empty($manual_hierarchy_terms) ? $manual_hierarchy_terms : $presentation['allowed_terms'];
        $taxonomy_terms = array();
        if (!empty($filter['taxonomy'])) {
            $taxonomy_terms = get_terms(
                array(
                    'taxonomy' => $filter['taxonomy'],
                    'hide_empty' => false,
                )
            );
        }
        $is_currently_selected_filter = isset($filter['type']) && 'currently_selected_filters' === $filter['type'];
        $is_reset_button_filter = isset($filter['type']) && 'reset_button' === $filter['type'];
        $has_parent_terms = false;
        foreach ($taxonomy_terms as $term) {
            if ((int) $term->parent === 0) {
                $has_parent_terms = true;
                break;
            }
        }

        echo '<section class="productmaster-card">';
        echo '<h2>' . sprintf(esc_html__('Edit Filter: %s', 'productmaster'), esc_html($filter['label'])) . '</h2>';
        echo '<form method="post">';
        wp_nonce_field('productmaster_save_tax_filter', 'productmaster_tax_filter_nonce');
        echo '<input type="hidden" name="productmaster_action" value="update_filter_presentation" />';
        echo '<input type="hidden" name="filter_id" value="' . esc_attr($filter['id']) . '" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="pm_display_text">' . esc_html__('Display text', 'productmaster') . '</label></th><td><input id="pm_display_text" name="display_text" type="text" class="regular-text" value="' . esc_attr($presentation['display_text']) . '" /></td></tr>';
        echo '<tr><th><label for="pm_font_size">' . esc_html__('Font size (px)', 'productmaster') . '</label></th><td><input id="pm_font_size" name="font_size" type="number" min="10" max="36" value="' . esc_attr((string) $presentation['font_size']) . '" /></td></tr>';
        echo '<tr><th><label for="pm_use_theme_colors">' . esc_html__('Use default theme colors', 'productmaster') . '</label></th><td><input id="pm_use_theme_colors" name="use_theme_colors" type="checkbox" value="1" ' . checked('1', $presentation['use_theme_colors'], false) . ' /></td></tr>';
        echo '<tr><th><label for="pm_bg_color">' . esc_html__('Background color', 'productmaster') . '</label></th><td><input id="pm_bg_color" name="bg_color" type="text" value="' . esc_attr($presentation['bg_color']) . '" /></td></tr>';
        echo '<tr><th><label for="pm_text_color">' . esc_html__('Text color', 'productmaster') . '</label></th><td><input id="pm_text_color" name="text_color" type="text" value="' . esc_attr($presentation['text_color']) . '" /></td></tr>';
        echo '<tr><th><label for="pm_accent_color">' . esc_html__('Accent color', 'productmaster') . '</label></th><td><input id="pm_accent_color" name="accent_color" type="text" value="' . esc_attr($presentation['accent_color']) . '" /></td></tr>';
        if ($is_currently_selected_filter || (isset($filter['type']) && 'multi_filter' === $filter['type'])) {
            $trackable_filters = $this->get_trackable_filters(isset($filter['id']) ? $filter['id'] : '');
            if (isset($filter['type']) && 'multi_filter' === $filter['type']) {
                $trackable_filters = array_values(
                    array_filter(
                        $trackable_filters,
                        function ($trackable_filter) {
                            return isset($trackable_filter['type']) && 'image_boxes' === $trackable_filter['type'];
                        }
                    )
                );
            }
            echo '<tr><th><label for="pm_selected_filter_ids">' . esc_html__('Source filters to show', 'productmaster') . '</label></th><td><div id="pm_selected_filter_ids" class="productmaster-term-toggle-list">';
            if (empty($trackable_filters)) {
                echo '<p>' . esc_html__('No other filters are available yet. Create category/attribute filters first.', 'productmaster') . '</p>';
            } else {
                foreach ($trackable_filters as $trackable_filter) {
                    $is_selected = in_array($trackable_filter['id'], (array) $presentation['selected_filter_ids'], true);
                    echo '<label class="productmaster-term-toggle">';
                    echo '<input type="checkbox" name="selected_filter_ids[]" value="' . esc_attr($trackable_filter['id']) . '" ' . checked($is_selected, true, false) . ' />';
                    echo '<span>' . esc_html($trackable_filter['label']) . '</span>';
                    echo '</label>';
                }
            }
            echo '</div><p class="description">' . esc_html__('Choose which labeled filters this block will track and display for the current view. Leave all unchecked to include all eligible filters.', 'productmaster') . '</p></td></tr>';
        }
        if (!$is_currently_selected_filter && !$is_reset_button_filter) {
            echo '<tr><th><label for="pm_hierarchical_visual">' . esc_html__('Hierarchical', 'productmaster') . '</label></th><td><select id="pm_hierarchical_visual" name="hierarchical_visual"><option value="disabled" ' . selected('disabled', $presentation['hierarchical_visual'], false) . '>' . esc_html__('Disabled', 'productmaster') . '</option><option value="enabled" ' . selected('enabled', $presentation['hierarchical_visual'], false) . '>' . esc_html__('Enabled', 'productmaster') . '</option></select></td></tr>';
            echo '<tr><th><label for="pm_value_match">' . esc_html__('Value matching (within filter)', 'productmaster') . '</label></th><td><select id="pm_value_match" name="value_match"><option value="or" ' . selected('or', $presentation['value_match'], false) . '>' . esc_html__('OR (default)', 'productmaster') . '</option><option value="and" ' . selected('and', $presentation['value_match'], false) . '>' . esc_html__('AND', 'productmaster') . '</option></select><p class="description">' . esc_html__('This controls how multiple values inside this single filter are combined. Different filters are always combined with AND.', 'productmaster') . '</p></td></tr>';
            echo '<tr><th><label for="pm_hierarchy_map_text">' . esc_html__('Manual Hierarchy Map', 'productmaster') . '</label></th><td><textarea id="pm_hierarchy_map_text" name="hierarchy_map_text" rows="6" class="large-text code">' . esc_textarea($presentation['hierarchy_map_text']) . '</textarea><p class="description">';
            echo esc_html__('Use format: parent_slug:child_slug_1,child_slug_2 (one parent per line).', 'productmaster') . ' ';
            if (!$has_parent_terms) {
                echo esc_html__('No parent terms detected in this taxonomy. Use this map to define parent/child relationships.', 'productmaster');
            } else {
                echo esc_html__('When set, only mapped terms are shown and Included taxonomy terms are ignored.', 'productmaster');
            }
            echo '</p></td></tr>';
            echo '<tr><th><label for="pm_checkbox_icon">' . esc_html__('Checkbox icon', 'productmaster') . '</label></th><td><input id="pm_checkbox_icon" name="checkbox_icon" type="text" value="' . esc_attr($presentation['checkbox_icon']) . '" /></td></tr>';
            echo '<tr><th>' . esc_html__('Image box size (px)', 'productmaster') . '</th><td><label for="pm_image_box_width">' . esc_html__('Width', 'productmaster') . '</label> <input id="pm_image_box_width" name="image_box_width" type="number" min="16" max="240" value="' . esc_attr((string) $presentation['image_box_width']) . '" /> <label for="pm_image_box_height">' . esc_html__('Height', 'productmaster') . '</label> <input id="pm_image_box_height" name="image_box_height" type="number" min="16" max="240" value="' . esc_attr((string) $presentation['image_box_height']) . '" /></td></tr>';
            echo '<tr><th>' . esc_html__('Child image size (px)', 'productmaster') . '</th><td><label for="pm_child_image_box_width">' . esc_html__('Width', 'productmaster') . '</label> <input id="pm_child_image_box_width" name="child_image_box_width" type="number" min="16" max="240" value="' . esc_attr((string) $presentation['child_image_box_width']) . '" /> <label for="pm_child_image_box_height">' . esc_html__('Height', 'productmaster') . '</label> <input id="pm_child_image_box_height" name="child_image_box_height" type="number" min="16" max="240" value="' . esc_attr((string) $presentation['child_image_box_height']) . '" /></td></tr>';
            echo '<tr><th><label for="pm_allowed_terms">' . esc_html__('Included taxonomy terms', 'productmaster') . '</label></th><td><div id="pm_allowed_terms" class="productmaster-term-toggle-textarea"><div class="productmaster-term-toggle-list">';
            usort(
                $taxonomy_terms,
                function ($a, $b) use ($effective_allowed_terms) {
                    $a_selected = in_array($a->slug, $effective_allowed_terms, true);
                    $b_selected = in_array($b->slug, $effective_allowed_terms, true);
                    if ($a_selected !== $b_selected) {
                        return $a_selected ? -1 : 1;
                    }

                    return strcmp($a->name, $b->name);
                }
            );

            $is_image_box_filter = isset($filter['type']) && 'image_boxes' === $filter['type'];
            echo '<div class="productmaster-term-toggle-header">';
            echo '<span>' . esc_html__('Value', 'productmaster') . '</span>';
            echo '<span>' . esc_html__('Slug', 'productmaster') . '</span>';
            if ($is_image_box_filter) {
                echo '<span>' . esc_html__('Image', 'productmaster') . '</span>';
            }
            echo '</div>';
            foreach ($taxonomy_terms as $term) {
                $selected = in_array($term->slug, $effective_allowed_terms, true);
                $term_image = isset($presentation['term_images'][$term->slug]) ? $presentation['term_images'][$term->slug] : '';
                echo '<div class="productmaster-term-toggle-row">';
                echo '<label class="productmaster-term-toggle">';
                echo '<input type="checkbox" name="allowed_terms[]" value="' . esc_attr($term->slug) . '" ' . checked($selected, true, false) . ' />';
                echo '<span>' . esc_html($term->name) . '</span>';
                echo '</label>';
                echo '<code class="productmaster-term-slug">' . esc_html($term->slug) . '</code>';
                if ($is_image_box_filter) {
                    echo '<div class="productmaster-term-image-control">';
                    echo '<input type="hidden" class="productmaster-term-image-input" name="term_images[' . esc_attr($term->slug) . ']" value="' . esc_attr($term_image) . '" />';
                    echo '<button type="button" class="button button-small productmaster-select-image">' . esc_html__('Select image', 'productmaster') . '</button>';
                    echo '<span class="productmaster-image-selected-label">' . esc_html(!empty($term_image) ? __('Image selected', 'productmaster') : __('No image', 'productmaster')) . '</span>';
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '</div></div><p class="description">' . esc_html__('Toggle terms on/off to control exactly which values are available for this filter. Leave all off to include all terms.', 'productmaster') . '</p></td></tr>';
        }
        echo '<tr><th><label for="pm_custom_css">' . esc_html__('Custom CSS', 'productmaster') . '</label></th><td><textarea id="pm_custom_css" name="custom_css" rows="8" class="large-text code">' . esc_textarea($presentation['custom_css']) . '</textarea><p class="description">' . esc_html__('Use CSS declarations or full CSS. For full CSS selectors, use {{WRAPPER}} to target this filter instance.', 'productmaster') . '</p></td></tr>';
        echo '</tbody></table>';
        submit_button(__('Save Presentation', 'productmaster'));
        echo '</form>';

        echo '<h3>' . esc_html__('Preview', 'productmaster') . '</h3>';
        $preview_filter = $filter;
        $preview_filter['presentation'] = $presentation;
        echo '<div class="productmaster-filter-preview">';
        $this->render_single_filter_input($preview_filter);
        echo '</div>';
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var cssInput=document.getElementById("pm_custom_css");if(!cssInput){return;}var styleId="productmaster-preview-custom-css";var styleTag=document.getElementById(styleId);if(!styleTag){styleTag=document.createElement("style");styleTag.id=styleId;document.head.appendChild(styleTag);}var wrapper=".productmaster-filter-preview .productmaster-filter-instance-' . esc_js($filter['id']) . '";var applyCss=function(){var value=cssInput.value||"";if(value.indexOf("{")===-1){styleTag.textContent=wrapper+"{"+value+"}";return;}styleTag.textContent=value.replace(/\\{\\{WRAPPER\\}\\}/g,wrapper);};cssInput.addEventListener("input",applyCss);applyCss();});</script>';
        echo '</section>';
    }

    private function get_editing_filter($filters)
    {
        $edit_filter = isset($_GET['edit_filter']) ? sanitize_text_field(wp_unslash($_GET['edit_filter'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (empty($edit_filter)) {
            return null;
        }

        foreach ($filters as $filter) {
            if (isset($filter['id']) && $filter['id'] === $edit_filter) {
                return $filter;
            }
        }

        return null;
    }

    private function sanitize_presentation_settings($data)
    {
        $defaults = $this->get_default_presentation_settings();
        $allowed_terms = isset($data['allowed_terms']) && is_array($data['allowed_terms']) ? array_map('sanitize_title', wp_unslash($data['allowed_terms'])) : array();
        $hierarchy_map_text = isset($data['hierarchy_map_text']) ? sanitize_textarea_field(wp_unslash($data['hierarchy_map_text'])) : $defaults['hierarchy_map_text'];
        $hierarchy_map = $this->parse_hierarchy_map($hierarchy_map_text);
        $manual_terms = $this->extract_hierarchy_terms($hierarchy_map);

        if (!empty($manual_terms)) {
            $allowed_terms = $manual_terms;
        }

        return array(
            'display_text' => isset($data['display_text']) ? sanitize_text_field(wp_unslash($data['display_text'])) : $defaults['display_text'],
            'font_size' => isset($data['font_size']) ? max(10, min(36, absint($data['font_size']))) : $defaults['font_size'],
            'use_theme_colors' => isset($data['use_theme_colors']) ? '1' : '0',
            'bg_color' => isset($data['bg_color']) ? sanitize_hex_color(wp_unslash($data['bg_color'])) : $defaults['bg_color'],
            'text_color' => isset($data['text_color']) ? sanitize_hex_color(wp_unslash($data['text_color'])) : $defaults['text_color'],
            'accent_color' => isset($data['accent_color']) ? sanitize_hex_color(wp_unslash($data['accent_color'])) : $defaults['accent_color'],
            'hierarchical_visual' => isset($data['hierarchical_visual']) ? sanitize_key(wp_unslash($data['hierarchical_visual'])) : $defaults['hierarchical_visual'],
            'value_match' => (isset($data['value_match']) && 'and' === sanitize_key(wp_unslash($data['value_match']))) ? 'and' : 'or',
            'hierarchy_map_text' => $hierarchy_map_text,
            'hierarchy_map' => $hierarchy_map,
            'checkbox_icon' => isset($data['checkbox_icon']) ? sanitize_text_field(wp_unslash($data['checkbox_icon'])) : $defaults['checkbox_icon'],
            'image_box_width' => isset($data['image_box_width']) ? max(16, min(240, absint($data['image_box_width']))) : $defaults['image_box_width'],
            'image_box_height' => isset($data['image_box_height']) ? max(16, min(240, absint($data['image_box_height']))) : $defaults['image_box_height'],
            'child_image_box_width' => isset($data['child_image_box_width']) ? max(16, min(240, absint($data['child_image_box_width']))) : $defaults['child_image_box_width'],
            'child_image_box_height' => isset($data['child_image_box_height']) ? max(16, min(240, absint($data['child_image_box_height']))) : $defaults['child_image_box_height'],
            'allowed_terms' => $allowed_terms,
            'selected_filter_ids' => isset($data['selected_filter_ids']) && is_array($data['selected_filter_ids']) ? array_values(array_unique(array_map('sanitize_key', wp_unslash($data['selected_filter_ids'])))) : $defaults['selected_filter_ids'],
            'term_images' => isset($data['term_images']) && is_array($data['term_images']) ? $this->sanitize_term_images(wp_unslash($data['term_images'])) : $defaults['term_images'],
            'custom_css' => isset($data['custom_css']) ? wp_unslash($data['custom_css']) : $defaults['custom_css'],
        );
    }

    private function get_default_presentation_settings()
    {
        return array(
            'display_text' => __('Filter', 'productmaster'),
            'font_size' => 14,
            'use_theme_colors' => '1',
            'bg_color' => '#ffffff',
            'text_color' => '#1d2327',
            'accent_color' => '#2271b1',
            'hierarchical_visual' => 'disabled',
            'value_match' => 'or',
            'hierarchy_map_text' => '',
            'hierarchy_map' => array(),
            'checkbox_icon' => '☐',
            'image_box_width' => 40,
            'image_box_height' => 40,
            'child_image_box_width' => 54,
            'child_image_box_height' => 40,
            'allowed_terms' => array(),
            'selected_filter_ids' => array(),
            'term_images' => array(),
            'custom_css' => '',
        );
    }

    private function build_filter_inline_style($presentation)
    {
        if ('1' === $presentation['use_theme_colors']) {
            return '';
        }

        $style_parts = array();
        if (!empty($presentation['bg_color'])) {
            $style_parts[] = 'background:' . $presentation['bg_color'];
        }
        if (!empty($presentation['text_color'])) {
            $style_parts[] = 'color:' . $presentation['text_color'];
        }
        if (!empty($presentation['accent_color'])) {
            $style_parts[] = 'border-color:' . $presentation['accent_color'];
        }

        return implode(';', $style_parts);
    }

    private function generate_filter_id_from_label($label)
    {
        return sanitize_key(sanitize_title($label));
    }

    private function label_exists($label, $filters)
    {
        foreach ($filters as $filter) {
            if (!isset($filter['label'])) {
                continue;
            }

            if (0 === strcasecmp($filter['label'], $label)) {
                return true;
            }
        }

        return false;
    }

    private function find_filter_id_by_label($label)
    {
        foreach ($this->get_saved_taxonomy_filters() as $filter) {
            if (!isset($filter['label'], $filter['id'])) {
                continue;
            }

            if (0 === strcasecmp($filter['label'], $label)) {
                return $filter['id'];
            }
        }

        return '';
    }

    private function render_hierarchical_checkbox_terms($terms, $filter, $param_key, $selected_value, $presentation)
    {
        $terms_by_parent = array();
        $terms_by_slug = array();
        $has_native_parent_relationship = false;
        $use_manual_hierarchy = !empty($presentation['hierarchy_map']) && is_array($presentation['hierarchy_map']);

        foreach ($terms as $term) {
            if (!$use_manual_hierarchy && !empty($presentation['allowed_terms']) && !in_array($term->slug, $presentation['allowed_terms'], true)) {
                continue;
            }

            $terms_by_slug[$term->slug] = $term;
            if ((int) $term->parent > 0) {
                $has_native_parent_relationship = true;
            }

            $parent_id = (int) $term->parent;
            if (!isset($terms_by_parent[$parent_id])) {
                $terms_by_parent[$parent_id] = array();
            }
            $terms_by_parent[$parent_id][] = $term;
        }

        if ($use_manual_hierarchy) {
            $terms_by_parent = $this->build_terms_by_parent_from_manual_map($presentation['hierarchy_map'], $terms_by_slug);
        } elseif (!$has_native_parent_relationship) {
            // Leave as flat root list when no hierarchy data exists.
            $terms_by_parent = array(0 => array_values($terms_by_slug));
        }

        $this->render_hierarchical_term_nodes($terms_by_parent, 0, $filter, $param_key, $selected_value, $presentation);
    }

    private function render_hierarchical_term_nodes($terms_by_parent, $parent_id, $filter, $param_key, $selected_value, $presentation)
    {
        if (empty($terms_by_parent[$parent_id])) {
            return;
        }

        $class = 'image_boxes' === $filter['type'] ? 'productmaster-image-box' : '';

        foreach ($terms_by_parent[$parent_id] as $term) {
            $term_id = (int) $term->term_id;
            $term_checked = is_array($selected_value) && in_array($term->slug, $selected_value, true);
            $has_children = !empty($terms_by_parent[$term_id]);
            if (!$term_checked && $has_children && $this->is_branch_fully_selected($terms_by_parent, $term_id, $selected_value)) {
                $term_checked = true;
            }
            $branch_has_selected_child = $this->branch_has_selected_value($terms_by_parent, $term_id, $selected_value);
            $open_attr = ($term_checked || $branch_has_selected_child) ? ' open' : '';

            echo '<div class="productmaster-hierarchical-parent">';
            if ($has_children) {
                echo '<details class="productmaster-hierarchical-children"' . $open_attr . '>';
                echo '<summary class="productmaster-hierarchical-summary"><span class="productmaster-hierarchical-marker" aria-hidden="true">▸</span><label class="' . esc_attr($class) . '"><span class="productmaster-checkbox-icon">' . esc_html($presentation['checkbox_icon']) . '</span> <input type="checkbox" name="' . esc_attr($param_key) . '" value="' . esc_attr($term->slug) . '" ' . checked($term_checked, true, false) . ' /> ' . esc_html($term->name) . '</label></summary>';
                echo '<div class="productmaster-hierarchical-nested">';
                $this->render_hierarchical_term_nodes($terms_by_parent, $term_id, $filter, $param_key, $selected_value, $presentation);
                echo '</div>';
                echo '</details>';
            } else {
                echo '<label class="' . esc_attr($class) . '"><span class="productmaster-checkbox-icon">' . esc_html($presentation['checkbox_icon']) . '</span> <input type="checkbox" name="' . esc_attr($param_key) . '" value="' . esc_attr($term->slug) . '" ' . checked($term_checked, true, false) . ' /> ' . esc_html($term->name) . '</label>';
            }
            echo '</div>';
        }
    }

    private function branch_has_selected_value($terms_by_parent, $parent_id, $selected_value)
    {
        if (!is_array($selected_value) || empty($terms_by_parent[$parent_id])) {
            return false;
        }

        foreach ($terms_by_parent[$parent_id] as $child_term) {
            if (in_array($child_term->slug, $selected_value, true)) {
                return true;
            }

            if ($this->branch_has_selected_value($terms_by_parent, (int) $child_term->term_id, $selected_value)) {
                return true;
            }
        }

        return false;
    }

    private function is_branch_fully_selected($terms_by_parent, $parent_id, $selected_value)
    {
        if (!is_array($selected_value) || empty($terms_by_parent[$parent_id])) {
            return false;
        }

        foreach ($terms_by_parent[$parent_id] as $child_term) {
            if (!in_array($child_term->slug, $selected_value, true)) {
                return false;
            }

            if (!$this->is_branch_fully_selected($terms_by_parent, (int) $child_term->term_id, $selected_value) && !empty($terms_by_parent[(int) $child_term->term_id])) {
                return false;
            }
        }

        return true;
    }

    private function build_custom_css_output($filter_id, $custom_css)
    {
        $custom_css = trim((string) $custom_css);
        if ('' === $custom_css) {
            return '';
        }

        $wrapper = '.productmaster-filter-instance-' . sanitize_html_class($filter_id);

        if (false === strpos($custom_css, '{')) {
            return '<style>' . $wrapper . '{' . wp_strip_all_tags($custom_css) . '}</style>';
        }

        $scoped_css = str_replace('{{WRAPPER}}', $wrapper, $custom_css);
        return '<style>' . wp_strip_all_tags($scoped_css) . '</style>';
    }

    private function parse_hierarchy_map($raw_text)
    {
        $map = array();
        $lines = preg_split('/\r\n|\r|\n/', (string) $raw_text);

        foreach ($lines as $line) {
            $line = trim($line);
            if ('' === $line || false === strpos($line, ':')) {
                continue;
            }

            list($parent_slug, $children_part) = array_map('trim', explode(':', $line, 2));
            $parent_slug = sanitize_title($parent_slug);
            if ('' === $parent_slug) {
                continue;
            }

            $children = array_filter(array_map('sanitize_title', array_map('trim', explode(',', $children_part))));
            if (empty($children)) {
                continue;
            }

            $map[$parent_slug] = array_values(array_unique($children));
        }

        return $map;
    }

    private function build_terms_by_parent_from_manual_map($hierarchy_map, $terms_by_slug)
    {
        $terms_by_parent = array(0 => array());

        foreach ($hierarchy_map as $parent_slug => $child_slugs) {
            if (empty($terms_by_slug[$parent_slug])) {
                continue;
            }

            $parent_term = $terms_by_slug[$parent_slug];
            $parent_id = (int) $parent_term->term_id;

            $terms_by_parent[0][] = $parent_term;
            if (!isset($terms_by_parent[$parent_id])) {
                $terms_by_parent[$parent_id] = array();
            }

            foreach ((array) $child_slugs as $child_slug) {
                if (empty($terms_by_slug[$child_slug])) {
                    continue;
                }

                $child_term = $terms_by_slug[$child_slug];
                $terms_by_parent[$parent_id][] = $child_term;
            }
        }

        return $terms_by_parent;
    }

    private function get_manual_hierarchy_allowed_terms($filter)
    {
        if (empty($filter['presentation']['hierarchy_map']) || !is_array($filter['presentation']['hierarchy_map'])) {
            return array();
        }

        return $this->extract_hierarchy_terms($filter['presentation']['hierarchy_map']);
    }

    private function render_hierarchical_dropdown_selects($manual_hierarchy, $terms, $param_key)
    {
        $selected_parent = isset($_GET[$param_key . '_parent']) ? sanitize_title(wp_unslash($_GET[$param_key . '_parent'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $selected_child = isset($_GET[$param_key . '_child']) ? sanitize_title(wp_unslash($_GET[$param_key . '_child'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $term_labels = array();
        foreach ($terms as $term) {
            $term_labels[$term->slug] = $term->name;
        }

        echo '<div class="productmaster-hierarchical-dropdowns">';
        echo '<label>' . esc_html__('Parent', 'productmaster') . ' <select name="' . esc_attr($param_key . '_parent') . '"><option value="">' . esc_html__('Any parent', 'productmaster') . '</option>';
        foreach ($manual_hierarchy as $parent_slug => $children) {
            if (!isset($term_labels[$parent_slug])) {
                continue;
            }

            echo '<option value="' . esc_attr($parent_slug) . '" ' . selected($selected_parent, $parent_slug, false) . '>' . esc_html($term_labels[$parent_slug]) . '</option>';
        }
        echo '</select></label>';

        echo '<label>' . esc_html__('Child', 'productmaster') . ' <select name="' . esc_attr($param_key . '_child') . '"><option value="">' . esc_html__('Any child', 'productmaster') . '</option>';
        foreach ($manual_hierarchy as $parent_slug => $children) {
            if (!isset($term_labels[$parent_slug])) {
                continue;
            }

            echo '<optgroup label="' . esc_attr($term_labels[$parent_slug]) . '">';
            foreach ((array) $children as $child_slug) {
                if (!isset($term_labels[$child_slug])) {
                    continue;
                }

                echo '<option value="' . esc_attr($child_slug) . '" ' . selected($selected_child, $child_slug, false) . '>' . esc_html($term_labels[$child_slug]) . '</option>';
            }
            echo '</optgroup>';
        }
        echo '</select></label>';
        echo '</div>';
    }

    private function extract_hierarchy_terms($hierarchy_map)
    {
        $terms = array();
        foreach ((array) $hierarchy_map as $parent => $children) {
            $terms[] = sanitize_title((string) $parent);
            foreach ((array) $children as $child) {
                $terms[] = sanitize_title((string) $child);
            }
        }

        return array_values(array_unique(array_filter($terms)));
    }

    private function sanitize_term_images($term_images)
    {
        $clean = array();

        foreach ((array) $term_images as $slug => $url) {
            $clean_slug = sanitize_title((string) $slug);
            $clean_url = esc_url_raw((string) $url);

            if ('' === $clean_slug) {
                continue;
            }

            $clean[$clean_slug] = $clean_url;
        }

        return $clean;
    }

    private function resolve_term_image_url($term, $presentation)
    {
        if (!is_object($term) || empty($term->slug)) {
            return '';
        }

        $slug = (string) $term->slug;
        if (!empty($presentation['term_images'][$slug])) {
            $term_image_url = (string) $presentation['term_images'][$slug];
            if (!$this->is_local_media_file_missing($term_image_url)) {
                return esc_url($term_image_url);
            }
        }

        foreach ($this->get_swatch_image_meta_keys() as $meta_key) {
            $swatch_image = get_term_meta((int) $term->term_id, $meta_key, true);
            if ('' === $swatch_image || null === $swatch_image) {
                continue;
            }

            $swatch_image_url = '';
            if (is_numeric($swatch_image)) {
                $swatch_image_url = wp_get_attachment_url((int) $swatch_image);
            } elseif (is_array($swatch_image)) {
                if (!empty($swatch_image['id']) && is_numeric($swatch_image['id'])) {
                    $swatch_image_url = wp_get_attachment_url((int) $swatch_image['id']);
                } elseif (!empty($swatch_image['image']) && is_string($swatch_image['image'])) {
                    $swatch_image_url = $swatch_image['image'];
                } elseif (!empty($swatch_image['url']) && is_string($swatch_image['url'])) {
                    $swatch_image_url = $swatch_image['url'];
                } elseif (!empty($swatch_image['src']) && is_string($swatch_image['src'])) {
                    $swatch_image_url = $swatch_image['src'];
                }
            } elseif (is_string($swatch_image)) {
                $swatch_image_url = $swatch_image;
            }

            if (!is_string($swatch_image_url) || '' === $swatch_image_url) {
                continue;
            }

            if ($this->is_local_media_file_missing($swatch_image_url)) {
                continue;
            }

            return esc_url($swatch_image_url);
        }

        return '';
    }

    private function get_swatch_image_meta_keys()
    {
        $default_keys = array(
            'smart-swatches-framework--src',
            'smart_swatches_framework_src',
            'swatch_image',
            'swatch_image_id',
            'product_attribute_image',
            'product_attribute_image_id',
            'thumbnail_id',
        );

        $keys = apply_filters('productmaster_swatch_image_meta_keys', $default_keys);
        return is_array($keys) ? array_values(array_unique(array_filter($keys))) : $default_keys;
    }

    private function is_local_media_file_missing($url)
    {
        $url = trim((string) $url);
        if ('' === $url) {
            return true;
        }

        $parsed = wp_parse_url($url);
        if (empty($parsed['host']) || empty($parsed['path'])) {
            return false;
        }

        $site_url = wp_parse_url(home_url());
        if (empty($site_url['host']) || $parsed['host'] !== $site_url['host']) {
            return false;
        }

        $absolute_path = ABSPATH . ltrim($parsed['path'], '/');
        return !file_exists($absolute_path);
    }

    private function render_image_box_filter($filter, $terms, $param_key, $selected_value, $presentation)
    {
        $selected_values = $this->normalize_filter_values($selected_value);
        $term_by_slug = array();
        foreach ($terms as $term) {
            $term_by_slug[$term->slug] = $term;
        }

        $manual_hierarchy = isset($presentation['hierarchy_map']) ? (array) $presentation['hierarchy_map'] : array();
        $styles = '--pm-image-box-width:' . (int) $presentation['image_box_width'] . 'px;--pm-image-box-height:' . (int) $presentation['image_box_height'] . 'px;--pm-image-child-box-width:' . (int) $presentation['child_image_box_width'] . 'px;--pm-image-child-box-height:' . (int) $presentation['child_image_box_height'] . 'px;';
        echo '<div class="productmaster-image-box-grid" style="' . esc_attr($styles) . '">';

        if (!empty($manual_hierarchy)) {
            foreach ($manual_hierarchy as $parent_slug => $child_slugs) {
                if (!isset($term_by_slug[$parent_slug])) {
                    continue;
                }

                $parent_term = $term_by_slug[$parent_slug];
                $parent_checked = in_array($parent_slug, $selected_values, true);
                if (!$parent_checked && !empty($child_slugs)) {
                    $child_slugs = array_values(array_filter((array) $child_slugs));
                    $selected_child_slugs = array_intersect($child_slugs, $selected_values);
                    $parent_checked = !empty($child_slugs) && count($selected_child_slugs) === count($child_slugs);
                }
                $parent_image = $this->resolve_term_image_url($parent_term, $presentation);

                echo '<div class="productmaster-image-parent">';
                echo '<label class="productmaster-image-parent-label">';
                echo '<input type="checkbox" class="productmaster-image-parent-checkbox" name="' . esc_attr($param_key) . '" value="' . esc_attr($parent_slug) . '" ' . checked($parent_checked, true, false) . ' />';
                if (!empty($parent_image)) {
                    echo '<img src="' . esc_url($parent_image) . '" alt="' . esc_attr($parent_term->name) . '" class="productmaster-image-thumb" />';
                } else {
                    echo '<span class="productmaster-image-thumb productmaster-image-fallback">' . esc_html(substr($parent_term->name, 0, 1)) . '</span>';
                }
                echo '</label>';

                if (!empty($child_slugs)) {
                    echo '<div class="productmaster-image-children-menu" data-parent-slug="' . esc_attr($parent_slug) . '">';
                    echo '<label class="productmaster-image-children-header"><input type="checkbox" class="productmaster-image-children-toggle" value="' . esc_attr($parent_slug) . '" /> ' . esc_html($parent_term->name) . '</label>';
                    echo '<div class="productmaster-image-children-grid">';
                    foreach ((array) $child_slugs as $child_slug) {
                        if (!isset($term_by_slug[$child_slug])) {
                            continue;
                        }
                        $child_term = $term_by_slug[$child_slug];
                        $child_checked = in_array($child_slug, $selected_values, true) || in_array($parent_slug, $selected_values, true);
                        $child_image = $this->resolve_term_image_url($child_term, $presentation);
                        echo '<label class="productmaster-image-child-label">';
                        echo '<input type="checkbox" class="productmaster-image-child-checkbox" name="' . esc_attr($param_key) . '" value="' . esc_attr($child_slug) . '" ' . checked($child_checked, true, false) . ' />';
                        echo '<span class="productmaster-image-child-tag">' . esc_html($child_term->name) . '</span>';
                        if (!empty($child_image)) {
                            echo '<img src="' . esc_url($child_image) . '" alt="' . esc_attr($child_term->name) . '" class="productmaster-image-thumb" />';
                        } else {
                            echo '<span class="productmaster-image-thumb productmaster-image-fallback">' . esc_html(substr($child_term->name, 0, 1)) . '</span>';
                        }
                        echo '</label>';
                    }
                    echo '</div>';
                    echo '</div>';
                }

                echo '</div>';
            }
        } else {
            foreach ($terms as $term) {
                if (!empty($presentation['allowed_terms']) && !in_array($term->slug, $presentation['allowed_terms'], true)) {
                    continue;
                }
                $checked = in_array($term->slug, $selected_values, true);
                $image = $this->resolve_term_image_url($term, $presentation);
                echo '<label class="productmaster-image-parent-label">';
                echo '<input type="checkbox" class="productmaster-image-parent-checkbox" name="' . esc_attr($param_key) . '" value="' . esc_attr($term->slug) . '" ' . checked($checked, true, false) . ' />';
                if (!empty($image)) {
                    echo '<img src="' . esc_url($image) . '" alt="' . esc_attr($term->name) . '" class="productmaster-image-thumb" />';
                } else {
                    echo '<span class="productmaster-image-thumb productmaster-image-fallback">' . esc_html(substr($term->name, 0, 1)) . '</span>';
                }
                echo '</label>';
            }
        }

        echo '</div>';
    }

    private function render_multi_filter_input($filter)
    {
        $param_key = 'pmf_' . $filter['id'];
        $selected_pairs = $this->normalize_multi_filter_values(isset($_GET[$param_key]) ? wp_unslash($_GET[$param_key]) : null); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $selected_lookup = array_fill_keys($selected_pairs, true);
        $source_ids = isset($filter['presentation']['selected_filter_ids']) ? (array) $filter['presentation']['selected_filter_ids'] : array();

        $source_filters = array_values(
            array_filter(
                $this->get_saved_taxonomy_filters(),
                function ($saved_filter) use ($filter, $source_ids) {
                    if (empty($saved_filter['id']) || empty($saved_filter['taxonomy']) || empty($saved_filter['type'])) {
                        return false;
                    }
                    if ($saved_filter['id'] === $filter['id'] || 'image_boxes' !== $saved_filter['type']) {
                        return false;
                    }

                    return empty($source_ids) || in_array($saved_filter['id'], $source_ids, true);
                }
            )
        );

        if (empty($source_filters)) {
            echo '<p>' . esc_html__('No source image filters configured.', 'productmaster') . '</p>';
            return;
        }

        echo '<div class="productmaster-image-box-grid">';
        foreach ($source_filters as $source_filter) {
            $terms = get_terms(array('taxonomy' => $source_filter['taxonomy'], 'hide_empty' => false));
            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            $source_presentation = isset($source_filter['presentation']) && is_array($source_filter['presentation']) ? $source_filter['presentation'] : $this->get_default_presentation_settings();
            $terms_by_slug = array();
            foreach ($terms as $term) {
                $terms_by_slug[$term->slug] = $term;
            }

            $parent_only_terms = array();
            $manual_hierarchy = isset($source_presentation['hierarchy_map']) && is_array($source_presentation['hierarchy_map']) ? $source_presentation['hierarchy_map'] : array();
            if (!empty($manual_hierarchy)) {
                foreach (array_keys($manual_hierarchy) as $parent_slug) {
                    if (isset($terms_by_slug[$parent_slug])) {
                        $parent_only_terms[] = $terms_by_slug[$parent_slug];
                    }
                }
            } else {
                foreach ($terms as $term) {
                    if ((int) $term->parent === 0) {
                        $parent_only_terms[] = $term;
                    }
                }
            }

            if (empty($parent_only_terms)) {
                $parent_only_terms = $terms;
            }

            echo '<div class="productmaster-image-parent">';
            echo '<label class="productmaster-image-parent-label">';
            echo '<span class="productmaster-image-thumb productmaster-image-fallback">' . esc_html(substr((string) $source_filter['label'], 0, 1)) . '</span>';
            echo '<span class="productmaster-image-child-tag">' . esc_html($source_filter['label']) . '</span>';
            echo '</label>';

            echo '<div class="productmaster-image-children-menu">';
            echo '<div class="productmaster-image-children-header">' . esc_html($source_filter['label']) . '</div>';
            echo '<div class="productmaster-image-children-grid">';
            foreach ($parent_only_terms as $term) {
                $value = $source_filter['id'] . ':' . $term->slug;
                $checked = isset($selected_lookup[$value]);
                $term_image = $this->resolve_term_image_url($term, $source_presentation);
                echo '<label class="productmaster-image-child-label">';
                echo '<input type="checkbox" class="productmaster-image-child-checkbox" name="' . esc_attr($param_key) . '" value="' . esc_attr($value) . '" ' . checked($checked, true, false) . ' />';
                echo '<span class="productmaster-image-child-tag">' . esc_html($term->name) . '</span>';
                if (!empty($term_image)) {
                    echo '<img src="' . esc_url($term_image) . '" alt="' . esc_attr($term->name) . '" class="productmaster-image-thumb" />';
                } else {
                    echo '<span class="productmaster-image-thumb productmaster-image-fallback">' . esc_html(substr($term->name, 0, 1)) . '</span>';
                }
                echo '</label>';
            }
            echo '</div></div></div>';
        }
        echo '</div>';
    }

    private function expand_terms_by_manual_hierarchy($terms, $filter)
    {
        $expanded_terms = array_values(array_unique(array_map('sanitize_title', (array) $terms)));
        if (empty($filter['presentation']['hierarchy_map']) || !is_array($filter['presentation']['hierarchy_map'])) {
            return array_values(array_filter($expanded_terms));
        }

        foreach ($expanded_terms as $term_slug) {
            if (empty($filter['presentation']['hierarchy_map'][$term_slug])) {
                continue;
            }

            foreach ((array) $filter['presentation']['hierarchy_map'][$term_slug] as $child_slug) {
                $expanded_terms[] = sanitize_title((string) $child_slug);
            }
        }

        return array_values(array_unique(array_filter($expanded_terms)));
    }
}
