<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="Apex Growth Systems business results &mdash; backend credit repair fulfillment wins. Dispute workflows, bureau follow-ups, complaint documentation, and weekly reports for credit repair businesses." />
<title>Business Results | Apex Growth Systems &mdash; Credit Repair Fulfillment Partner</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ============================================
   APEX GROWTH SYSTEMS — Results Page
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
  box-shadow: 0 1px 24px rgba(15, 32, 67, 0.06);
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
  color: var(--smoke);
  letter-spacing: 0.02em;
  position: relative;
  padding: 8px 0;
  transition: color 0.3s var(--ease);
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

.btn { display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s var(--ease); }
.btn-primary {
  background: var(--ink);
  color: var(--ivory);
  padding: 10px 22px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  letter-spacing: 0.02em;
}
.btn-primary:hover {
  background: var(--charcoal);
  transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(15, 32, 67, 0.2);
}
.btn-primary .arrow {
  transition: transform 0.3s var(--ease);
  font-size: 14px;
}
.btn-primary:hover .arrow { transform: translateX(3px); }

/* Mobile nav */
.mobile-toggle {
  display: none;
  flex-direction: column;
  gap: 5px;
  cursor: pointer;
  padding: 8px;
}
.mobile-toggle span {
  display: block;
  width: 22px;
  height: 1.5px;
  background: var(--ink);
  transition: all 0.3s var(--ease);
}

/* ============================================
   SCROLL REVEAL
   ============================================ */
.reveal {
  opacity: 0;
  transform: translateY(32px);
  transition: opacity 0.8s var(--ease), transform 0.8s var(--ease);
}
.reveal.visible {
  opacity: 1;
  transform: translateY(0);
}

/* ============================================
   SECTION COMMONS
   ============================================ */
.section {
  padding: 96px 24px;
  position: relative;
}
.section-inner {
  max-width: 1200px;
  margin: 0 auto;
}
.section-eyebrow {
  font-family: var(--mono);
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 16px;
}
.section-headline {
  font-family: var(--display);
  font-size: clamp(2rem, 4vw, 3rem);
  font-weight: 600;
  color: var(--ink);
  letter-spacing: -0.025em;
  line-height: 1.15;
  margin-bottom: 16px;
}
.section-headline em {
  font-style: normal;
  background: linear-gradient(135deg, var(--gold), var(--gold-light));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.section-sub {
  font-size: clamp(1rem, 1.5vw, 1.15rem);
  color: var(--smoke);
  font-weight: 400;
  line-height: 1.7;
  max-width: 640px;
}

/* ============================================
   HERO
   ============================================ */
.results-hero {
  background: var(--white);
  padding: 120px 24px 80px;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.results-hero::before {
  content: '';
  position: absolute;
  top: -50%;
  left: 50%;
  transform: translateX(-50%);
  width: 800px;
  height: 800px;
  background: radial-gradient(circle, rgba(26, 111, 196, 0.04) 0%, transparent 70%);
  pointer-events: none;
}
.results-hero .section-sub {
  margin: 0 auto;
}

/* ============================================
   STATS STRIP
   ============================================ */
.stats-strip {
  background: var(--ivory);
  padding: 72px 24px;
  border-top: 1px solid var(--fog);
  border-bottom: 1px solid var(--fog);
}
.stats-grid {
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 32px;
  text-align: center;
}
.stat-item {
  padding: 24px 16px;
}
.stat-number {
  font-family: var(--display);
  font-size: clamp(2.5rem, 5vw, 3.5rem);
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -0.03em;
  line-height: 1;
  margin-bottom: 8px;
}
.stat-label {
  font-family: var(--mono);
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--ash);
}

/* ============================================
   RESULT IMAGES GRID
   ============================================ */
.results-grid-section {
  background: var(--white);
  padding: 96px 24px;
}
.results-grid-section .section-headline {
  text-align: center;
  margin-bottom: 48px;
}
.results-image-grid {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
}
.result-image-card {
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--fog);
  background: var(--white);
  transition: all 0.4s var(--ease);
  cursor: pointer;
}
.result-image-card:hover {
  transform: translateY(-4px) scale(1.03);
  box-shadow: 0 12px 40px rgba(26, 111, 196, 0.15);
  border-color: var(--gold);
}
.result-image-card img {
  width: 100%;
  height: auto;
  display: block;
  transition: transform 0.4s var(--ease);
}
.result-image-card:hover img {
  transform: scale(1.02);
}
.view-more-wrap {
  text-align: center;
  margin-top: 48px;
}
.view-more-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-family: var(--mono);
  font-size: 13px;
  font-weight: 500;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--gold);
  padding: 14px 32px;
  border: 1.5px solid var(--gold);
  border-radius: 8px;
  transition: all 0.3s var(--ease);
}
.view-more-btn:hover {
  background: var(--gold);
  color: var(--white);
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(26, 111, 196, 0.2);
}

