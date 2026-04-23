(function ($) {
    'use strict';

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
})(jQuery);
