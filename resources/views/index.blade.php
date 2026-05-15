<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="Apex Growth Systems — backend credit repair fulfillment partner. Dispute prep, bureau follow-up calls, CFPB / FTC documentation, Innovis disputes, and weekly client reporting for credit repair businesses." />
<title>Apex Growth Systems | Credit Repair Fulfillment Partner For Credit Repair Businesses</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="image" href="/Images/heroimage.webp" type="image/webp" imagesrcset="/Images/heroimage.webp 1x" fetchpriority="high">
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ============================================
   APEX GROWTH SYSTEMS — Blue & White Edition
   Navy · Electric Blue · Silver · White
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

/* ============================================
   PREMIUM EFFECTS SYSTEM
   ============================================ */

/* Gradient text utility */
.gradient-text {
  background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 40%, var(--ink) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* Text glow */
.text-glow {
  text-shadow:
    0 0 40px rgba(26, 111, 196, 0.15),
    0 0 80px rgba(26, 111, 196, 0.05);
}

/* Glass card effect */
.glass {
  background: rgba(255, 255, 255, 0.7);
  backdrop-filter: blur(20px) saturate(180%);
  -webkit-backdrop-filter: blur(20px) saturate(180%);
  border: 1px solid rgba(255, 255, 255, 0.5);
  box-shadow:
    0 8px 32px rgba(15, 32, 67, 0.08),
    inset 0 1px 0 rgba(255, 255, 255, 0.6);
}

/* Luminous border on hover */
.lumi-border {
  position: relative;
}
.lumi-border::after {
  content: '';
  position: absolute;
  inset: -1px;
  border-radius: inherit;
  background: linear-gradient(135deg, rgba(33, 150, 243, 0.4), transparent 50%, rgba(26, 111, 196, 0.3));
  z-index: -1;
  opacity: 0;
  transition: opacity 0.5s var(--ease);
}
.lumi-border:hover::after { opacity: 1; }

/* Floating animation */
@keyframes float {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-12px); }
}
@keyframes floatSlow {
  0%, 100% { transform: translateY(0) rotate(0deg); }
  33% { transform: translateY(-8px) rotate(1deg); }
  66% { transform: translateY(4px) rotate(-0.5deg); }
}

/* Shimmer effect */
@keyframes shimmer {
  0% { background-position: -200% center; }
  100% { background-position: 200% center; }
}
.shimmer-text {
  background: linear-gradient(90deg, var(--ink) 0%, var(--ink) 40%, var(--gold-light) 50%, var(--ink) 60%, var(--ink) 100%);
  background-size: 200% auto;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  animation: shimmer 4s linear infinite;
}

/* Parallax container */
[data-parallax] {
  will-change: transform;
  transition: transform 0.1s linear;
}

/* Enhanced scroll reveal with multiple styles */
.reveal-slide-left {
  opacity: 0;
  transform: translateX(-60px);
  transition: opacity 0.8s var(--ease), transform 0.8s var(--ease);
}
.reveal-slide-left.in { opacity: 1; transform: translateX(0); }

.reveal-slide-right {
  opacity: 0;
  transform: translateX(60px);
  transition: opacity 0.8s var(--ease), transform 0.8s var(--ease);
}
.reveal-slide-right.in { opacity: 1; transform: translateX(0); }

.reveal-scale {
  opacity: 0;
  transform: scale(0.9);
  transition: opacity 0.8s var(--ease), transform 0.8s var(--ease);
}
.reveal-scale.in { opacity: 1; transform: scale(1); }

.reveal-blur {
  opacity: 0;
  filter: blur(10px);
  transform: translateY(20px);
  transition: opacity 0.8s var(--ease), filter 0.8s var(--ease), transform 0.8s var(--ease);
}
.reveal-blur.in { opacity: 1; filter: blur(0); transform: translateY(0); }

/* Counter number glow on animate */
.counter-glow {
  transition: text-shadow 0.5s var(--ease);
}
.counter-glow.active {
  text-shadow: 0 0 30px rgba(26, 111, 196, 0.3), 0 0 60px rgba(26, 111, 196, 0.1);
}

/* Magnetic hover effect for cards */
.magnetic-card {
  transition: transform 0.4s var(--ease), box-shadow 0.4s var(--ease);
  will-change: transform;
}

/* Smooth line draw animation */
@keyframes drawLine {
  from { stroke-dashoffset: 1000; }
  to { stroke-dashoffset: 0; }
}

/* Pulse ring effect */
@keyframes pulseRing {
  0% { transform: scale(0.9); opacity: 0.8; }
  50% { transform: scale(1.05); opacity: 0.4; }
  100% { transform: scale(0.9); opacity: 0.8; }
}

/* ============================================
   TOP TICKER (scrolling announcement)
   ============================================ */
.ticker {
  background: var(--ink);
  color: var(--ivory);
  padding: 11px 0;
  font-family: var(--mono);
  font-size: 11px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  overflow: hidden;
  position: relative;
  z-index: 50;
}
.ticker-track {
  display: flex;
  gap: 64px;
  white-space: nowrap;
  animation: tickerScroll 45s linear infinite;
  width: max-content;
}
.ticker-item { display: inline-flex; align-items: center; gap: 14px; }
.ticker-item .orb {
  width: 5px; height: 5px; border-radius: 50%;
  background: var(--gold);
  box-shadow: 0 0 10px var(--gold);
  animation: pulseOrb 2s ease-in-out infinite;
}
@keyframes pulseOrb {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(1.3); }
}
@keyframes tickerScroll {
  from { transform: translateX(0); }
  to { transform: translateX(-50%); }
}

/* ============================================
   NAV
   ============================================ */
.nav {
  position: sticky;
  top: 0;
  z-index: 100;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(24px) saturate(200%);
  -webkit-backdrop-filter: blur(24px) saturate(200%);
  border-bottom: 1px solid rgba(226, 232, 240, 0.6);
  transition: all 0.5s var(--ease);
  margin: 0;
  padding: 0;
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
}
.logo {
  display: flex;
  align-items: center;
}
.logo-img {
  height: 80px;
  width: auto;
  object-fit: contain;
  transition: opacity 0.3s var(--ease);
}
.logo:hover .logo-img { opacity: 0.85; }

.nav-links { display: flex; align-items: center; gap: 36px; list-style: none; }
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
  bottom: 0; left: 50%;
  width: 0; height: 2px;
  background: linear-gradient(90deg, var(--gold-light), var(--gold));
  transition: width 0.4s var(--ease), left 0.4s var(--ease);
  border-radius: 1px;
  box-shadow: 0 0 8px rgba(26, 111, 196, 0.3);
}
.nav-links a:hover::after { width: 100%; left: 0; }

/* ============================================
   BUTTONS
   ============================================ */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 15px 32px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  border-radius: 6px;
  transition: all 0.5s var(--ease);
  position: relative;
  overflow: hidden;
  cursor: pointer;
  font-family: var(--body);
}
.btn-primary {
  background: var(--ink);
  color: var(--ivory);
  border: 1px solid var(--ink);
}
.btn-primary::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, var(--gold) 0%, var(--gold-deep) 100%);
  transform: translateY(101%);
  transition: transform 0.5s var(--ease);
  z-index: 0;
}
.btn-primary span, .btn-primary .arrow { position: relative; z-index: 1; }
.btn-primary:hover::before { transform: translateY(0); }
.btn-primary:hover { border-color: var(--gold); transform: translateY(-3px); box-shadow: 0 20px 40px rgba(26, 111, 196, 0.3), 0 0 0 1px rgba(26, 111, 196, 0.1); }

.btn-ghost {
  background: transparent;
  color: var(--ink);
  border: 1px solid var(--ink);
}
.btn-ghost:hover { background: var(--ink); color: var(--ivory); box-shadow: 0 12px 32px rgba(15, 32, 67, 0.2); transform: translateY(-2px); }

.btn-gold {
  background: linear-gradient(180deg, var(--gold-light) 0%, var(--gold) 50%, var(--gold-deep) 100%);
  color: var(--white);
  border: 1px solid var(--gold);
  box-shadow: 0 8px 24px rgba(26, 111, 196, 0.2), inset 0 1px 0 rgba(255,255,255,0.35);
  font-weight: 700;
}
.btn-gold::before {
  content: '';
  position: absolute;
  top: 0; left: -100%;
  width: 100%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
  transition: left 0.7s var(--ease);
}
.btn-gold:hover::before { left: 100%; }
.btn-gold:hover { transform: translateY(-3px); box-shadow: 0 16px 40px rgba(26, 111, 196, 0.4), inset 0 1px 0 rgba(255,255,255,0.5); }

.btn .arrow { transition: transform 0.4s var(--ease); font-family: var(--mono); font-weight: 400; }
.btn:hover .arrow { transform: translateX(6px); }

/* ============================================
   HERO
   ============================================ */
.hero-wrapper {
  position: relative;
  overflow: hidden;
}

/* Animated background elements */
.hero-bg {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: 0;
  overflow: hidden;
}
.hero-bg-orb {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0;
  animation: orbPulse 8s var(--ease-smooth) infinite;
}
.hero-bg-orb:nth-child(1) {
  width: 400px; height: 400px;
  background: radial-gradient(circle, rgba(26, 111, 196, 0.12) 0%, transparent 70%);
  top: -10%; right: 15%;
  animation-delay: 0s;
}
.hero-bg-orb:nth-child(2) {
  width: 300px; height: 300px;
  background: radial-gradient(circle, rgba(15, 32, 67, 0.08) 0%, transparent 70%);
  bottom: 5%; left: -5%;
  animation-delay: 3s;
}
.hero-bg-orb:nth-child(3) {
  width: 250px; height: 250px;
  background: radial-gradient(circle, rgba(33, 150, 243, 0.06) 0%, transparent 70%);
  top: 40%; right: -8%;
  animation-delay: 5s;
}
@keyframes orbPulse {
  0%, 100% { opacity: 0; transform: scale(0.8) translate(0, 0); }
  30% { opacity: 1; }
  50% { opacity: 1; transform: scale(1.1) translate(20px, -10px); }
  70% { opacity: 1; }
}

/* Floating grid lines */
.hero-bg-lines {
  position: absolute;
  inset: 0;
  opacity: 0.03;
  background-image:
    linear-gradient(var(--ink) 1px, transparent 1px),
    linear-gradient(90deg, var(--ink) 1px, transparent 1px);
  background-size: 80px 80px;
  animation: gridDrift 25s linear infinite;
}
@keyframes gridDrift {
  from { transform: translate(0, 0); }
  to { transform: translate(80px, 80px); }
}

/* Floating particles */
.hero-bg-particle {
  position: absolute;
  width: 3px; height: 3px;
  background: var(--gold);
  border-radius: 50%;
  opacity: 0;
  animation: particleFloat 12s var(--ease-smooth) infinite;
}
.hero-bg-particle:nth-child(1) { left: 10%; top: 20%; animation-delay: 0s; animation-duration: 10s; }
.hero-bg-particle:nth-child(2) { left: 25%; top: 60%; animation-delay: 2s; animation-duration: 14s; }
.hero-bg-particle:nth-child(3) { left: 55%; top: 15%; animation-delay: 4s; animation-duration: 11s; }
.hero-bg-particle:nth-child(4) { left: 75%; top: 45%; animation-delay: 1s; animation-duration: 13s; }
.hero-bg-particle:nth-child(5) { left: 85%; top: 75%; animation-delay: 6s; animation-duration: 9s; }
.hero-bg-particle:nth-child(6) { left: 40%; top: 80%; animation-delay: 3s; animation-duration: 12s; }
@keyframes particleFloat {
  0%, 100% { opacity: 0; transform: translateY(0) scale(1); }
  20% { opacity: 0.5; }
  50% { opacity: 0.3; transform: translateY(-60px) scale(1.5); }
  80% { opacity: 0.5; }
}

/* Diagonal gold accent line */
.hero-bg-accent {
  position: absolute;
  top: 0; right: 40%;
  width: 1px; height: 120%;
  background: linear-gradient(180deg, transparent 0%, var(--gold) 30%, var(--gold) 70%, transparent 100%);
  opacity: 0.06;
  transform: rotate(15deg);
  transform-origin: top center;
  animation: accentShimmer 6s var(--ease-smooth) infinite;
}
@keyframes accentShimmer {
  0%, 100% { opacity: 0.04; }
  50% { opacity: 0.1; }
}

.hero {
  position: relative;
  padding: 0 24px;
  max-width: 1440px;
  margin: 0 auto;
  overflow: visible;
  min-height: calc(100vh - 94px);
  display: flex;
  align-items: center;
  z-index: 1;
}

.hero-grid {
  display: grid;
  grid-template-columns: 1.1fr 0.9fr;
  gap: 48px;
  align-items: center;
  position: relative;
  z-index: 2;
  width: 100%;
  padding: 24px 0;
}

.eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 16px;
  font-family: var(--mono);
  font-size: 11px;
  letter-spacing: 0.25em;
  text-transform: uppercase;
  color: var(--gold-deep);
  margin-bottom: 16px;
  opacity: 0;
  animation: fadeUp 1s var(--ease) 0.2s forwards;
}
.eyebrow .line {
  width: 48px; height: 1px; background: var(--gold-deep);
  animation: lineGrow 1.2s var(--ease) 0.3s forwards;
  transform-origin: left;
  transform: scaleX(0);
}
@keyframes lineGrow { to { transform: scaleX(1); } }

/* Hero H1 with per-word reveal */
.hero h1 {
  font-family: var(--display);
  font-size: clamp(44px, 5.5vw, 80px);
  line-height: 1.04;
  font-weight: 800;
  letter-spacing: -0.04em;
  color: var(--ink);
  margin-bottom: 28px;
}
.hero h1 .word {
  display: inline-block;
  opacity: 0;
  transform: translateY(40px);
  animation: wordReveal 0.8s var(--ease-smooth) forwards;
}
.hero h1 em {
  font-style: normal;
  font-weight: 800;
  color: transparent;
  background: linear-gradient(135deg, #2196F3 0%, #1A6FC4 50%, #0F2043 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  position: relative;
  display: inline-block;
}
.hero h1 em::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  width: 0;
  height: 3px;
  background: linear-gradient(90deg, #2196F3, #1A6FC4);
  animation: underlineGrow 1.2s var(--ease) 1.8s forwards;
  border-radius: 2px;
}
@keyframes underlineGrow { to { width: 100%; } }
@keyframes wordReveal {
  to { opacity: 1; transform: translateY(0) rotateX(0deg); }
}

.hero-sub {
  font-size: 16px;
  line-height: 1.6;
  color: var(--smoke);
  max-width: 520px;
  margin-bottom: 28px;
  font-weight: 400;
  opacity: 0;
  animation: fadeUp 1s var(--ease) 1.4s forwards;
}

.hero-cta {
  display: flex;
  gap: 16px;
  margin-bottom: 32px;
  flex-wrap: wrap;
  opacity: 0;
  animation: fadeUp 1s var(--ease) 1.6s forwards;
}

.trust-row {
  display: flex;
  gap: 36px;
  padding-top: 24px;
  border-top: 1px solid var(--fog);
  flex-wrap: wrap;
  opacity: 0;
  animation: fadeUp 1s var(--ease) 1.8s forwards;
}
.trust-item { display: flex; flex-direction: column; gap: 4px; }
.trust-item .num {
  font-family: var(--display);
  font-size: 30px;
  font-weight: 600;
  color: var(--ink);
  letter-spacing: -0.035em;
  text-shadow: 0 1px 20px rgba(15, 32, 67, 0.06);
  line-height: 1;
}
.trust-item .num .plus { color: var(--gold-deep);  }
.trust-item .label {
  font-size: 11px;
  letter-spacing: 0.15em;
  text-transform: uppercase;
  color: var(--ash);
  font-family: var(--mono);
}

@keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }

/* Hero Right — Image */
.hero-image {
  position: relative;
  border-radius: 16px;
  overflow: hidden;
  opacity: 0;
  transform: translateY(40px) scale(0.95);
  animation: cardEnter 1.4s var(--ease) 0.8s forwards;
}
@keyframes cardEnter {
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.hero-image img {
  width: 100%;
  height: 100%;
  max-height: calc(100vh - 160px);
  object-fit: cover;
  border-radius: 16px;
  box-shadow:
    0 40px 80px -20px rgba(15, 32, 67, 0.18),
    0 20px 40px -10px rgba(15, 32, 67, 0.1);
  transition: transform 0.6s var(--ease);
}
.hero-image:hover img {
  transform: scale(1.03);
}
/* Decorative glow behind image */
.hero-image::before {
  content: '';
  position: absolute;
  inset: -20px;
  background: radial-gradient(circle at 50% 50%, rgba(26, 111, 196, 0.12), transparent 70%);
  z-index: -1;
  border-radius: 24px;
  animation: pulseRing 4s ease-in-out infinite;
}
.hero-image::after {
  content: '';
  position: absolute;
  top: 14px; left: 20px;
  width: 28px; height: 28px;
  border-left: 2px solid rgba(33, 150, 243, 0.5);
  border-top: 2px solid rgba(33, 150, 243, 0.5);
  border-radius: 2px 0 0 0;
}

/* ============================================
   MARQUEE (infinite scroll banner)
   ============================================ */
.marquee {
  background: linear-gradient(90deg, var(--ink), var(--charcoal), var(--ink));
  color: var(--ivory);
  padding: 72px 0;
  overflow: hidden;
  border-top: 1px solid rgba(26, 111, 196, 0.15);
  border-bottom: 1px solid rgba(26, 111, 196, 0.15);
  position: relative;
}
.marquee-track {
  display: flex;
  align-items: center;
  gap: 80px;
  white-space: nowrap;
  animation: marqueeScroll 40s linear infinite;
  width: max-content;
}
.marquee-item {
  font-family: var(--display);
  font-size: clamp(48px, 6vw, 88px);
  font-weight: 700;
  letter-spacing: -0.03em;
  display: inline-flex;
  align-items: center;
  gap: 48px;
  text-transform: uppercase;
  text-shadow: 0 2px 30px rgba(26, 111, 196, 0.15);
}
.marquee-item .star {
  color: var(--gold-light);
  font-style: normal;
  font-size: 0.5em;
  transform: rotate(0deg);
  animation: spin 8s linear infinite;
  display: inline-block;
  filter: drop-shadow(0 0 8px rgba(33, 150, 243, 0.4));
}
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes marqueeScroll {
  from { transform: translateX(0); }
  to { transform: translateX(-50%); }
}

/* ============================================
   SECTIONS BASE
   ============================================ */
section { padding: 140px 48px; position: relative; z-index: 2; }
.container { max-width: 1440px; margin: 0 auto; }

.section-label {
  font-family: var(--mono);
  font-size: 12px;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--gold);
  margin-bottom: 20px;
  display: inline-flex;
  align-items: center;
  gap: 14px;
  font-weight: 500;
}
.section-label::before {
  content: ''; width: 28px; height: 2px; background: var(--gold); border-radius: 1px;
}

.section-head {
  display: grid;
  grid-template-columns: 1fr 1.3fr;
  gap: 80px;
  margin-bottom: 90px;
  align-items: end;
}
.section-title {
  font-family: var(--display);
  font-size: clamp(36px, 4.5vw, 64px);
  font-weight: 700;
  line-height: 1.08;
  letter-spacing: -0.035em;
  color: var(--ink);
}
.section-title em {
  font-weight: 700;
  font-style: normal;
  color: transparent;
  background: linear-gradient(135deg, #2196F3 0%, #1A6FC4 40%, #0F2043 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.section-intro {
  font-size: 17px;
  line-height: 1.7;
  color: var(--smoke);
  font-weight: 400;
  max-width: 560px;
}

/* ============================================
   STATS STRIP (counting numbers)
   ============================================ */
.stats-strip {
  padding: 100px 48px;
  background: var(--white);
  border-top: 1px solid var(--fog);
  border-bottom: 1px solid var(--fog);
}
.stats-grid {
  max-width: 1440px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0;
}
.stat-block {
  padding: 24px 40px;
  border-right: 1px solid var(--fog);
  position: relative;
  transition: background 0.4s var(--ease);
}
.stat-block:last-child { border-right: none; }
.stat-block:hover { background: var(--paper); }
.stat-block:hover .stat-big { color: var(--gold-deep); }
.stat-big {
  font-family: var(--display);
  font-size: clamp(52px, 6vw, 80px);
  font-weight: 600;
  color: var(--ink);
  letter-spacing: -0.04em;
  line-height: 1;
  text-shadow: 0 2px 30px rgba(15, 32, 67, 0.05);
  transition: color 0.4s var(--ease), text-shadow 0.4s var(--ease);
  display: flex;
  align-items: baseline;
  gap: 4px;
}
.stat-block:hover .stat-big {
  text-shadow: 0 4px 40px rgba(26, 111, 196, 0.15);
}
.stat-big .sym { color: var(--gold-deep); font-size: 0.55em; font-weight: 400; }
.stat-big .unit { color: var(--ash); font-size: 0.4em; font-style: normal; font-weight: 400; margin-left: 6px; font-family: var(--body); }
.stat-desc {
  margin-top: 16px;
  font-family: var(--mono);
  font-size: 11px;
  letter-spacing: 0.15em;
  text-transform: uppercase;
  color: var(--ash);
  padding-top: 16px;
  border-top: 1px solid var(--fog);
}

/* ============================================
   APEX METHOD — 4 stages with timeline
   ============================================ */
.method {
  background: var(--paper);
  position: relative;
}
.method::after {
  content: 'FULFILLMENT WORKFLOW';
  position: absolute;
  top: 60px; right: 48px;
  font-family: var(--mono);
  font-size: 10px;
  letter-spacing: 0.35em;
  color: var(--ash);
}

.timeline {
  position: relative;
  margin-top: 40px;
}
.timeline-line {
  position: absolute;
  top: 44px;
  left: 4%;
  right: 4%;
  height: 1px;
  background: var(--fog);
}
.timeline-line::before {
  content: '';
  position: absolute;
  top: 0; left: 0;
  height: 100%;
  width: 0;
  background: var(--gold);
  animation: fillLine 3s var(--ease) forwards;
  animation-play-state: paused;
}
.timeline.in-view .timeline-line::before { animation-play-state: running; }
@keyframes fillLine { to { width: 100%; } }

.stages {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 40px;
  position: relative;
}
.stage {
  text-align: center;
  position: relative;
  padding-top: 0;
  opacity: 0;
  transform: translateY(40px);
  transition: opacity 0.8s var(--ease), transform 0.8s var(--ease);
}
.timeline.in-view .stage { opacity: 1; transform: translateY(0); }
.timeline.in-view .stage:nth-child(1) { transition-delay: 0.2s; }
.timeline.in-view .stage:nth-child(2) { transition-delay: 0.5s; }
.timeline.in-view .stage:nth-child(3) { transition-delay: 0.8s; }
.timeline.in-view .stage:nth-child(4) { transition-delay: 1.1s; }

.stage-dot {
  width: 88px; height: 88px;
  margin: 0 auto 32px;
  display: grid;
  place-items: center;
  background: var(--white);
  border: 1px solid var(--fog);
  border-radius: 50%;
  font-family: var(--display);
  font-size: 26px;
  font-weight: 500;
  color: var(--ink);
  position: relative;
  transition: all 0.5s var(--ease);
  z-index: 2;
  letter-spacing: -0.02em;
}
.stage-dot::before {
  content: '';
  position: absolute;
  inset: -6px;
  border: 1px solid var(--gold);
  border-radius: 50%;
  opacity: 0;
  transition: opacity 0.4s var(--ease), inset 0.4s var(--ease);
}
.stage:hover .stage-dot {
  background: var(--ink);
  color: var(--gold-light);
  transform: scale(1.08);
  box-shadow: 0 8px 30px rgba(15, 32, 67, 0.2), 0 0 0 4px rgba(26, 111, 196, 0.1);
}
.stage:hover .stage-dot::before { opacity: 1; inset: -10px; }

.stage-num {
  font-family: var(--mono);
  font-size: 10px;
  letter-spacing: 0.3em;
  color: var(--gold-deep);
  text-transform: uppercase;
  margin-bottom: 12px;
}
.stage h3 {
  font-family: var(--display);
  font-size: 22px;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 16px;
  letter-spacing: -0.02em;
}
.stage p {
  font-size: 14px;
  line-height: 1.65;
  color: var(--smoke);
  max-width: 260px;
  margin: 0 auto 20px;
}
.stage .tag {
  display: inline-block;
  font-family: var(--mono);
  font-size: 10px;
  letter-spacing: 0.15em;
  color: var(--gold-deep);
  text-transform: uppercase;
  padding: 6px 12px;
  border: 1px solid var(--gold);
  border-radius: 2px;
}

/* ============================================
   TARGETS — 8 dispute items
   ============================================ */
.targets {
  background: var(--white);
}
.targets-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0;
  border-top: 1px solid var(--fog);
  border-left: 1px solid var(--fog);
}
.target {
  background: var(--white);
  padding: 48px 36px;
  border-right: 1px solid var(--fog);
  border-bottom: 1px solid var(--fog);
  transition: all 0.5s var(--ease);
  position: relative;
  overflow: hidden;
  cursor: pointer;
}
.target::before {
  content: '';
  position: absolute;
  inset: 0;
  background: var(--ink);
  transform: translateY(101%);
  transition: transform 0.6s var(--ease);
  z-index: 0;
}
.target:hover::before, .target.active::before { transform: translateY(0); }
.target > * { position: relative; z-index: 1; transition: color 0.4s var(--ease); }
.target:hover h4, .target:hover .roman, .target.active h4, .target.active .roman { color: var(--gold-light); }
.target:hover p, .target:hover .status, .target.active p, .target.active .status { color: var(--ivory); }
.target:hover .status, .target.active .status { border-color: var(--gold); }