/* ============================================
   BEFORE & AFTER SHOWCASE
   ============================================ */
.case-studies {
  background: var(--white);
  padding: 96px 24px;
}
.case-studies .section-headline {
  text-align: center;
  margin-bottom: 12px;
}
.case-studies .section-sub {
  text-align: center;
  margin: 0 auto 56px;
}
.case-cards {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 28px;
}
.case-card {
  background: var(--white);
  border: 1px solid var(--fog);
  border-radius: 12px;
  padding: 32px 28px;
  transition: all 0.4s var(--ease);
  position: relative;
  overflow: hidden;
}
.case-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--gold), var(--gold-light));
  opacity: 0;
  transition: opacity 0.4s var(--ease);
}
.case-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 16px 48px rgba(15, 32, 67, 0.08);
  border-color: rgba(26, 111, 196, 0.2);
}
.case-card:hover::before { opacity: 1; }

.case-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 28px;
}
.case-id {
  font-family: var(--mono);
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--gold);
}
.case-timeline {
  font-family: var(--mono);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.04em;
  color: var(--ash);
  background: var(--ivory);
  padding: 4px 12px;
  border-radius: 20px;
}

.score-display {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 20px;
  margin-bottom: 28px;
}
.score-before {
  font-family: var(--display);
  font-size: 2.2rem;
  font-weight: 600;
  color: var(--crimson);
  text-decoration: line-through;
  text-decoration-thickness: 2px;
  opacity: 0.7;
}
.score-arrow {
  font-size: 1.5rem;
  color: var(--dust);
}
.score-after {
  font-family: var(--display);
  font-size: 2.8rem;
  font-weight: 700;
  color: var(--gold);
  line-height: 1;
}

.progress-bar-wrap {
  background: var(--ivory);
  border-radius: 8px;
  height: 8px;
  margin-bottom: 24px;
  overflow: hidden;
  position: relative;
}
.progress-bar-fill {
  height: 100%;
  border-radius: 8px;
  background: linear-gradient(90deg, var(--crimson), var(--gold), var(--gold-light));
  width: 0;
  transition: width 1.6s var(--ease);
}

.case-stats {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--fog);
}
.case-stat-label {
  font-family: var(--mono);
  font-size: 11px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--ash);
}
.case-stat-value {
  font-family: var(--display);
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--ink);
}

.case-quote {
  font-size: 0.95rem;
  color: var(--smoke);
  font-style: italic;
  line-height: 1.6;
  padding-left: 14px;
  border-left: 2px solid var(--champagne);
}

/* ============================================
   WHAT WE REMOVE
   ============================================ */
