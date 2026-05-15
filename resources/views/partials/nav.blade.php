<nav class="nav" id="nav">
  <div class="nav-inner">
    <a href="/" class="logo"><img src="/Images/logo.png" alt="Apex Growth Systems" class="logo-img"></a>
    <ul class="nav-links" id="navLinks">
      <li><a href="/">Home</a></li>
      <li><a href="/#process">Fulfillment Process</a></li>
      <li><a href="/#services">Services</a></li>
      <li><a href="/results">Business Results</a></li>
      <li><a href="/#faq">FAQ</a></li>
      <li><a href="/contact">Contact</a></li>
      <li><a href="{{ route('client.login') }}">Business Owner Login</a></li>
    </ul>
    @unless(request()->is('trial'))
      <a href="/trial" class="btn btn-primary"><span>Try 5 Test Clients</span> <span class="arrow">&rarr;</span></a>
    @endunless
    <div class="mobile-toggle" id="mobileToggle" aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </div>
  </div>
</nav>