.target .roman {
  font-family: var(--mono);
  font-size: 11px;
  font-weight: 500;
  color: var(--gold-deep);
  letter-spacing: 0.25em;
  line-height: 1;
  margin-bottom: 28px;
  display: inline-block;
  padding: 6px 10px;
  border: 1px solid var(--fog);
  transition: color 0.4s, border-color 0.4s;
}
.target:hover .roman, .target.active .roman { border-color: var(--gold); }
.target h4 {
  font-family: var(--display);
  font-size: 20px;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 12px;
  letter-spacing: -0.02em;
}
.target p {
  font-size: 13px;
  color: var(--smoke);
  line-height: 1.6;
  margin-bottom: 24px;
}
.target .status {
  font-family: var(--mono);
  font-size: 10px;
  letter-spacing: 0.2em;
  color: var(--gold-deep);
  text-transform: uppercase;
  padding-top: 16px;
  border-top: 1px solid var(--fog);
  display: flex;
  align-items: center;
  gap: 8px;
}
.target .status::before { content: '→'; color: var(--gold); }

/* ============================================
   CREDIT SIMULATOR
   ============================================ */
.simulator {
  background: linear-gradient(180deg, var(--paper) 0%, var(--white) 100%);
  border-top: 1px solid var(--fog);
  border-bottom: 1px solid var(--fog);
}
.sim-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 80px;
  align-items: center;
}
.sim-left h2 {
  font-family: var(--display);
  font-size: clamp(36px, 4.5vw, 56px);
  font-weight: 500;
  line-height: 1.1;
  letter-spacing: -0.03em;
  color: var(--ink);
  margin-bottom: 24px;
}
.sim-left h2 em {  color: var(--gold-deep); font-weight: 500; }
.sim-left p {
  font-size: 17px;
  color: var(--smoke);
  line-height: 1.65;
  margin-bottom: 32px;
  font-weight: 400;
}

.sim-card {
  background: var(--white);
  border: 1px solid var(--fog);
  padding: 48px;
  position: relative;
  box-shadow: 0 30px 60px -20px rgba(15,32,67,0.1);
}
.sim-card::before {
  content: '';
  position: absolute;
  top: -1px; left: -1px;
  width: 36px; height: 36px;
  border-left: 2px solid var(--gold);
  border-top: 2px solid var(--gold);
}
.sim-card::after {
  content: '';
  position: absolute;
  bottom: -1px; right: -1px;
  width: 36px; height: 36px;
  border-right: 2px solid var(--gold);
  border-bottom: 2px solid var(--gold);
}
.sim-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--fog);
}
.sim-header .label {
  font-family: var(--mono);
  font-size: 10px;
  letter-spacing: 0.25em;
  color: var(--ash);
  text-transform: uppercase;
}
.sim-header .badge {
  font-family: var(--mono);
  font-size: 10px;
  padding: 4px 10px;
  background: var(--ink);
  color: var(--gold-light);
  border-radius: 100px;
  letter-spacing: 0.15em;
}
.sim-score-display {
  text-align: center;
  margin-bottom: 36px;
}
.sim-score-display .now {
  font-family: var(--display);
  font-size: 80px;
  font-weight: 500;
  color: var(--ink);
  letter-spacing: -0.05em;
  line-height: 1;
  font-variant-numeric: tabular-nums;
  transition: color 0.4s;
}
.sim-score-display .now.excellent { color: var(--gold-deep); }
.sim-score-display .cat-label {
  font-family: var(--mono);
  font-size: 11px;
  letter-spacing: 0.2em;
  color: var(--smoke);
  text-transform: uppercase;
  margin-top: 8px;
}

.sim-bar {
  height: 8px;
  background: var(--fog);
  border-radius: 100px;
  position: relative;
  margin-bottom: 8px;
  overflow: hidden;
}
.sim-bar-fill {
  position: absolute;
  top: 0; left: 0;
  height: 100%;
  background: linear-gradient(90deg, var(--crimson) 0%, #C48A1C 50%, var(--gold-deep) 100%);
  border-radius: 100px;
  transition: width 0.5s var(--ease);
}
.sim-range {
  display: flex;
  justify-content: space-between;
  font-family: var(--mono);
  font-size: 10px;
  color: var(--ash);
  letter-spacing: 0.1em;
  margin-bottom: 32px;
}

.sim-controls { display: flex; flex-direction: column; gap: 20px; }
.sim-control {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 0;
  border-bottom: 1px solid var(--fog);
  cursor: pointer;
  transition: background 0.3s;
}
.sim-control:hover { background: var(--paper); margin: 0 -12px; padding: 16px 12px; }
.sim-control .info { display: flex; flex-direction: column; gap: 2px; }
.sim-control .name { font-size: 14px; font-weight: 500; color: var(--ink); }
.sim-control .desc { font-size: 12px; color: var(--ash); }
.sim-control .toggle {
  position: relative;
  width: 44px; height: 24px;
  background: var(--fog);
  border-radius: 100px;
  transition: background 0.3s;
  flex-shrink: 0;
}
.sim-control .toggle::after {
  content: '';
  position: absolute;
  top: 3px; left: 3px;
  width: 18px; height: 18px;
  background: var(--white);
  border-radius: 50%;
  transition: transform 0.3s var(--ease);
  box-shadow: 0 2px 4px rgba(0,0,0,0.15);
}
.sim-control.active .toggle { background: var(--ink); }
.sim-control.active .toggle::after { transform: translateX(20px); background: var(--gold-light); }
.sim-control .pts {
  font-family: var(--mono);
  font-size: 11px;
  color: var(--gold-deep);
  font-weight: 500;
  margin-left: 16px;
  min-width: 40px;
  text-align: right;
}

/* ============================================
   RESULTS / PROOF
   ============================================ */
.proof { background: var(--ivory); color: var(--ink); }
.proof .section-title { color: var(--ink); }
.proof .section-title em {
  background: linear-gradient(135deg, #2196F3 0%, #1A6FC4 40%, #0F2043 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.proof .section-intro { color: var(--smoke); }
.proof .section-label { color: var(--gold); }
.proof .section-label::before { background: var(--gold); }

.proof-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
}
.proof-img {
  position: relative;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid rgba(26, 111, 196, 0.15);
  transition: all 0.5s var(--ease);
  cursor: pointer;
}
.proof-img:hover {
  transform: translateY(-8px) scale(1.03);
  border-color: var(--gold);
  box-shadow:
    0 24px 60px rgba(15, 32, 67, 0.18),
    0 0 0 4px rgba(26, 111, 196, 0.08),
    0 0 30px rgba(26, 111, 196, 0.12);
}
.proof-img::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, transparent 60%, rgba(26, 111, 196, 0.08));
  opacity: 0;
  transition: opacity 0.4s var(--ease);
  border-radius: 12px;
}
.proof-img:hover::after { opacity: 1; }
.proof-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  border-radius: 9px;
}
.proof-view-more {
  text-align: center;
  margin-top: 48px;
}
.proof-view-more a {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-family: var(--mono);
  font-size: 12px;
  letter-spacing: 0.15em;
  text-transform: uppercase;
  color: var(--gold);
  padding: 16px 36px;
  border: 1px solid rgba(26, 111, 196, 0.35);
  border-radius: 2px;
  transition: all 0.4s var(--ease);
}
.proof-view-more a:hover {
  background: var(--gold);
  color: var(--white);
  border-color: var(--gold-deep);
  transform: translateY(-2px);
  box-shadow: 0 12px 32px rgba(26, 111, 196, 0.25);
}
.proof-view-more a .arrow {
  transition: transform 0.4s var(--ease);
  font-weight: 400;
}
.proof-view-more a:hover .arrow { transform: translateX(6px); }

/* ============================================
   VS COMPARISON
   ============================================ */
.versus { background: var(--paper); }
.vs-table {
  background: var(--white);
  border: 1px solid var(--fog);
  border-radius: 2px;
  overflow: hidden;
  box-shadow: 0 20px 40px -20px rgba(15,32,67,0.08);
}
.vs-head, .vs-row {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr;
  align-items: center;
}
.vs-head {
  background: var(--ink);
  color: var(--ivory);
  padding: 24px 36px;
}
.vs-head .col {
  font-family: var(--mono);
  font-size: 11px;
  letter-spacing: 0.2em;
  text-transform: uppercase;
}
.vs-head .col.apex { color: var(--gold-light); display: flex; align-items: center; gap: 8px; }
.vs-head .col.apex::before {
  content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--gold);
}
.vs-row {
  padding: 20px 36px;
  border-bottom: 1px solid var(--fog);
  transition: background 0.3s;
}
.vs-row:last-child { border-bottom: none; }
.vs-row:hover { background: var(--paper); }
.vs-row .feature { font-family: var(--display); font-size: 17px; font-weight: 400; color: var(--ink); }
.vs-row .check-apex, .vs-row .check-others {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
}
.vs-row .check-apex { color: var(--gold-deep); font-weight: 500; }
.vs-row .check-apex::before { content: '✓'; font-weight: 700; font-size: 16px; }
.vs-row .check-others { color: var(--ash); }
.vs-row .check-others::before { content: '✕'; font-weight: 600; font-size: 14px; color: var(--crimson); }

/* ============================================
   PRICING
   ============================================ */
.pricing { background: var(--white); }
.pricing-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
.price-card {
  background: var(--white);
  border: 1px solid var(--fog);
  padding: 52px 44px;
  position: relative;
  transition: all 0.5s var(--ease);
  display: flex;
  flex-direction: column;
}
.price-card:hover {
  transform: translateY(-10px);
  border-color: var(--gold);
  box-shadow: 0 40px 80px -20px rgba(15,32,67,0.15);
}
.price-card.featured {
  background: var(--ink);
  color: var(--ivory);
  border: 1px solid var(--ink);
  transform: scale(1.02);
}
.price-card.featured:hover { transform: translateY(-10px) scale(1.02); }
.price-card.featured::before {
  content: 'MOST CHOSEN';
  position: absolute;
  top: 0; left: 50%;
  transform: translate(-50%, -50%);
  background: var(--gold);
  color: var(--white);
  font-family: var(--mono);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.25em;
  padding: 7px 18px;
}
.price-tier {
  font-family: var(--mono);
  font-size: 11px;
  letter-spacing: 0.25em;
  color: var(--gold-deep);
  text-transform: uppercase;
  margin-bottom: 14px;
}
.price-card.featured .price-tier { color: var(--gold-light); }
.price-card h3 {
  font-family: var(--display);
  font-size: 26px;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 24px;
  letter-spacing: -0.025em;
}
.price-card.featured h3 { color: var(--ivory); }
.price-amount { display: flex; align-items: baseline; gap: 6px; margin-bottom: 10px; color: var(--ink); }
.price-card.featured .price-amount { color: var(--ivory); }
.price-amount .dollar { font-family: var(--display); font-size: 28px;  color: var(--gold-deep); }
.price-card.featured .price-amount .dollar { color: var(--gold-light); }
.price-amount .num {
  font-family: var(--display);
  font-size: 60px;
  font-weight: 500;
  letter-spacing: -0.045em;
  line-height: 1;
}
.price-amount .period { font-size: 14px; color: var(--ash); margin-left: 6px; }
.price-card.featured .price-amount .period { color: var(--dust); }

.price-note {
  font-size: 13px;
  color: var(--smoke);
  margin-bottom: 36px;
  padding-bottom: 32px;
  border-bottom: 1px solid var(--fog);
  line-height: 1.55;
}
.price-card.featured .price-note { color: var(--dust); border-bottom-color: rgba(26,111,196,0.2); }

.price-features { list-style: none; margin-bottom: 40px; flex: 1; }
.price-features li {
  display: flex;
  gap: 14px;
  padding: 11px 0;
  font-size: 14px;
  color: var(--smoke);
  line-height: 1.5;
  align-items: flex-start;
}
.price-card.featured .price-features li { color: var(--ivory); }
.price-features li::before {
  content: '✓';
  color: var(--gold-deep);
  font-weight: 700;
  flex-shrink: 0;
}
.price-card.featured .price-features li::before { color: var(--gold-light); }

/* ============================================
   FOUNDER / ABOUT
   ============================================ */