.remove-section {
  background: var(--ivory);
  padding: 96px 24px;
  border-top: 1px solid var(--fog);
  border-bottom: 1px solid var(--fog);
}
.remove-section .section-headline {
  text-align: center;
  margin-bottom: 48px;
}
.remove-grid {
  max-width: 1100px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.remove-item {
  background: var(--white);
  border: 1px solid var(--fog);
  border-radius: 10px;
  padding: 20px 18px;
  display: flex;
  align-items: center;
  gap: 12px;
  transition: all 0.3s var(--ease);
  cursor: default;
}
.remove-item:hover {
  background: var(--gold);
  color: var(--white);
  border-color: var(--gold);
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(26, 111, 196, 0.2);
}
.remove-icon {
  width: 28px;
  height: 28px;
  min-width: 28px;
  border-radius: 50%;
  background: var(--champagne);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s var(--ease);
}
.remove-item:hover .remove-icon {
  background: rgba(255, 255, 255, 0.2);
}
.remove-icon svg {
  width: 14px;
  height: 14px;
  stroke: var(--gold);
  fill: none;
  stroke-width: 2.5;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: stroke 0.3s var(--ease);
}
.remove-item:hover .remove-icon svg {
  stroke: var(--white);
}
.remove-label {
  font-size: 0.95rem;
  font-weight: 500;
  letter-spacing: 0.01em;
}

/* ============================================
   GUARANTEE SECTION
   ============================================ */
.guarantee-section {
  background: linear-gradient(135deg, var(--ink) 0%, #081530 100%);
  padding: 96px 24px;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.guarantee-section::before {
  content: '';
  position: absolute;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 600px;
  height: 600px;
  background: radial-gradient(circle, rgba(26, 111, 196, 0.08) 0%, transparent 70%);
  pointer-events: none;
}
.guarantee-inner {
  max-width: 720px;
  margin: 0 auto;
  position: relative;
  z-index: 2;
}
.guarantee-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-family: var(--mono);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--gold-light);
  margin-bottom: 24px;
  padding: 8px 20px;
  border: 1px solid rgba(26, 111, 196, 0.3);
  border-radius: 24px;
}
.guarantee-headline {
  font-family: var(--display);
  font-size: clamp(1.8rem, 3.5vw, 2.6rem);
  font-weight: 600;
  color: var(--white);
  letter-spacing: -0.02em;
  line-height: 1.2;
  margin-bottom: 20px;
}
.guarantee-text {
  font-size: clamp(1rem, 1.5vw, 1.15rem);
  color: var(--dust);
  font-weight: 400;
  line-height: 1.8;
  max-width: 580px;
  margin: 0 auto;
}

/* ============================================
   CTA SECTION
   ============================================ */
.cta-section {
  background: var(--white);
  padding: 96px 24px;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.cta-section::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image: radial-gradient(circle at 20% 50%, rgba(26, 111, 196, 0.03) 0%, transparent 50%),
                     radial-gradient(circle at 80% 50%, rgba(26, 111, 196, 0.03) 0%, transparent 50%);
  pointer-events: none;
}
.cta-inner {
  position: relative;
  z-index: 2;
  max-width: 640px;
  margin: 0 auto;
}
.cta-headline {
  font-family: var(--display);
  font-size: clamp(1.8rem, 3.5vw, 2.6rem);
  font-weight: 600;
  color: var(--ink);
  letter-spacing: -0.02em;
  line-height: 1.2;
  margin-bottom: 32px;
}
.cta-headline em {
  font-style: normal;
  background: linear-gradient(135deg, var(--gold), var(--gold-light));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.cta-buttons {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  flex-wrap: wrap;
}
.btn-gradient {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: linear-gradient(135deg, var(--gold), var(--gold-light));
  color: var(--white);
  padding: 16px 36px;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.01em;
  transition: all 0.3s var(--ease);
  box-shadow: 0 4px 16px rgba(26, 111, 196, 0.25);
}
.btn-gradient:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 32px rgba(26, 111, 196, 0.35);
}
.btn-gradient .arrow {
  transition: transform 0.3s var(--ease);
}
.btn-gradient:hover .arrow { transform: translateX(3px); }
.btn-ghost {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: transparent;
  color: var(--ink);
  padding: 16px 36px;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.01em;
  border: 1.5px solid var(--fog);
  transition: all 0.3s var(--ease);
}
.btn-ghost:hover {
  border-color: var(--gold);
  color: var(--gold);
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(26, 111, 196, 0.1);
}

/* ============================================
   FOOTER
   ============================================ */
footer {
  background: linear-gradient(180deg, var(--ink) 0%, #081530 100%);
  color: var(--white);
  padding: 80px 24px 32px;
}
.footer-top {
  max-width: 1200px;
  margin: 0 auto 64px;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  padding-bottom: 48px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.footer-top h3 {
  font-family: var(--display);
  font-size: clamp(1.8rem, 3vw, 2.4rem);
  font-weight: 300;
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
  gap: 8px;
}
.footer-contact a {
  font-family: var(--mono);
  font-size: 14px;
  color: rgba(255, 255, 255, 0.7);
  transition: color 0.3s var(--ease);
}
.footer-contact a:hover { color: var(--champagne); }

.footer-grid {
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 48px;
  padding-bottom: 48px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.footer-brand p {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.6);
  line-height: 1.7;
  margin-top: 16px;
  max-width: 320px;
}
.footer-col h5 {
  font-family: var(--mono);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.4);
  margin-bottom: 20px;
}
.footer-col ul { list-style: none; }
.footer-col ul li { margin-bottom: 12px; }
.footer-col ul a {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.7);
  transition: color 0.3s var(--ease);
}
.footer-col ul a:hover { color: var(--champagne); }

.footer-bottom {
  max-width: 1200px;
  margin: 0 auto;
  padding-top: 32px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.footer-bottom span {
  font-family: var(--mono);
  font-size: 11px;
  letter-spacing: 0.08em;
  color: rgba(255, 255, 255, 0.3);
}

/* ============================================
   LIGHTBOX
   ============================================ */
.lightbox-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.85);
  backdrop-filter: blur(12px);
  z-index: 200;
  display: none;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.3s var(--ease);
}
.lightbox-overlay.active {
  display: flex;
  opacity: 1;
}
.lightbox-overlay img {
  max-width: 90vw;
  max-height: 90vh;
  border-radius: 12px;
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.4);
  cursor: default;
}
.lightbox-close {
  position: absolute;
  top: 24px;
  right: 32px;
  font-size: 32px;
  color: var(--white);
  cursor: pointer;
  opacity: 0.7;
  transition: opacity 0.3s;
  font-weight: 300;
  line-height: 1;
}
.lightbox-close:hover { opacity: 1; }

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 1024px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .results-image-grid { grid-template-columns: repeat(2, 1fr); }
  .case-cards { grid-template-columns: repeat(2, 1fr); }
  .remove-grid { grid-template-columns: repeat(3, 1fr); }
  .footer-grid { grid-template-columns: 1fr 1fr; gap: 32px; }
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
    padding: 24px;
    gap: 16px;
    border-bottom: 1px solid var(--fog);
    box-shadow: 0 8px 32px rgba(15, 32, 67, 0.08);
  }
  .nav .btn-primary { display: none; }

  .results-hero { padding: 100px 24px 56px; }
  .section { padding: 64px 24px; }

  .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 16px; }
  .results-image-grid { grid-template-columns: 1fr; max-width: 480px; }
  .case-cards { grid-template-columns: 1fr; }
  .remove-grid { grid-template-columns: repeat(2, 1fr); }

  .footer-top { flex-direction: column; align-items: flex-start; gap: 24px; }
  .footer-contact { align-items: flex-start; }
  .footer-grid { grid-template-columns: 1fr; gap: 32px; }
  .footer-bottom { flex-direction: column; gap: 8px; text-align: center; }

  .cta-buttons { flex-direction: column; }
  .btn-gradient, .btn-ghost { width: 100%; justify-content: center; }
}

