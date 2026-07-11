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
<div class="motiv-hero">
    <div class="motiv-hero-body">
        <div class="motiv-hero-text">
            <span class="motiv-quote">&ldquo;{{ $motivQuote }}&rdquo;</span>
        </div>
        <div class="motiv-hype" aria-hidden="true">
            <div id="motivHero3d" class="motiv-anim"></div>
            <div class="motiv-tag">Lead by example.</div>
        </div>
    </div>
</div>

@once
@push('head')
<style>
    /* Full-bleed banner: break out of .pro-content's 24px/26px padding so it
       touches the sidebar and the right edge. Matches the dashboard hero. */
    .motiv-hero {
        position:relative; overflow:hidden;
        margin:-24px -26px 22px; border-radius:0 0 22px 0;
        background:
            linear-gradient(115deg, rgba(12,17,48,.94) 0%, rgba(20,26,62,.84) 45%, rgba(27,19,80,.70) 100%),
            #12163a url("{{ asset('Images/heroimage.png') }}") center/cover no-repeat;
        box-shadow:0 12px 30px rgba(15,23,42,.18);
    }
    .motiv-hero-body {
        position:relative; z-index:1;
        display:flex; align-items:center; justify-content:space-between; gap:20px;
        padding:15px 34px 14px;
    }
    .motiv-hero-text { min-width:0; }
    .motiv-quote {
        display:block; max-width:700px; font-size:20px; line-height:1.3; font-weight:600;
        font-style:italic; letter-spacing:-.01em; color:#eef2ff;
        text-shadow:0 2px 14px rgba(0,0,0,.3); word-break:break-word;
    }
    .motiv-hype { flex:none; display:flex; flex-direction:column; align-items:center; gap:4px; }
    .motiv-anim { width:74px; height:74px; filter:drop-shadow(0 8px 22px rgba(0,0,0,.35)); }
    .motiv-anim svg { width:100% !important; height:100% !important; }
    .motiv-tag {
        font-size:10.5px; font-weight:700; letter-spacing:.15em; text-transform:uppercase;
        color:#c7d0f5; text-shadow:0 2px 10px rgba(0,0,0,.35);
    }
    @media (max-width:1200px) { .motiv-anim { width:64px; height:64px; } }
    @media (max-width:900px) {
        .motiv-hero { margin:-16px -16px 16px; border-radius:0 0 16px 0; }
        .motiv-hero-body { padding:15px 18px; }
        .motiv-quote { font-size:16.5px; }
        .motiv-hype { display:none; }
    }
</style>
@endpush
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
