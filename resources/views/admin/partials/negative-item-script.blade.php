{{-- Shared behaviour for a negative-item entry row/form: category is chosen
     FIRST, then the remaining fields adapt to the item type. Each field carries
     a data-ni="name|detail|goal|category|bureau" hook. --}}
<script>
    (function () {
        var NAME_PH = {
            negative_account: 'Account / creditor name',
            inquiry: 'Inquiry name',
            bankruptcy: 'Bankruptcy name',
            personal_information: 'Address, employer, or other personal info'
        };
        var DETAIL_PH = {
            negative_account: 'Account number',
            inquiry: 'Inquiry date (MM/DD/YYYY)',
            bankruptcy: 'Reference number'
        };

        // Show/hide + relabel the fields of one row/form based on its category.
        window.niSync = function (scope) {
            var cat = scope.querySelector('[data-ni="category"]');
            if (!cat) return;
            var v = cat.value;
            var name = scope.querySelector('[data-ni="name"]');
            var detail = scope.querySelector('[data-ni="detail"]');
            var goal = scope.querySelector('[data-ni="goal"]');

            if (name) name.placeholder = NAME_PH[v] || 'Item name';

            if (detail) {
                if (DETAIL_PH[v]) {
                    detail.style.display = '';
                    detail.placeholder = DETAIL_PH[v];
                } else {
                    detail.style.display = 'none';
                    detail.value = '';
                }
            }
            // Only a Negative Account can be Updated to positive; the rest are Delete.
            if (goal) {
                if (v === 'negative_account') {
                    goal.style.display = '';
                } else {
                    goal.style.display = 'none';
                    goal.value = 'delete';
                }
            }
        };

        // Wire the category change listener once, then sync immediately.
        window.niBind = function (scope) {
            var cat = scope.querySelector('[data-ni="category"]');
            if (cat && !cat.dataset.niBound) {
                cat.dataset.niBound = '1';
                cat.addEventListener('change', function () { window.niSync(scope); });
            }
            window.niSync(scope);
        };
    })();
</script>