@media (max-width: 480px) {
  .remove-grid { grid-template-columns: 1fr; }
  .score-before { font-size: 1.6rem; }
  .score-after { font-size: 2rem; }
}
</style>
</head>
<body>
@include('partials.loader')


@include('partials.bg-animation')

<!-- NAV -->
@include('partials.nav')

<!-- HERO -->
<section class="results-hero">
  <div class="section-inner reveal">
    <div class="section-eyebrow">BUSINESS RESULTS</div>
    <h1 class="section-headline">Real client files. Real <em>fulfillment wins.</em></h1>
    <p class="section-sub">Below: outcomes from the credit repair businesses we run fulfillment for. Letters sent, bureau calls made, CFPB filings prepared, and items deleted &mdash; across thousands of client files. Results vary. We do not guarantee removal of accurate or verifiable information.</p>
  </div>
</section>

<!-- STATS STRIP -->
<section class="stats-strip">
  <div class="stats-grid reveal">
    <div class="stat-item">
      <div class="stat-number" data-count="72" data-suffix="hr">0</div>
      <div class="stat-label">To Launch First Round</div>
    </div>
    <div class="stat-item">
      <div class="stat-number" data-count="100" data-suffix="%">0</div>
      <div class="stat-label">Weekly Client File Updates</div>
    </div>
    <div class="stat-item">
      <div class="stat-number" data-count="4">0</div>
      <div class="stat-label">Bureaus Worked Every File</div>
    </div>
    <div class="stat-item">
      <div class="stat-number" data-count="24" data-suffix="/7">0</div>
      <div class="stat-label">US Daytime Coverage</div>
    </div>
  </div>
