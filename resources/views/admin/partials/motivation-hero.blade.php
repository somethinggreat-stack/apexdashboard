{{-- Motivational hero shown at the top of every super-admin page (except the
     dashboard, which has its own welcome header). One quote per page view,
     cycling through the list one-by-one via a per-session counter. Super admin
     only — rendered from layouts/admin-pro. --}}
@php
    $motivQuotes = [
        "Success is built one disciplined action at a time.",
        "Great teams don't wait for opportunities—they create them.",
        "Excellence is a habit, not an achievement.",
        "Every client deserves your best, every single day.",
        "Consistency beats talent when talent lacks discipline.",
        "The small details you master today become tomorrow's big wins.",
        "Work with purpose. Deliver with excellence.",
        "Progress is earned through focus, not excuses.",
        "Lead by example. Inspire through action.",
        "Every completed task is a step toward something greater.",
        "Professionalism is doing the right thing—even when no one is watching.",
        "A strong team is built on trust, accountability, and execution.",
        "Your attitude determines the quality of your results.",
        "Don't chase perfection. Chase consistent improvement.",
        "Today's effort becomes tomorrow's reputation.",
        "Winning starts with showing up prepared.",
        "The difference between average and exceptional is consistency.",
        "Focus on solutions, not obstacles.",
        "Every challenge is an opportunity to grow stronger.",
        "Success belongs to those who execute, not those who hesitate.",
    ];
    $mi = (int) session('motiv_quote_i', 0);
    $motivQuote = $motivQuotes[$mi % count($motivQuotes)];
    session(['motiv_quote_i' => ($mi + 1) % count($motivQuotes)]);
@endphp
@php
    // Page buttons that used to live in the white top bar now ride in this banner.
    // (The client search is rendered full-width below the banner, not here.)
    $motivHasActions = $__env->hasSection('topbar-content')
        || $__env->hasSection('topbar-action');
@endphp
<div class="motiv-hero">
    <div class="motiv-hero-body">
        <div class="motiv-hero-text">
            <span class="motiv-quote">&ldquo;{{ $motivQuote }}&rdquo;</span>
        </div>
        @if ($motivHasActions)
            {{-- Pages with buttons (Add New Client, back/delete): show the
                 buttons on the right, no animation. --}}
            <div class="motiv-actions">
                @yield('topbar-content')
                @yield('topbar-action')
            </div>
        @else
            {{-- Pages without buttons: animation + tagline on the right. --}}
            <div class="motiv-hype" aria-hidden="true">
                <div id="motivHero3d" class="motiv-anim"></div>
                <div class="motiv-tag">Lead by example.</div>
            </div>
        @endif
    </div>
</div>

{{-- Styling lives in admin-pro.css: this partial is included from the layout,
     which renders after the <head> stack has already flushed, so a @push('head')
     here would be dropped. The scripts stack (below) is fine — it's at the end. --}}
@once
@push('scripts')
<script src="{{ asset('js/lottie-light.min.js') }}"></script>
<script>
(function () {
    var host = document.getElementById('motivHero3d');
    if (!host || !window.lottie) return;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    try {
        window.lottie.loadAnimation({
            container: host,
            renderer: 'svg',
            loop: true,
            autoplay: !reduce,
            path: '{{ asset('lottie/superfatmanwalk.json') }}'
        });
    } catch (e) {}
})();
</script>
@endpush
@endonce
