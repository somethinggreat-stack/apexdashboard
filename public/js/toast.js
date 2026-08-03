/* ==========================================================================
   toast.js — success/error confirmation popups.

   On every page load it turns a server flash message (rendered as a hidden
   .toast-flash element by the layout) into a slide-in toast with a checkmark
   that auto-dismisses. Any page can also fire one manually: window.apexToast(
   'Saved.', 'success'). Vanilla, no dependencies, theme-aware via CSS.
   ========================================================================== */
(function () {
    'use strict';

    var CHECK = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
    var CROSS = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

    function container() {
        var c = document.querySelector('.toast-container');
        if (!c) {
            c = document.createElement('div');
            c.className = 'toast-container';
            document.body.appendChild(c);
        }
        return c;
    }

    function show(message, type) {
        message = (message || '').toString().trim();
        if (!message) return;
        var isError = type === 'error';

        var t = document.createElement('div');
        t.className = 'toast toast-' + (isError ? 'error' : 'success');
        t.setAttribute('role', 'status');
        t.innerHTML =
            '<span class="toast-ico">' + (isError ? CROSS : CHECK) + '</span>' +
            '<span class="toast-msg"></span>' +
            '<button type="button" class="toast-close" aria-label="Dismiss">&times;</button>';
        t.querySelector('.toast-msg').textContent = message;

        container().appendChild(t);
        requestAnimationFrame(function () { t.classList.add('show'); });

        var timer = setTimeout(dismiss, 4200);
        function dismiss() {
            clearTimeout(timer);
            t.classList.remove('show');
            setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 360);
        }
        t.querySelector('.toast-close').addEventListener('click', dismiss);
    }

    // Manual API for any inline script.
    window.apexToast = show;

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.toast-flash').forEach(function (el) {
            show(el.textContent, el.getAttribute('data-toast') || 'success');
            if (el.parentNode) el.parentNode.removeChild(el);
        });
    });
})();