</section>

<!-- RESULT IMAGES GRID -->
<section class="results-grid-section">
  <div class="section-inner">
    <h2 class="section-headline reveal">Documented <em>Fulfillment Outcomes</em></h2>
    <div class="results-image-grid">
      @for ($i = 1; $i <= 8; $i++)
        <div class="result-image-card reveal" onclick="openLightbox(this)">
          <picture>
            <source srcset="/Images/test{{ $i }}.webp" type="image/webp">
            <img src="/Images/test{{ $i }}.png" alt="Client file workflow {{ $i }}"
                 width="800" height="600"
                 loading="lazy" decoding="async">
          </picture>
        </div>
      @endfor
    </div>
    <div class="view-more-wrap reveal">
      <a href="/contact" class="view-more-btn">Book A Fulfillment Call <span>&rarr;</span></a>
    </div>
  </div>
</section>

<!-- LIGHTBOX -->
<div class="lightbox-overlay" id="lightbox" onclick="closeLightbox(event)">
  <span class="lightbox-close">&times;</span>
  <img id="lightboxImg" src="" alt="Result detail">
</div>

<!-- BEFORE & AFTER SHOWCASE -->
<section class="case-studies section">
  <div class="section-inner">
    <div class="section-eyebrow reveal" style="text-align:center;">FULFILLMENT CASE FILES</div>
    <h2 class="section-headline reveal">Backend Workflow <em>Snapshots</em></h2>
    <p class="section-sub reveal">Sample fulfillment workloads we've executed for credit repair businesses. Letters mailed, bureau calls logged, items documented as deleted. Results vary; we do not guarantee removal of accurate or verifiable information.</p>

    <div class="case-cards">
      <!-- Case 1 -->
      <div class="case-card reveal">
        <div class="case-header">
          <span class="case-id">Business AP-0847</span>
          <span class="case-timeline">62 days</span>
        </div>
        <div class="score-display">
          <span class="score-before">510</span>
          <span class="score-arrow">&rarr;</span>
          <span class="score-after" data-score-from="510" data-score-to="708">708</span>
        </div>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" data-width="78"></div>
        </div>
        <div class="case-stats">
          <span class="case-stat-label">Items Documented As Deleted</span>
          <span class="case-stat-value">17</span>
        </div>
        <p class="case-quote">"120 active client files &middot; weekly status reports delivered on time"</p>
      </div>

      <!-- Case 2 -->
      <div class="case-card reveal">
        <div class="case-header">
          <span class="case-id">Business AP-0914</span>
          <span class="case-timeline">45 days</span>
        </div>
        <div class="score-display">
          <span class="score-before">452</span>
          <span class="score-arrow">&rarr;</span>
          <span class="score-after" data-score-from="452" data-score-to="719">719</span>
        </div>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" data-width="85"></div>
        </div>
        <div class="case-stats">
          <span class="case-stat-label">Items Documented As Deleted</span>
          <span class="case-stat-value">23</span>
        </div>
        <p class="case-quote">"75 active files &middot; CFPB filings prepared &middot; Innovis included Round 1"</p>
      </div>

      <!-- Case 3 -->
      <div class="case-card reveal">
        <div class="case-header">
          <span class="case-id">Business AP-1021</span>
          <span class="case-timeline">90 days</span>
        </div>
        <div class="score-display">
          <span class="score-before">428</span>
          <span class="score-arrow">&rarr;</span>
          <span class="score-after" data-score-from="428" data-score-to="801">801</span>
        </div>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" data-width="95"></div>
        </div>
        <div class="case-stats">
          <span class="case-stat-label">Items Documented As Deleted</span>
          <span class="case-stat-value">31</span>
        </div>
        <p class="case-quote">"200+ active files &middot; full white-label reporting &middot; Round 2 escalations executed"</p>
      </div>
    </div>
  </div>
