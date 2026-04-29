<?php

/**
 * WOOCOMMERCE ATTRIBUTE IMAGE SWATCHES (COLOR)
 */
/**
 * WooCommerce Attribute Images (Color swatches + Import/Export)
 *
 * What it does:
 * 1) Adds an image upload field to attribute terms (e.g. pa_color).
 * 2) Saves the image ID on the term.
 * 3) Replaces color text with image swatches on layered nav widgets/blocks.
 * 4) Adds Import/Export tools for attribute terms including swatch images.
 *
 * Usage:
 * - Activate snippet.
 * - Go to Products → Attributes → Configure terms (for Color).
 * - Upload a swatch image for each color term.
 * - Go to Products → Color Swatch Import/Export to move terms to another site.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Change this if your attribute slug is different.
 * For WooCommerce "Color" attribute, taxonomy is usually "pa_color".
 */
if ( ! defined( 'TTA_SWATCH_TAXONOMY' ) ) {
    define( 'TTA_SWATCH_TAXONOMY', 'pa_color' );
}

if ( ! defined( 'TTA_SWATCH_TERM_META_KEY' ) ) {
    define( 'TTA_SWATCH_TERM_META_KEY', 'tta_swatch_image_id' );
}

/**
 * Enqueue media uploader only on relevant term screens.
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
        return;
    }

    $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
    if ( TTA_SWATCH_TAXONOMY !== $taxonomy ) {
        return;
    }

    wp_enqueue_media();
} );

/**
 * Add image field when creating a new term.
 */
add_action( TTA_SWATCH_TAXONOMY . '_add_form_fields', function () {
    ?>
    <div class="form-field tta-swatch-wrap">
        <label for="tta_swatch_image_id"><?php esc_html_e( 'Swatch image', 'tta' ); ?></label>
        <input type="hidden" name="tta_swatch_image_id" id="tta_swatch_image_id" value="" />
        <div class="tta-swatch-preview" style="margin:8px 0;"></div>
        <button type="button" class="button tta-swatch-upload"><?php esc_html_e( 'Upload image', 'tta' ); ?></button>
        <button type="button" class="button tta-swatch-remove" style="display:none;"><?php esc_html_e( 'Remove image', 'tta' ); ?></button>
        <p class="description"><?php esc_html_e( 'Recommended: square JPG/PNG/WebP, ~100×100.', 'tta' ); ?></p>
    </div>
    <?php
} );

/**
 * Add image field on edit term screen.
 */
add_action( TTA_SWATCH_TAXONOMY . '_edit_form_fields', function ( $term ) {
    $image_id = (int) get_term_meta( $term->term_id, TTA_SWATCH_TERM_META_KEY, true );
    $image    = $image_id ? wp_get_attachment_image( $image_id, 'thumbnail', false, [ 'style' => 'max-width:64px;height:auto;' ] ) : '';
    ?>
    <tr class="form-field tta-swatch-wrap">
        <th scope="row"><label for="tta_swatch_image_id"><?php esc_html_e( 'Swatch image', 'tta' ); ?></label></th>
        <td>
            <input type="hidden" name="tta_swatch_image_id" id="tta_swatch_image_id" value="<?php echo esc_attr( $image_id ); ?>" />
            <div class="tta-swatch-preview" style="margin:8px 0;"><?php echo wp_kses_post( $image ); ?></div>
            <button type="button" class="button tta-swatch-upload"><?php esc_html_e( 'Upload / Change image', 'tta' ); ?></button>
            <button type="button" class="button tta-swatch-remove" <?php echo $image_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove image', 'tta' ); ?></button>
            <p class="description"><?php esc_html_e( 'Recommended: square JPG/PNG/WebP, ~100×100.', 'tta' ); ?></p>
        </td>
    </tr>
    <?php
} );

/**
 * Save term image metadata.
 */
