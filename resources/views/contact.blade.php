<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="Contact Apex Growth Systems &mdash; backend credit repair fulfillment for credit repair businesses. Dispute prep, bureau follow-ups, complaint documentation, weekly reporting." />
<title>Contact | Apex Growth Systems &mdash; Credit Repair Fulfillment Partner</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ============================================
   APEX GROWTH SYSTEMS -- Contact
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

body::before {
  content: '';
  position: fixed;
  inset: 0;
  pointer-events: none;
  z-index: 1;
  opacity: 0.025;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  mix-blend-mode: multiply;
}

a { color: inherit; text-decoration: none; }
button { font-family: inherit; cursor: pointer; border: none; background: none; color: inherit; }
img { max-width: 100%; display: block; }

/* ============================================
   NAV
   ============================================ */
.nav {
  position: sticky;
  top: 0;
  z-index: 100;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(24px) saturate(180%);
  -webkit-backdrop-filter: blur(24px) saturate(180%);
  border-bottom: 1px solid rgba(226, 232, 240, 0.6);
  transition: all 0.4s var(--ease);
}
.nav.scrolled {
  background: rgba(255, 255, 255, 0.98);
  box-shadow: 0 1px 20px rgba(15, 32, 67, 0.06);
}
.nav-inner {
  max-width: 1440px;
  margin: 0 auto;
  padding: 6px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.logo { display: flex; align-items: center; }
.logo-img {
  height: 80px;
  width: auto;
  object-fit: contain;
  transition: opacity 0.3s var(--ease);
}
.logo:hover .logo-img { opacity: 0.85; }

.nav-links {
  display: flex;
  list-style: none;
  gap: 32px;
  align-items: center;
}
.nav-links a {
  font-size: 13px;
  font-weight: 500;
  letter-spacing: 0.02em;
  color: var(--smoke);
  transition: color 0.3s var(--ease);
  position: relative;
  padding: 8px 0;
}
.nav-links a:hover { color: var(--ink); }
.nav-links a::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 0;
  height: 1.5px;
  background: var(--gold);
  transition: width 0.4s var(--ease);
}
.nav-links a:hover::after { width: 100%; }

.btn { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; padding: 10px 20px; border-radius: 6px; transition: all 0.3s var(--ease); letter-spacing: 0.02em; }
.btn-primary { background: var(--ink); color: var(--ivory); }
.btn-primary:hover { background: var(--charcoal); transform: translateY(-1px); box-shadow: 0 4px 16px rgba(15,32,67,0.15); }
.btn-primary .arrow { transition: transform 0.3s var(--ease); }
.btn-primary:hover .arrow { transform: translateX(3px); }

/* Mobile menu */
.mobile-toggle { display: none; flex-direction: column; gap: 5px; padding: 8px; cursor: pointer; }
.mobile-toggle span { width: 20px; height: 1.5px; background: var(--ink); transition: all 0.3s var(--ease); }

/* ============================================
   HERO
   ============================================ */
.contact-hero {
  background: var(--white);
  padding: 80px 24px 60px;
  text-align: center;
  position: relative;
}
.contact-hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse at 50% 80%, rgba(26,111,196,0.03) 0%, transparent 70%);
  pointer-events: none;
}
.contact-hero-inner {
  max-width: 680px;
  margin: 0 auto;
  position: relative;
  z-index: 2;
}
.eyebrow {
  font-family: var(--mono);
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 16px;
}
.contact-hero h1 {
  font-family: var(--display);
  font-size: clamp(2.2rem, 4.5vw, 3.4rem);
  font-weight: 600;
  color: var(--ink);
  letter-spacing: -0.025em;
  line-height: 1.15;
  margin-bottom: 16px;
}
.contact-hero p {
  font-size: clamp(1rem, 1.4vw, 1.1rem);
  color: var(--smoke);
  font-weight: 400;
  line-height: 1.7;
  max-width: 560px;
  margin: 0 auto;
}

/* ============================================
   MAIN SECTION
   ============================================ */
.contact-main {
  padding: 0 24px 80px;
  background: var(--white);
}
.contact-grid {
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 55% 45%;
  gap: 40px;
  align-items: start;
}