</section>

<!-- WHAT WE REMOVE -->
<section class="remove-section">
  <div class="section-inner">
    <div class="section-eyebrow reveal" style="text-align:center;">FULFILLMENT WORKSTREAMS</div>
    <h2 class="section-headline reveal">Workstreams We Run For <em>Credit Repair Businesses</em></h2>
    <div class="remove-grid">
      <div class="remove-item reveal">
        <span class="remove-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
        <span class="remove-label">Certified Dispute Letters</span>
      </div>
      <div class="remove-item reveal">
        <span class="remove-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
        <span class="remove-label">Bureau Follow-Up Calls</span>
      </div>
      <div class="remove-item reveal">
        <span class="remove-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
        <span class="remove-label">CFPB Documentation</span>
      </div>
      <div class="remove-item reveal">
        <span class="remove-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
        <span class="remove-label">FTC Documentation</span>
      </div>
      <div class="remove-item reveal">
        <span class="remove-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
        <span class="remove-label">Innovis Disputes</span>
      </div>
      <div class="remove-item reveal">
        <span class="remove-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
        <span class="remove-label">ChexSystems Freezes</span>
      </div>
      <div class="remove-item reveal">
        <span class="remove-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
        <span class="remove-label">Clarity / SageStream</span>
      </div>
      <div class="remove-item reveal">
        <span class="remove-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
        <span class="remove-label">LexisNexis / ARS</span>
      </div>
      <div class="remove-item reveal">
        <span class="remove-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
        <span class="remove-label">Response Monitoring</span>
      </div>
      <div class="remove-item reveal">
        <span class="remove-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
        <span class="remove-label">Weekly Client Reports</span>
      </div>
      <div class="remove-item reveal">
        <span class="remove-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
        <span class="remove-label">Round 2 Escalation Prep</span>
      </div>
      <div class="remove-item reveal">
        <span class="remove-icon"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg></span>
        <span class="remove-label">White-Label Reporting</span>
      </div>
    </div>
  </div>
</section>

<!-- FULFILLMENT COMMITMENT -->
<section class="guarantee-section">
  <div class="guarantee-inner reveal">
    <div class="guarantee-badge">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      Fulfillment Commitment
    </div>
    <h2 class="guarantee-headline">Run The Workflow. Document The Trail.</h2>
    <p class="guarantee-text">Day 1 letters out. Day 7-8 bureau calls. CFPB / FTC documentation prepared where appropriate. Week 4 client status reports. We do not guarantee deletions, score increases, or removal of accurate / verifiable information &mdash; results vary. We do guarantee disciplined execution on every client file you hand us.</p>
  </div>
</section>

<!-- CTA -->
<section class="cta-section">
  <div class="cta-inner reveal">
    <h2 class="cta-headline">Scale your credit repair fulfillment <em>this week.</em></h2>
    <div class="cta-buttons">
      <a href="/trial" class="btn-gradient"><span>Try 5 Test Clients</span> <span class="arrow">&rarr;</span></a>
      <a href="/contact" class="btn-ghost"><span>Book A Fulfillment Call</span></a>
    </div>
  </div>
</section>

<!-- FOOTER -->
@include('partials.footer')

@include('partials.popup')

