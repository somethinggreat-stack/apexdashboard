/* ==========================================================================
   inplace.js — submit a work-list row action without a full page reload.

   A form marked  <form ... data-inplace>  posts via fetch; on success it shows
   a toast (or the success modal for approvals), then refreshes the page's
   [data-page-refresh] (or [data-clients-refresh]) region in place — so the list
   stays exactly where you were, like the Payments page. The region is locked
   with a spinner for the whole round-trip so a row action can't be double-clicked.

   Confirm forms (data-confirm-delete / data-confirm-action) are routed here by
   confirm-modal / interactions after the user confirms.

   Relies on: window.apexToast, window.apexConfirm (confirm-modal.js), closeModal.
   ========================================================================== */
(function () {
    'use strict';

    function region() {
        return document.querySelector('[data-page-refresh]') || document.querySelector('[data-clients-refresh]');
    }
    function lock(on) {
        var r = region();
        if (r) r.classList[on ? 'add' : 'remove']('is-refreshing');
    }

    // Re-fetch the current page and swap just the refreshable region's contents.
    window.apexRefreshRegion = function () {
        var cur = region();
        if (!cur) { window.location.reload(); return; }
        cur.classList.add('is-refreshing');
        var sel = cur.hasAttribute('data-page-refresh') ? '[data-page-refresh]' : '[data-clients-refresh]';
        fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var fresh = doc.querySelector(sel);
                var now = document.querySelector(sel);
                if (fresh && now) { now.innerHTML = fresh.innerHTML; now.classList.remove('is-refreshing'); }
                else { window.location.reload(); }
            })
            .catch(function () { window.location.reload(); });
    };

    // POST a form in place. Closes a containing modal, locks the region, then
    // toasts + refreshes on success. Falls back to a native submit on a network
    // error so an action is never silently lost.
    window.apexSubmitInPlace = function (form) {
        if (form.__inpBusy) return;
        form.__inpBusy = true;

        var modal = form.closest ? form.closest('.modal') : null;
        if (modal && window.closeModal) { try { closeModal(modal.id); } catch (e) {} }
        lock(true);

        fetch(form.action, {
            method: (form.method || 'POST').toUpperCase(),
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; });
        }).then(function (res) {
            form.__inpBusy = false;
            if (res.ok) {
                var d = res.d || {};
                if (d.confirm && window.apexConfirm) window.apexConfirm(d.confirm);
                else if (window.apexToast) window.apexToast(d.status || 'Done.', 'success');
                window.apexRefreshRegion();
            } else {
                lock(false);
                var err = 'Could not complete that.';
                if (res.d) {
                    if (res.d.message) err = res.d.message;
                    else if (res.d.errors) { var f = Object.values(res.d.errors)[0]; if (f && f[0]) err = f[0]; }
                }
                if (window.apexToast) window.apexToast(err, 'error'); else alert(err);
            }
        }).catch(function () {
            form.__inpBusy = false;
            lock(false);
            HTMLFormElement.prototype.submit.call(form);   // network hiccup — real submit
        });
    };

    // Plain (non-confirm) in-place forms: intercept their submit directly.
    // Confirm forms are handled after confirmation by interactions.js -> go().
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.hasAttribute || !form.hasAttribute('data-inplace')) return;
        var needsConfirm = form.hasAttribute('data-confirm-delete') || form.hasAttribute('data-confirm-action');
        if (needsConfirm && form.dataset.confirmed !== '1') return;   // let the confirm flow run first
        e.preventDefault();
        window.apexSubmitInPlace(form);
    });
})();
