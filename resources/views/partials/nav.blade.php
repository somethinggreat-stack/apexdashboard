<style>
/* ============================================
   APEX NAV — single source of truth, self-contained.
   Mirrors the canonical homepage nav so every page renders
   identical chrome regardless of host-page CSS. Uses literal
   values (no CSS variables) so it works on any page scope.
   ============================================ */
.nav {
  position: sticky;
  top: 0;
  z-index: 100;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(24px) saturate(200%);
  -webkit-backdrop-filter: blur(24px) saturate(200%);
  border-bottom: 1px solid rgba(226, 232, 240, 0.6);
  transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
  margin: 0;
  padding: 0;
  font-family: 'Geist', -apple-system, BlinkMacSystemFont, sans-serif;
}
.nav.scrolled {
  background: rgba(255, 255, 255, 0.97);
  box-shadow: 0 4px 30px rgba(15, 32, 67, 0.06);
  border-bottom-color: transparent;
}
.nav-inner {
  max-width: 1440px;
  margin: 0 auto;
  padding: 6px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}
.nav .logo { display: flex; align-items: center; }
.nav .logo-img {
  height: 80px;
  width: auto;
  object-fit: contain;
  transition: opacity 0.3s cubic-bezier(0.22, 1, 0.36, 1);
}
.nav .logo:hover .logo-img { opacity: 0.85; }

.nav .nav-links {
  display: flex;
  align-items: center;
  gap: 36px;
  list-style: none;
  padding: 0;
  margin: 0;
}
.nav .nav-links a {
  font-size: 13px;
  font-weight: 500;
  letter-spacing: 0.02em;
  color: #475569;
  text-decoration: none;
  transition: color 0.3s cubic-bezier(0.22, 1, 0.36, 1);
  position: relative;
  padding: 8px 0;
  font-family: inherit;
}
.nav .nav-links a:hover { color: #0F2043; }
.nav .nav-links a::after {
  content: '';
  position: absolute;
  bottom: 0; left: 50%;
  width: 0; height: 2px;
  background: linear-gradient(90deg, #2196F3, #1A6FC4);
  transition: width 0.4s cubic-bezier(0.22, 1, 0.36, 1), left 0.4s cubic-bezier(0.22, 1, 0.36, 1);
  border-radius: 1px;
  box-shadow: 0 0 8px rgba(26, 111, 196, 0.3);
}
.nav .nav-links a:hover::after { width: 100%; left: 0; }

.nav .btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 15px 32px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  border-radius: 6px;
  transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
  position: relative;
  overflow: hidden;
  cursor: pointer;
  background: #0F2043;
  color: #F1F5F9;
  border: 1px solid #0F2043;
  text-decoration: none;
  font-family: inherit;
}
.nav .btn-primary::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, #1A6FC4 0%, #0F2043 100%);
  transform: translateY(101%);
  transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1);
  z-index: 0;
}
.nav .btn-primary span, .nav .btn-primary .arrow { position: relative; z-index: 1; }
.nav .btn-primary:hover::before { transform: translateY(0); }
.nav .btn-primary:hover {
  border-color: #1A6FC4;
  transform: translateY(-3px);
  box-shadow: 0 20px 40px rgba(26, 111, 196, 0.3), 0 0 0 1px rgba(26, 111, 196, 0.1);
}

.nav .mobile-toggle {
  display: none;
  flex-direction: column;
  gap: 5px;
  cursor: pointer;
  padding: 8px;
  background: none;
  border: none;
}
.nav .mobile-toggle span {
  width: 22px;
  height: 2px;
  background: #0F2043;
  transition: all 0.3s;
  display: block;
}

@media (max-width: 1024px) {
  .nav .nav-links { gap: 24px; }
  .nav .nav-links a { font-size: 12.5px; }
}
@media (max-width: 900px) {
  .nav-inner { padding: 4px 20px; }
  .nav .nav-links { display: none; }
  .nav .mobile-toggle { display: flex; }
  .nav .nav-links.open {
    display: flex;
    position: absolute;
    top: 100%;
    left: 0; right: 0;
    flex-direction: column;
    background: #fff;
    padding: 16px 20px;
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
    gap: 14px;
    box-shadow: 0 10px 30px rgba(15, 32, 67, 0.08);
  }
  .nav .btn-primary { padding: 12px 20px; font-size: 11px; }
  .nav .logo-img { height: 64px; }
}
@media (max-width: 600px) {
  .nav-inner { padding: 4px 16px; }
  .nav .logo-img { height: 56px; }
  .nav .btn-primary span:not(.arrow) { display: none; }
  .nav .btn-primary { padding: 10px 14px; }
}
</style>

<nav class="nav" id="nav">
  <div class="nav-inner">
    <a href="/" class="logo"><img src="/Images/logo.png" alt="Apex Growth Systems" class="logo-img"></a>
    <ul class="nav-links" id="navLinks">
      <li><a href="/#services">Services</a></li>
      <li><a href="/results">Business Results</a></li>
      <li><a href="/service-areas">Service Areas</a></li>
      <li><a href="/contact">Contact</a></li>
      <li><a href="{{ route('client.login') }}">Business Owner Login</a></li>
    </ul>
    @unless(request()->is('trial'))
      <a href="/trial" class="btn-primary"><span>Try 5 Test Clients</span> <span class="arrow">&rarr;</span></a>
    @endunless
    <button type="button" class="mobile-toggle" id="mobileToggle" aria-label="Toggle menu" aria-controls="navLinks">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<script>
(function () {
    var toggle = document.getElementById('mobileToggle');
    var links = document.getElementById('navLinks');
    var nav = document.getElementById('nav');
    if (toggle && links) {
        toggle.addEventListener('click', function () {
            links.classList.toggle('open');
        });
        links.addEventListener('click', function (e) {
            if (e.target.tagName === 'A') links.classList.remove('open');
        });
    }
    if (nav) {
        var onScroll = function () {
            if (window.scrollY > 24) nav.classList.add('scrolled');
            else nav.classList.remove('scrolled');
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }
})();
</script>
