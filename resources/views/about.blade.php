<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="Apex Growth Systems — backend credit repair fulfillment partner. We handle dispute workload, bureau follow-up calls, CFPB / FTC documentation, and weekly client reporting for credit repair businesses." />
<title>About | Apex Growth Systems — Credit Repair Fulfillment Partner</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ============================================
   APEX GROWTH SYSTEMS — About Us
   ============================================ */

:root {
  --white: #FFFFFF;
  --paper: #F8FAFC;
  --ivory: #F1F5F9;
  --bone: #E2E8F0;
  --ink: #0F2043;
  --charcoal: #1E3A5F;
  --smoke: #475569;
  --ash: #64748B;
  --dust: #94A3B8;
  --fog: #E2E8F0;
  --gold: #1A6FC4;
  --gold-deep: #0F2043;
  --gold-light: #2196F3;
  --champagne: #DBEAFE;
  --crimson: #DC2626;

  --display: 'Geist', -apple-system, BlinkMacSystemFont, sans-serif;
  --body: 'Geist', -apple-system, BlinkMacSystemFont, sans-serif;
  --mono: 'IBM Plex Mono', 'SF Mono', monospace;

  --ease: cubic-bezier(0.22, 1, 0.36, 1);
  --ease-smooth: cubic-bezier(0.65, 0, 0.35, 1);
  --ease-bounce: cubic-bezier(0.34, 1.56, 0.64, 1);
}

* { margin: 0; padding: 0; box-sizing: border-box; }
::selection { background: var(--ink); color: var(--gold-light); }
html { scroll-behavior: smooth; }

body {
  font-family: var(--body);
  background: var(--white);
  color: var(--ink);
  line-height: 1.6;
  overflow-x: hidden;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

a { color: inherit; text-decoration: none; }
button { font-family: inherit; cursor: pointer; border: none; background: none; color: inherit; }
img { max-width: 100%; display: block; }

/* Gradient text utility */
.gradient-text,
em {
  background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 40%, var(--ink) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  font-style: normal;
}

/* ============================================
   NAV
   ============================================ */
.nav {
  position: sticky;
  top: 0;
  z-index: 100;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border-bottom: 1px solid rgba(226, 232, 240, 0.6);
  transition: background 0.3s var(--ease), box-shadow 0.3s var(--ease);
}

.nav.scrolled {
  background: var(--white);
  box-shadow: 0 1px 8px rgba(15, 32, 67, 0.06);
}

.nav-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 40px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 72px;
}

.logo-img { height: 80px; }

.nav-links {
  display: flex;
  list-style: none;
  gap: 36px;
}

.nav-links a {
  font-size: 13px;
  font-weight: 500;
  color: var(--smoke);
  letter-spacing: 0.02em;
  position: relative;
  transition: color 0.3s var(--ease);
}

.nav-links a::after {
  content: '';
  position: absolute;
  bottom: -4px;
  left: 50%;
  width: 0;
  height: 2px;
  background: var(--gold);
  transform: translateX(-50%);
  transition: width 0.3s var(--ease);
}

.nav-links a:hover {
  color: var(--ink);
}

.nav-links a:hover::after {
  width: 100%;
}

.btn-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--ink);
  color: var(--ivory);
  padding: 10px 22px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  letter-spacing: 0.02em;
  transition: all 0.3s var(--ease);
}

.btn-primary:hover {
  background: var(--charcoal);
  transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(15, 32, 67, 0.2);
}

.btn-primary .arrow {
  transition: transform 0.3s var(--ease);
}

.btn-primary:hover .arrow {
  transform: translateX(3px);
}

/* ============================================
   SCROLL REVEAL
   ============================================ */
.reveal {
  opacity: 0;
  transform: translateY(40px);
  transition: opacity 0.8s var(--ease), transform 0.8s var(--ease);
}

.reveal.visible {
  opacity: 1;
  transform: translateY(0);
}

/* ============================================
   HERO SECTION
   ============================================ */