.founder { background: var(--paper); }
.founder-story {
  max-width: 800px;
  margin: 0 auto;
  text-align: center;
}
.founder-story h2 {
  font-family: var(--display);
  font-size: clamp(32px, 4vw, 52px);
  font-weight: 700;
  line-height: 1.08;
  letter-spacing: -0.035em;
  color: var(--ink);
  margin-bottom: 40px;
}
.founder-story h2 em {
  font-weight: 700;
  font-style: normal;
  color: transparent;
  background: linear-gradient(135deg, #2196F3 0%, #1A6FC4 40%, #0F2043 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.founder-story-body {
  text-align: left;
  max-width: 680px;
  margin: 0 auto;
}
.founder-story p {
  font-size: 17px;
  line-height: 1.75;
  color: var(--smoke);
  margin-bottom: 20px;
  font-weight: 400;
}
.founder-story p strong { color: var(--ink); font-weight: 600; }
.founder-story .sig {
  font-family: var(--display);
  font-size: 22px;
  color: var(--gold-deep);
  font-weight: 600;
  letter-spacing: -0.02em;
  margin-top: 40px;
  padding-top: 32px;
  border-top: 1px solid var(--fog);
  text-align: center;
}
.founder-story .sig-title {
  font-family: var(--mono);
  font-size: 10px;
  letter-spacing: 0.25em;
  color: var(--ash);
  text-transform: uppercase;
  margin-top: 6px;
  text-align: center;
}

/* ============================================
   FAQ
   ============================================ */
.faq { background: var(--white); }
.faq-list { max-width: 900px; margin: 0 auto; border-top: 1px solid var(--fog); }
.faq-item { border-bottom: 1px solid var(--fog); }
.faq-q {
  width: 100%;
  padding: 32px 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  text-align: left;
  cursor: pointer;
  transition: color 0.3s;
  gap: 24px;
}
.faq-q:hover { color: var(--gold-deep); }
.faq-q h4 {
  font-family: var(--display);
  font-size: 19px;
  font-weight: 500;
  color: inherit;
  letter-spacing: -0.015em;
  line-height: 1.4;
  flex: 1;
}
.faq-icon {
  width: 40px; height: 40px;
  border: 1px solid var(--ink);
  border-radius: 50%;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  transition: all 0.4s var(--ease);
  font-family: var(--mono);
  font-weight: 400;
  font-size: 18px;
}
.faq-item.open .faq-icon { background: var(--ink); color: var(--gold-light); transform: rotate(45deg); }
.faq-a {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.6s var(--ease), padding 0.4s var(--ease);
  padding-right: 64px;
}
.faq-item.open .faq-a {
  max-height: 500px;
  padding-bottom: 32px;
}
.faq-a p {
  font-size: 16px;
  line-height: 1.7;
  color: var(--smoke);
  font-weight: 400;
}

/* ============================================
   GUARANTEE
   ============================================ */
.guarantee { background: linear-gradient(145deg, var(--ink) 0%, var(--charcoal) 50%, var(--ink) 100%); color: var(--ivory); position: relative; overflow: hidden; }
.guarantee-inner {
  max-width: 900px;
  margin: 0 auto;
  text-align: center;
  position: relative;
  padding: 100px 48px;
  z-index: 2;
}
.seal {
  width: 120px; height: 120px;
  margin: 0 auto 40px;
  display: grid;
  place-items: center;
  border: 1px solid var(--gold);
  border-radius: 50%;
  color: var(--gold-light);
  position: relative;
}
.seal::before {
  content: '';
  position: absolute;
  inset: -8px;
  border: 1px dashed var(--gold);
  border-radius: 50%;
  opacity: 0.4;
  animation: sealRotate 30s linear infinite;
}
@keyframes sealRotate { to { transform: rotate(360deg); } }
.guarantee h2 {
  font-family: var(--display);
  font-size: clamp(40px, 5vw, 64px);
  font-weight: 700;
  line-height: 1.06;
  letter-spacing: -0.035em;
  margin-bottom: 32px;
}
.guarantee h2 em {
  font-weight: 700;
  font-style: normal;
  color: transparent;
  background: linear-gradient(135deg, #60B5FF 0%, #2196F3 40%, #1A6FC4 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.guarantee p {
  font-size: 18px;
  line-height: 1.75;
  color: var(--dust);
  max-width: 640px;
  margin: 0 auto 20px;
  font-weight: 400;
}
.guarantee .sig-line {
  margin-top: 48px;
  padding-top: 32px;
  border-top: 1px solid rgba(26, 111, 196, 0.2);
  display: inline-block;
}
.guarantee .sig-line .name {
  font-family: var(--display);
  font-size: 20px;
  color: var(--gold-light);
  font-weight: 600;
  letter-spacing: -0.02em;
}
.guarantee .sig-line .role {
  font-family: var(--mono);
  font-size: 10px;
  letter-spacing: 0.25em;
  color: var(--ash);
  text-transform: uppercase;
  margin-top: 8px;
}

/* ============================================
   CTA FINAL
   ============================================ */
.cta-final {
  background: linear-gradient(180deg, var(--white), var(--ivory));
  padding: 160px 48px;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.cta-final-inner { max-width: 1000px; margin: 0 auto; position: relative; z-index: 2; }
.cta-final h2 {
  font-family: var(--display);
  font-size: clamp(48px, 7vw, 96px);
  font-weight: 800;
  line-height: 1.02;
  letter-spacing: -0.045em;
  margin-bottom: 40px;
  color: var(--ink);
}
.cta-final h2 em {
  font-style: normal;
  font-weight: 800;
  color: transparent;
  background: linear-gradient(135deg, #2196F3 0%, #1A6FC4 40%, #0F2043 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  position: relative;
}
.cta-final h2 em::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  width: 0;
  height: 4px;
  background: var(--gold);
  transition: width 1.5s var(--ease) 0.3s;
}
.cta-final.in-view h2 em::after { width: 100%; }

.cta-final p {
  font-size: 19px;
  color: var(--smoke);
  max-width: 600px;
  margin: 0 auto 56px;
  line-height: 1.6;
  font-weight: 400;
}
.cta-final .hero-cta { justify-content: center; gap: 20px; }

/* ============================================
   FOOTER
   ============================================ */
footer { background: linear-gradient(180deg, var(--ink) 0%, #081530 100%); color: #FFFFFF; padding: 100px 48px 48px; }
.footer-top {
  max-width: 1440px;
  margin: 0 auto 64px;
  padding-bottom: 56px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  display: grid;
  grid-template-columns: 1.2fr 1fr;
  gap: 80px;
  align-items: end;
}
.footer-top h3 {
  font-family: var(--display);
  font-size: clamp(36px, 4.5vw, 56px);
  font-weight: 700;
  line-height: 1.08;
  letter-spacing: -0.035em;
  color: #FFFFFF;
}
.footer-top h3 em {
  font-weight: 700;
  font-style: normal;
  background: linear-gradient(135deg, #60B5FF 0%, #2196F3 50%, #1A6FC4 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.footer-contact { display: flex; flex-direction: column; gap: 16px; }
.footer-contact a {
  font-family: var(--display);
  font-size: 22px;
  font-weight: 500;
  color: #FFFFFF;
  letter-spacing: -0.02em;
  transition: color 0.3s, text-shadow 0.3s;
  display: inline-flex;
  align-items: center;
  gap: 12px;
}
.footer-contact a:hover { color: #DBEAFE; text-shadow: 0 0 20px rgba(33, 150, 243, 0.3); }
.footer-contact a::before {
  content: '→';
  font-family: var(--mono);
  font-style: normal;
  font-size: 18px;
}

.footer-grid {
  max-width: 1440px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 64px;
  margin-bottom: 60px;
}
.footer-brand p { font-size: 14px; color: rgba(255,255,255,0.7); line-height: 1.65; margin-top: 24px; max-width: 360px; }
.footer-col h5 {
  font-family: var(--mono);
  font-size: 11px;
  letter-spacing: 0.25em;
  color: #FFFFFF;
  text-transform: uppercase;
  margin-bottom: 24px;
}
.footer-col ul { list-style: none; }
.footer-col li { margin-bottom: 14px; }
.footer-col a { font-size: 14px; color: rgba(255,255,255,0.7); transition: color 0.3s; }
.footer-col a:hover { color: #DBEAFE; }

.footer-disclaimer {
  max-width: 1440px;
  margin: 0 auto;
  padding-top: 40px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  font-size: 11px;
  color: rgba(255,255,255,0.5);
  line-height: 1.75;
}
.footer-disclaimer strong { color: rgba(255,255,255,0.7); }

.footer-bottom {
  max-width: 1440px;
  margin: 40px auto 0;
  padding-top: 28px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11px;
  color: rgba(255,255,255,0.5);
  font-family: var(--mono);
  letter-spacing: 0.15em;
  flex-wrap: wrap;
  gap: 16px;
}

/* ============================================
   SECTION A — COST OF WAITING
   ============================================ */
.cost-section { background: var(--white); }
.cost-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 80px;
  align-items: start;
}
.cost-left h2 {
  font-family: var(--display);
  font-size: clamp(32px, 4vw, 52px);
  font-weight: 700;
  line-height: 1.08;
  letter-spacing: -0.035em;
  color: var(--ink);
  margin-bottom: 24px;
}
.cost-left p {
  font-size: 17px;
  line-height: 1.75;
  color: var(--smoke);
  max-width: 460px;
}
.cost-items { display: flex; flex-direction: column; gap: 0; }
.cost-item {
  padding: 28px 0;
  border-bottom: 1px solid var(--fog);
}
.cost-item:first-child { padding-top: 0; }
.cost-item:last-child { border-bottom: none; }
.cost-val {
  font-family: var(--display);
  font-size: clamp(28px, 3.5vw, 44px);
  font-weight: 600;
  color: var(--ink);
  letter-spacing: -0.03em;
  line-height: 1.1;
  margin-bottom: 6px;
}
.cost-desc {
  font-size: 14px;
  color: var(--ash);
  line-height: 1.5;
}
.cost-cta-line {
  margin-top: 48px;
  padding-top: 32px;
  border-top: 1px solid var(--fog);
}
.cost-cta-line a {
  font-family: var(--mono);
  font-size: 12px;
  letter-spacing: 0.1em;
  color: var(--gold);
  transition: color 0.3s var(--ease);
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.cost-cta-line a:hover { color: var(--ink); }
.cost-cta-line a .arrow { transition: transform 0.3s var(--ease); }
.cost-cta-line a:hover .arrow { transform: translateX(4px); }

/* ============================================
   SECTION B — QUALIFIER
   ============================================ */
.qualifier { background: var(--paper); }
.qualifier-card {
  max-width: 800px;
  margin: 0 auto;
  background: var(--ink);
  border: 1px solid rgba(26, 111, 196, 0.15);
  padding: 56px;
  position: relative;
}
.qual-progress {
  display: flex;
  gap: 8px;
  margin-bottom: 48px;
}
.qual-bar {
  flex: 1;
  height: 2px;
  background: rgba(255,255,255,0.1);
  border-radius: 1px;
  overflow: hidden;
}
.qual-bar-fill {
  height: 100%;
  width: 0;
  background: var(--gold-light);
  border-radius: 1px;
  transition: width 0.5s var(--ease);
}
.qual-bar.active .qual-bar-fill { width: 100%; }
.qual-bar.done .qual-bar-fill { width: 100%; }
.qual-step-label {
  display: flex;
  justify-content: space-between;
  margin-bottom: 8px;
}
.qual-step-label span {
  font-family: var(--mono);
  font-size: 10px;
  letter-spacing: 0.15em;
  color: rgba(255,255,255,0.3);
  text-transform: uppercase;
}
.qual-step-label span.active { color: var(--gold-light); }
.qual-step {
  display: none;
}
.qual-step.active { display: block; }
.qual-step h3 {
  font-family: var(--display);
  font-size: 28px;
  font-weight: 600;
  color: var(--ivory);
  margin-bottom: 32px;
  letter-spacing: -0.02em;
}
.qual-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
.qual-pill {
  padding: 12px 22px;
  font-family: var(--body);
  font-size: 14px;
  font-weight: 500;
  color: rgba(255,255,255,0.6);
  background: transparent;
  border: 1px solid rgba(255,255,255,0.15);
  cursor: pointer;
  transition: all 0.3s var(--ease);
}
.qual-pill:hover {
  border-color: rgba(255,255,255,0.4);
  color: rgba(255,255,255,0.9);
}
.qual-pill.selected {
  background: var(--ivory);
  color: var(--ink);
  border-color: var(--ivory);
}
.qual-fields {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 24px;
}
.qual-fields input {
  width: 100%;
  padding: 14px 18px;
  font-family: var(--body);
  font-size: 14px;
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.15);
  color: var(--ivory);
  outline: none;
  transition: border-color 0.3s;
}
.qual-fields input::placeholder { color: rgba(255,255,255,0.3); }
.qual-fields input:focus { border-color: var(--gold-light); }
.qual-submit {
  width: 100%;
  padding: 16px;
  background: var(--gold);
  color: var(--white);
  border: none;
  font-family: var(--body);
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  cursor: pointer;
  transition: all 0.4s var(--ease);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}
.qual-submit:hover { background: var(--gold-light); transform: translateY(-2px); box-shadow: 0 12px 32px rgba(26, 111, 196, 0.3); }
.qual-legal {
  margin-top: 20px;
  font-size: 11px;
  color: rgba(255,255,255,0.25);
  text-align: center;
  line-height: 1.5;
}
.qual-success {
  text-align: center;
  padding: 40px 0;
  display: none;
}
.qual-success.active { display: block; }
.qual-success h3 { margin-bottom: 12px; }
.qual-success p { color: var(--dust); font-size: 15px; }

/* ============================================
   SECTION C — VIDEO / ON RECORD
   ============================================ */
.on-record { background: var(--ink); color: var(--ivory); }
.on-record .section-label { color: var(--gold-light); }
.on-record .section-label::before { background: var(--gold-light); }
.video-wrap {
  max-width: 960px;
  margin: 0 auto;
}
.video-player {
  position: relative;
  aspect-ratio: 16/9;
  background: var(--charcoal);
  border: 1px solid rgba(255,255,255,0.06);
  overflow: hidden;
  cursor: pointer;
}
.video-player video,
.video-player iframe {
  width: 100%; height: 100%;
  object-fit: cover;
  display: block;
}
.video-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 32, 67, 0.4);
  transition: background 0.4s var(--ease);
}
.video-player:hover .video-overlay { background: rgba(15, 32, 67, 0.25); }
.play-btn {
  width: 72px; height: 72px;
  border: 2px solid rgba(255,255,255,0.5);
  border-radius: 50%;
  display: grid;
  place-items: center;
  transition: all 0.4s var(--ease);
}
.play-btn::after {
  content: '';
  width: 0; height: 0;
  border-top: 12px solid transparent;
  border-bottom: 12px solid transparent;
  border-left: 20px solid rgba(255,255,255,0.8);
  margin-left: 4px;
}
.video-player:hover .play-btn { border-color: var(--white); transform: scale(1.08); }
.video-micro {
  position: absolute;
  top: 20px; left: 24px;
  font-family: var(--mono);
  font-size: 10px;
  letter-spacing: 0.15em;
  color: rgba(255,255,255,0.5);
  text-transform: uppercase;
  z-index: 2;
}
.video-quote {
  max-width: 720px;
  margin: 48px auto 0;
  text-align: center;
}
.video-quote blockquote {
  font-family: var(--display);
  font-size: clamp(22px, 3vw, 32px);
  font-weight: 400;
  line-height: 1.4;
  letter-spacing: -0.02em;
  color: var(--ivory);
  font-style: italic;
  margin-bottom: 20px;
}
.video-quote cite {
  font-family: var(--mono);
  font-size: 11px;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--dust);
  font-style: normal;
}

/* ============================================
   SECTION D — BEFORE / AFTER SHIFT
   ============================================ */
.shift { background: var(--white); }
.shift-grid {
  max-width: 900px;
  margin: 0 auto;
  border: 1px solid var(--fog);
}
.shift-header {
  display: grid;
  grid-template-columns: 1fr 1fr;
}
.shift-header span {
  font-family: var(--mono);
  font-size: 10px;
  letter-spacing: 0.25em;
  text-transform: uppercase;
  padding: 20px 36px;
  color: var(--ash);
}
.shift-header span:first-child { border-right: 1px solid var(--fog); }
.shift-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  border-top: 1px solid var(--fog);
}
.shift-row .before,
.shift-row .after {
  padding: 22px 36px;
  font-size: 16px;
  line-height: 1.5;
}
.shift-row .before {
  color: var(--dust);
  border-right: 1px solid var(--fog);
}
.shift-row .after {
  color: var(--ink);
  font-weight: 500;
}

/* ============================================
   SECTION E — MILESTONES TICKER
   ============================================ */
.milestones { background: var(--paper); padding: 64px 48px; }
.milestones-label {
  font-family: var(--mono);
  font-size: 11px;
  letter-spacing: 0.3em;
  text-transform: uppercase;
  color: var(--ash);
  text-align: center;
  margin-bottom: 24px;
}
.milestones-track-wrap {
  overflow: hidden;
  position: relative;
}
.milestones-track {
  display: flex;
  gap: 48px;
  white-space: nowrap;
  animation: milestoneScroll 60s linear infinite;
  width: max-content;
}
.milestone-item {
  font-family: var(--mono);
  font-size: 13px;
  letter-spacing: 0.08em;
  color: var(--smoke);
  display: inline-flex;
  align-items: center;
  gap: 12px;
}
.milestone-item .star { color: var(--gold); font-size: 10px; }
@keyframes milestoneScroll {
  from { transform: translateX(0); }
  to { transform: translateX(-50%); }
}

/* ============================================
   SECTION F — RESOURCE LIBRARY
   ============================================ */
.library { background: var(--white); }
.library-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
}
.library-card {
  background: var(--ink);
  border: 1px solid rgba(26, 111, 196, 0.15);
  padding: 44px 36px;
  transition: all 0.5s var(--ease);
  display: flex;
  flex-direction: column;
}
.library-card:hover {
  border-color: var(--gold);
  transform: translateY(-6px);
  box-shadow: 0 24px 48px rgba(15, 32, 67, 0.2);
}
.library-card-label {
  font-family: var(--mono);
  font-size: 10px;
  letter-spacing: 0.2em;
  color: var(--dust);
  text-transform: uppercase;
  margin-bottom: 24px;
}
.library-card h3 {
  font-family: var(--display);
  font-size: 24px;
  font-weight: 600;
  color: var(--ivory);
  letter-spacing: -0.02em;
  margin-bottom: 12px;
  line-height: 1.25;
}
.library-card p {
  font-size: 14px;
  color: var(--dust);
  line-height: 1.65;
  margin-bottom: 32px;
  flex: 1;
}
.library-card-cta {
  font-family: var(--mono);
  font-size: 12px;
  letter-spacing: 0.1em;
  color: var(--gold-light);
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: color 0.3s var(--ease);
}
.library-card:hover .library-card-cta { color: var(--ivory); }
.library-card-cta .arrow { transition: transform 0.3s var(--ease); }
.library-card:hover .library-card-cta .arrow { transform: translateX(4px); }

/* ============================================
   REVEAL ANIMATIONS
   ============================================ */
.reveal { opacity: 0; transform: translateY(50px); transition: opacity 0.9s var(--ease), transform 0.9s var(--ease), filter 0.9s var(--ease); filter: blur(3px); }
.reveal.in { opacity: 1; transform: translateY(0); filter: blur(0); }

.reveal-stagger > * { opacity: 0; transform: translateY(30px); transition: opacity 0.8s var(--ease), transform 0.8s var(--ease); }
.reveal-stagger.in > *:nth-child(1) { opacity: 1; transform: translateY(0); transition-delay: 0.1s; }
.reveal-stagger.in > *:nth-child(2) { opacity: 1; transform: translateY(0); transition-delay: 0.2s; }
.reveal-stagger.in > *:nth-child(3) { opacity: 1; transform: translateY(0); transition-delay: 0.3s; }
.reveal-stagger.in > *:nth-child(4) { opacity: 1; transform: translateY(0); transition-delay: 0.4s; }
.reveal-stagger.in > *:nth-child(5) { opacity: 1; transform: translateY(0); transition-delay: 0.5s; }
.reveal-stagger.in > *:nth-child(6) { opacity: 1; transform: translateY(0); transition-delay: 0.6s; }
.reveal-stagger.in > *:nth-child(7) { opacity: 1; transform: translateY(0); transition-delay: 0.7s; }
.reveal-stagger.in > *:nth-child(8) { opacity: 1; transform: translateY(0); transition-delay: 0.8s; }

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 1024px) {
  .hero-grid { grid-template-columns: 1fr; gap: 40px; }
  .hero { min-height: auto; padding: 32px 24px 48px; }
  .section-head { grid-template-columns: 1fr; gap: 32px; }
  .stages { grid-template-columns: repeat(2, 1fr); gap: 64px; }
  .timeline-line { display: none; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .stat-block:nth-child(2n) { border-right: none; }
  .stat-block { border-bottom: 1px solid var(--fog); }
  .stat-block:nth-child(n+3) { border-bottom: none; }
  .targets-grid { grid-template-columns: repeat(2, 1fr); }
  .sim-grid, .founder-grid { grid-template-columns: 1fr; gap: 48px; }
  .proof-grid { grid-template-columns: repeat(2, 1fr); }
  .pricing-grid { grid-template-columns: 1fr; }
  .price-card.featured { transform: none; }
  .price-card.featured:hover { transform: translateY(-10px); }
  .footer-grid { grid-template-columns: 1fr 1fr; }
  .footer-top { grid-template-columns: 1fr; gap: 40px; }
  .vs-head, .vs-row { grid-template-columns: 1.5fr 1fr 1fr; }
  .cost-grid { grid-template-columns: 1fr; gap: 48px; }
  .qualifier-card { padding: 40px 32px; }
  .shift-row .before, .shift-row .after { padding: 18px 24px; font-size: 14px; }
  .library-grid { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  section { padding: 90px 24px; }
  .hero { padding: 24px 16px 40px; min-height: auto; }
  .nav-inner { padding: 4px 16px; }
  .logo-img { height: 70px; }
  .nav-links { display: none; }
  .stages { grid-template-columns: 1fr; }
  .stats-grid { grid-template-columns: 1fr; }
  .stat-block { border-right: none; border-bottom: 1px solid var(--fog); }
  .stat-block:last-child { border-bottom: none; }
  .targets-grid { grid-template-columns: 1fr; }
  .proof-grid { grid-template-columns: 1fr; gap: 16px; }
  .footer-grid { grid-template-columns: 1fr; gap: 40px; }
  .footer-bottom { flex-direction: column; text-align: center; }
  .trust-row { gap: 32px; }
  .trust-item .num { font-size: 30px; }
  .sim-card { padding: 32px 24px; }
  .vs-head, .vs-row { grid-template-columns: 1.5fr 0.8fr 0.8fr; padding: 16px 20px; font-size: 13px; }
  .vs-row .feature { font-size: 14px; }
  .vs-head .col { font-size: 9px; letter-spacing: 0.1em; }
  .guarantee-inner { padding: 80px 24px; }
  .seal { width: 96px; height: 96px; font-size: 40px; }
  footer { padding: 70px 24px 32px; }
  .cta-final { padding: 100px 24px; }
  .qual-fields { grid-template-columns: 1fr; }
  .qualifier-card { padding: 32px 24px; }
  .qual-pills { gap: 8px; }
  .qual-pill { padding: 10px 16px; font-size: 13px; }
  .shift-header, .shift-row { grid-template-columns: 1fr; }
  .shift-header span:first-child { border-right: none; border-bottom: 1px solid var(--fog); }
  .shift-row .before { border-right: none; border-bottom: 1px solid var(--fog); }
  .shift-row .before::before { content: 'BEFORE: '; font-family: var(--mono); font-size: 9px; letter-spacing: 0.15em; color: var(--ash); }
  .shift-row .after::before { content: 'AFTER: '; font-family: var(--mono); font-size: 9px; letter-spacing: 0.15em; color: var(--gold); }
  .milestones { padding: 48px 24px; }
  .milestones-track { animation-duration: 80s; }
}
</style>
</head>
<body>
@include('partials.loader')


@include('partials.bg-animation')

<!-- ============ NAV ============ -->
@include('partials.nav')

<!-- ============ HERO ============ -->
<div class="hero-wrapper">
  <div class="hero-bg">
    <div class="hero-bg-lines"></div>
    <div class="hero-bg-orb"></div>
    <div class="hero-bg-orb"></div>
    <div class="hero-bg-orb"></div>
    <div class="hero-bg-particle"></div>
    <div class="hero-bg-particle"></div>
    <div class="hero-bg-particle"></div>
    <div class="hero-bg-particle"></div>
    <div class="hero-bg-particle"></div>
    <div class="hero-bg-particle"></div>
    <div class="hero-bg-accent"></div>
  </div>
<header class="hero">
  <div class="hero-grid">
    <div class="hero-left">
      <div class="eyebrow">
        <span class="line"></span>
        Backend Credit Repair Fulfillment · Est. 2026
      </div>
      <h1>
        <span class="word" style="animation-delay: 0.4s">Scale</span>
        <span class="word" style="animation-delay: 0.5s">Your</span>
        <span class="word" style="animation-delay: 0.6s">Credit</span><br>
        <span class="word" style="animation-delay: 0.7s">Repair</span>
        <span class="word" style="animation-delay: 0.8s">Business.</span>
        <span class="word" style="animation-delay: 0.9s">We</span>
        <span class="word" style="animation-delay: 1.1s"><em>Run The&nbsp;Fulfillment.</em></span>
      </h1>
      <p class="hero-sub">
        Apex Growth Systems is the backend credit repair fulfillment partner for credit repair businesses. We handle dispute letter prep, bureau follow-up calls, CFPB and FTC complaint documentation, Innovis disputes, small bureau freeze support, response monitoring, and weekly client status reports — so your credit repair business can scale without drowning in manual work.
      </p>
      <div class="hero-cta">
        <a href="/trial" class="btn btn-gold"><span>Try 5 Test Clients</span> <span class="arrow">→</span></a>
        <a href="/contact" class="btn btn-ghost"><span>Book A Fulfillment Call</span></a>
      </div>
      <div class="trust-row">
        <div class="trust-item">
          <span class="num">72 Hrs</span>
          <span class="label">To Launch First Round</span>
        </div>
        <div class="trust-item">
          <span class="num">Weekly</span>
          <span class="label">Client File Updates</span>
        </div>
        <div class="trust-item">
          <span class="num">Multi-Channel</span>
          <span class="label">Dispute Workflow</span>
        </div>
      </div>
    </div>

    <div class="hero-right">
      <div class="hero-image" data-parallax="0.05">
        <picture>
          <source srcset="/Images/heroimage.webp" type="image/webp">
          <img src="/Images/heroimage.png" alt="Apex Growth Systems backend credit repair workflow"
               width="1200" height="900"
               loading="eager" decoding="async" fetchpriority="high">
        </picture>
      </div>
    </div>
  </div>
</header>
</div>



<!-- ============ MARQUEE ============ -->
<div class="marquee">
  <div class="marquee-track">
    <span class="marquee-item">Backend Credit Repair Operations <span class="star">✦</span> White-Label Dispute Support <span class="star">✦</span> Built For Credit Repair Businesses</span>
    <span class="marquee-item">Backend Credit Repair Operations <span class="star">✦</span> White-Label Dispute Support <span class="star">✦</span> Built For Credit Repair Businesses</span>
    <span class="marquee-item">Backend Credit Repair Operations <span class="star">✦</span> White-Label Dispute Support <span class="star">✦</span> Built For Credit Repair Businesses</span>
    <span class="marquee-item">Backend Credit Repair Operations <span class="star">✦</span> White-Label Dispute Support <span class="star">✦</span> Built For Credit Repair Businesses</span>
  </div>
</div>



<!-- ============ PROBLEM ============ -->
<section class="targets" id="problem">
  <div class="container">
    <div class="section-label">01 · The Problem</div>
    <div class="section-head">
      <h2 class="section-title">Your credit repair business is growing,<br>but dispute workload is <em>eating your time.</em></h2>
      <p class="section-intro">Sales close. Clients sign up. And then the real work begins — letters to prep, bureaus to call, complaints to document, 30-day windows to track, and weekly client updates to send. Owners burn out before the business can scale. Apex Growth Systems is the backend operations layer that handles the execution so you can stay focused on growth.</p>
    </div>

    <div class="targets-grid">
      <div class="target">
        <span class="roman">01 / PAIN</span>
        <h4>Letter Backlog</h4>
        <p>Certified dispute letters piling up. Clients waiting 2-3 weeks for Round 1 to even mail.</p>
        <div class="status">We Handle It</div>
      </div>
      <div class="target">
        <span class="roman">02 / PAIN</span>
        <h4>No Bureau Follow-Up</h4>
        <p>TransUnion, Experian, and Equifax calls never get made. Auto-verification slips through.</p>
        <div class="status">We Handle It</div>
      </div>
      <div class="target">
        <span class="roman">03 / PAIN</span>
        <h4>Missed CFPB Filings</h4>
        <p>Complaint documentation never gets prepared. Pressure on bureaus stays low.</p>
        <div class="status">We Handle It</div>
      </div>
      <div class="target">
        <span class="roman">04 / PAIN</span>
        <h4>FTC Documentation Gaps</h4>
        <p>FTC complaint support left undone. Identity theft and fraud cases sit idle.</p>
        <div class="status">We Handle It</div>
      </div>
      <div class="target">
        <span class="roman">05 / PAIN</span>
        <h4>Innovis Ignored</h4>
        <p>The fourth bureau most teams skip. Disputes never reach the file.</p>
        <div class="status">We Handle It</div>
      </div>
      <div class="target">
        <span class="roman">06 / PAIN</span>
        <h4>Small Bureau Freezes Skipped</h4>
        <p>ChexSystems, ARS, Clarity, SageStream, LexisNexis — never frozen, never disputed.</p>
        <div class="status">We Handle It</div>
      </div>
      <div class="target">
        <span class="roman">07 / PAIN</span>
        <h4>No Response Tracking</h4>
        <p>30-day investigation windows missed. Auto-verification patterns flagged too late.</p>
        <div class="status">We Handle It</div>
      </div>
      <div class="target">
        <span class="roman">08 / PAIN</span>
        <h4>Silent Client Files</h4>
        <p>Clients ghosted between rounds. Refund requests pile up. Reputation suffers.</p>
        <div class="status">We Handle It</div>
      </div>
    </div>
  </div>
</section>


<!-- ============ SERVICES ============ -->
<section class="targets" id="services" style="background: var(--paper);">
  <div class="container">
    <div class="section-label">02 · Services</div>
    <div class="section-head">
      <h2 class="section-title">Everything your credit repair business<br>needs to <em>execute on every client file.</em></h2>
      <p class="section-intro">A complete backend fulfillment stack. We'll run it on 5 test clients first — at our cost. You only pay once the results are in. Scale it across your full client base when the workflow proves out. White-label friendly — letters and weekly client status reports can ship under your credit repair business name.</p>
    </div>

    <div class="targets-grid">
      <div class="target">
        <span class="roman">01 / SERVICE</span>
        <h4>Certified Dispute Letter Preparation</h4>
        <p>Round 1 letters drafted same-day for Experian, Equifax, TransUnion, and Innovis. Designed to document the dispute trail and increase pressure.</p>
        <div class="status">Day 1</div>
      </div>
      <div class="target">
        <span class="roman">02 / SERVICE</span>
        <h4>Bureau Follow-Up Calls</h4>
        <p>TransUnion, Experian, and Equifax phone follow-ups during US business hours. Representative name, case number, and status documented on every call.</p>
        <div class="status">Day 7-8</div>
      </div>
      <div class="target">
        <span class="roman">03 / SERVICE</span>
        <h4>CFPB Complaint Documentation</h4>
        <p>Complaint support prepared and filed where appropriate to escalate stalled disputes and document a regulatory record on the file.</p>
        <div class="status">Day 1</div>
      </div>
      <div class="target">
        <span class="roman">04 / SERVICE</span>
        <h4>FTC Complaint Documentation</h4>
        <p>FTC complaint documentation support prepared where appropriate — particularly for identity theft, fraud, and creditor-level violations.</p>
        <div class="status">Day 1</div>
      </div>
      <div class="target">
        <span class="roman">05 / SERVICE</span>
        <h4>Innovis Disputes</h4>
        <p>The fourth bureau most teams ignore. Innovis dispute prep included in every Round 1 file we run.</p>
        <div class="status">Day 1</div>
      </div>
      <div class="target">
        <span class="roman">06 / SERVICE</span>
        <h4>Small Bureau Freeze Support</h4>
        <p>ChexSystems, ARS, Clarity, SageStream, LexisNexis, and similar bureaus — freeze and dispute support where applicable.</p>
        <div class="status">48 Hours</div>
      </div>
      <div class="target">
        <span class="roman">07 / SERVICE</span>
        <h4>Response Monitoring</h4>
        <p>30-day investigation windows tracked. Auto-verification patterns flagged. Improperly verified items routed to escalation.</p>
        <div class="status">Week 2-3</div>
      </div>
      <div class="target">
        <span class="roman">08 / SERVICE</span>
        <h4>Weekly Client Status Reports</h4>
        <p>What was filed, what was deleted, what changed, what's still pending, and what's coming in Round 2 — delivered to your client every week, in your brand.</p>
        <div class="status">Weekly</div>
      </div>
      <div class="target">
        <span class="roman">09 / SERVICE</span>
        <h4>Round 2 Escalation Prep</h4>
        <p>Non-deletions get stronger follow-up language citing failure to investigate properly. Designed to support stronger follow-up and document the dispute trail.</p>
        <div class="status">Week 4+</div>
      </div>
      <div class="target">
        <span class="roman">10 / SERVICE</span>
        <h4>Debt Validation Letter Prep</h4>
        <p>Validation letters prepared for collections and third-party debt buyers. Documents the chain of ownership and supports the dispute trail when verification fails.</p>
        <div class="status">As Needed</div>
      </div>
      <div class="target">
        <span class="roman">11 / SERVICE</span>
        <h4>Method-of-Verification Requests</h4>
        <p>Formal MOV requests prepared after bureau "verifications." Forces bureaus to disclose how the item was verified — designed to increase pressure on improper investigations.</p>
        <div class="status">Round 2</div>
      </div>
      <div class="target">
        <span class="roman">12 / SERVICE</span>
        <h4>Metro 2 Compliance Review</h4>
        <p>Client files reviewed against Metro 2 reporting standards. Discrepancies in account status, dates, balances, and codes flagged for the next dispute round.</p>
        <div class="status">Per File</div>
      </div>
    </div>
  </div>
</section>


<!-- ============ PROCESS ============ -->
<section class="method" id="process">
  <div class="container">
    <div class="section-label">03 · Fulfillment Process</div>
    <div class="section-head">
      <h2 class="section-title">Four phases.<br>Built for <em>credit repair businesses.</em></h2>
      <p class="section-intro">Day 1 launch on every client file you hand us. Multi-bureau letter prep, complaint documentation, follow-up calls, response monitoring, and a Week 4 client status report. Compliant, documented, and tracked end-to-end. Results vary — we do not guarantee removal of accurate or verifiable information.</p>
    </div>

    <div class="timeline" id="timeline">
      <div class="timeline-line"></div>
      <div class="stages">
        <div class="stage">
          <div class="stage-dot">01</div>
          <div class="stage-num">Day 1</div>
          <h3>Client File Launch</h3>
          <p>Client report uploaded. Same day: certified dispute letters prepared for Experian, Equifax, TransUnion, and Innovis. CFPB and FTC complaint documentation prepared and filed where appropriate.</p>
          <div class="tag">Same-Day Launch</div>
        </div>
        <div class="stage">
          <div class="stage-dot">02</div>
          <div class="stage-num">Day 7-8</div>
          <h3>Follow-Up Wave</h3>
          <p>TransUnion letter arrival tracked, then phone follow-up referencing the written dispute. Experian and Equifax calls made — representative name, case number, and status documented on every file.</p>
          <div class="tag">Bureau Calls Logged</div>
        </div>
        <div class="stage">
          <div class="stage-dot">03</div>
          <div class="stage-num">Week 2-3</div>
          <h3>Response Monitoring</h3>
          <p>Bureau responses monitored. 30-day investigation windows tracked. Auto-verification patterns flagged. Delayed or improperly verified items escalated with follow-up calls and second complaint documentation.</p>
          <div class="tag">Tracked &amp; Escalated</div>
        </div>
        <div class="stage">
          <div class="stage-dot">04</div>
          <div class="stage-num">Week 4</div>
          <h3>Client Status Report</h3>
          <p>Delivered to your client in your brand: what was filed, what was deleted, what changed, what is still pending, and what is coming in Round 2. Non-deletions get stronger follow-up language citing failure to investigate properly.</p>
          <div class="tag">Weekly Reporting</div>
        </div>
      </div>
    </div>
  </div>
</section>



<!-- ============ PROOF ============ -->
<section class="proof" id="proof">
  <div class="container">
    <div class="section-label">04 · Business Results</div>
    <div class="section-head">
      <h2 class="section-title">Real client files.<br>Real <em>fulfillment wins.</em></h2>
      <p class="section-intro">Documented outcomes across Experian, Equifax, TransUnion, and Innovis from the credit repair businesses we run fulfillment for. Results vary — every client profile is different — and we do not guarantee removal of accurate or verifiable information. The work below represents what disciplined, multi-channel dispute execution looks like.</p>
    </div>

    <div class="proof-grid reveal-stagger">
      @for ($i = 1; $i <= 8; $i++)
        <div class="proof-img">
          <picture>
            <source srcset="/Images/test{{ $i }}.webp" type="image/webp">
            <img src="/Images/test{{ $i }}.png" alt="Client file workflow {{ $i }}"
                 width="800" height="600"
                 loading="lazy" decoding="async">
          </picture>
        </div>
      @endfor
    </div>
    <div class="proof-view-more">
      <a href="/results"><span>View More Business Results</span> <span class="arrow">→</span></a>
    </div>
  </div>
</section>

<!-- ============ FOUNDER ============ -->
<section class="founder">
  <div class="container">
    <div class="founder-story reveal">
      <div class="section-label">05 · Why We Built This</div>
      <h2>Built for credit repair businesses who are <em>winning sales but drowning in fulfillment.</em></h2>
      <div class="founder-story-body">
        <p>We watched it happen over and over. A credit repair business closes a strong sales month. Sign-ups roll in. And then the real work begins — Round 1 letters to draft, bureaus to call, CFPB filings to prepare, 30-day windows to track, and weekly client updates to send. Owners stop selling and start drafting letters at midnight.</p>
        <p>So we built the backend. <strong>Letters. Calls. Filings. Tracking. Reporting.</strong> Run by an operations team during US business hours — Pakistan night shift aligns with US daytime — at a fraction of what a full domestic dispute team costs. White-label friendly so every letter and every weekly client status report ships under your credit repair business name.</p>
        <p>Apex Growth Systems exists so credit repair business owners can scale on volume and quality, not on burnout. We do not provide legal advice. We do not guarantee deletions. We do run the workflow — disciplined, documented, and tracked end-to-end.</p>
      </div>
      <div class="sig">Built for credit repair businesses ready to scale.</div>
      <div class="sig-title">The Team at Apex Growth Systems</div>
    </div>
  </div>
</section>



<!-- ============ WHY BUSINESSES USE US ============ -->
<section class="versus" id="why">
  <div class="container">
    <div class="section-label">05 · Why Credit Repair Businesses Use Us</div>
    <div class="section-head">
      <h2 class="section-title">Why credit repair business owners hand fulfillment to <em>Apex.</em></h2>
      <p class="section-intro">Most credit repair fulfillment is templated, slow, and silent — letter mills with no follow-up calls, no complaint documentation, and no weekly reporting. Here is what changes when Apex runs the backend.</p>
    </div>

    <div class="vs-table">
      <div class="vs-head">
        <div class="col">Workflow</div>
        <div class="col apex">Apex Growth</div>
        <div class="col">Typical Letter Mills</div>
      </div>
      <div class="vs-row">
        <div class="feature">Reduce owner workload</div>
        <div class="check-apex">Full handoff</div>
        <div class="check-others">Owner still drafts</div>
      </div>
      <div class="vs-row">
        <div class="feature">Faster execution — Day 1 launch</div>
        <div class="check-apex">Same-day Round 1</div>
        <div class="check-others">2-3 week delay</div>
      </div>
      <div class="vs-row">
        <div class="feature">Better tracking — 30-day windows</div>
        <div class="check-apex">Tracked file-by-file</div>
        <div class="check-others">Untracked</div>
      </div>
      <div class="vs-row">
        <div class="feature">US daytime availability</div>
        <div class="check-apex">Pakistan night shift = US daytime</div>
        <div class="check-others">Offshore mismatch</div>
      </div>
      <div class="vs-row">
        <div class="feature">Bureau follow-up calls</div>
        <div class="check-apex">TU / EX / EQ documented</div>
        <div class="check-others">Never made</div>
      </div>
      <div class="vs-row">
        <div class="feature">CFPB &amp; FTC complaint documentation</div>
        <div class="check-apex">Prepared where appropriate</div>
        <div class="check-others">Not offered</div>
      </div>
      <div class="vs-row">
        <div class="feature">Innovis &amp; small bureau coverage</div>
        <div class="check-apex">Included in Round 1</div>
        <div class="check-others">Skipped entirely</div>
      </div>
      <div class="vs-row">
        <div class="feature">White-label friendly</div>
        <div class="check-apex">Letters &amp; reports in your brand</div>
        <div class="check-others">Their brand only</div>
      </div>
      <div class="vs-row">
        <div class="feature">Organized weekly reporting</div>
        <div class="check-apex">Status to every client weekly</div>
        <div class="check-others">No client updates</div>
      </div>
    </div>
  </div>
</section>

<!-- ============ BEFORE / AFTER SHIFT ============ -->
<section class="shift reveal">
  <div class="container">
    <div class="section-label" style="text-align:center;display:block;">06 · The Shift</div>
    <div class="section-head" style="display:flex;flex-direction:column;align-items:center;text-align:center;max-width:800px;margin:0 auto 90px;">
      <h2 class="section-title">From <em>doing fulfillment yourself</em> to running a real credit repair operation.</h2>
    </div>

    <div class="shift-grid">
      <div class="shift-header">
        <span>Without Us</span>
        <span>With Apex</span>
      </div>
      <div class="shift-row">
        <div class="before">Owner drafting letters at midnight.</div>
        <div class="after">Round 1 letters out same-day, every client.</div>
      </div>
      <div class="shift-row">
        <div class="before">Bureau calls never get made.</div>
        <div class="after">TU / EX / EQ follow-ups documented on Day 7-8.</div>
      </div>
      <div class="shift-row">
        <div class="before">CFPB and FTC filings sit on the to-do list.</div>
        <div class="after">Complaint documentation prepared where appropriate.</div>
      </div>
      <div class="shift-row">
        <div class="before">Clients ghosted between rounds.</div>
        <div class="after">Weekly status reports sent in your brand.</div>
      </div>
      <div class="shift-row">
        <div class="before">Refund requests pile up.</div>
        <div class="after">Documented dispute trail. Stronger Round 2.</div>
      </div>
    </div>
  </div>
</section>

<!-- ============ TEST CLIENT OFFER ============ -->
<section class="pricing" id="test-clients">
  <div class="container">
    <div class="section-label" style="text-align:center;display:block;">07 · Pay-After-Results Trial</div>
    <div class="section-head" style="display:flex;flex-direction:column;align-items:center;text-align:center;max-width:800px;margin:0 auto 90px;">
      <h2 class="section-title">We'll run 5 test clients. <em>You pay only after results.</em></h2>
      <p class="section-intro">Don't move your full client base on a promise. Hand us 5 active client files — we'll execute the full Apex fulfillment workflow — certified letters, bureau follow-up calls, CFPB / FTC documentation where appropriate, response monitoring, and a Week 4 client status report — and you only pay once the results are in. Not before. That's how confident we are in the workflow — and how we earn full trust on your side before you scale. Results vary; we do not guarantee removal of accurate or verifiable information.</p>
    </div>

    <div class="pricing-grid reveal-stagger" style="grid-template-columns: 1fr; max-width: 720px; margin: 0 auto;">
      <div class="price-card featured">
        <div class="price-tier">Test Client Engagement</div>
        <h3>The Pay-After-Results Trial</h3>
        <div class="price-amount" style="font-size: 32px; line-height: 1.3;"><span class="num" style="font-size: 32px;">Backend Fulfillment</span></div>
        <div class="price-note">Hand off 5 active client files. We run the full multi-channel workflow on each one — at our cost upfront. You pay only once Week 4 results are in. Scope, white-label setup, and rate confirmed on the fulfillment call.</div>
        <ul class="price-features">
          <li>Day 1 certified letters — Experian, Equifax, TransUnion, Innovis</li>
          <li>CFPB &amp; FTC complaint documentation where appropriate</li>
          <li>Small bureau freeze support (ChexSystems, ARS, Clarity, SageStream, LexisNexis)</li>
          <li>Day 7-8 bureau follow-up calls — TU / EX / EQ documented</li>
          <li>Week 2-3 response monitoring &amp; 30-day window tracking</li>
          <li>Week 4 client status report — delivered in your brand</li>
          <li>Round 2 escalation prep on non-deletions</li>
        </ul>
        <a href="/trial" class="btn btn-gold"><span>Try 5 Test Clients</span> <span class="arrow">→</span></a>
      </div>
    </div>

    <p style="text-align:center;max-width:680px;margin:32px auto 0;font-size:13px;line-height:1.7;color:var(--ash);">Apex Growth Systems provides administrative credit repair fulfillment support, dispute preparation, documentation support, and operational services for credit repair businesses. We are not a law firm and do not provide legal advice. Results vary. We do not guarantee score increases, funding approvals, or removal of accurate / verifiable information.</p>
  </div>
</section>

<!-- ============ FAQ ============ -->
<section class="faq" id="faq">
  <div class="container" style="max-width: 1200px;">
    <div class="section-label" style="text-align:center;display:block;">08 · FAQ</div>
    <div class="section-head" style="display:flex;flex-direction:column;align-items:center;text-align:center;max-width:800px;margin:0 auto 90px;">
      <h2 class="section-title">Answers for <em>credit repair business owners.</em></h2>
      <p class="section-intro">Everything worth asking before handing dispute fulfillment to a backend partner. If your question isn't here, we'll answer it directly on the fulfillment call — no filter, no sales pitch.</p>
    </div>

    <div class="faq-list">
      <div class="faq-item">
        <button class="faq-q">
          <h4>Do you work directly with consumers?</h4>
          <span class="faq-icon">+</span>
        </button>
        <div class="faq-a">
          <p>No. Apex Growth Systems is a backend fulfillment partner for credit repair businesses. Your business owns the client relationship, billing, contracts, and brand. We execute the dispute workflow on your client files behind the scenes — letters, bureau calls, complaint documentation, response monitoring, and weekly client status reports.</p>
        </div>
      </div>
      <div class="faq-item">
        <button class="faq-q">
          <h4>Can you work under our brand?</h4>
          <span class="faq-icon">+</span>
        </button>
        <div class="faq-a">
          <p>Yes. We are white-label friendly. Certified letters, weekly client status reports, and dispute documentation can ship under your credit repair business name. Logo, contact details, and reporting templates are confirmed during onboarding.</p>
        </div>
      </div>
      <div class="faq-item">
        <button class="faq-q">
          <h4>What do you need to start?</h4>
          <span class="faq-icon">+</span>
        </button>
        <div class="faq-a">
          <p>A short fulfillment call, a signed scope agreement, your CRM access (CRC, DisputeFox, GoHighLevel, Client Dispute Manager, or other), and the client files you want us to run. Most credit repair businesses begin with the 5-client trial before scaling to their full client base.</p>
        </div>
      </div>
      <div class="faq-item">
        <button class="faq-q">
          <h4>Do you call the bureaus?</h4>
          <span class="faq-icon">+</span>
        </button>
        <div class="faq-a">
          <p>Yes. Day 7-8 of every client file: TransUnion letter arrival is tracked, then phone follow-up is made referencing the written dispute. Experian and Equifax calls are made to follow up on account disputes. Representative name, case number, and status are documented on every call so the dispute trail is fully recorded.</p>
        </div>
      </div>
      <div class="faq-item">
        <button class="faq-q">
          <h4>Do you file CFPB and FTC complaints?</h4>
          <span class="faq-icon">+</span>
        </button>
        <div class="faq-a">
          <p>We prepare CFPB complaint documentation and file where appropriate to escalate stalled disputes and document a regulatory record on the file. FTC complaint documentation is prepared where appropriate — particularly for identity theft, fraud, and creditor-level violations. This is administrative documentation support, not legal advice.</p>
        </div>
      </div>
      <div class="faq-item">
        <button class="faq-q">
          <h4>Do you guarantee deletions?</h4>
          <span class="faq-icon">+</span>
        </button>
        <div class="faq-a">
          <p>No. Any provider who guarantees deletions is making a claim that violates federal credit repair regulations. Results vary by client profile, documentation quality, creditor response, bureau investigation, and whether information is inaccurate, incomplete, unverifiable, or outdated. We do not guarantee removal of accurate or verifiable information. We do run the multi-channel workflow with discipline — letters, calls, complaints, monitoring, and Round 2 escalation prep — so the dispute trail is fully documented and pressure on bureaus is consistently applied.</p>
        </div>
      </div>
      <div class="faq-item">
        <button class="faq-q">
          <h4>Can you handle bulk clients?</h4>
          <span class="faq-icon">+</span>
        </button>
        <div class="faq-a">
          <p>Yes. After the 5-client trial proves out, credit repair businesses typically scale us across their full client base. Volume pricing, dedicated team allocation, and CRM integration are confirmed during scope on the fulfillment call.</p>
        </div>
      </div>
      <div class="faq-item">
        <button class="faq-q">
          <h4>What timezone do you work?</h4>
          <span class="faq-icon">+</span>
        </button>
        <div class="faq-a">
          <p>US business hours. Our team operates from Pakistan on a night shift that aligns with US daytime — so bureau calls, client communication, and your operational handoff happen during US business hours, not after.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ MILESTONES TICKER ============ -->
<section class="milestones" id="milestones">
  <div class="section-label" style="text-align:center;display:block;">09 · In Motion</div>
  <div class="milestones-label">This week at Apex Fulfillment</div>
  <div class="milestones-track-wrap">
    <div class="milestones-track">
      <span class="milestone-item">Round 1 Letters Mailed · 4 Bureaus <span class="star">✦</span></span>
      <span class="milestone-item">CFPB Complaint Documentation Prepared <span class="star">✦</span></span>
      <span class="milestone-item">TransUnion Follow-Up Calls Logged <span class="star">✦</span></span>
      <span class="milestone-item">Innovis Disputes Filed <span class="star">✦</span></span>
      <span class="milestone-item">Weekly Client Status Reports Delivered <span class="star">✦</span></span>
      <span class="milestone-item">Small Bureau Freezes Submitted · ChexSystems / SageStream <span class="star">✦</span></span>
      <span class="milestone-item">Round 2 Escalation Prep Complete <span class="star">✦</span></span>
      <span class="milestone-item">New Credit Repair Business Onboarded · 5-Client Trial <span class="star">✦</span></span>
      <span class="milestone-item">Round 1 Letters Mailed · 4 Bureaus <span class="star">✦</span></span>
      <span class="milestone-item">CFPB Complaint Documentation Prepared <span class="star">✦</span></span>
      <span class="milestone-item">Experian / Equifax Follow-Up Calls Logged <span class="star">✦</span></span>
      <span class="milestone-item">FTC Complaint Documentation Prepared <span class="star">✦</span></span>
      <span class="milestone-item">Weekly Client Status Reports Delivered <span class="star">✦</span></span>
      <span class="milestone-item">Response Monitoring · 30-Day Windows Tracked <span class="star">✦</span></span>
      <span class="milestone-item">White-Label Reports Shipped In Brand <span class="star">✦</span></span>
      <span class="milestone-item">New Credit Repair Business Onboarded · Full Handoff <span class="star">✦</span></span>
    </div>
  </div>
</section>

<!-- ============ LIBRARY ============ -->
<section class="library" id="library">
  <div class="container">
    <div class="section-label">10 · Operations Library</div>
    <div class="section-head">
      <h2 class="section-title">Built for <em>credit repair operations.</em></h2>
      <p class="section-intro">Resources for credit repair business owners scaling fulfillment — workflow templates, weekly reporting examples, and the exact dispute trail documentation we run on every client file.</p>
    </div>

    <div class="library-grid reveal-stagger">
      <div class="library-card">
        <div class="library-card-label">Resource · 01</div>
        <h3>The Multi-Channel Workflow Map</h3>
        <p>A visual breakdown of the Day 1 / Day 7-8 / Week 2-3 / Week 4 fulfillment workflow we run on every client file — letters, calls, complaints, monitoring, reporting.</p>
        <a href="/contact" class="library-card-cta"><span>Request</span> <span class="arrow">→</span></a>
      </div>
      <div class="library-card">
        <div class="library-card-label">Resource · 02</div>
        <h3>Weekly Client Status Report Sample</h3>
        <p>A sample of the white-labeled weekly status report we send to your clients — what was filed, deleted, and what's coming in Round 2.</p>
        <a href="/contact" class="library-card-cta"><span>Request</span> <span class="arrow">→</span></a>
      </div>
      <div class="library-card">
        <div class="library-card-label">Resource · 03</div>
        <h3>Bureau Follow-Up Call Log Template</h3>
        <p>The exact template our team uses to document representative name, case number, and status on every TransUnion / Experian / Equifax follow-up call.</p>
        <a href="/contact" class="library-card-cta"><span>Request</span> <span class="arrow">→</span></a>
      </div>
    </div>
  </div>
</section>

<!-- ============ FULFILLMENT COMMITMENT ============ -->
<section class="guarantee">
  <div class="guarantee-inner">
    <div class="seal">
      <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 6L9 17l-5-5"/>
      </svg>
    </div>
    <h2>Run the workflow. <em>Document the trail.</em></h2>
    <p>What we commit to: certified letters out on Day 1, bureau follow-up calls on Day 7-8, CFPB / FTC documentation prepared where appropriate, response monitoring through 30-day windows, and a Week 4 client status report — every file, every round, in your brand.</p>
    <p>What we don't promise: guaranteed deletions, score increases, or removal of accurate / verifiable information. Results vary by client profile, documentation quality, creditor response, and bureau investigation. We run the workflow with discipline. The outcomes belong to the law and the file.</p>
    <div class="sig-line">
      <div class="name">The Apex Fulfillment Commitment</div>
      <div class="role">The Apex Growth Systems Team</div>
    </div>
  </div>
</section>

<!-- ============ CTA FINAL ============ -->
<section class="cta-final" id="contact">
  <div class="cta-final-inner">
    <h2>Scale your credit repair fulfillment <em>this week.</em></h2>
    <p>Hand us 5 test clients. We'll execute the full Apex fulfillment workflow — Day 1 letters, Day 7-8 bureau calls, CFPB / FTC documentation where appropriate, response monitoring, and a Week 4 client status report — and you only pay once the results are in. Not before. Full trust on your side, full skin in the game on ours. Results vary; we do not guarantee removal of accurate or verifiable information.</p>
    <div class="hero-cta">
      <a href="/trial" class="btn btn-gold"><span>Try 5 Test Clients</span> <span class="arrow">→</span></a>
      <a href="/contact" class="btn btn-ghost"><span>Book A Fulfillment Call</span></a>
    </div>
  </div>
</section>

<!-- ============ FOOTER ============ -->
@include('partials.footer')

<script>
/* ============================================
   Nav scroll state
   ============================================ */
(function(){
  const nav = document.getElementById('nav');
  window.addEventListener('scroll', () => {
    if (window.scrollY > 40) nav.classList.add('scrolled');
    else nav.classList.remove('scrolled');
  }, { passive: true });
})();

/* ============================================
   Scroll reveal + stat counters
   ============================================ */
(function(){
  const els = document.querySelectorAll('.reveal, .reveal-stagger, #timeline, .cta-final');
  if (!('IntersectionObserver' in window)) { els.forEach(e => e.classList.add('in')); return; }

  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('in');
        if (entry.target.id === 'timeline') entry.target.classList.add('in-view');
        if (entry.target.classList.contains('cta-final')) entry.target.classList.add('in-view');
        entry.target.querySelectorAll('.counter').forEach(c => {
          animateCounter(c);
          c.classList.add('counter-glow', 'active');
          setTimeout(() => c.classList.remove('active'), 2200);
        });
        io.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });
  els.forEach(e => io.observe(e));

  // Also observe new reveal types
  document.querySelectorAll('.reveal-slide-left, .reveal-slide-right, .reveal-scale, .reveal-blur').forEach(el => {
    io.observe(el);
  });

  function animateCounter(el){
    const target = parseInt(el.dataset.target, 10);
    const duration = 1800;
    const t0 = performance.now();
    function step(now){
      const p = Math.min((now - t0) / duration, 1);
      const eased = 1 - Math.pow(1 - p, 3);
      const val = Math.floor(target * eased);
      el.textContent = val.toLocaleString();
      if (p < 1) requestAnimationFrame(step);
      else el.textContent = target.toLocaleString();
    }
    requestAnimationFrame(step);
  }
})();

/* ============================================
   FAQ accordion
   ============================================ */
(function(){
  document.querySelectorAll('.faq-q').forEach(q => {
    q.addEventListener('click', () => {
      const item = q.parentElement;
      const wasOpen = item.classList.contains('open');
      document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
      if (!wasOpen) item.classList.add('open');
    });
  });
})();

/* ============================================
   Credit Simulator
   ============================================ */
(function(){
  const baseScore = 512;
  const scoreEl = document.getElementById('simScore');
  const catEl = document.getElementById('simCat');
  const barEl = document.getElementById('simBar');
  const controls = document.querySelectorAll('.sim-control');

  function getCategory(score){
    if (score >= 800) return { name: 'EXCEPTIONAL', cls: 'excellent' };
    if (score >= 740) return { name: 'VERY GOOD', cls: 'excellent' };
    if (score >= 670) return { name: 'GOOD', cls: 'excellent' };
    if (score >= 580) return { name: 'FAIR', cls: '' };
    return { name: 'POOR', cls: '' };
  }

  function update(){
    let total = baseScore;
    controls.forEach(c => {
      if (c.classList.contains('active')) {
        total += parseInt(c.dataset.points, 10);
      }
    });
    scoreEl.textContent = total;
    const cat = getCategory(total);
    catEl.textContent = cat.name;
    scoreEl.className = 'now ' + cat.cls;
    // Bar width: map 300-850 to 0-100%
    const pct = ((total - 300) / 550) * 100;
    barEl.style.width = Math.max(0, Math.min(100, pct)) + '%';
  }

  controls.forEach(c => {
    c.addEventListener('click', () => {
      c.classList.toggle('active');
      update();
    });
  });

  update();
})();

/* ============================================
   Smooth nav anchor scrolling
   ============================================ */
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', (e) => {
    const id = a.getAttribute('href');
    if (id.length > 1) {
      const target = document.querySelector(id);
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
  });
});

