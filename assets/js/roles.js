/**
 * Signatures → Settings (includes/roles.php): a role's "Add", "Delete" and
 * "WPBakery element" only with its "Edit signatures" (greyed and off without
 * it, as the saved settings keep them: signatur_sanitize_roles()).
 */
(function () {
    'use strict';

    function sync(row) {
        var edit = row.querySelector('input[data-action="edit"]');

        if (!edit || edit.disabled) {
            return;
        }

        ['create', 'delete', 'wpbakery'].forEach(function (action) {
            var box = row.querySelector('input[data-action="' + action + '"]');

            if (!box) {
                return;
            }

            box.disabled = !edit.checked;

            if (!edit.checked) {
                box.checked = false;
            }
        });
    }

    Array.prototype.forEach.call(document.querySelectorAll('.signatur-roles__table tbody tr'), function (row) {
        sync(row);
        row.addEventListener('change', function (e) {
            if (e.target.matches('input[data-action="edit"]')) {
                sync(row);
            }
        });
    });
})();
