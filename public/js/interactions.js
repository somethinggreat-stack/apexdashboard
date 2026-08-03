/* ==========================================================================
   interactions.js — small UX guards shared across the admin dashboard.

   1) Copy-to-clipboard:  any [data-copy] element copies its value and toasts.
        <button data-copy="literal text">Copy</button>
        <button data-copy-target="#apiKey">Copy</button>   (copies that field's value)
   2) Unsaved-changes guard:  form[data-guard-unsaved] warns before the user
        navigates away or closes its modal with edits pending.
   3) Destructive confirm:  form[data-confirm-delete] shows the centered danger
        modal (see confirm-modal.js) instead of a native confirm(). Add
        data-confirm-name="X" to require typing X (used for business owners).

   Vanilla, no dependencies. Relies on window.apexToast / window.apexConfirmDelete.
   ========================================================================== */
(function () {
    'use strict';

    /* --- 1) Copy to clipboard ------------------------------------------- */
    function copyText(text, okMsg) {
        text = (text || '').toString();
        function done() { if (window.apexToast) window.apexToast(okMsg || 'Copied to clipboard', 'success'); }
        function fail() { if (window.apexToast) window.apexToast('Could not copy — select and copy manually.', 'error'); }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, fallback);
        } else {
            fallback();
        }
        function fallback() {
            try {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                var ok = document.execCommand('copy');
                document.body.removeChild(ta);
                ok ? done() : fail();
            } catch (e) { fail(); }
        }
    }
    window.apexCopy = copyText;

    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-copy], [data-copy-target]');
        if (!el) return;
        e.preventDefault();
        var text = el.getAttribute('data-copy');
        if (text === null) {
            var sel = el.getAttribute('data-copy-target');
            var field = sel && document.querySelector(sel);
            text = field ? (field.value !== undefined ? field.value : field.textContent) : '';
        }
        copyText(text, el.getAttribute('data-copy-msg') || 'Copied to clipboard');
    });

    /* --- 2) Unsaved-changes guard --------------------------------------- */
    (function () {
        var guarded = [];
        function snapshot(form) {
            return Array.prototype.map.call(form.elements, function (el) {
                if (!el.name) return '';
                if (el.type === 'checkbox' || el.type === 'radio') return el.checked ? '1' : '0';
                return el.value;
            }).join('');
        }
        function init() {
            document.querySelectorAll('form[data-guard-unsaved]').forEach(function (form) {
                var g = { form: form, clean: snapshot(form), submitting: false };
                guarded.push(g);
                form.addEventListener('submit', function () { g.submitting = true; });
            });
        }
        function anyDirty() {
            return guarded.some(function (g) { return !g.submitting && snapshot(g.form) !== g.clean; });
        }
        window.apexHasUnsavedChanges = anyDirty;

        window.addEventListener('beforeunload', function (e) {
            if (anyDirty()) { e.preventDefault(); e.returnValue = ''; return ''; }
        });

        // Closing the edit modal (× or backdrop) with pending edits — confirm first.
        document.addEventListener('click', function (e) {
            var closer = e.target.closest('.modal-close');
            var backdrop = e.target.classList && e.target.classList.contains('modal') ? e.target : null;
            var trigger = closer || backdrop;
            if (!trigger) return;
            var modal = trigger.closest ? trigger.closest('.modal') : backdrop;
            if (backdrop) modal = backdrop;
            if (!modal || !modal.querySelector('form[data-guard-unsaved]')) return;
            var g = guarded.filter(function (x) { return modal.contains(x.form); })
                           .find(function (x) { return !x.submitting && snapshot(x.form) !== x.clean; });
            if (!g) return;
            e.preventDefault();
            e.stopPropagation();
            if (window.apexConfirmDelete) {
                window.apexConfirmDelete({
                    title: 'Discard changes?',
                    message: 'You have unsaved edits on this profile. Close without saving?',
                    okLabel: 'Discard',
                    onConfirm: function () { g.clean = snapshot(g.form); trigger.click(); }
                });
            } else if (confirm('Discard unsaved changes?')) {
                g.clean = snapshot(g.form); trigger.click();
            }
        }, true);
        init();
    })();

    /* --- 3) Confirm before submit (destructive OR significant) ---------- */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.hasAttribute) return;
        var isDelete = form.hasAttribute('data-confirm-delete');
        var isAction = form.hasAttribute('data-confirm-action');
        if (!isDelete && !isAction) return;
        if (form.dataset.confirmed === '1') return;   // already approved — let it through
        e.preventDefault();
        var go = function () { form.dataset.confirmed = '1'; form.submit(); };
        var opts = {
            title:   form.getAttribute('data-confirm-title')   || 'Are you sure?',
            message: form.getAttribute('data-confirm-message') || (isDelete ? 'This action cannot be undone.' : ''),
            okLabel: form.getAttribute('data-confirm-ok')      || (isDelete ? 'Delete' : 'Confirm'),
            name:    isDelete ? (form.getAttribute('data-confirm-name') || null) : null,
            onConfirm: go
        };
        if (isDelete && window.apexConfirmDelete) {
            window.apexConfirmDelete(opts);
        } else if (!isDelete && window.apexConfirmAction) {
            window.apexConfirmAction(opts);
        } else if (confirm(opts.message || 'Are you sure?')) {
            go();
        }
    }, true);

    /* --- 4) Live duplicate-email check on add-client forms -------------- */
    (function () {
        var CSS = '.dup-email-error{display:none;margin-top:6px;font-size:12.5px;font-weight:600;'
                + 'color:#dc2626;line-height:1.35;}'
                + 'input.input-dup{border-color:#dc2626 !important;'
                + 'box-shadow:0 0 0 2px rgba(220,38,38,.14) !important;}';
        var styled = false;
        function injectOnce() {
            if (styled) return; styled = true;
            var s = document.createElement('style'); s.textContent = CSS; document.head.appendChild(s);
        }

        function wire(input) {
            injectOnce();
            var url = input.getAttribute('data-check-email');
            var form = input.closest('form');
            var submit = form ? form.querySelector('[type="submit"]') : null;

            var err = document.createElement('div');
            err.className = 'dup-email-error';
            err.setAttribute('role', 'alert');
            input.insertAdjacentElement('afterend', err);

            var timer, lastToken = 0;

            function clear() {
                input.classList.remove('input-dup');
                err.style.display = 'none';
                if (submit && submit.dataset.dupBlock === '1') {
                    submit.disabled = false;
                    delete submit.dataset.dupBlock;
                }
            }
            function flag(name) {
                input.classList.add('input-dup');
                err.textContent = name
                    ? ('A client with this email already exists — ' + name + '. Please check before adding.')
                    : 'A client with this email already exists. Please check before adding.';
                err.style.display = 'block';
                if (submit) { submit.disabled = true; submit.dataset.dupBlock = '1'; }
            }
            function check() {
                var email = input.value.trim();
                if (!email || input.validity.typeMismatch) { clear(); return; }
                var token = ++lastToken;
                var q = url + (url.indexOf('?') === -1 ? '?' : '&') + 'email=' + encodeURIComponent(email);
                fetch(q, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (token !== lastToken) return;   // a newer keystroke won
                        if (d && d.exists) flag(d.name); else clear();
                    })
                    .catch(function () { /* network hiccup — don't block the VA */ });
            }

            input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(check, 400); });
            input.addEventListener('blur', check);
        }

        document.querySelectorAll('input[data-check-email]').forEach(wire);
    })();
})();