/* ============================================
   Parallax on scroll
   ============================================ */
(function(){
  const parallaxEls = document.querySelectorAll('[data-parallax]');
  if (!parallaxEls.length || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  let ticking = false;
  window.addEventListener('scroll', () => {
    if (!ticking) {
      requestAnimationFrame(() => {
        const scrollY = window.scrollY;
        parallaxEls.forEach(el => {
          const speed = parseFloat(el.dataset.parallax) || 0.1;
          const rect = el.getBoundingClientRect();
          const center = rect.top + rect.height / 2;
          const offset = (center - window.innerHeight / 2) * speed;
          el.style.transform = 'translateY(' + offset + 'px)';
        });
        ticking = false;
      });
      ticking = true;
    }
  }, { passive: true });
})();

/* ============================================
   Magnetic hover on cards
   ============================================ */
(function(){
  if (window.matchMedia('(hover: none)').matches) return;
  document.querySelectorAll('.price-card, .stage-dot').forEach(card => {
    card.addEventListener('mousemove', (e) => {
      const rect = card.getBoundingClientRect();
      const x = e.clientX - rect.left - rect.width / 2;
      const y = e.clientY - rect.top - rect.height / 2;
      const maxTilt = 4;
      const tiltX = (y / rect.height) * maxTilt;
      const tiltY = -(x / rect.width) * maxTilt;
      card.style.transform = card.style.transform.replace(/perspective\([^)]+\)\s*rotateX\([^)]+\)\s*rotateY\([^)]+\)/, '') + ' perspective(600px) rotateX(' + tiltX + 'deg) rotateY(' + tiltY + 'deg)';
    });
    card.addEventListener('mouseleave', () => {
      card.style.transform = card.style.transform.replace(/perspective\([^)]+\)\s*rotateX\([^)]+\)\s*rotateY\([^)]+\)/, '');
    });
  });
})();

