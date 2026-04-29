(function () {
    'use strict';

    function submitForm(form) {
        if (!form) {
            return;
        }

        normalizeHierarchySelectionsForSubmit(form);
        form.requestSubmit ? form.requestSubmit() : form.submit();
    }

    function normalizeHierarchySelectionsForSubmit(form) {
        var toggledInputs = [];
        var childMenus = form.querySelectorAll('.productmaster-image-children-menu');

        childMenus.forEach(function (menu) {
            var parent = menu.closest('.productmaster-image-parent');
            if (!parent) {
                return;
            }

            var parentCheckbox = parent.querySelector('.productmaster-image-parent-checkbox');
            var childCheckboxes = menu.querySelectorAll('.productmaster-image-child-checkbox');
            if (!parentCheckbox || !childCheckboxes.length) {
                return;
            }

            var allChildrenChecked = true;
            childCheckboxes.forEach(function (childCheckbox) {
                if (!childCheckbox.checked) {
                    allChildrenChecked = false;
                }
            });

            if (!allChildrenChecked) {
                return;
            }

            parentCheckbox.checked = true;
            childCheckboxes.forEach(function (childCheckbox) {
                childCheckbox.disabled = true;
                toggledInputs.push(childCheckbox);
            });
        });

        if (!toggledInputs.length) {
            normalizeCheckboxArrayParamsForSubmit(form);
            return;
        }

        window.setTimeout(function () {
            normalizeCheckboxArrayParamsForSubmit(form);
            toggledInputs.forEach(function (input) {
                input.disabled = false;
            });
        }, 0);
    }

    function normalizeCheckboxArrayParamsForSubmit(form) {
        var groupedValues = {};
        var toggledInputs = [];
        var existingNormalizedInputs = form.querySelectorAll('.productmaster-normalized-array-param');
        existingNormalizedInputs.forEach(function (input) {
            input.parentNode.removeChild(input);
        });

        var checkboxInputs = form.querySelectorAll('input[type="checkbox"][name^="pmf_"]');
        checkboxInputs.forEach(function (input) {
            if (input.disabled || !input.checked) {
                return;
            }

            var inputName = input.name;
            if (!groupedValues[inputName]) {
                groupedValues[inputName] = [];
            }
            groupedValues[inputName].push(input.value);
        });

        Object.keys(groupedValues).forEach(function (inputName) {
            var allInputsForName = form.querySelectorAll('input[type="checkbox"][name="' + inputName.replace(/"/g, '\\"') + '"]');
            if (allInputsForName.length < 2) {
                return;
            }

            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = inputName;
            hidden.value = groupedValues[inputName].join(',');
            hidden.className = 'productmaster-normalized-array-param';
            form.appendChild(hidden);

            allInputsForName.forEach(function (input) {
                input.disabled = true;
                toggledInputs.push(input);
            });
        });

        window.setTimeout(function () {
            toggledInputs.forEach(function (input) {
                input.disabled = false;
            });
        }, 0);
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
                var currentMenu = target.closest('.productmaster-image-children-menu');
                syncChildrenHeaderState(currentMenu);

                if (!target.checked && currentMenu) {
                    var currentParent = currentMenu.closest('.productmaster-image-parent');
                    if (currentParent) {
                        var currentParentCheckbox = currentParent.querySelector('.productmaster-image-parent-checkbox');
                        if (currentParentCheckbox) {
                            currentParentCheckbox.checked = false;
                        }
                    }
                }
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
