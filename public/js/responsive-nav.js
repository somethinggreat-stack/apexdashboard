/* ==========================================================================
   responsive-nav.js — one off-canvas sidebar drawer controller for every
   console (super/VA "pro", leads, and the business-owner portal).

   Wired purely through data attributes so all three layouts share this file
   instead of each shipping its own inline copy:

     <button data-drawer-toggle="#sidebar" data-drawer-scrim="#sidebarScrim">

   Behaviour: open/close via the button, close on scrim click, on Escape, on
   following a nav link (route change), and when the viewport grows past the
   mobile breakpoint. Locks background scroll while open and keeps focus sane.
   Pure vanilla, no dependencies, safe to load on pages without a drawer.
   ========================================================================== */
(function () {
    'use strict';

    var MOBILE = 900;                       // matches the CSS drawer breakpoint
    var toggles = [].slice.call(document.querySelectorAll('[data-drawer-toggle]'));
    if (!toggles.length) return;

    var openPanel = null;
    var openScrim = null;
    var lastFocus = null;

    function panelOf(t) { return document.querySelector(t.getAttribute('data-drawer-toggle')); }
    function scrimOf(t) {
        var sel = t.getAttribute('data-drawer-scrim');
        return sel ? document.querySelector(sel) : null;
    }

    function open(toggle) {
        var panel = panelOf(toggle);
        if (!panel) return;
        var scrim = scrimOf(toggle);

        lastFocus = toggle;
        panel.classList.add('open');
        if (scrim) scrim.classList.add('open');
        document.body.classList.add('drawer-open');
        toggle.setAttribute('aria-expanded', 'true');

        openPanel = panel;
        openScrim = scrim;

        // Move focus into the drawer for keyboard/screen-reader users.
        var first = panel.querySelector('a, button, input, select, textarea, [tabindex]');
        if (first) { try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); } }
    }

    function close() {
        if (!openPanel) return;
        openPanel.classList.remove('open');
        if (openScrim) openScrim.classList.remove('open');
        document.body.classList.remove('drawer-open');
        toggles.forEach(function (t) { t.setAttribute('aria-expanded', 'false'); });
        openPanel = null;
        openScrim = null;
        if (lastFocus) {
            try { lastFocus.focus({ preventScroll: true }); } catch (e) { /* noop */ }
            lastFocus = null;
        }
    }

    toggles.forEach(function (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            (openPanel && openPanel === panelOf(toggle)) ? close() : open(toggle);
        });

        var scrim = scrimOf(toggle);
        if (scrim && !scrim._drawerWired) {
            scrim._drawerWired = true;
            scrim.addEventListener('click', close);
        }

        var panel = panelOf(toggle);
        if (panel && !panel._drawerWired) {
            panel._drawerWired = true;
            // Following a link changes the route → close so the new page starts clean.
            panel.addEventListener('click', function (e) { if (e.target.closest('a')) close(); });
        }
    });

    document.addEventListener('keydown', function (e) {
        if ((e.key === 'Escape' || e.key === 'Esc') && openPanel) close();
    });

    // Reset when the viewport grows back to desktop (rAF-debounced — no thrash).
    var raf = 0;
    window.addEventListener('resize', function () {
        if (raf) cancelAnimationFrame(raf);
        raf = requestAnimationFrame(function () {
            if (window.innerWidth > MOBILE && openPanel) close();
        });
    });
})();