/* ============================================
   Smooth number counting with easing
   ============================================ */
(function(){
  document.querySelectorAll('.trust-item .num').forEach(el => {
    const text = el.textContent;
    const match = text.match(/[\d,]+/);
    if (!match) return;
    const target = parseInt(match[0].replace(/,/g, ''));
    const prefix = text.slice(0, text.indexOf(match[0]));
    const suffix = text.slice(text.indexOf(match[0]) + match[0].length);

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          let start = 0;
          const duration = 2000;
          const t0 = performance.now();
          function tick(now) {
            const p = Math.min((now - t0) / duration, 1);
            const eased = 1 - Math.pow(1 - p, 4);
            const val = Math.floor(target * eased);
            el.textContent = prefix + val.toLocaleString() + suffix;
            if (p < 1) requestAnimationFrame(tick);
            else el.textContent = text;
          }
          requestAnimationFrame(tick);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });
    observer.observe(el);
  });
})();

/* ============================================
   Target cards auto-highlight carousel
   ============================================ */
(function(){
  var cards = document.querySelectorAll('.targets-grid .target');
  if (!cards.length) return;

  var current = -1;
  var timer = null;
  var DELAY = 2500;

  function step() {
    // Remove active from previous
    if (current >= 0) cards[current].classList.remove('active');
    // Advance
    current = (current + 1) % cards.length;
    // Add active to new
    cards[current].classList.add('active');
    timer = setTimeout(step, DELAY);
  }

  // Pause on hover, resume on leave
  var grid = document.querySelector('.targets-grid');
  grid.addEventListener('mouseenter', function() {
    clearTimeout(timer);
  });
  grid.addEventListener('mouseleave', function() {
    timer = setTimeout(step, 800);
  });

  // Start first card immediately
  step();
})();

