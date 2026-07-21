{{--
    Inactivity warning + auto sign-out. Included by every signed-in layout
    (super/VA console and the business-owner portal).

    Pass $logoutRoute when including, e.g.:
        @include('partials.idle-logout', ['logoutRoute' => route('admin.logout')])
--}}
<div id="idleGuard"
     data-idle-minutes="10"
     data-grace-seconds="60"
     data-logout-url="{{ $logoutRoute }}"
     data-keepalive-url="{{ route('session.keepalive') }}"></div>

<div id="idleModal" class="idle-modal" role="alertdialog" aria-modal="true" aria-hidden="true"
     aria-labelledby="idleTitle" aria-describedby="idleDesc">
    <div class="idle-box">
        <div class="idle-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>
            </svg>
        </div>
        <h3 id="idleTitle">Still there?</h3>
        <p id="idleDesc">
            You've been inactive for 10 minutes. For security you'll be signed out in
            <strong><span id="idleCount">60</span></strong> seconds.
        </p>
        <div class="idle-actions">
            <button type="button" id="idleStay" class="idle-btn idle-btn-primary">Stay signed in</button>
            <button type="button" id="idleOut" class="idle-btn idle-btn-ghost">Log out now</button>
        </div>
    </div>
</div>

{{-- Style + script are inline on purpose: this partial is included from the
     layout BODY, and @push('head') would be dropped because that stack has
     already been rendered by then. --}}
@once
<style>
    .idle-modal {
        position: fixed; inset: 0; z-index: 99999; display: none;
        align-items: center; justify-content: center;
        background: rgba(8, 12, 32, .62); backdrop-filter: blur(2px);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    .idle-modal.is-open { display: flex; }
    .idle-box {
        width: min(420px, calc(100vw - 32px));
        background: #fff; border-radius: 18px; padding: 26px 26px 22px;
        box-shadow: 0 24px 60px rgba(8, 12, 32, .32); text-align: center;
    }
    .idle-icon {
        width: 52px; height: 52px; margin: 0 auto 14px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: #eef2ff; color: #4f46e5;
    }
    .idle-icon svg { width: 26px; height: 26px; }
    .idle-box h3 { margin: 0 0 8px; font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -.01em; }
    .idle-box p  { margin: 0 0 20px; font-size: 14px; line-height: 1.55; color: #475569; }
    .idle-box strong { color: #0f172a; }
    .idle-actions { display: flex; gap: 10px; }
    .idle-btn {
        flex: 1; padding: 11px 16px; border-radius: 11px; cursor: pointer;
        font: inherit; font-size: 14px; font-weight: 600; transition: filter .15s, background .15s;
    }
    .idle-btn-primary {
        border: 0; color: #fff; background: linear-gradient(135deg, #4f46e5, #4338ca);
        box-shadow: 0 8px 20px rgba(79, 70, 229, .3);
    }
    .idle-btn-primary:hover { filter: brightness(1.08); }
    .idle-btn-ghost { background: #f1f5f9; border: 1px solid #e2e8f0; color: #475569; }
    .idle-btn-ghost:hover { background: #e2e8f0; }
</style>

<script src="{{ asset('js/idle-logout.js') }}" defer></script>
@endonce