/* Form Card */
.form-card {
  background: var(--white);
  border: 1px solid var(--fog);
  border-radius: 12px;
  padding: 40px;
  box-shadow: 0 4px 40px rgba(15,32,67,0.05);
}
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
.form-group {
  margin-bottom: 20px;
}
.form-group label {
  display: block;
  font-family: var(--mono);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--ash);
  margin-bottom: 6px;
}
.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 12px 14px;
  font-family: var(--body);
  font-size: 14px;
  color: var(--ink);
  background: var(--paper);
  border: 1px solid var(--fog);
  border-radius: 8px;
  outline: none;
  transition: all 0.3s var(--ease);
  -webkit-appearance: none;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  border-color: var(--gold);
  box-shadow: 0 0 0 3px rgba(26,111,196,0.1);
  background: var(--white);
}
.form-group input::placeholder,
.form-group textarea::placeholder {
  color: var(--dust);
}
.form-group select {
  cursor: pointer;
  background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1.5L6 6.5L11 1.5' stroke='%2394A3B8' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 14px center;
  padding-right: 36px;
}
.form-group textarea {
  resize: vertical;
  min-height: 100px;
}
.form-group .error-msg {
  font-size: 12px;
  color: var(--crimson);
  margin-top: 4px;
  display: none;
}
.form-group.has-error input,
.form-group.has-error select,
.form-group.has-error textarea {
  border-color: var(--crimson);
  box-shadow: 0 0 0 3px rgba(220,38,38,0.08);
}
.form-group.has-error .error-msg {
  display: block;
}

.submit-btn {
  width: 100%;
  padding: 14px 24px;
  font-family: var(--body);
  font-size: 15px;
  font-weight: 600;
  color: var(--white);
  background: linear-gradient(135deg, var(--gold), var(--gold-light));
  border: none;
  border-radius: 8px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.3s var(--ease);
  margin-top: 4px;
}
.submit-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(26,111,196,0.25);
}
.submit-btn .arrow {
  transition: transform 0.3s var(--ease);
}
.submit-btn:hover .arrow {
  transform: translateX(3px);
}

/* Success State */
.form-success {
  display: none;
  text-align: center;
  padding: 60px 20px;
}
.form-success .success-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--gold), var(--gold-light));
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 24px;
}
.form-success .success-icon svg {
  width: 28px;
  height: 28px;
  stroke: var(--white);
  stroke-width: 2.5;
  fill: none;
}
.form-success h3 {
  font-family: var(--display);
  font-size: 1.5rem;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 8px;
}
.form-success p {
  font-size: 1rem;
  color: var(--smoke);
  line-height: 1.6;
}