$tta_save_term_image = function ( $term_id ) {
    if ( ! isset( $_POST['tta_swatch_image_id'] ) ) {
        return;
    }

    $image_id = (int) wp_unslash( $_POST['tta_swatch_image_id'] );

    if ( $image_id > 0 ) {
        update_term_meta( $term_id, TTA_SWATCH_TERM_META_KEY, $image_id );
    } else {
        delete_term_meta( $term_id, TTA_SWATCH_TERM_META_KEY );
    }
};
add_action( 'created_' . TTA_SWATCH_TAXONOMY, $tta_save_term_image );
add_action( 'edited_' . TTA_SWATCH_TAXONOMY, $tta_save_term_image );

/**
 * Admin JS for media modal on term pages.
 */
add_action( 'admin_footer', function () {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || TTA_SWATCH_TAXONOMY !== $screen->taxonomy ) {
        return;
    }
    ?>
    <script>
    jQuery(function($){
        let frame;

        function setImage(context, attachment) {
            context.find('#tta_swatch_image_id').val(attachment.id);
            context.find('.tta-swatch-preview').html('<img src="' + attachment.sizes.thumbnail.url + '" style="max-width:64px;height:auto;" />');
            context.find('.tta-swatch-remove').show();
        }

        $(document).on('click', '.tta-swatch-upload', function(e){
            e.preventDefault();
            const context = $(this).closest('.tta-swatch-wrap, td');

            frame = wp.media({
                title: 'Select swatch image',
                button: { text: 'Use this image' },
                multiple: false
            });

            frame.on('select', function(){
                const attachment = frame.state().get('selection').first().toJSON();
                if (!attachment || !attachment.id) return;

                if (!attachment.sizes || !attachment.sizes.thumbnail) {
                    attachment.sizes = attachment.sizes || {};
                    attachment.sizes.thumbnail = { url: attachment.url };
                }

                setImage(context, attachment);
            });

            frame.open();
        });

        $(document).on('click', '.tta-swatch-remove', function(e){
            e.preventDefault();
            const context = $(this).closest('.tta-swatch-wrap, td');
            context.find('#tta_swatch_image_id').val('');
            context.find('.tta-swatch-preview').empty();
            $(this).hide();
        });
    });
    </script>
    <?php
} );

/**
 * Output image swatch in layered nav lists (widgets and many themes).
 */
add_filter( 'woocommerce_layered_nav_term_html', function ( $term_html, $term ) {
    if ( ! $term instanceof WP_Term || TTA_SWATCH_TAXONOMY !== $term->taxonomy ) {
        return $term_html;
    }

    $image_id = (int) get_term_meta( $term->term_id, TTA_SWATCH_TERM_META_KEY, true );
    if ( ! $image_id ) {
        return $term_html;
    }

    $thumb = wp_get_attachment_image( $image_id, 'thumbnail', false, [
        'class'   => 'tta-attr-swatch',
        'alt'     => $term->name,
        'loading' => 'lazy',
        'style'   => 'width:18px;height:18px;object-fit:cover;border-radius:50%;display:inline-block;vertical-align:middle;margin-right:8px;'
    ] );

    if ( ! $thumb ) {
        return $term_html;
    }

    return $thumb . '<span class="tta-attr-label">' . esc_html( $term->name ) . '</span>';
}, 10, 2 );

/**
 * Tools page: Products → Color Swatch Import/Export.
 */
add_action( 'admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=product',
        __( 'Color Swatch Import/Export', 'tta' ),
        __( 'Color Swatch Import/Export', 'tta' ),
        'manage_product_terms',
        'tta-color-swatch-import-export',
        'tta_render_color_swatch_tools_page'
    );
} );