.hero {
  background: var(--white);
  padding: 120px 40px 100px;
  text-align: center;
}

.hero-inner {
  max-width: 800px;
  margin: 0 auto;
}

.eyebrow {
  font-family: var(--mono);
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.15em;
  color: var(--gold);
  text-transform: uppercase;
  margin-bottom: 24px;
}

.hero h1 {
  font-family: var(--display);
  font-size: clamp(36px, 5vw, 56px);
  font-weight: 600;
  line-height: 1.15;
  color: var(--ink);
  margin-bottom: 28px;
}

.hero p {
  font-size: 17px;
  line-height: 1.75;
  color: var(--smoke);
  max-width: 640px;
  margin: 0 auto;
}

/* ============================================
   MISSION SECTION
   ============================================ */
.mission {
  background: var(--white);
  padding: 80px 40px 100px;
}

.mission-inner {
  max-width: 1200px;
  margin: 0 auto;
}

.mission-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 32px;
}

.mission-card {
  background: var(--white);
  border: 1px solid var(--fog);
  border-radius: 12px;
  padding: 44px 36px;
  transition: all 0.4s var(--ease);
  position: relative;
  overflow: hidden;
}

.mission-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--gold-light), var(--gold));
  opacity: 0;
  transition: opacity 0.4s var(--ease);
}

.mission-card:hover {
  transform: translateY(-6px);
  border-color: var(--gold);
  box-shadow: 0 20px 60px rgba(26, 111, 196, 0.1), 0 8px 24px rgba(15, 32, 67, 0.06);
}

.mission-card:hover::before {
  opacity: 1;
}

.mission-icon {
  width: 52px;
  height: 52px;
  border-radius: 12px;
  background: var(--champagne);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 24px;
}

.mission-icon svg {
  width: 24px;
  height: 24px;
  stroke: var(--gold);
  fill: none;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.mission-card h3 {
  font-family: var(--display);
  font-size: 20px;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 14px;
}

.mission-card p {
  font-size: 15px;
  line-height: 1.7;
  color: var(--smoke);
}

/* ============================================
   NUMBERS SECTION
   ============================================ */
.numbers {
  background: var(--ivory);
  padding: 100px 40px;
}

.numbers-inner {
  max-width: 1200px;
  margin: 0 auto;
}

.numbers-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 32px;
  text-align: center;
}

.stat-item {
  padding: 20px;
}

.stat-number {
  font-family: var(--display);
  font-size: clamp(36px, 4vw, 52px);
  font-weight: 700;
  color: var(--ink);
  line-height: 1.1;
  margin-bottom: 8px;
}

.stat-label {
  font-family: var(--mono);
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--ash);
}

/* ============================================
   DIFFERENT SECTION
   ============================================ */
.different {
  background: var(--white);
  padding: 100px 40px;
}

.different-inner {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 80px;
  align-items: center;
}

.different-left h2 {
  font-family: var(--display);
  font-size: clamp(28px, 3.5vw, 42px);
  font-weight: 600;
  line-height: 1.2;
  color: var(--ink);
}

.different-right {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.check-item {
  display: flex;
  gap: 16px;
  align-items: flex-start;
}

.check-icon {
  flex-shrink: 0;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--champagne);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 2px;
}

