<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="Try Apex on 5 test clients first. We run the full credit repair fulfillment workflow — certified letters, bureau follow-up calls, CFPB / FTC documentation, response monitoring, and Week 4 reporting — and you only pay once results are in. No upfront commitment." />
<title>Try 5 Test Clients — Pay After Results | Apex Growth Systems</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="image" href="/Images/heroimage.webp" type="image/webp" fetchpriority="high">
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
  background: linear-gradient(180deg, var(--white) 0%, var(--paper) 100%);
  padding: 120px 40px 80px;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.hero::before {
  content: '';
  position: absolute;
  top: -120px; left: 50%;
  transform: translateX(-50%);
  width: 720px; height: 720px;
  background: radial-gradient(circle, rgba(33,150,243,0.10) 0%, rgba(33,150,243,0) 60%);
  pointer-events: none;
}

.hero-inner {
  max-width: 880px;
  margin: 0 auto;
  position: relative;
  z-index: 1;
}

.hero-cta {
  display: flex;
  gap: 16px;
  justify-content: center;
  flex-wrap: wrap;
  margin-top: 40px;
}
.hero-cta .btn-gradient,
.hero-cta .btn-ghost {
  font-family: var(--display);
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.01em;
  padding: 16px 32px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  gap: 10px;
  transition: all 0.35s var(--ease);
  cursor: pointer;
}
.hero-cta .btn-gradient {
  background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 50%, var(--gold-deep) 100%);
  color: var(--white);
  box-shadow: 0 12px 32px rgba(26,111,196,0.28);
  border: none;
}
.hero-cta .btn-gradient:hover {
  transform: translateY(-3px);
  box-shadow: 0 20px 44px rgba(26,111,196,0.40);
}
.hero-cta .btn-ghost {
  background: transparent;
  color: var(--ink);
  border: 1.5px solid var(--bone);
}
.hero-cta .btn-ghost:hover {
  border-color: var(--gold);
  color: var(--gold);
  transform: translateY(-2px);
}

.hero-trust {
  margin-top: 32px;
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 22px;
  font-family: var(--mono);
  font-size: 11px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--ash);
}
.hero-trust span { display: inline-flex; align-items: center; gap: 6px; }
.hero-trust span::before {
  content: '';
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--gold);
}

.hero-image-wrap {
  max-width: 1100px;
  margin: 64px auto 0;
  padding: 0 40px;
  position: relative;
  z-index: 1;
}
.hero-image {
  width: 100%;
  height: auto;
  border-radius: 18px;
  box-shadow:
    0 32px 80px rgba(15, 32, 67, 0.18),
    0 0 0 1px rgba(15, 32, 67, 0.04);
  display: block;
}
@media (max-width: 720px) {
  .hero { padding: 96px 24px 64px; }
  .hero-image-wrap { padding: 0 24px; margin-top: 44px; }
  .hero-cta .btn-gradient,
  .hero-cta .btn-ghost { width: 100%; justify-content: center; }
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
  padding: 100px 40px 110px;
  position: relative;
}
.mission::before {
  content: 'HOW IT WORKS';
  display: block;
  font-family: var(--mono);
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.18em;
  color: var(--gold);
  text-align: center;
  margin-bottom: 12px;
}
.mission::after {
  content: 'Six steps from handoff to results — without a dollar out of your pocket.';
  display: block;
  font-family: var(--display);
  font-size: clamp(24px, 3vw, 34px);
  font-weight: 600;
  letter-spacing: -0.02em;
  color: var(--ink);
  text-align: center;
  max-width: 720px;
  margin: 0 auto 56px;
  line-height: 1.25;
}
.mission-card .mission-icon {
  background: linear-gradient(135deg, rgba(33,150,243,0.14), rgba(26,111,196,0.10));
}
.mission-card:hover .mission-icon {
  background: linear-gradient(135deg, var(--gold-light), var(--gold));
  color: var(--white);
}
.mission-card:hover .mission-icon svg { stroke: var(--white); }

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
@include('partials.loader')