function tta_render_color_swatch_tools_page() {
    if ( ! current_user_can( 'manage_product_terms' ) ) {
        wp_die( esc_html__( 'You are not allowed to manage product attributes.', 'tta' ) );
    }

    $message = isset( $_GET['tta_msg'] ) ? sanitize_key( wp_unslash( $_GET['tta_msg'] ) ) : '';
    $created = isset( $_GET['tta_created'] ) ? absint( $_GET['tta_created'] ) : 0;
    $updated = isset( $_GET['tta_updated'] ) ? absint( $_GET['tta_updated'] ) : 0;
    $images  = isset( $_GET['tta_images'] ) ? absint( $_GET['tta_images'] ) : 0;
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Color Swatch Import/Export', 'tta' ); ?></h1>

        <?php if ( 'imported' === $message ) : ?>
            <div class="notice notice-success is-dismissible"><p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: created terms, 2: updated terms, 3: imported images */
                        __( 'Import complete. Created: %1$d, Updated: %2$d, Images imported: %3$d.', 'tta' ),
                        $created,
                        $updated,
                        $images
                    )
                );
                ?>
            </p></div>
        <?php elseif ( 'invalid_file' === $message ) : ?>
            <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'The uploaded file is invalid. Please upload a valid JSON export file.', 'tta' ); ?></p></div>
        <?php elseif ( 'import_failed' === $message ) : ?>
            <div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Import failed. Please try again.', 'tta' ); ?></p></div>
        <?php endif; ?>

        <h2><?php esc_html_e( 'Export', 'tta' ); ?></h2>
        <p><?php esc_html_e( 'Download all terms from this attribute taxonomy, including embedded swatch image files.', 'tta' ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'tta_export_color_swatches' ); ?>
            <input type="hidden" name="action" value="tta_export_color_swatches" />
            <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Download Export File', 'tta' ); ?></button></p>
        </form>

        <hr />

        <h2><?php esc_html_e( 'Import', 'tta' ); ?></h2>
        <p><?php esc_html_e( 'Import terms and swatch images from a previously exported JSON file.', 'tta' ); ?></p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field( 'tta_import_color_swatches' ); ?>
            <input type="hidden" name="action" value="tta_import_color_swatches" />
            <input type="file" name="tta_import_file" accept="application/json,.json" required />
            <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Import File', 'tta' ); ?></button></p>
        </form>
    </div>
    <?php
}

add_action( 'admin_post_tta_export_color_swatches', function () {
    if ( ! current_user_can( 'manage_product_terms' ) ) {
        wp_die( esc_html__( 'You are not allowed to export product attributes.', 'tta' ) );
    }

    check_admin_referer( 'tta_export_color_swatches' );

    $terms = get_terms(
        [
            'taxonomy'   => TTA_SWATCH_TAXONOMY,
            'hide_empty' => false,
        ]
    );

    if ( is_wp_error( $terms ) ) {
        wp_die( esc_html__( 'Unable to load attribute terms for export.', 'tta' ) );
    }

    $export = [
        'version'   => 1,
        'taxonomy'  => TTA_SWATCH_TAXONOMY,
        'generated' => gmdate( 'c' ),
        'site_url'  => home_url(),
        'terms'     => [],
    ];

    foreach ( $terms as $term ) {
        $item = [
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => $term->description,
            'parent_slug' => '',
            'image'       => null,
        ];

        if ( $term->parent ) {
            $parent = get_term( $term->parent, TTA_SWATCH_TAXONOMY );
            if ( $parent instanceof WP_Term ) {
                $item['parent_slug'] = $parent->slug;
            }
        }

        $image_id = (int) get_term_meta( $term->term_id, TTA_SWATCH_TERM_META_KEY, true );
        if ( $image_id > 0 ) {
            $file_path = get_attached_file( $image_id );
            if ( $file_path && file_exists( $file_path ) && is_readable( $file_path ) ) {
                $file_data = file_get_contents( $file_path );
                if ( false !== $file_data ) {
                    $item['image'] = [
                        'filename' => wp_basename( $file_path ),
                        'mime'     => get_post_mime_type( $image_id ) ?: 'image/jpeg',
                        'data'     => base64_encode( $file_data ),
                    ];
                }
            }
        }

        $export['terms'][] = $item;
    }

    $json = wp_json_encode( $export );
    if ( ! $json ) {
        wp_die( esc_html__( 'Unable to generate export file.', 'tta' ) );
    }

    nocache_headers();
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( TTA_SWATCH_TAXONOMY . '-swatches-' . gmdate( 'Ymd-His' ) . '.json' ) . '"' );
    echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
} );

