{{-- Shared welcome hero (inner content). The host page wraps this in
     <div class="dash-hero"> and supplies the layout-specific breakout CSS.
     Used by the super-admin dashboard and the VA landing (client-selector). --}}
@php $heroMe = $heroMe ?? Auth::guard('admin')->user(); @endphp
<div class="dash-hero-body">
    <div class="dash-hero-text">
        <span class="dash-greet">“He who cannot be a good follower cannot be a good leader.”</span>
        <h1 class="dash-name">Welcome {{ $heroMe?->full_name ?: 'Admin' }}.</h1>
        <div class="dash-date">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="17" rx="2.5"/><line x1="3" y1="9.5" x2="21" y2="9.5"/><line x1="8" y1="2.5" x2="8" y2="6"/><line x1="16" y1="2.5" x2="16" y2="6"/></svg>
            <span id="dashDate">{{ now()->format('l, j F Y') }}</span>
        </div>
    </div>
    <div class="dash-hero-hype" aria-hidden="true">
        <div id="dashHero3d" class="dash-hero-anim"></div>
        <div class="dash-hype-tag">Lead by example.</div>
    </div>
</div>

@once
@push('scripts')
<script>
(function () {
    // Show today's date in the viewer's local time (server runs on UTC).
    var del = document.getElementById('dashDate');
    if (del) { try { del.textContent = new Date().toLocaleDateString(undefined, { weekday:'long', day:'numeric', month:'long', year:'numeric' }); } catch (e) {} }
})();
</script>
<script src="{{ asset('js/lottie-light.min.js') }}"></script>
<script>
(function () {
    var host = document.getElementById('dashHero3d');
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
