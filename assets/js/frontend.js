(function () {
    'use strict';

    function submitForm(form) {
        if (!form) {
            return;
        }

        form.requestSubmit ? form.requestSubmit() : form.submit();
    }

    function encodeFilterState(data) {
        var json = JSON.stringify(data);
        return btoa(unescape(encodeURIComponent(json))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
    }

    function collectFilterState(form) {
        var state = {};
        var formData = new FormData(form);

        formData.forEach(function (value, key) {
            if (key.indexOf('pmf_') !== 0 || key === 'pmf_state') {
                return;
            }

            if (Object.prototype.hasOwnProperty.call(state, key)) {
                if (!Array.isArray(state[key])) {
                    state[key] = [state[key]];
                }
                state[key].push(value);
                return;
            }

            state[key] = value;
        });

        return state;
    }

    function compactFilterSubmission(form) {
        var stateInput = form.querySelector('input[name="pmf_state"]');
        if (!stateInput) {
            return;
        }

        var state = collectFilterState(form);
        stateInput.value = encodeFilterState(state);

        var filterInputs = form.querySelectorAll('[name^="pmf_"]');
        filterInputs.forEach(function (input) {
            if (input.name === 'pmf_state') {
                return;
            }
            input.dataset.originalName = input.name;
            input.removeAttribute('name');
        });
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
            var gridRect = grid.getBoundingClientRect();
            var width = gridRect.width;
            if (!width) {
                return;
            }

            var childMenus = grid.querySelectorAll('.productmaster-image-children-menu');
            childMenus.forEach(function (menu) {
                var parent = menu.closest('.productmaster-image-parent');
                var leftOffset = 0;
                if (parent) {
                    var parentRect = parent.getBoundingClientRect();
                    leftOffset = parentRect.left - gridRect.left;
                }

                menu.style.width = width + 'px';
                menu.style.left = '-' + leftOffset + 'px';
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

            if (target.classList.contains('productmaster-image-parent-checkbox')) {
                var parent = target.closest('.productmaster-image-parent');
                if (!parent) {
                    return;
                }

                var parentMenu = parent.querySelector('.productmaster-image-children-menu');
                if (!parentMenu) {
                    return;
                }

                var headerToggle = parentMenu.querySelector('.productmaster-image-children-toggle');
                var nestedChildCheckboxes = parentMenu.querySelectorAll('.productmaster-image-child-checkbox');
                if (headerToggle) {
                    headerToggle.checked = target.checked;
                    headerToggle.indeterminate = false;
                }

                nestedChildCheckboxes.forEach(function (checkbox) {
                    checkbox.checked = target.checked;
                });
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

            form.addEventListener('submit', function () {
                compactFilterSubmission(form);
            });

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
