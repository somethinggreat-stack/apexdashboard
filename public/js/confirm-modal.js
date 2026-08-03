/* ==========================================================================
   confirm-modal.js — centered success confirmation for the big actions.

   Client added / Client updated / Business owner added / Business owner updated
   post a `confirm` flash. The layout renders it as a hidden .confirm-flash
   element; this turns it into a centered modal with an animated tick and an OK
   button. It closes when OK is pressed (VA continues) or on its own after 3
   seconds — whichever comes first. Vanilla, no dependencies, theme-aware via
   the same CSS variables the rest of the dashboard uses.

   Manual API:  window.apexConfirm('Client updated');
   ========================================================================== */
(function () {
    'use strict';

    var AUTO_MS = 3000;   // auto-dismiss after 3s of no interaction
    var injected = false;

    function injectStyles() {
        if (injected) return;
        injected = true;
        var css = [
            '.apex-confirm-overlay{position:fixed;inset:0;z-index:4000;display:flex;',
            'align-items:center;justify-content:center;padding:20px;',
            'background:rgba(15,23,42,.55);backdrop-filter:blur(3px);',
            '-webkit-backdrop-filter:blur(3px);opacity:0;transition:opacity .22s ease;}',
            '.apex-confirm-overlay.show{opacity:1;}',

            '.apex-confirm-card{width:100%;max-width:380px;background:var(--surface,#fff);',
            'border:1px solid var(--border,#e2e8f0);border-radius:20px;',
            'box-shadow:0 30px 70px rgba(15,23,42,.35);padding:34px 30px 26px;',
            'text-align:center;color:var(--text,#0f172a);position:relative;overflow:hidden;',
            'transform:translateY(10px) scale(.94);opacity:0;',
            'transition:transform .28s cubic-bezier(.34,1.56,.64,1),opacity .22s ease;}',
            '.apex-confirm-overlay.show .apex-confirm-card{transform:translateY(0) scale(1);opacity:1;}',

            '.apex-confirm-badge{width:76px;height:76px;margin:0 auto 18px;border-radius:50%;',
            'display:flex;align-items:center;justify-content:center;',
            'background:linear-gradient(135deg,#34d399,#059669);',
            'box-shadow:0 10px 26px rgba(5,150,105,.4);}',
            '.apex-confirm-badge svg{width:38px;height:38px;stroke:#fff;stroke-width:3.2;',
            'fill:none;stroke-linecap:round;stroke-linejoin:round;',
            'stroke-dasharray:26;stroke-dashoffset:26;',
            'animation:apexCheck .4s .18s cubic-bezier(.65,0,.45,1) forwards;}',
            '@keyframes apexCheck{to{stroke-dashoffset:0;}}',
            '.apex-confirm-badge{animation:apexPop .34s cubic-bezier(.34,1.56,.64,1) both;}',
            '@keyframes apexPop{0%{transform:scale(.4);opacity:0;}100%{transform:scale(1);opacity:1;}}',

            '.apex-confirm-title{font-size:20px;font-weight:800;line-height:1.25;',
            'letter-spacing:-.01em;margin:0 0 6px;}',
            '.apex-confirm-sub{font-size:13.5px;color:var(--muted,#64748b);margin:0 0 22px;}',

            '.apex-confirm-ok{appearance:none;border:0;cursor:pointer;width:100%;',
            'padding:12px 18px;border-radius:12px;font-size:15px;font-weight:700;',
            'color:#fff;background:linear-gradient(135deg,#34d399,#059669);',
            'box-shadow:0 8px 20px rgba(5,150,105,.32);transition:filter .15s,transform .05s;}',
            '.apex-confirm-ok:hover{filter:brightness(1.05);}',
            '.apex-confirm-ok:active{transform:translateY(1px);}',
            '.apex-confirm-ok:focus-visible{outline:2px solid #059669;outline-offset:2px;}',

            '.apex-confirm-bar{position:absolute;left:0;bottom:0;height:3px;width:100%;',
            'transform-origin:left;background:linear-gradient(90deg,#34d399,#059669);',
            'animation:apexBar linear forwards;}',
            '@keyframes apexBar{from{transform:scaleX(1);}to{transform:scaleX(0);}}',

            '@media (prefers-reduced-motion:reduce){',
            '.apex-confirm-overlay,.apex-confirm-card,.apex-confirm-badge,',
            '.apex-confirm-badge svg,.apex-confirm-bar{animation:none!important;transition:none!important;}',
            '.apex-confirm-badge svg{stroke-dashoffset:0;}}'
        ].join('');
        var s = document.createElement('style');
        s.textContent = css;
        document.head.appendChild(s);
    }

    var CHECK = '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';

    function show(message) {
        message = (message || '').toString().trim();
        if (!message) return;
        injectStyles();

        // Only one at a time.
        var existing = document.querySelector('.apex-confirm-overlay');
        if (existing && existing.parentNode) existing.parentNode.removeChild(existing);

        var overlay = document.createElement('div');
        overlay.className = 'apex-confirm-overlay';
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-live', 'assertive');
        overlay.innerHTML =
            '<div class="apex-confirm-card">' +
                '<div class="apex-confirm-badge">' + CHECK + '</div>' +
                '<h3 class="apex-confirm-title"></h3>' +
                '<p class="apex-confirm-sub">Changes saved successfully.</p>' +
                '<button type="button" class="apex-confirm-ok">OK</button>' +
                '<span class="apex-confirm-bar" style="animation-duration:' + AUTO_MS + 'ms"></span>' +
            '</div>';
        overlay.querySelector('.apex-confirm-title').textContent = message;

        document.body.appendChild(overlay);
        requestAnimationFrame(function () { overlay.classList.add('show'); });

        var timer = setTimeout(dismiss, AUTO_MS);
        var closed = false;
        function dismiss() {
            if (closed) return;
            closed = true;
            clearTimeout(timer);
            document.removeEventListener('keydown', onKey);
            overlay.classList.remove('show');
            setTimeout(function () { if (overlay.parentNode) overlay.parentNode.removeChild(overlay); }, 240);
        }
        function onKey(e) { if (e.key === 'Escape' || e.key === 'Enter') dismiss(); }

        overlay.querySelector('.apex-confirm-ok').addEventListener('click', dismiss);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) dismiss(); });
        document.addEventListener('keydown', onKey);
        overlay.querySelector('.apex-confirm-ok').focus();
    }

    window.apexConfirm = show;

    document.addEventListener('DOMContentLoaded', function () {
        var el = document.querySelector('.confirm-flash');
        if (el) {
            show(el.textContent);
            if (el.parentNode) el.parentNode.removeChild(el);
        }
    });
})();
