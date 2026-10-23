<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="facebook-domain-verification" content="y1dk6klawxgk5dafue8fvhyj3grd02" />
<title>@yield('title', 'Apex Growth Solutions — Credit Report Review & Dispute Assistance')</title>
<meta name="description" content="@yield('description', 'Apex Growth Solutions reviews your three-bureau credit reports and helps you dispute information that may be inaccurate, incomplete or unverifiable under the FCRA and FDCPA.')">
<link rel="canonical" href="{{ url()->current() }}" />
@unless (config('app.consumers_live'))
<meta name="robots" content="noindex, nofollow">
@endunless
<meta property="og:type" content="website">
<meta property="og:site_name" content="Apex Growth Solutions">
<meta property="og:title" content="@yield('title', 'Apex Growth Solutions — Credit Report Review & Dispute Assistance')">
<meta property="og:description" content="@yield('description', 'Professional credit report review and dispute assistance.')">
<meta property="og:url" content="{{ url()->current() }}">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="alternate icon" href="/Images/logo.png">
<link rel="apple-touch-icon" href="/Images/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/consumers.css?v={{ @filemtime(public_path('css/consumers.css')) ?: '1' }}">
@stack('head')
</head>
<body>
<header class="site-header">
    <div class="container nav">
        <a class="nav-logo" href="{{ route('consumers.home') }}" aria-label="Apex Growth Solutions home">
            <img src="/Images/logo.png" alt="Apex Growth Solutions" width="180" height="48">
        </a>
        <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false" aria-controls="navLinks"><span></span></button>
        <ul class="nav-links" id="navLinks">
            <li><a href="{{ route('consumers.services') }}" class="{{ request()->routeIs('consumers.services') ? 'active' : '' }}">Services</a></li>
            <li><a href="{{ route('consumers.how-it-works') }}" class="{{ request()->routeIs('consumers.how-it-works') ? 'active' : '' }}">How It Works</a></li>
            <li><a href="{{ route('consumers.packages') }}" class="{{ request()->routeIs('consumers.packages') ? 'active' : '' }}">Packages</a></li>
            <li><a href="{{ route('consumers.results') }}" class="{{ request()->routeIs('consumers.results') ? 'active' : '' }}">Results</a></li>
            <li><a href="{{ route('consumers.faq') }}" class="{{ request()->routeIs('consumers.faq') ? 'active' : '' }}">FAQ</a></li>
            <li><a href="{{ route('consumers.about') }}" class="{{ request()->routeIs('consumers.about') ? 'active' : '' }}">About</a></li>
            <li><a href="{{ route('consumers.contact') }}" class="btn btn-primary btn-sm">Get Started</a></li>
        </ul>
    </div>
</header>

<main>
@yield('content')
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <img src="/Images/whitelogo.png" alt="Apex Growth Solutions" width="200" height="52">
                <p>Credit report review and dispute assistance, built on consumer protection law and clear communication.</p>
            </div>
            <div>
                <h4>Explore</h4>
                <ul>
                    <li><a href="{{ route('consumers.services') }}">Services</a></li>
                    <li><a href="{{ route('consumers.how-it-works') }}">How It Works</a></li>
                    <li><a href="{{ route('consumers.packages') }}">Packages</a></li>
                    <li><a href="{{ route('consumers.results') }}">Results</a></li>
                    <li><a href="{{ route('consumers.score-guide') }}">Score Guide</a></li>
                    <li><a href="{{ route('consumers.faq') }}">FAQ</a></li>
                </ul>
            </div>
            <div>
                <h4>Legal</h4>
                <ul>
                    <li><a href="{{ route('consumers.credit-rights') }}">Consumer Credit File Rights</a></li>
                    <li><a href="{{ route('consumers.cancellation') }}">Notice of Cancellation</a></li>
                    <li><a href="{{ route('consumers.privacy') }}">Privacy Policy</a></li>
                    <li><a href="{{ route('consumers.terms') }}">Terms of Service</a></li>
                </ul>
            </div>
            <div>
                <h4>Contact</h4>
                <ul>
                    <li><a href="https://wa.me/923013061491" target="_blank" rel="noopener">WhatsApp: +92 301 3061491</a></li>
                    <li><a href="mailto:apexgrow@apexgrowthsolution.com">apexgrow@apexgrowthsolution.com</a></li>
                    <li><a href="{{ route('consumers.contact') }}">Contact form</a></li>
                    <li><a href="{{ route('consumers.about') }}">About us</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-legal">
            <p>&copy; {{ date('Y') }} Apex Growth Solutions. All rights reserved.</p>
            <p>Apex Growth Solutions provides credit education, credit report analysis and dispute assistance. We are not a law firm and do not provide legal advice. We do not guarantee the removal of accurate information or any specific credit score increase. Results vary based on each consumer's credit profile and circumstances.</p>
            <p>Apex Growth Solutions is a credit repair organization as defined under the Credit Repair Organizations Act (CROA), 15 U.S.C. § 1679 et seq. You have the right to dispute inaccurate information in your credit report by contacting the credit bureau directly.</p>
        </div>
    </div>
</footer>

<script>
(function () {
    var t = document.getElementById('navToggle'), n = document.getElementById('navLinks');
    if (!t || !n) return;
    t.addEventListener('click', function () {
        var open = n.classList.toggle('open');
        t.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
})();
</script>
@stack('scripts')
</body>
</html>
