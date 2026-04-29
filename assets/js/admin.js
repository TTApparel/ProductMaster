(function ($) {
    'use strict';


    function syncImageChildrenMenuWidths() {
        $('.productmaster-filters-form .productmaster-image-box-grid').each(function () {
            var $grid = $(this);
            var width = $grid.outerWidth();
            if (!width) {
                return;
            }

            var gridLeft = $grid.offset().left;
            $grid.find('.productmaster-image-children-menu').each(function () {
                var $menu = $(this);
                var $parent = $menu.closest('.productmaster-image-parent');
                var parentLeft = $parent.length ? $parent.offset().left : gridLeft;
                var leftOffset = parentLeft - gridLeft;

                $menu.css({
                    width: width + 'px',
                    left: '-' + leftOffset + 'px'
                });
            });
        });
    }

    function setFeedback(variationId, message, isError) {
        var $feedback = $('.productmaster-stock-feedback[data-variation-id="' + variationId + '"]');
        $feedback.text(message || '');
        $feedback.toggleClass('is-error', !!isError);
        $feedback.toggleClass('is-success', !isError && !!message);
    }

    $(document).on('click', '.productmaster-save-stock', function () {
        var variationId = $(this).data('variation-id');
        var $input = $('.productmaster-stock-input[data-variation-id="' + variationId + '"]');
        var qty = $input.val();
        var $button = $(this);

        $button.prop('disabled', true);
        setFeedback(variationId, productmasterAdmin.savingText, false);

        $.post(productmasterAdmin.ajaxUrl, {
            action: 'productmaster_update_variation_stock',
            nonce: productmasterAdmin.nonce,
            variation_id: variationId,
            qty: qty
        })
            .done(function (response) {
                if (!response || !response.success) {
                    var errorMessage = response && response.data && response.data.message ? response.data.message : productmasterAdmin.errorText;
                    setFeedback(variationId, errorMessage, true);
                    return;
                }

                if (response.data && typeof response.data.qty !== 'undefined') {
                    $input.val(response.data.qty);
                }

                setFeedback(variationId, response.data && response.data.message ? response.data.message : productmasterAdmin.savedText, false);
            })
            .fail(function () {
                setFeedback(variationId, productmasterAdmin.errorText, true);
            })
            .always(function () {
                $button.prop('disabled', false);
            });
    });

    $(document).on('click', '.productmaster-select-image', function () {
        var $button = $(this);
        var $container = $button.closest('.productmaster-term-image-control');
        var $input = $container.find('.productmaster-term-image-input');
        var $label = $container.find('.productmaster-image-selected-label');

        var frame = wp.media({
            title: 'Select term image',
            multiple: false,
            library: {
                type: 'image'
            }
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            if (!attachment || !attachment.url) {
                return;
            }

            $input.val(attachment.url);
            $label.text('Image selected');
        });

        frame.open();
    });

    $(function () {
        syncImageChildrenMenuWidths();
        $(window).on('resize', syncImageChildrenMenuWidths);

        $('.productmaster-loop-card').each(function () {
            var $card = $(this);
            var $mainImage = $card.find('.productmaster-loop-main-image').first();
            if (!$mainImage.length) {
                return;
            }
            var defaultSrc = $mainImage.attr('src');
            $card.find('.productmaster-loop-color-swatch[data-variation-image]').on('mouseenter', function () {
                var targetSrc = $(this).attr('data-variation-image');
                if (targetSrc) {
                    $mainImage.attr('src', targetSrc);
                }
            }).on('mouseleave', function () {
                $mainImage.attr('src', defaultSrc);
            });
        });
    });
})(jQuery);