@include('partials.bg-animation')

<!-- ============================================
     NAV
     ============================================ -->
@include('partials.nav')

<!-- ============================================
     HERO
     ============================================ -->
<section class="hero">
  <div class="hero-inner reveal">
    <p class="eyebrow">PAY-AFTER-RESULTS TRIAL</p>
    <h1>We'll run 5 test clients for you. <em>You pay only once the results are in.</em></h1>
    <p>Don't move your full client base on a promise. Hand us 5 active client files and we'll execute the full Apex fulfillment workflow at our cost &mdash; certified letters, bureau follow-up calls, CFPB / FTC documentation where appropriate, response monitoring, and a Week 4 client status report. You only pay once the results are in. Not before.</p>
    <div class="hero-cta">
      <a href="/contact" class="btn-gradient"><span>Contact Us To Start</span> <span>&rarr;</span></a>
      <a href="/contact" class="btn-ghost"><span>Book A Fulfillment Call</span></a>
    </div>
    <div class="hero-trust">
      <span>$0 Upfront</span>
      <span>No Contract</span>
      <span>Pay After Results</span>
      <span>White-Label Friendly</span>
    </div>
  </div>
  <div class="hero-image-wrap reveal">
    <picture>
        <source srcset="/Images/heroimage.webp" type="image/webp">
        <img src="/Images/heroimage.png" alt="Apex Growth Systems fulfillment workflow on a credit repair client file"
             class="hero-image"
             width="1200" height="900"
             loading="eager" decoding="async" fetchpriority="high">
    </picture>
  </div>
</section>

<!-- ============================================
     HOW THE TRIAL WORKS — 6 steps, 3x2 grid
     ============================================ -->
<section class="mission">
  <div class="mission-inner">
    <div class="mission-grid">
      <div class="mission-card reveal">
        <div class="mission-icon">
          <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        </div>
        <h3>1. Free scoping call</h3>
        <p>A 15-minute call. We confirm your CRM (CRC, DisputeFox, GHL, Client Dispute Manager), the white-label setup, and which 5 files you want us to run. No pressure. No contract.</p>
      </div>
      <div class="mission-card reveal">
        <div class="mission-icon">
          <svg viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <h3>2. You hand off 5 files</h3>
        <p>Pick the 5 most active clients drowning your queue. Send us the disputes, IDs, addresses, and bureau access. No upfront fees. No deposit. No long-term billing trap.</p>
      </div>
      <div class="mission-card reveal">
        <div class="mission-icon">
          <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>
        <h3>3. Day 1: certified letters</h3>
        <p>Letters go out to all four bureaus &mdash; Experian, Equifax, TransUnion, Innovis &mdash; certified, tracked, and logged in your dispute trail with timestamps and tracking numbers.</p>
      </div>
      <div class="mission-card reveal">
        <div class="mission-icon">
          <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        </div>
        <h3>4. Day 7-8: bureau calls</h3>
        <p>We call TU, EX, and EQ directly during US business hours. Every call documented &mdash; rep names, ticket numbers, timestamps &mdash; captured to the client file for audit.</p>
      </div>
      <div class="mission-card reveal">
        <div class="mission-icon">
          <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
        </div>
        <h3>5. Week 4: results delivered</h3>
        <p>A full client status report &mdash; every action, every response, every escalation &mdash; delivered in your brand. Ready to ship straight to the client. You judge the quality firsthand.</p>
      </div>
      <div class="mission-card reveal">
        <div class="mission-icon">
          <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <h3>6. You pay &mdash; only now</h3>
        <p>Results in. Work documented. If we delivered, you pay. If we didn't, you don't &mdash; you keep 5 professionally worked files free. Then we scope the full handoff for your client base.</p>
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
        <div class="stat-number" data-target="0" data-prefix="$" data-suffix="">0</div>
        <div class="stat-label">Upfront Cost To Start</div>
      </div>
      <div class="stat-item reveal">
        <div class="stat-number" data-target="5" data-suffix="">0</div>
        <div class="stat-label">Test Client Files</div>
      </div>
      <div class="stat-item reveal">
        <div class="stat-number" data-target="4" data-suffix=" wks">0</div>
        <div class="stat-label">Until Results In Your Hands</div>
      </div>
      <div class="stat-item reveal">
        <div class="stat-number" data-target="0" data-suffix="">0</div>
        <div class="stat-label">Long-Term Contract</div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================
     WHAT'S INCLUDED
     ============================================ -->
