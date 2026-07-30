/* ==========================================================================
   theme-toggle.js — light/dark theme switch for the admin consoles.

   The initial theme is set by a tiny inline script in each layout's <head>
   (before first paint, so there's no flash). This file only handles the click:
   flip data-theme on <html>, persist the choice, and keep every toggle's label
   and pressed-state in sync. Icons are swapped by CSS off [data-theme].
   ========================================================================== */
(function () {
    'use strict';

    var KEY = 'apex-theme';

    function current() {
        return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function sync() {
        var dark = current() === 'dark';
        document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
            btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
            var label = btn.querySelector('.theme-toggle-label');
            if (label) label.textContent = dark ? 'Light mode' : 'Dark mode';
        });
    }

    function set(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        try { localStorage.setItem(KEY, theme); } catch (e) { /* private mode */ }
        sync();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-theme-toggle]');
        if (!btn) return;
        e.preventDefault();
        set(current() === 'dark' ? 'light' : 'dark');
    });

    sync();
})();
