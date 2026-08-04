{{-- Motivational hero shown at the top of every business-owner page, mirroring
     the admin console's banner. Business-owner-specific quotes (kept separate
     from the VA/admin set). When a page carries action buttons they ride on the
     right of the banner (no animation); otherwise the "Lead by example" lottie
     shows there. Included from layouts/client. --}}
@php
    // The dashboard shows a personal "Welcome back" instead of a quote (it
    // supplies @section('hero-welcome')). Every other page rotates a quote.
    $boHasWelcome = $__env->hasSection('hero-welcome');

    // Pages with buttons show them on the right (no animation), same as the VA
    // banner. Pages without buttons get the lottie instead.
    $boHasActions = $__env->hasSection('topbar-content') || $__env->hasSection('topbar-action');

    $boQuote = null;
    if (!$boHasWelcome) {
        $boQuotes = [
            "Your clients' success is your reputation — we protect both.",
            "Great results speak louder than promises.",
            "Every dispute resolved is a life changed.",
            "Your brand grows when your clients win.",
            "Trust is built one restored score at a time.",
            "Behind every score is a family's fresh start.",
            "You bring the clients — we deliver the results.",
            "A satisfied client is your best marketing.",
            "Credit repaired today, opportunities unlocked tomorrow.",
            "Your business rises on the results we deliver together.",
            "Consistency turns clients into lifelong referrals.",
            "Every point gained is a promise kept.",
            "Success is a partnership — let's build it together.",
            "Real results. Real trust. Real growth.",
            "Your vision, our execution, their transformation.",
            "Empowering your clients empowers your business.",
            "Turning setbacks into fresh starts, every single day.",
            "The strongest brands are built on delivered promises.",
            "When your clients succeed, everybody wins.",
            "Growth follows those who serve with excellence.",
        ];
        $bi = (int) session('bo_motiv_quote_i', 0);
        $boQuote = $boQuotes[$bi % count($boQuotes)];
        session(['bo_motiv_quote_i' => ($bi + 1) % count($boQuotes)]);
    }
@endphp
<div class="motiv-hero">
    <div class="motiv-hero-body">
        <div class="motiv-hero-text">
            @if ($boHasWelcome)
                @yield('hero-welcome')
            @else
                <span class="motiv-quote">&ldquo;{{ $boQuote }}&rdquo;</span>
            @endif
        </div>
        @if ($boHasActions)
            <div class="motiv-actions">
                @yield('topbar-content')
                @yield('topbar-action')
            </div>
        @else
            <div class="motiv-hype" aria-hidden="true">
                <div id="boMotivHero3d" class="motiv-anim"></div>
                <div class="motiv-tag">Lead by example.</div>
            </div>
        @endif
    </div>
</div>

@once
@push('scripts')
<script src="{{ asset('js/lottie-light.min.js') }}"></script>
<script>
(function () {
    var host = document.getElementById('boMotivHero3d');
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