/* ============================================
   Qualifier form logic
   ============================================ */
(function(){
  var card = document.getElementById('qualifierCard');
  if (!card) return;
  var currentStep = 1;

  window.qualSelect = function(pill, step) {
    // Mark selected
    pill.closest('.qual-pills').querySelectorAll('.qual-pill').forEach(function(p) {
      p.classList.remove('selected');
    });
    pill.classList.add('selected');

    // Advance after brief delay
    setTimeout(function() { goQualStep(step + 1); }, 350);
  };

  window.qualSubmit = function() {
    var name = document.getElementById('qualName').value.trim();
    var email = document.getElementById('qualEmail').value.trim();
    if (!name || !email) return;

    // Hide steps, show success
    card.querySelectorAll('.qual-step').forEach(function(s) { s.classList.remove('active'); });
    card.querySelector('.qual-legal').style.display = 'none';
    document.getElementById('qualSuccess').classList.add('active');

    // Fill all bars
    card.querySelectorAll('.qual-bar').forEach(function(b) { b.classList.add('done'); });
    console.log('Qualifier lead:', { name: name, email: email });
  };

  function goQualStep(step) {
    if (step > 3) return;
    currentStep = step;

    // Toggle step visibility
    card.querySelectorAll('.qual-step').forEach(function(s) { s.classList.remove('active'); });
    var next = card.querySelector('[data-qstep="' + step + '"]');
    if (next) next.classList.add('active');

    // Update progress bars
    card.querySelectorAll('.qual-bar').forEach(function(b, i) {
      b.classList.remove('active', 'done');
      if (i < step - 1) b.classList.add('done');
      if (i === step - 1) b.classList.add('active');
    });

    // Update step labels
    card.querySelectorAll('.qual-step-label span').forEach(function(s) {
      s.classList.remove('active');
      if (parseInt(s.dataset.qs) === step) s.classList.add('active');
    });
  }
})();
</script>

@include('partials.popup')

</body>
</html>