/* Right Column */
.contact-sidebar {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.info-card {
  background: var(--white);
  border: 1px solid var(--fog);
  border-radius: 12px;
  padding: 24px;
  display: flex;
  gap: 16px;
  align-items: flex-start;
  transition: all 0.3s var(--ease);
  cursor: default;
}
.info-card:hover {
  transform: translateY(-2px);
  border-color: var(--gold);
  box-shadow: 0 8px 24px rgba(15,32,67,0.06);
}
.info-card-icon {
  width: 44px;
  height: 44px;
  min-width: 44px;
  border-radius: 10px;
  background: var(--champagne);
  display: flex;
  align-items: center;
  justify-content: center;
}
.info-card-icon svg {
  width: 20px;
  height: 20px;
  stroke: var(--gold);
  fill: none;
  stroke-width: 1.8;
}
.info-card-content h4 {
  font-family: var(--display);
  font-size: 15px;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 2px;
}
.info-card-content p {
  font-size: 13px;
  color: var(--ash);
  line-height: 1.5;
}

.trust-block {
  margin-top: 16px;
  padding: 24px;
  background: var(--paper);
  border-radius: 12px;
  text-align: center;
}
.trust-stars {
  display: flex;
  justify-content: center;
  gap: 4px;
  margin-bottom: 10px;
}
.trust-stars svg {
  width: 18px;
  height: 18px;
  fill: #F59E0B;
}
.trust-block p {
  font-size: 13px;
  font-weight: 500;
  color: var(--smoke);
}

/* ============================================
   FAQ SECTION
   ============================================ */
.faq-section {
  padding: 80px 24px;
  background: var(--ivory);
}
.faq-inner {
  max-width: 760px;
  margin: 0 auto;
}
.faq-section h2 {
  font-family: var(--display);
  font-size: clamp(1.6rem, 3vw, 2.2rem);
  font-weight: 600;
  color: var(--ink);
  text-align: center;
  margin-bottom: 40px;
  letter-spacing: -0.02em;
}
.faq-section h2 em {
  font-style: normal;
  background: linear-gradient(135deg, var(--gold), var(--gold-light));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.accordion-item {
  background: var(--white);
  border: 1px solid var(--fog);
  border-radius: 10px;
  margin-bottom: 12px;
  overflow: hidden;
  transition: all 0.3s var(--ease);
}
.accordion-item:hover {
  border-color: var(--dust);
}
.accordion-item.active {
  border-color: var(--gold);
  box-shadow: 0 4px 20px rgba(26,111,196,0.06);
}
.accordion-header {
  width: 100%;
  padding: 20px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-family: var(--display);
  font-size: 15px;
  font-weight: 500;
  color: var(--ink);
  cursor: pointer;
  text-align: left;
  background: none;
  border: none;
  transition: color 0.3s var(--ease);
}
.accordion-header:hover {
  color: var(--gold);
}
.accordion-icon {
  width: 24px;
  height: 24px;
  min-width: 24px;
  border-radius: 50%;
  background: var(--paper);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s var(--ease);
}
.accordion-item.active .accordion-icon {
  background: var(--champagne);
  transform: rotate(180deg);
}
.accordion-icon svg {
  width: 12px;
  height: 12px;
  stroke: var(--ash);
  stroke-width: 2;
  fill: none;
}
.accordion-item.active .accordion-icon svg {
  stroke: var(--gold);
}
.accordion-body {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.4s var(--ease);
}
.accordion-body-inner {
  padding: 0 24px 20px;
  font-size: 14px;
  color: var(--smoke);
  line-height: 1.7;
}

/* ============================================
   CTA SECTION
   ============================================ */
.cta-section {
  background: linear-gradient(135deg, var(--ink) 0%, #081530 100%);
  padding: 80px 24px;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.cta-section::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse at 50% 50%, rgba(26,111,196,0.08) 0%, transparent 70%);
  pointer-events: none;
}
.cta-inner {
  max-width: 600px;
  margin: 0 auto;
  position: relative;
  z-index: 2;
}
.cta-section h2 {
  font-family: var(--display);
  font-size: clamp(1.6rem, 3vw, 2.2rem);
  font-weight: 600;
  color: var(--white);
  letter-spacing: -0.02em;
  margin-bottom: 28px;
}
.cta-buttons {
  display: flex;
  gap: 16px;
  justify-content: center;
  flex-wrap: wrap;
}
.btn-cta-primary {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 14px 28px;
  font-size: 14px;
  font-weight: 600;
  color: var(--white);
  background: linear-gradient(135deg, var(--gold), var(--gold-light));
  border-radius: 8px;
  transition: all 0.3s var(--ease);
}
.btn-cta-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(26,111,196,0.3);
}
.btn-cta-ghost {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 14px 28px;
  font-size: 14px;
  font-weight: 500;
  color: var(--white);
  background: transparent;
  border: 1px solid rgba(255,255,255,0.3);
  border-radius: 8px;
  transition: all 0.3s var(--ease);
}
.btn-cta-ghost:hover {
  border-color: rgba(255,255,255,0.6);
  background: rgba(255,255,255,0.05);
  transform: translateY(-2px);
}

/* ============================================
   FOOTER
   ============================================ */
footer {
  background: linear-gradient(180deg, var(--ink) 0%, #081530 100%);
  color: var(--white);
  padding: 80px 24px 40px;
}
.footer-top {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  padding-bottom: 48px;
  border-bottom: 1px solid rgba(255,255,255,0.1);
  margin-bottom: 48px;
}
.footer-top h3 {
  font-family: var(--display);
  font-size: clamp(1.8rem, 3vw, 2.4rem);
  font-weight: 600;
  color: var(--white);
  letter-spacing: -0.02em;
  line-height: 1.2;
}
.footer-top h3 em {
  font-style: normal;
  background: linear-gradient(135deg, var(--gold), var(--gold-light));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.footer-contact {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
}
.footer-contact a {
  font-size: 14px;
  color: rgba(255,255,255,0.7);
  transition: color 0.3s var(--ease);
}
.footer-contact a:hover { color: var(--champagne); }

.footer-grid {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 40px;
  padding-bottom: 48px;
  border-bottom: 1px solid rgba(255,255,255,0.1);
  margin-bottom: 32px;
}
.footer-brand p {
  font-size: 14px;
  color: rgba(255,255,255,0.6);
  line-height: 1.7;
  margin-top: 16px;
}
.footer-col h5 {
  font-family: var(--mono);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--white);
  margin-bottom: 16px;
}
.footer-col ul { list-style: none; }
.footer-col ul li { margin-bottom: 10px; }
.footer-col ul li a {
  font-size: 14px;
  color: rgba(255,255,255,0.7);
  transition: color 0.3s var(--ease);
}
.footer-col ul li a:hover { color: var(--champagne); }

.footer-bottom {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.footer-bottom span {
  font-family: var(--mono);
  font-size: 11px;
  letter-spacing: 0.06em;
  color: rgba(255,255,255,0.5);
}

/* ============================================
   SCROLL REVEAL
   ============================================ */
.reveal {
  opacity: 0;
  transform: translateY(30px);
  transition: opacity 0.7s var(--ease), transform 0.7s var(--ease);
}
.reveal.visible {
  opacity: 1;
  transform: translateY(0);
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 900px) {
  .contact-grid {
    grid-template-columns: 1fr;
    gap: 32px;
  }
  .form-card {
    padding: 28px;
  }
  .footer-top {
    flex-direction: column;
    align-items: flex-start;
    gap: 20px;
  }
  .footer-contact { align-items: flex-start; }
  .footer-grid {
    grid-template-columns: 1fr 1fr;
    gap: 32px;
  }
}

@media (max-width: 768px) {
  .nav-links { display: none; }
  .mobile-toggle { display: flex; }
  .nav-links.open {
    display: flex;
    flex-direction: column;
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--white);
    padding: 20px 24px;
    border-bottom: 1px solid var(--fog);
    box-shadow: 0 8px 24px rgba(15,32,67,0.08);
    gap: 0;
  }
  .nav-links.open li { padding: 12px 0; }
  .nav-inner { position: relative; }
  .form-row {
    grid-template-columns: 1fr;
    gap: 0;
  }
  .contact-hero {
    padding: 60px 24px 40px;
  }
  .cta-buttons {
    flex-direction: column;
    align-items: center;
  }
  .footer-grid {
    grid-template-columns: 1fr;
    gap: 28px;
  }
  .footer-bottom {
    flex-direction: column;
    gap: 8px;
    text-align: center;
  }
}
</style>
</head>
<body>

@include('partials.bg-animation')

<!-- NAV -->
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
    <a href="/contact" class="btn btn-primary"><span>Send 5 Test Clients</span> <span class="arrow">&rarr;</span></a>
    <div class="mobile-toggle" id="mobileToggle" aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="contact-hero">
  <div class="contact-hero-inner reveal">
    <div class="eyebrow">BOOK A FULFILLMENT CALL</div>
    <h1>Hand off the workload. Keep the wins.</h1>
    <p>Tell us about your credit repair business and where you're drowning. We'll review your fulfillment volume, current CRM, and client load &mdash; and propose a clean handoff so disputes go out, follow-ups happen, and clients get weekly updates without you running it.</p>
  </div>
</section>

<!-- MAIN SECTION -->
<section class="contact-main">
  <div class="contact-grid">
    <!-- Left: Form -->
    <div class="reveal">
      <div class="form-card" id="formCard">
        <form id="contactForm" novalidate>
          <div class="form-row">
            <div class="form-group">
              <label for="firstName">First Name *</label>
              <input type="text" id="firstName" name="firstName" placeholder="Your first name" required>
              <div class="error-msg">Please enter your first name.</div>
            </div>
            <div class="form-group">
              <label for="lastName">Last Name *</label>
              <input type="text" id="lastName" name="lastName" placeholder="Your last name" required>
              <div class="error-msg">Please enter your last name.</div>
            </div>
          </div>
          <div class="form-group">
            <label for="companyName">Company Name *</label>
            <input type="text" id="companyName" placeholder="Your credit repair business name" required>
            <div class="error-msg">Please enter your company name.</div>
          </div>
          <div class="form-group">
            <label for="email">Business Email *</label>
            <input type="email" id="email" name="email" placeholder="owner@yourbusiness.com" required>
            <div class="error-msg">Please enter a valid email address.</div>
          </div>
          <div class="form-group">
            <label for="phone">Phone / WhatsApp</label>
            <input type="tel" id="phone" name="phone" placeholder="(555) 123-4567">
            <div class="error-msg">Please enter a valid phone number.</div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="activeClients">Active Clients</label>
              <select id="activeClients">
                <option value="" disabled selected>Select range</option>
                <option value="1-25">1-25</option>
                <option value="26-75">26-75</option>
                <option value="76-200">76-200</option>
                <option value="200+">200+</option>
              </select>
              <div class="error-msg"></div>
            </div>
            <div class="form-group">
              <label for="crm">Current CRM</label>
              <select id="crm">
                <option value="" disabled selected>Select CRM</option>
                <option value="CRC">CRC</option>
                <option value="DisputeFox">DisputeFox</option>
                <option value="GoHighLevel">GoHighLevel (GHL)</option>
                <option value="Client Dispute Manager">Client Dispute Manager</option>
                <option value="Other">Other</option>
              </select>
              <div class="error-msg"></div>
            </div>
          </div>
          <div class="form-group">
            <label for="subject">Scope of Fulfillment Support *</label>
            <select id="subject" name="subject" required>
              <option value="" disabled selected>Select scope</option>
              <option value="Full Fulfillment Handoff">Full Fulfillment Handoff</option>
              <option value="Dispute Letter Preparation Only">Dispute Letter Preparation Only</option>
              <option value="Bureau Follow-Up Calls Only">Bureau Follow-Up Calls Only</option>
              <option value="CFPB / FTC Documentation">CFPB / FTC Documentation</option>
              <option value="Innovis &amp; Small Bureau Disputes">Innovis &amp; Small Bureau Disputes</option>
              <option value="Weekly Reporting &amp; Tracking">Weekly Reporting &amp; Tracking</option>
              <option value="Round 2 Escalation Prep">Round 2 Escalation Prep</option>
              <option value="Other">Other</option>
            </select>
            <div class="error-msg">Please select a scope.</div>
          </div>
          <div class="form-group">
            <label for="message">What do you need help with?</label>
            <textarea id="message" name="message" rows="4" placeholder="Tell us about your fulfillment workload, weekly volume, and pain points..."></textarea>
            <div class="error-msg"></div>
          </div>
          <button type="submit" class="submit-btn">
            <span>Request Fulfillment Support</span>
            <span class="arrow">&rarr;</span>
          </button>
        </form>

        <div class="form-success" id="formSuccess">
          <div class="success-icon">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
          </div>
          <h3>Request received.</h3>
          <p>A fulfillment lead will reach out within one business day to scope your client load, your CRM, and a clean handoff plan. If your inbox is heavy, check spam for our reply.</p>
        </div>
      </div>
    </div>

    <!-- Right: Info Cards -->
    <div class="contact-sidebar reveal">
      <div class="info-card">
        <div class="info-card-icon">
          <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"></path></svg>
        </div>
        <div class="info-card-content">
          <h4>(000) 000-0000</h4>
          <p>US Business Hours &middot; Mon&ndash;Fri (Pakistan night shift coverage)</p>
        </div>
      </div>
      <div class="info-card">
        <div class="info-card-icon">
          <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
        </div>
        <div class="info-card-content">
          <h4>hello@apexgrowthsystems.com</h4>
          <p>Replies within 1 business day</p>
        </div>
      </div>
      <div class="info-card">
        <div class="info-card-icon">
          <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
        </div>
        <div class="info-card-content">
          <h4>Remote &middot; US Daytime Coverage</h4>
          <p>Backend ops for US-based credit repair businesses</p>
        </div>
      </div>

      <div class="trust-block">
        <div class="trust-stars">
          <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
          <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
          <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
          <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
          <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
        </div>
        <p>Trusted backend by credit repair businesses scaling client volume</p>
      </div>
    </div>
  </div>
</section>

<!-- FAQ SECTION -->
<section class="faq-section">
  <div class="faq-inner">
    <h2 class="reveal">Common <em>Questions</em></h2>

    <div class="accordion-item reveal">
      <button class="accordion-header">
        <span>Do you work directly with consumers?</span>
        <div class="accordion-icon"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg></div>
      </button>
      <div class="accordion-body">
        <div class="accordion-body-inner">No. We are a backend fulfillment partner for credit repair businesses. Your business owns the client relationship, billing, and contracts. We execute the dispute workflow on your client files behind the scenes.</div>
      </div>
    </div>

    <div class="accordion-item reveal">
      <button class="accordion-header">
        <span>Can you work under our brand?</span>
        <div class="accordion-icon"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg></div>
      </button>
      <div class="accordion-body">
        <div class="accordion-body-inner">Yes. We are white-label friendly. Certified letters, weekly client status reports, and dispute documentation can ship under your credit repair business name. Branding details are confirmed during onboarding.</div>
      </div>
    </div>

    <div class="accordion-item reveal">
      <button class="accordion-header">
        <span>What do you need to start?</span>
        <div class="accordion-icon"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg></div>
      </button>
      <div class="accordion-body">
        <div class="accordion-body-inner">A short fulfillment call, a signed scope agreement, your CRM access (CRC, DisputeFox, GHL, Client Dispute Manager, etc.), and the client files you want us to run. Most credit repair businesses start with 5 test clients before scaling.</div>
      </div>
    </div>

    <div class="accordion-item reveal">
      <button class="accordion-header">
        <span>Do you guarantee deletions?</span>
        <div class="accordion-icon"><svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"></polyline></svg></div>
      </button>
      <div class="accordion-body">
        <div class="accordion-body-inner">No, and any provider that does is making a claim that violates federal credit repair regulations. Results vary by client profile, documentation quality, creditor response, bureau investigation, and whether the information is inaccurate, incomplete, unverifiable, or outdated. We document the dispute trail, run the multi-channel workflow, and escalate properly &mdash; but we do not guarantee removal of accurate or verifiable information.</div>
      </div>
    </div>
  </div>
</section>

<!-- CTA SECTION -->
<section class="cta-section">
  <div class="cta-inner reveal">
    <h2>Ready to scale your credit repair fulfillment?</h2>
    <div class="cta-buttons">
      <a href="/contact" class="btn-cta-primary"><span>Send 5 Test Clients</span> <span>&rarr;</span></a>
      <a href="tel:+10000000000" class="btn-cta-ghost"><span>Book A Fulfillment Call</span></a>
    </div>
  </div>
</section>

<!-- FOOTER -->
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

<script>
(function() {
  'use strict';

  /* ---- Nav scroll ---- */
  var nav = document.getElementById('nav');
  var scrolled = false;
  window.addEventListener('scroll', function() {
    if (window.scrollY > 20 && !scrolled) {
      nav.classList.add('scrolled');
      scrolled = true;
    } else if (window.scrollY <= 20 && scrolled) {
      nav.classList.remove('scrolled');
      scrolled = false;
    }
  });

  /* ---- Mobile toggle ---- */
  var toggle = document.getElementById('mobileToggle');
  var navLinks = document.getElementById('navLinks');
  if (toggle) {
    toggle.addEventListener('click', function() {
      navLinks.classList.toggle('open');
    });
  }

  /* ---- Scroll reveal ---- */
  var reveals = document.querySelectorAll('.reveal');
  var observer = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
  reveals.forEach(function(el) { observer.observe(el); });

  /* ---- Accordion ---- */
  var accordionHeaders = document.querySelectorAll('.accordion-header');
  accordionHeaders.forEach(function(header) {
    header.addEventListener('click', function() {
      var item = this.parentElement;
      var body = item.querySelector('.accordion-body');
      var inner = body.querySelector('.accordion-body-inner');
      var isActive = item.classList.contains('active');

      // Close all
      document.querySelectorAll('.accordion-item').forEach(function(ai) {
        ai.classList.remove('active');
        ai.querySelector('.accordion-body').style.maxHeight = '0';
      });

      // Open clicked if it wasn't active
      if (!isActive) {
        item.classList.add('active');
        body.style.maxHeight = inner.scrollHeight + 20 + 'px';
      }
    });
  });

  /* ---- Form Validation ---- */
  var form = document.getElementById('contactForm');
  var formSuccess = document.getElementById('formSuccess');

  function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  function validatePhone(phone) {
    if (!phone) return true; // optional
    return /^[\d\s\-\(\)\+]{7,}$/.test(phone);
  }

  function setError(id, show) {
    var group = document.getElementById(id).closest('.form-group');
    if (show) {
      group.classList.add('has-error');
    } else {
      group.classList.remove('has-error');
    }
  }

  // Remove error on input
  var inputs = form.querySelectorAll('input, select, textarea');
  inputs.forEach(function(input) {
    input.addEventListener('input', function() {
      this.closest('.form-group').classList.remove('has-error');
    });
    input.addEventListener('change', function() {
      this.closest('.form-group').classList.remove('has-error');
    });
  });

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    var valid = true;

    var firstName = document.getElementById('firstName').value.trim();
    var lastName = document.getElementById('lastName').value.trim();
    var companyName = document.getElementById('companyName').value.trim();
    var email = document.getElementById('email').value.trim();
    var phone = document.getElementById('phone').value.trim();
    var subject = document.getElementById('subject').value;
    var activeClients = (document.getElementById('activeClients') || {}).value || '';
    var crm = (document.getElementById('crm') || {}).value || '';

    if (!firstName) { setError('firstName', true); valid = false; } else { setError('firstName', false); }
    if (!lastName) { setError('lastName', true); valid = false; } else { setError('lastName', false); }
    if (!companyName) { setError('companyName', true); valid = false; } else { setError('companyName', false); }
    if (!email || !validateEmail(email)) { setError('email', true); valid = false; } else { setError('email', false); }
    if (!validatePhone(phone)) { setError('phone', true); valid = false; } else { setError('phone', false); }
    if (!subject) { setError('subject', true); valid = false; } else { setError('subject', false); }

    if (!valid) return;

    var rawMessage = document.getElementById('message').value.trim();
    var fullMessage = '[Company: ' + companyName + ' | Active Clients: ' + (activeClients || 'n/a') + ' | CRM: ' + (crm || 'n/a') + ']\n\n' + rawMessage;
    var submitBtn = form.querySelector('button[type="submit"]');
    var originalLabel = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>Sending&hellip;</span>';

    fetch('{{ route('contact.submit') }}', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({
        firstName: firstName,
        lastName: lastName,
        email: email,
        phone: phone,
        subject: subject,
        message: fullMessage,
        source_page: window.location.pathname
      })
    }).then(function(res) {
      if (!res.ok) throw new Error('Request failed: ' + res.status);
      return res.json();
    }).then(function() {
      form.style.display = 'none';
      formSuccess.style.display = 'block';
      formSuccess.style.opacity = '0';
      formSuccess.style.transform = 'translateY(10px)';
      formSuccess.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
      setTimeout(function() {
        formSuccess.style.opacity = '1';
        formSuccess.style.transform = 'translateY(0)';
      }, 50);
    }).catch(function(err) {
      console.error('Contact submit failed', err);
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalLabel;
      setError('email', true);
    });
  });

})();
</script>

</body>
</html>
