/* ==========================================================================
   confirm-modal.js — centered modals: success, error, and destructive confirm.

   Public API (all vanilla, theme-aware, no dependencies):
     window.apexConfirm('Client updated')            success tick, OK, auto-3s
     window.apexError('Upload failed', 'Sorry')      red X, stays until dismissed
     window.apexConfirmDelete({                       danger confirm (Cancel/Delete)
        title, message, name, okLabel, onConfirm })   if `name` set, must be typed

   Flash-driven (rendered hidden by the layout):
     .confirm-flash        -> success modal
     .confirm-error-flash  -> error modal
   ========================================================================== */
(function () {
    'use strict';

    var AUTO_MS = 3000;   // success auto-dismiss
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

            '.apex-confirm-card{width:100%;max-width:390px;background:var(--surface,#fff);',
            'border:1px solid var(--border,#e2e8f0);border-radius:20px;',
            'box-shadow:0 30px 70px rgba(15,23,42,.35);padding:34px 30px 26px;',
            'text-align:center;color:var(--text,#0f172a);position:relative;overflow:hidden;',
            'transform:translateY(10px) scale(.94);opacity:0;',
            'transition:transform .28s cubic-bezier(.34,1.56,.64,1),opacity .22s ease;}',
            '.apex-confirm-overlay.show .apex-confirm-card{transform:translateY(0) scale(1);opacity:1;}',

            '.apex-confirm-badge{width:76px;height:76px;margin:0 auto 18px;border-radius:50%;',
            'display:flex;align-items:center;justify-content:center;',
            'animation:apexPop .34s cubic-bezier(.34,1.56,.64,1) both;}',
            '.apex-confirm-badge.ok{background:linear-gradient(135deg,#34d399,#059669);',
            'box-shadow:0 10px 26px rgba(5,150,105,.4);}',
            '.apex-confirm-badge.err,.apex-confirm-badge.danger{',
            'background:linear-gradient(135deg,#f87171,#dc2626);',
            'box-shadow:0 10px 26px rgba(220,38,38,.4);}',
            '.apex-confirm-badge svg{width:38px;height:38px;stroke:#fff;stroke-width:3.2;',
            'fill:none;stroke-linecap:round;stroke-linejoin:round;',
            'stroke-dasharray:48;stroke-dashoffset:48;',
            'animation:apexDraw .42s .16s cubic-bezier(.65,0,.45,1) forwards;}',
            '@keyframes apexDraw{to{stroke-dashoffset:0;}}',
            '@keyframes apexPop{0%{transform:scale(.4);opacity:0;}100%{transform:scale(1);opacity:1;}}',

            '.apex-confirm-title{font-size:20px;font-weight:800;line-height:1.25;',
            'letter-spacing:-.01em;margin:0 0 6px;}',
            '.apex-confirm-sub{font-size:13.5px;color:var(--muted,#64748b);margin:0 0 22px;',
            'line-height:1.45;}',

            '.apex-confirm-input{width:100%;box-sizing:border-box;padding:11px 13px;margin:0 0 18px;',
            'border:1px solid var(--border,#e2e8f0);border-radius:11px;font-size:14px;',
            'background:var(--bg,#f8fafc);color:var(--text,#0f172a);text-align:center;}',
            '.apex-confirm-input:focus{outline:2px solid #dc2626;outline-offset:1px;}',

            '.apex-confirm-actions{display:flex;gap:10px;}',
            '.apex-confirm-actions .apex-confirm-btn{flex:1;}',
            '.apex-confirm-btn{appearance:none;border:0;cursor:pointer;width:100%;',
            'padding:12px 18px;border-radius:12px;font-size:15px;font-weight:700;',
            'transition:filter .15s,transform .05s,opacity .15s;}',
            '.apex-confirm-btn:active{transform:translateY(1px);}',
            '.apex-confirm-btn.ok{color:#fff;background:linear-gradient(135deg,#34d399,#059669);',
            'box-shadow:0 8px 20px rgba(5,150,105,.32);}',
            '.apex-confirm-btn.danger{color:#fff;background:linear-gradient(135deg,#f87171,#dc2626);',
            'box-shadow:0 8px 20px rgba(220,38,38,.32);}',
            '.apex-confirm-btn.ghost{background:var(--bg,#f1f5f9);color:var(--text,#0f172a);',
            'border:1px solid var(--border,#e2e8f0);}',
            '.apex-confirm-btn:hover{filter:brightness(1.05);}',
            '.apex-confirm-btn:disabled{opacity:.45;cursor:not-allowed;filter:none;}',
            '.apex-confirm-btn:focus-visible{outline:2px solid #059669;outline-offset:2px;}',

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
    var CROSS = '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

    // Low-level modal. opts: {kind:'ok'|'err'|'danger', title, sub, autoMs,
    // confirmLabel, cancel:bool, requireText, onConfirm}
    function open(opts) {
        opts = opts || {};
        injectStyles();

        var prev = document.querySelector('.apex-confirm-overlay');
        if (prev && prev.parentNode) prev.parentNode.removeChild(prev);

        var kind = opts.kind || 'ok';
        var isOk = kind === 'ok';
        var badgeClass = isOk ? 'ok' : (kind === 'err' ? 'err' : 'danger');
        var btnClass = isOk ? 'ok' : 'danger';
        var icon = isOk ? CHECK : CROSS;

        var overlay = document.createElement('div');
        overlay.className = 'apex-confirm-overlay';
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-modal', 'true');

        var html =
            '<div class="apex-confirm-card">' +
                '<div class="apex-confirm-badge ' + badgeClass + '">' + icon + '</div>' +
                '<h3 class="apex-confirm-title"></h3>' +
                (opts.sub ? '<p class="apex-confirm-sub"></p>' : '');

        if (opts.requireText) {
            html += '<input type="text" class="apex-confirm-input" autocomplete="off" ' +
                    'placeholder="Type the name to confirm">';
        }

        if (opts.cancel) {
            html += '<div class="apex-confirm-actions">' +
                        '<button type="button" class="apex-confirm-btn ghost" data-act="cancel">Cancel</button>' +
                        '<button type="button" class="apex-confirm-btn ' + btnClass + '" data-act="ok">' +
                            (opts.confirmLabel || 'Confirm') + '</button>' +
                    '</div>';
        } else {
            html += '<button type="button" class="apex-confirm-btn ' + btnClass + '" data-act="ok">' +
                        (opts.confirmLabel || 'OK') + '</button>';
        }

        if (opts.autoMs) {
            html += '<span class="apex-confirm-bar" style="animation-duration:' + opts.autoMs + 'ms"></span>';
        }
        html += '</div>';
        overlay.innerHTML = html;

        overlay.querySelector('.apex-confirm-title').textContent = opts.title || '';
        if (opts.sub) overlay.querySelector('.apex-confirm-sub').textContent = opts.sub;

        document.body.appendChild(overlay);
        requestAnimationFrame(function () { overlay.classList.add('show'); });

        var okBtn = overlay.querySelector('[data-act="ok"]');
        var input = overlay.querySelector('.apex-confirm-input');
        var closed = false;
        var timer = opts.autoMs ? setTimeout(function () { close(false); }, opts.autoMs) : null;

        function close(confirmed) {
            if (closed) return;
            closed = true;
            if (timer) clearTimeout(timer);
            document.removeEventListener('keydown', onKey);
            overlay.classList.remove('show');
            setTimeout(function () { if (overlay.parentNode) overlay.parentNode.removeChild(overlay); }, 240);
            if (confirmed && typeof opts.onConfirm === 'function') opts.onConfirm();
        }
        function onKey(e) {
            if (e.key === 'Escape') close(false);
            else if (e.key === 'Enter' && !okBtn.disabled) close(true);
        }

        okBtn.addEventListener('click', function () { close(true); });
        var cancelBtn = overlay.querySelector('[data-act="cancel"]');
        if (cancelBtn) cancelBtn.addEventListener('click', function () { close(false); });
        // Backdrop closes only non-destructive modals (avoid accidental confirm loss).
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay && kind !== 'danger') close(false);
        });
        document.addEventListener('keydown', onKey);

        if (input) {
            okBtn.disabled = true;
            var want = (opts.requireText || '').trim().toLowerCase();
            input.addEventListener('input', function () {
                okBtn.disabled = input.value.trim().toLowerCase() !== want;
            });
            setTimeout(function () { input.focus(); }, 60);
        } else {
            setTimeout(function () { okBtn.focus(); }, 60);
        }
    }

    // --- Public API ---------------------------------------------------------
    window.apexConfirm = function (message) {
        message = (message || '').toString().trim();
        if (!message) return;
        open({ kind: 'ok', title: message, sub: 'Changes saved successfully.', autoMs: AUTO_MS });
    };

    window.apexError = function (message, title) {
        open({
            kind: 'err',
            title: (title || 'Something went wrong').toString(),
            sub: (message || 'Please try again.').toString(),
            confirmLabel: 'OK'
        });
    };

    window.apexConfirmDelete = function (o) {
        o = o || {};
        open({
            kind: 'danger',
            title: o.title || 'Are you sure?',
            sub: o.message || 'This action cannot be undone.',
            cancel: true,
            confirmLabel: o.okLabel || 'Delete',
            requireText: o.name || null,
            onConfirm: o.onConfirm
        });
    };

    // Positive (green) confirm — Cancel / Confirm, no auto-dismiss. For
    // significant-but-not-destructive actions like "mark all balances paid".
    window.apexConfirmAction = function (o) {
        o = o || {};
        open({
            kind: 'ok',
            title: o.title || 'Please confirm',
            sub: o.message || '',
            cancel: true,
            confirmLabel: o.okLabel || 'Confirm',
            onConfirm: o.onConfirm
        });
    };

    document.addEventListener('DOMContentLoaded', function () {
        var ok = document.querySelector('.confirm-flash');
        if (ok) { window.apexConfirm(ok.textContent); if (ok.parentNode) ok.parentNode.removeChild(ok); }
        var err = document.querySelector('.confirm-error-flash');
        if (err) { window.apexError(err.textContent); if (err.parentNode) err.parentNode.removeChild(err); }
    });
})();