<script>
(function() {
  // ============================================
  // NAV SCROLL
  // ============================================
  const nav = document.getElementById('nav');
  let lastScroll = 0;
  window.addEventListener('scroll', function() {
    if (window.scrollY > 40) {
      nav.classList.add('scrolled');
    } else {
      nav.classList.remove('scrolled');
    }
    lastScroll = window.scrollY;
  }, { passive: true });

  // Mobile toggle
  const toggle = document.getElementById('mobileToggle');
  const links = document.getElementById('navLinks');
  if (toggle) {
    toggle.addEventListener('click', function() {
      links.classList.toggle('open');
    });
  }

  // ============================================
  // SCROLL REVEAL (IntersectionObserver)
  // ============================================
  const revealEls = document.querySelectorAll('.reveal');
  const revealObserver = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        revealObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  revealEls.forEach(function(el) { revealObserver.observe(el); });

  // ============================================
  // COUNTER ANIMATION
  // ============================================
  function animateCounter(el) {
    var target = parseInt(el.getAttribute('data-count'));
    var prefix = el.getAttribute('data-prefix') || '';
    var suffix = el.getAttribute('data-suffix') || '';
    var duration = 2000;
    var startTime = null;

    // (legacy hook removed; suffixes now come exclusively from data-suffix attributes)

    function step(timestamp) {
      if (!startTime) startTime = timestamp;
      var progress = Math.min((timestamp - startTime) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      var current = Math.floor(eased * target);
      el.textContent = prefix + current.toLocaleString() + suffix;
      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        el.textContent = prefix + target.toLocaleString() + suffix;
      }
    }
    requestAnimationFrame(step);
  }

  var statNumbers = document.querySelectorAll('.stat-number');
  var statsObserver = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
      if (entry.isIntersecting) {
        animateCounter(entry.target);
        statsObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.3 });
  statNumbers.forEach(function(el) { statsObserver.observe(el); });

  // ============================================
  // PROGRESS BAR ANIMATION
  // ============================================
  var bars = document.querySelectorAll('.progress-bar-fill');
  var barObserver = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
      if (entry.isIntersecting) {
        var width = entry.target.getAttribute('data-width');
        entry.target.style.width = width + '%';
        barObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.3 });
  bars.forEach(function(el) { barObserver.observe(el); });

  // ============================================
  // SCORE COUNTER ANIMATION
  // ============================================
  var scoreEls = document.querySelectorAll('.score-after[data-score-to]');
  var scoreObserver = new IntersectionObserver(function(entries) {
    entries.forEach(function(entry) {
      if (entry.isIntersecting) {
        var el = entry.target;
        var from = parseInt(el.getAttribute('data-score-from'));
        var to = parseInt(el.getAttribute('data-score-to'));
        var duration = 1800;
        var startTime = null;
        function step(timestamp) {
          if (!startTime) startTime = timestamp;
          var progress = Math.min((timestamp - startTime) / duration, 1);
          var eased = 1 - Math.pow(1 - progress, 3);
          var current = Math.floor(from + eased * (to - from));
          el.textContent = current;
          if (progress < 1) {
            requestAnimationFrame(step);
          } else {
            el.textContent = to;
          }
        }
        requestAnimationFrame(step);
        scoreObserver.unobserve(el);
      }
    });
  }, { threshold: 0.3 });
  scoreEls.forEach(function(el) { scoreObserver.observe(el); });

  // ============================================
  // LIGHTBOX
  // ============================================
  window.openLightbox = function(card) {
    var img = card.querySelector('img');
    var overlay = document.getElementById('lightbox');
    var lbImg = document.getElementById('lightboxImg');
    lbImg.src = img.src;
    lbImg.alt = img.alt;
    overlay.style.display = 'flex';
    requestAnimationFrame(function() {
      overlay.classList.add('active');
    });
    document.body.style.overflow = 'hidden';
  };
  window.closeLightbox = function(e) {
    if (e.target === document.getElementById('lightboxImg')) return;
    var overlay = document.getElementById('lightbox');
    overlay.classList.remove('active');
    setTimeout(function() {
      overlay.style.display = 'none';
      document.body.style.overflow = '';
    }, 300);
  };
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      var overlay = document.getElementById('lightbox');
      if (overlay.classList.contains('active')) {
        closeLightbox(e);
      }
    }
  });

})();
</script>
</body>
</html>
