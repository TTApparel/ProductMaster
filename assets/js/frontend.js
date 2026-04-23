(function () {
    'use strict';

    function submitForm(form) {
        if (!form) {
            return;
        }

        form.requestSubmit ? form.requestSubmit() : form.submit();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('.productmaster-filters-form');

        forms.forEach(function (form) {
            var debounceTimer = null;

            form.addEventListener('change', function (event) {
                var target = event.target;
                if (!target || !target.name) {
                    return;
                }

                submitForm(form);
            });

            form.addEventListener('input', function (event) {
                var target = event.target;
                if (!target || target.type !== 'search') {
                    return;
                }

                window.clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(function () {
                    submitForm(form);
                }, 350);
            });
        });
    });
})();