<section class="different">
  <div class="different-inner">
    <div class="different-left reveal">
      <h2>Everything we run on your 5 trial files <em>before you ever pay a dollar</em></h2>
    </div>
    <div class="different-right">
      <div class="check-item reveal">
        <div class="check-icon">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p><strong>Day 1 certified letters</strong> &mdash; Experian, Equifax, TransUnion, and Innovis. Tracked, mailed, and logged in your dispute trail.</p>
      </div>
      <div class="check-item reveal">
        <div class="check-icon">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p><strong>Day 7-8 bureau follow-up calls</strong> &mdash; TU / EX / EQ called, documented, with rep names, ticket numbers, and timestamps captured for the file.</p>
      </div>
      <div class="check-item reveal">
        <div class="check-icon">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p><strong>CFPB &amp; FTC documentation</strong> &mdash; Complaint packages prepared where the file profile supports it, with full evidence trail.</p>
      </div>
      <div class="check-item reveal">
        <div class="check-icon">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p><strong>Small-bureau freeze support</strong> &mdash; ChexSystems, ARS, Clarity, SageStream, LexisNexis &mdash; included on every applicable file.</p>
      </div>
      <div class="check-item reveal">
        <div class="check-icon">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p><strong>Week 2-3 response monitoring</strong> &mdash; Every 30-day window tracked. No file falls into a black hole between rounds.</p>
      </div>
      <div class="check-item reveal">
        <div class="check-icon">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p><strong>Week 4 client status report</strong> &mdash; Every action, every response, every escalation &mdash; delivered in your brand, ready to ship to the client.</p>
      </div>
      <div class="check-item reveal">
        <div class="check-icon">
          <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p><strong>Round 2 escalation prep</strong> &mdash; Non-deletions get stronger follow-up language citing failure to investigate properly. We don't stop at Round 1.</p>
      </div>
    </div>
  </div>
</section>

<!-- ============================================
     WHY WE OFFER THIS
     ============================================ -->
<section class="founder">
  <div class="founder-inner reveal">
    <p class="eyebrow">WHY WE WORK THIS WAY</p>
    <h2 class="founder-name">Full Trust On Your Side. Full Skin In The Game On Ours.</h2>
    <p class="founder-role">The Apex Fulfillment Commitment</p>
    <p class="founder-bio">Most fulfillment partners ask you to wire money, sign a contract, and hope. We won't. If you're being asked to scale your entire client base to a backend team you've never worked with, the only way to honestly de-risk that is for us to do the work first &mdash; in full, on live files, with a paper trail you can audit &mdash; and let the results decide whether we deserve to be paid. We absorb the cost of those 5 files. You absorb zero risk. If the workflow proves out, you pay for the trial and we scope the full handoff. If it doesn't, you walk away with nothing lost and 5 dispute files that were professionally worked at our expense. Apex Growth Systems is not a law firm and does not provide legal advice. Results vary. We do not guarantee removal of accurate or verifiable information.</p>
  </div>
</section>

<!-- ============================================
     CTA
     ============================================ -->
<section class="cta">
  <div class="cta-inner reveal">
    <h2>Ready to hand us your 5 files?</h2>
    <div class="cta-buttons">
      <a href="/contact" class="btn-gradient">Contact Us To Start <span>&rarr;</span></a>
      <a href="/contact" class="btn-ghost">Book A Fulfillment Call</a>
    </div>
  </div>
</section>

<!-- ============================================
     FOOTER
     ============================================ -->
@include('partials.footer')

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