.check-icon svg {
  width: 14px;
  height: 14px;
  stroke: var(--gold);
  fill: none;
  stroke-width: 2.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.check-item p {
  font-size: 16px;
  line-height: 1.6;
  color: var(--smoke);
}

.check-item p strong {
  color: var(--ink);
  font-weight: 600;
}

/* ============================================
   FOUNDER SECTION
   ============================================ */
.founder {
  background: var(--white);
  padding: 80px 40px 100px;
  text-align: center;
}

.founder-inner {
  max-width: 640px;
  margin: 0 auto;
}

.founder-inner .eyebrow {
  margin-bottom: 20px;
}

.founder-name {
  font-family: var(--display);
  font-size: 28px;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 6px;
}

.founder-role {
  font-family: var(--mono);
  font-size: 13px;
  font-weight: 500;
  color: var(--gold);
  letter-spacing: 0.08em;
  text-transform: uppercase;
  margin-bottom: 24px;
}

.founder-bio {
  font-size: 16px;
  line-height: 1.8;
  color: var(--smoke);
}

/* ============================================
   CTA SECTION
   ============================================ */
.cta {
  background: linear-gradient(135deg, var(--ink) 0%, #081530 100%);
  padding: 100px 40px;
  text-align: center;
}

.cta-inner {
  max-width: 680px;
  margin: 0 auto;
}

.cta h2 {
  font-family: var(--display);
  font-size: clamp(28px, 4vw, 44px);
  font-weight: 600;
  color: var(--white);
  line-height: 1.2;
  margin-bottom: 40px;
}

.cta-buttons {
  display: flex;
  gap: 20px;
  justify-content: center;
  flex-wrap: wrap;
}

.btn-gradient {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(135deg, var(--gold-light), var(--gold));
  color: var(--white);
  padding: 14px 32px;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.02em;
  transition: all 0.3s var(--ease);
}

.btn-gradient:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 32px rgba(26, 111, 196, 0.35);
}

.btn-ghost {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: transparent;
  color: var(--white);
  padding: 14px 32px;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 500;
  letter-spacing: 0.02em;
  border: 1px solid rgba(255, 255, 255, 0.3);
  transition: all 0.3s var(--ease);
}

.btn-ghost:hover {
  border-color: rgba(255, 255, 255, 0.7);
  background: rgba(255, 255, 255, 0.05);
  transform: translateY(-2px);
}

/* ============================================
   FOOTER
   ============================================ */
footer {
  background: linear-gradient(180deg, var(--ink) 0%, #081530 100%);
  color: var(--white);
  padding: 80px 40px 40px;
}

.footer-top {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  padding-bottom: 60px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.footer-top h3 {
  font-family: var(--display);
  font-size: clamp(36px, 4.5vw, 56px);
  font-weight: 600;
  color: var(--white);
  line-height: 1.15;
}

.footer-top h3 em {
  background: linear-gradient(135deg, var(--gold-light), var(--gold));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  font-style: italic;
}

.footer-contact {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
}

.footer-contact a {
  color: var(--white);
  font-size: 15px;
  transition: all 0.3s var(--ease);
}

.footer-contact a:hover {
  color: var(--champagne);
  text-shadow: 0 0 20px rgba(219, 234, 254, 0.3);
}

.footer-grid {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 48px;
  padding: 60px 0;
}

.footer-brand p {
  font-size: 14px;
  line-height: 1.7;
  color: rgba(255, 255, 255, 0.7);
  margin-top: 20px;
  max-width: 320px;
}

.footer-col h5 {
  font-family: var(--mono);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.15em;
  text-transform: uppercase;
  color: var(--white);
  margin-bottom: 20px;
}

.footer-col ul {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.footer-col a {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.7);
  transition: all 0.3s var(--ease);
}

.footer-col a:hover {
  color: var(--champagne);
}

.footer-bottom {
  max-width: 1200px;
  margin: 0 auto;
  padding-top: 32px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  justify-content: space-between;
}

.footer-bottom span {
  font-family: var(--mono);
  font-size: 11px;
  letter-spacing: 0.1em;
  color: rgba(255, 255, 255, 0.5);
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 1024px) {
  .mission-grid {
    grid-template-columns: 1fr;
    max-width: 500px;
    margin: 0 auto;
  }
  .numbers-grid {
    grid-template-columns: repeat(2, 1fr);
  }
  .different-inner {
    grid-template-columns: 1fr;
    gap: 48px;
  }
  .footer-grid {
    grid-template-columns: 1fr 1fr;
    gap: 40px;
  }
}

@media (max-width: 768px) {
  .nav-links { display: none; }
  .nav-inner { padding: 0 24px; }
  .hero { padding: 100px 24px 80px; }
  .mission { padding: 60px 24px 80px; }
  .numbers { padding: 80px 24px; }
  .different { padding: 80px 24px; }
  .founder { padding: 60px 24px 80px; }
  .cta { padding: 80px 24px; }
  footer { padding: 60px 24px 32px; }

  .footer-top {
    flex-direction: column;
    align-items: flex-start;
    gap: 32px;
  }
  .footer-contact {
    align-items: flex-start;
  }
  .footer-grid {
    grid-template-columns: 1fr;
    gap: 36px;
  }
  .footer-bottom {
    flex-direction: column;
    gap: 8px;
  }
  .numbers-grid {
    grid-template-columns: 1fr 1fr;
    gap: 24px;
  }
  .cta-buttons {
    flex-direction: column;
    align-items: center;
  }
}

@media (max-width: 480px) {
  .numbers-grid {
    grid-template-columns: 1fr;
  }
  .hero h1 {
    font-size: 30px;
  }
}
</style>
</head>
<body>

@include('partials.bg-animation')

<!-- ============================================
     NAV
     ============================================ -->
<nav class="nav" id="nav">
  <div class="nav-inner">
    <a href="/" class="logo"><img src="/Images/logo.png" alt="Apex Growth Systems" class="logo-img"></a>
    <ul class="nav-links">
      <li><a href="/">Home</a></li>
      <li><a href="/#process">Fulfillment Process</a></li>
      <li><a href="/#services">Services</a></li>
      <li><a href="/results">Business Results</a></li>
      <li><a href="/#faq">FAQ</a></li>
      <li><a href="/contact">Contact</a></li>
      <li><a href="{{ route('client.login') }}">Business Owner Login</a></li>
    </ul>
    <a href="/contact" class="btn btn-primary"><span>Send 5 Test Clients</span> <span class="arrow">&rarr;</span></a>
  </div>
</nav>

<!-- ============================================
     HERO
     ============================================ -->
<section class="hero">
  <div class="hero-inner reveal">
    <p class="eyebrow">01 &middot; OUR STORY</p>
    <h1>Built to handle the workload your credit repair business can't.</h1>
    <p>Apex Growth Systems exists for one reason &mdash; credit repair businesses are growing faster than they can execute. Disputes pile up. Bureau calls go unmade. CFPB filings get delayed. Client updates slip past schedule. We are the backend operations team that picks up the dispute workload, runs the follow-up calls, files the complaint documentation, and sends weekly client status reports so the business owner can stay focused on sales, retention, and growth.</p>
  </div>
</section>

<!-- ============================================
     MISSION
     ============================================ -->
<section class="mission">
  <div class="mission-inner">
    <div class="mission-grid">
      <div class="mission-card reveal">
        <div class="mission-icon">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        </div>
        <h3>Our Mission</h3>
        <p>To give credit repair businesses a reliable execution arm &mdash; disputes prepared, bureaus called, complaints filed, and clients updated on time, every week, without the owner doing the work themselves.</p>
      </div>
      <div class="mission-card reveal">
        <div class="mission-icon">
          <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
        <h3>Our Vision</h3>
        <p>A credit repair industry where business owners scale on volume and quality, not on burnout. Where every active client file gets professional, multi-channel dispute work &mdash; not template letters and silence between rounds.</p>
      </div>
      <div class="mission-card reveal">
        <div class="mission-icon">
          <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <h3>Our Values</h3>
        <p>Compliance first. Documentation always. We do not promise deletions. We do not claim guaranteed results. We run the workflow with discipline so your credit repair business is protected and your clients are served.</p>
      </div>
    </div>
  </div>
</section>

<!-- ============================================
     NUMBERS
     ============================================ -->
<section class="numbers">
  <div class="numbers-inner">
    <div class="numbers-grid">
      <div class="stat-item reveal">
        <div class="stat-number" data-target="72" data-suffix="hr">0</div>
        <div class="stat-label">To Launch First Round</div>
      </div>
      <div class="stat-item reveal">
        <div class="stat-number" data-target="100" data-suffix="%">0</div>
        <div class="stat-label">Weekly Client File Updates</div>
      </div>
      <div class="stat-item reveal">
        <div class="stat-number" data-target="4" data-suffix="">0</div>
        <div class="stat-label">Bureaus Worked Every File</div>
      </div>
      <div class="stat-item reveal">
        <div class="stat-number" data-target="24" data-suffix="/7">0</div>
        <div class="stat-label">US Daytime Coverage</div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================
     HOW WE'RE DIFFERENT
     ============================================ -->
<section class="different">
  <div class="different-inner">
    <div class="different-left reveal">
      <h2>What makes Apex <em>different</em> from a typical fulfillment shop</h2>
    </div>
    <div class="different-right">
      <div class="check-item reveal">
        <div class="check-icon">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p><strong>Multi-channel dispute workflow</strong> &mdash; Certified letters, bureau follow-up calls, CFPB and FTC complaint documentation, and small-bureau freeze support &mdash; all in the same client file.</p>
      </div>
      <div class="check-item reveal">
        <div class="check-icon">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p><strong>US business-hour coverage</strong> &mdash; Pakistan night hours align with US daytime, so bureau calls and client communication happen during business hours, not after.</p>
      </div>
      <div class="check-item reveal">
        <div class="check-icon">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p><strong>White-label friendly</strong> &mdash; We work behind your brand. Letters, reports, and client updates can ship under your credit repair business name.</p>
      </div>
      <div class="check-item reveal">
        <div class="check-icon">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p><strong>Documentation-driven</strong> &mdash; Every dispute, call, response, and 30-day window is tracked and shown back to you in weekly client status reports.</p>
      </div>
      <div class="check-item reveal">
        <div class="check-icon">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p><strong>Round 2 escalation built in</strong> &mdash; Non-deletions get stronger follow-up language citing failure to investigate properly. We don't stop at Round 1.</p>
      </div>
    </div>
  </div>
</section>

<!-- ============================================
     FOUNDER
     ============================================ -->
<section class="founder">
  <div class="founder-inner reveal">
    <p class="eyebrow">02 &middot; WHY WE BUILT THIS</p>
    <h2 class="founder-name">Built To Run The Workload Owners Hate Doing</h2>
    <p class="founder-role">The Apex Growth Systems Team</p>
    <p class="founder-bio">Apex Growth Systems was built after watching credit repair business owners win sales and then drown in fulfillment. The disputes, the bureau calls, the CFPB filings, the client updates &mdash; every piece of post-sale execution that doesn't generate revenue but determines whether clients stay or refund. Our team operates the entire backend so the business owner can sell, scale, and protect their reputation. We are not a law firm and we do not provide legal advice. We provide administrative dispute preparation, complaint documentation support, response monitoring, and weekly client reporting on behalf of credit repair businesses. Results vary. We do not guarantee removal of accurate or verifiable information.</p>
  </div>
</section>

<!-- ============================================
     CTA
     ============================================ -->
<section class="cta">
  <div class="cta-inner reveal">
    <h2>Ready to hand off the dispute workload?</h2>
    <div class="cta-buttons">
      <a href="/contact" class="btn-gradient">Send 5 Test Clients <span>&rarr;</span></a>
      <a href="/contact" class="btn-ghost">Book A Fulfillment Call</a>
    </div>
  </div>
</section>

<!-- ============================================
     FOOTER
     ============================================ -->
<footer>
  <div class="footer-top">
    <h3>Ready to <em>scale?</em><br>Let's run your fulfillment.</h3>
    <div class="footer-contact">
      <a href="tel:+10000000000">(000) 000-0000</a>
      <a href="mailto:hello@apexgrowthsystems.com">hello@apexgrowthsystems.com</a>
    </div>
  </div>
  <div class="footer-grid">
    <div class="footer-brand">
      <a href="/" class="logo"><img src="/Images/logo.png" alt="Apex Growth Systems" class="logo-img" style="height:64px;filter:brightness(0) invert(1);opacity:0.9;"></a>
      <p>Backend credit repair fulfillment for credit repair businesses. Dispute preparation, bureau follow-up calls, complaint documentation, response monitoring, and weekly client status reports. We are not a law firm and do not provide legal advice.</p>
    </div>
    <div class="footer-col"><h5>Services</h5><ul><li><a href="/#services">Dispute Letter Prep</a></li><li><a href="/#services">Bureau Follow-Up</a></li><li><a href="/#services">CFPB / FTC Documentation</a></li><li><a href="/#services">Weekly Reporting</a></li></ul></div>
    <div class="footer-col"><h5>Company</h5><ul><li><a href="/about">About</a></li><li><a href="/results">Business Results</a></li><li><a href="/contact">Contact</a></li><li><a href="/contact">Send 5 Test Clients</a></li></ul></div>
    <div class="footer-col"><h5>Legal</h5><ul><li><a href="#">Privacy Policy</a></li><li><a href="#">Terms of Service</a></li></ul></div>
  </div>
  <div class="footer-disclaimer" style="max-width:1200px;margin:24px auto 0;padding:24px;font-size:11px;line-height:1.7;color:rgba(255,255,255,0.55);"><p>Apex Growth Systems provides administrative credit repair fulfillment support, credit report review assistance, dispute preparation, documentation support, and operational services for credit repair companies. We are not a law firm and do not provide legal advice. Credit repair results vary by client profile, documentation, creditor response, bureau investigation, and whether information is inaccurate, incomplete, unverifiable, or outdated. We do not guarantee score increases, funding approvals, or removal of accurate / verifiable information.</p></div>
  <div class="footer-bottom"><span>&copy; 2026 APEX GROWTH SYSTEMS LLC</span><span>BACKEND CREDIT REPAIR FULFILLMENT</span></div>
</footer>

@include('partials.popup')

<!-- ============================================
     JAVASCRIPT
     ============================================ -->
<script>
(function() {
  // Nav scroll state
  var nav = document.getElementById('nav');
  var scrollThreshold = 40;
  function handleScroll() {
    if (window.scrollY > scrollThreshold) {
      nav.classList.add('scrolled');
    } else {
      nav.classList.remove('scrolled');
    }
  }
  window.addEventListener('scroll', handleScroll, { passive: true });
  handleScroll();

  // Scroll reveal with IntersectionObserver
  var revealElements = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    var observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, {
      threshold: 0.15,
      rootMargin: '0px 0px -40px 0px'
    });
    revealElements.forEach(function(el) {
      observer.observe(el);
    });
  } else {
    revealElements.forEach(function(el) {
      el.classList.add('visible');
    });
  }

  // Counter animation
  var statElements = document.querySelectorAll('.stat-number');
  var countersStarted = false;

  function animateCounters() {
    if (countersStarted) return;
    countersStarted = true;

    statElements.forEach(function(el) {
      var target = parseInt(el.getAttribute('data-target'), 10);
      var prefix = el.getAttribute('data-prefix') || '';
      var suffix = el.getAttribute('data-suffix') || '';
      var duration = 2000;
      var startTime = null;

      function easeOutExpo(t) {
        return t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
      }

      function step(timestamp) {
        if (!startTime) startTime = timestamp;
        var progress = Math.min((timestamp - startTime) / duration, 1);
        var easedProgress = easeOutExpo(progress);
        var current = Math.floor(easedProgress * target);
        el.textContent = prefix + current.toLocaleString() + suffix;
        if (progress < 1) {
          requestAnimationFrame(step);
        } else {
          el.textContent = prefix + target.toLocaleString() + suffix;
        }
      }

      requestAnimationFrame(step);
    });
  }

  if ('IntersectionObserver' in window && statElements.length > 0) {
    var statsObserver = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          animateCounters();
          statsObserver.disconnect();
        }
      });
    }, { threshold: 0.3 });

    var numbersSection = document.querySelector('.numbers');
    if (numbersSection) {
      statsObserver.observe(numbersSection);
    }
  }
})();
</script>
</body>
</html>