add_action( 'admin_post_tta_import_color_swatches', function () {
    if ( ! current_user_can( 'manage_product_terms' ) ) {
        wp_die( esc_html__( 'You are not allowed to import product attributes.', 'tta' ) );
    }

    check_admin_referer( 'tta_import_color_swatches' );

    $redirect_url = admin_url( 'edit.php?post_type=product&page=tta-color-swatch-import-export' );

    if ( empty( $_FILES['tta_import_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['tta_import_file']['tmp_name'] ) ) {
        wp_safe_redirect( add_query_arg( 'tta_msg', 'invalid_file', $redirect_url ) );
        exit;
    }

    $contents = file_get_contents( $_FILES['tta_import_file']['tmp_name'] );
    if ( false === $contents ) {
        wp_safe_redirect( add_query_arg( 'tta_msg', 'import_failed', $redirect_url ) );
        exit;
    }

    $data = json_decode( $contents, true );
    if ( ! is_array( $data ) || empty( $data['taxonomy'] ) || TTA_SWATCH_TAXONOMY !== $data['taxonomy'] || empty( $data['terms'] ) || ! is_array( $data['terms'] ) ) {
        wp_safe_redirect( add_query_arg( 'tta_msg', 'invalid_file', $redirect_url ) );
        exit;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';

    $created = 0;
    $updated = 0;
    $images  = 0;

    foreach ( $data['terms'] as $item ) {
        if ( empty( $item['slug'] ) || empty( $item['name'] ) ) {
            continue;
        }

        $slug = sanitize_title( $item['slug'] );
        $name = sanitize_text_field( $item['name'] );

        $term_id = 0;
        $existing = get_term_by( 'slug', $slug, TTA_SWATCH_TAXONOMY );

        if ( $existing instanceof WP_Term ) {
            $term_id = (int) $existing->term_id;
            wp_update_term(
                $term_id,
                TTA_SWATCH_TAXONOMY,
                [
                    'name'        => $name,
                    'description' => isset( $item['description'] ) ? wp_kses_post( $item['description'] ) : '',
                ]
            );
            $updated++;
        } else {
            $created_term = wp_insert_term(
                $name,
                TTA_SWATCH_TAXONOMY,
                [
                    'slug'        => $slug,
                    'description' => isset( $item['description'] ) ? wp_kses_post( $item['description'] ) : '',
                ]
            );

            if ( is_wp_error( $created_term ) || empty( $created_term['term_id'] ) ) {
                continue;
            }

            $term_id = (int) $created_term['term_id'];
            $created++;
        }

        if ( ! empty( $item['image'] ) && is_array( $item['image'] ) && ! empty( $item['image']['data'] ) ) {
            $filename = ! empty( $item['image']['filename'] ) ? sanitize_file_name( $item['image']['filename'] ) : ( $slug . '.jpg' );
            $raw_data = base64_decode( (string) $item['image']['data'], true );

            if ( false !== $raw_data ) {
                $uploaded = wp_upload_bits( $filename, null, $raw_data );

                if ( empty( $uploaded['error'] ) && ! empty( $uploaded['file'] ) ) {
                    $filetype = wp_check_filetype( $uploaded['file'] );
                    $attachment_id = wp_insert_attachment(
                        [
                            'post_mime_type' => ! empty( $item['image']['mime'] ) ? sanitize_text_field( $item['image']['mime'] ) : ( $filetype['type'] ?: 'image/jpeg' ),
                            'post_title'     => sanitize_text_field( pathinfo( $filename, PATHINFO_FILENAME ) ),
                            'post_content'   => '',
                            'post_status'    => 'inherit',
                        ],
                        $uploaded['file']
                    );

                    if ( ! is_wp_error( $attachment_id ) ) {
                        $meta = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
                        wp_update_attachment_metadata( $attachment_id, $meta );
                        update_term_meta( $term_id, TTA_SWATCH_TERM_META_KEY, (int) $attachment_id );
                        $images++;
                    }
                }
            }
        }
    }

    wp_safe_redirect(
        add_query_arg(
            [
                'tta_msg'     => 'imported',
                'tta_created' => $created,
                'tta_updated' => $updated,
                'tta_images'  => $images,
            ],
            $redirect_url
        )
    );
    exit;
} );
