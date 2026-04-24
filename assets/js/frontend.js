(function () {
    'use strict';

    function submitForm(form) {
        if (!form) {
            return;
        }

        form.requestSubmit ? form.requestSubmit() : form.submit();
    }

    function syncChildrenHeaderState(menu) {
        if (!menu) {
            return;
        }

        var headerToggle = menu.querySelector('.productmaster-image-children-toggle');
        var childCheckboxes = menu.querySelectorAll('.productmaster-image-child-checkbox');

        if (!headerToggle || !childCheckboxes.length) {
            return;
        }

        var checkedCount = 0;
        childCheckboxes.forEach(function (checkbox) {
            if (checkbox.checked) {
                checkedCount += 1;
            }
        });

        headerToggle.checked = checkedCount === childCheckboxes.length;
        headerToggle.indeterminate = checkedCount > 0 && checkedCount < childCheckboxes.length;
    }


    function syncChildrenMenuWidths(form) {
        if (!form) {
            return;
        }

        var grids = form.querySelectorAll('.productmaster-image-box-grid');

        grids.forEach(function (grid) {
            var width = grid.getBoundingClientRect().width;
            if (!width) {
                return;
            }

            var childMenus = grid.querySelectorAll('.productmaster-image-children-menu');
            childMenus.forEach(function (menu) {
                menu.style.width = width + 'px';
            });
        });
    }

    function initializeImageChildrenToggles(form) {
        var childMenus = form.querySelectorAll('.productmaster-image-children-menu');

        childMenus.forEach(function (menu) {
            syncChildrenHeaderState(menu);
        });

        form.addEventListener('change', function (event) {
            var target = event.target;
            if (!target) {
                return;
            }

            if (target.classList.contains('productmaster-image-children-toggle')) {
                var menu = target.closest('.productmaster-image-children-menu');
                if (!menu) {
                    return;
                }

                var childCheckboxes = menu.querySelectorAll('.productmaster-image-child-checkbox');
                childCheckboxes.forEach(function (checkbox) {
                    checkbox.checked = target.checked;
                });
                target.indeterminate = false;
                return;
            }

            if (target.classList.contains('productmaster-image-child-checkbox')) {
                syncChildrenHeaderState(target.closest('.productmaster-image-children-menu'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var forms = document.querySelectorAll('.productmaster-filters-form');

        forms.forEach(function (form) {
            var debounceTimer = null;

            initializeImageChildrenToggles(form);
            syncChildrenMenuWidths(form);

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

            window.addEventListener('resize', function () {
                syncChildrenMenuWidths(form);
            });
        });
    });
})();
