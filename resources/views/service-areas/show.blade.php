@php
    $stateName = $state['name'];
    $abbr = $state['abbr'];
    $slug = $state['slug'];
    $metros = $state['metros'];
    $region = $state['region'];
    $url = url("/service-areas/{$slug}");

    $seoTitle = "Backend Credit Repair Support in {$stateName} | Apex Growth Systems";
    $seoDescription = "Backend credit repair fulfillment partner for {$stateName} credit repair companies, dispute specialists, and credit consultants. Certified letters, bureau follow-up calls, CFPB documentation, weekly white-label reporting.";

    // FAQ schema items combine state-specific + a few evergreen entries
    $faqItems = collect($state['state_faqs'])->concat([
        ['q' => "How does Apex Growth Systems support a {$stateName} credit repair company?", 'a' => "We act as the backend fulfillment partner behind your brand in {$stateName}. Day 1 certified dispute letters to all four bureaus, Day 7-8 bureau follow-up calls, CFPB and FTC complaint documentation where appropriate, response monitoring through 30-day windows, and a Week 4 client status report delivered in your brand."],
        ['q' => "Can a new {$stateName} credit repair business start with a small trial?", 'a' => "Yes. We run five test files end-to-end and you only pay once the Week 4 results are in. This is built so {$stateName} operators can validate fulfillment quality on live files before scaling the full client base."],
        ['q' => "Do you work with DisputeFox, Credit Repair Cloud, and GoHighLevel for {$stateName} operators?", 'a' => "Yes. We plug into the operator's existing CRM — DisputeFox, Credit Repair Cloud, Client Dispute Manager, GoHighLevel, or a custom GHL credit repair build — and run the dispute workflow without forcing a tool change."],
    ])->all();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="{{ $seoDescription }}" />
<link rel="canonical" href="{{ $url }}" />
<meta property="og:type" content="article" />
<meta property="og:title" content="{{ $seoTitle }}" />
<meta property="og:description" content="{{ $seoDescription }}" />
<meta property="og:url" content="{{ $url }}" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $seoTitle }}" />
<meta name="twitter:description" content="{{ $seoDescription }}" />
<title>{{ $seoTitle }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Home", "item": "{{ url('/') }}" },
    { "@type": "ListItem", "position": 2, "name": "Service Areas", "item": "{{ url('/service-areas') }}" },
    { "@type": "ListItem", "position": 3, "name": "{{ $stateName }}", "item": "{{ $url }}" }
  ]
}
</script>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "ProfessionalService",
  "name": "Apex Growth Systems — Backend Credit Repair Support for {{ $stateName }}",
  "url": "{{ $url }}",
  "areaServed": {
    "@type": "State",
    "name": "{{ $stateName }}",
    "containedInPlace": { "@type": "Country", "name": "United States" }
  },
  "serviceType": [
    "Backend credit repair fulfillment",
    "Credit repair virtual assistant support",
    "Dispute letter preparation",
    "Bureau follow-up calls",
    "CFPB and FTC complaint documentation",
    "Credit report analysis",
    "Credit repair CRM and automation setup",
    "GoHighLevel credit repair funnel setup"
  ],
  "provider": {
    "@type": "Organization",
    "name": "Apex Growth Systems",
    "url": "{{ url('/') }}"
  }
}
</script>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    @foreach ($faqItems as $i => $faq)
    {
      "@type": "Question",
      "name": @json($faq['q']),
      "acceptedAnswer": { "@type": "Answer", "text": @json($faq['a']) }
    }@if (!$loop->last),@endif
    @endforeach
  ]
}
</script>

<style>
:root {
    --white: #FFFFFF; --paper: #F8FAFC; --ivory: #F1F5F9; --bone: #E2E8F0;
    --ink: #0F2043; --charcoal: #1E3A5F; --smoke: #475569; --ash: #64748B; --dust: #94A3B8;
    --gold: #1A6FC4; --gold-deep: #0F2043; --gold-light: #2196F3; --champagne: #DBEAFE;
    --display: 'Geist', -apple-system, BlinkMacSystemFont, sans-serif;
    --mono: 'IBM Plex Mono', 'SF Mono', monospace;
    --ease: cubic-bezier(0.22, 1, 0.36, 1);
}
* { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; }
body { font-family: var(--display); background: var(--white); color: var(--ink); line-height: 1.7; -webkit-font-smoothing: antialiased; }
a { color: inherit; text-decoration: none; }
img { max-width: 100%; display: block; }
em {
    background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 40%, var(--ink) 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    font-style: normal;
}

/* Nav (mirrors other marketing pages so partial CSS lands) */
.nav { position: sticky; top: 0; z-index: 100; background: rgba(255,255,255,0.92); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(226,232,240,0.6); }
.nav-inner { max-width: 1200px; margin: 0 auto; padding: 0 40px; display: flex; align-items: center; justify-content: space-between; height: 72px; gap: 16px; }
.logo-img { height: 70px; }
.nav-links { list-style: none; display: flex; gap: 28px; align-items: center; }
.nav-links a { font-size: 14px; color: var(--smoke); transition: color 0.2s; }
.nav-links a:hover { color: var(--ink); }
.btn { display: inline-flex; align-items: center; gap: 8px; font: inherit; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.25s var(--ease); border: none; }
.btn-primary { background: linear-gradient(135deg, var(--gold-light), var(--gold) 60%, var(--gold-deep)); color: #fff; padding: 11px 20px; font-size: 14px; box-shadow: 0 8px 20px rgba(26,111,196,0.25); }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(26,111,196,0.35); }
.btn-primary .arrow { transition: transform 0.2s; }
.btn-primary:hover .arrow { transform: translateX(4px); }
.mobile-toggle { display: none; flex-direction: column; gap: 5px; cursor: pointer; padding: 8px; }
.mobile-toggle span { width: 22px; height: 2px; background: var(--ink); transition: all 0.3s; }

/* Breadcrumb */
.crumbs { max-width: 1200px; margin: 0 auto; padding: 18px 40px 0; font-size: 12px; color: var(--ash); font-family: var(--mono); letter-spacing: 0.04em; }
.crumbs a { color: var(--gold); }
.crumbs a:hover { text-decoration: underline; }
.crumbs span { margin: 0 8px; color: var(--dust); }

/* Hero */
.hero { padding: 60px 40px 80px; background: linear-gradient(180deg, var(--paper) 0%, var(--white) 100%); position: relative; overflow: hidden; }
.hero::before { content: ''; position: absolute; top: -160px; right: -160px; width: 600px; height: 600px; background: radial-gradient(circle, rgba(33,150,243,0.10), transparent 60%); pointer-events: none; }
.hero-inner { max-width: 1100px; margin: 0 auto; position: relative; z-index: 1; }
.hero-eyebrow { font-family: var(--mono); font-size: 12px; letter-spacing: 0.16em; text-transform: uppercase; color: var(--gold); margin-bottom: 18px; }
.hero h1 { font-size: clamp(34px, 5vw, 54px); font-weight: 600; line-height: 1.12; letter-spacing: -0.02em; max-width: 880px; margin-bottom: 22px; }
.hero-lede { font-size: 18px; line-height: 1.7; color: var(--smoke); max-width: 760px; margin-bottom: 32px; }
.hero-ctas { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 36px; }
.hero-ctas .btn-ghost { background: #fff; color: var(--ink); padding: 11px 20px; font-size: 14px; font-weight: 600; border: 1.5px solid var(--bone); border-radius: 8px; }
.hero-ctas .btn-ghost:hover { border-color: var(--gold); color: var(--gold); }
.hero-trust { display: flex; flex-wrap: wrap; gap: 18px; font-family: var(--mono); font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ash); }
.hero-trust span { display: inline-flex; align-items: center; gap: 6px; }
.hero-trust span::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--gold); }

/* Sections */
section { padding: 96px 24px; }
.container { max-width: 1440px; margin: 0 auto; padding: 0 24px; }
.hero { padding: 80px 24px 96px; }
.hero-inner { max-width: 1440px; margin: 0 auto; padding: 0 24px; }
.crumbs { max-width: 1440px; margin: 0 auto; padding: 20px 48px 0; }
.section-label { font-family: var(--mono); font-size: 12px; letter-spacing: 0.16em; text-transform: uppercase; color: var(--gold); margin-bottom: 12px; }
.section-title { font-size: clamp(26px, 3.4vw, 38px); font-weight: 600; line-height: 1.2; letter-spacing: -0.02em; margin-bottom: 18px; max-width: 800px; }
.section-intro { font-size: 16px; color: var(--smoke); max-width: 760px; margin-bottom: 32px; }

/* Two-column intro */
.two-col { display: grid; grid-template-columns: 1.2fr 1fr; gap: 48px; align-items: start; }
.metro-card { background: var(--paper); border-radius: 14px; padding: 28px; border: 1px solid var(--bone); }
.metro-card h3 { font-size: 14px; font-family: var(--mono); letter-spacing: 0.1em; text-transform: uppercase; color: var(--gold); margin-bottom: 14px; }
.metro-list { list-style: none; columns: 2; column-gap: 24px; }
.metro-list li { font-size: 14px; padding: 6px 0; color: var(--charcoal); }
.metro-list li::before { content: '\2022'; color: var(--gold); margin-right: 8px; }

/* Services grid */
.services { background: var(--paper); }
.service-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }
.service-card { background: #fff; border: 1px solid var(--bone); border-radius: 12px; padding: 28px; transition: all 0.3s var(--ease); }
.service-card:hover { transform: translateY(-3px); box-shadow: 0 18px 36px rgba(15,32,67,0.07); border-color: var(--gold); }
.service-card h3 { font-size: 18px; font-weight: 600; margin-bottom: 10px; color: var(--ink); }
.service-card p { font-size: 14px; color: var(--smoke); line-height: 1.65; }

/* Process timeline */
.process-steps { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; }
.process-step { display: flex; gap: 16px; padding: 22px; background: var(--paper); border-radius: 12px; border-left: 3px solid var(--gold); }
.process-step .num { font-family: var(--mono); font-size: 22px; color: var(--gold); font-weight: 600; line-height: 1; flex-shrink: 0; }
.process-step h4 { font-size: 15px; margin-bottom: 4px; }
.process-step p { font-size: 13px; color: var(--smoke); line-height: 1.6; }

/* Why */
.why-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 8px; }
.why-card { padding: 24px; background: var(--paper); border-radius: 12px; }
.why-card h4 { font-size: 16px; margin-bottom: 8px; }
.why-card p { font-size: 13.5px; color: var(--smoke); line-height: 1.65; }

/* FAQ */
.faq-list { display: flex; flex-direction: column; gap: 14px; }
.faq-item { background: var(--paper); border: 1px solid var(--bone); border-radius: 10px; overflow: hidden; }
.faq-item summary { padding: 18px 22px; cursor: pointer; font-weight: 600; font-size: 15px; list-style: none; display: flex; justify-content: space-between; align-items: center; }
.faq-item summary::-webkit-details-marker { display: none; }
.faq-item summary::after { content: '+'; font-size: 22px; color: var(--gold); font-weight: 400; }
.faq-item[open] summary::after { content: '−'; }
.faq-item .faq-a { padding: 0 22px 20px; font-size: 14px; color: var(--smoke); line-height: 1.75; }

/* Related states */
.related { background: var(--paper); }
.related-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
.related-card { background: #fff; border: 1px solid var(--bone); border-radius: 10px; padding: 18px 20px; transition: all 0.2s; }
.related-card:hover { border-color: var(--gold); transform: translateY(-2px); }
.related-card .abbr { font-family: var(--mono); font-size: 11px; color: var(--gold); letter-spacing: 0.1em; }
.related-card .name { font-size: 16px; font-weight: 600; margin-top: 4px; }

/* Final CTA */
.cta-final { background: linear-gradient(135deg, var(--gold-light), var(--gold) 50%, var(--gold-deep)); color: #fff; }
.cta-final-inner { max-width: 900px; margin: 0 auto; padding: 80px 40px; text-align: center; }
.cta-final h2 { font-size: clamp(28px, 4vw, 42px); font-weight: 600; margin-bottom: 16px; line-height: 1.2; }
.cta-final h2 em { background: linear-gradient(135deg, #fff, #DBEAFE); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.cta-final p { font-size: 17px; color: rgba(255,255,255,0.85); max-width: 700px; margin: 0 auto 28px; }
.cta-final .btn-gold { background: #fff; color: var(--ink); padding: 14px 28px; font-size: 15px; font-weight: 600; border-radius: 10px; display: inline-flex; align-items: center; gap: 10px; }
.cta-final .btn-gold:hover { transform: translateY(-2px); }

/* Responsive */
@media (max-width: 900px) {
    .nav-inner { padding: 0 20px; }
    .nav-links { display: none; }
    .mobile-toggle { display: flex; }
    .hero { padding: 40px 24px 60px; }
    section { padding: 56px 24px; }
    .crumbs { padding: 14px 24px 0; }
    .two-col, .service-grid, .process-steps, .why-grid { grid-template-columns: 1fr; gap: 18px; }
    .cta-final-inner { padding: 56px 24px; }
    .metro-list { columns: 1; }
}
</style>
</head>
<body>
@include('partials.loader')


@include('partials.nav')

<nav class="crumbs" aria-label="Breadcrumb">
    <a href="/">Home</a><span>/</span>
    <a href="{{ route('service-areas.index') }}">Service Areas</a><span>/</span>
    {{ $stateName }}
</nav>

<header class="hero">
    <div class="hero-inner">
        <div class="hero-eyebrow">Backend Credit Repair Support · {{ $stateName }}</div>
        <h1>The silent backend behind <em>{{ $stateName }}</em> credit repair companies, dispute specialists, and credit consultants.</h1>
        <p class="hero-lede">Apex Growth Systems runs the dispute workload, bureau follow-up calls, CFPB and FTC documentation, response monitoring, and weekly white-label client reporting that {{ $stateName }} credit repair operators ship to their clients under their own brand.</p>
        <div class="hero-ctas">
            <a href="/trial" class="btn btn-primary"><span>Try 5 Test Clients</span> <span class="arrow">&rarr;</span></a>
            <a href="/contact" class="btn-ghost"><span>Book A Fulfillment Call</span></a>
        </div>
        <div class="hero-trust">
            <span>Pay After Results</span>
            <span>White-Label Friendly</span>
            <span>US Business Hours Coverage</span>
            <span>Documentation-Driven</span>
        </div>
    </div>
</header>

<section>
    <div class="container">
        <div class="two-col">
            <div>
                <div class="section-label">01 · {{ strtoupper($stateName) }} OVERVIEW</div>
                <h2 class="section-title">Backend credit repair fulfillment, built for {{ $stateName }} operators.</h2>
                <p style="font-size: 16px; color: var(--smoke); margin-bottom: 18px;">{{ $state['intro_paragraph'] }}</p>
                <p style="font-size: 15px; color: var(--smoke);">{{ $state['state_signal'] }}</p>
            </div>
            <aside class="metro-card">
                <h3>{{ $stateName }} Metros We Support</h3>
                <ul class="metro-list">
                    @foreach ($metros as $metro)
                        <li>{{ $metro }}</li>
                    @endforeach
                </ul>
                <p style="font-size: 12px; color: var(--ash); margin-top: 14px; line-height: 1.6;">Service is delivered remotely, so coverage extends to every {{ $stateName }} city not listed above.</p>
            </aside>
        </div>
    </div>
</section>

<section style="background: var(--paper);">
    <div class="container">
        <div class="section-label">02 · {{ $stateName }} CREDIT REPAIR LANDSCAPE</div>
        <h2 class="section-title">The regulatory and market reality {{ $stateName }} operators work inside.</h2>
        <p class="section-intro">{{ $state['landscape_paragraph'] }}</p>
        <p class="section-intro">{{ $state['why_backend_paragraph'] }}</p>
    </div>
</section>

<section class="services">
    <div class="container">
        <div class="section-label">03 · SERVICES FOR {{ strtoupper($stateName) }}</div>
        <h2 class="section-title">Everything we run behind your brand in {{ $stateName }}.</h2>
        <p class="section-intro">Backend credit repair specialists, credit repair VAs, dispute specialists, automation engineers — one bench, one set of files, one white-label output.</p>
        <div class="service-grid">
            <div class="service-card"><h3>Backend Credit Repair Specialist Support</h3><p>Dedicated dispute specialists who run Round 1 through Round 4 on every {{ $stateName }} client file — certified letters Day 1, bureau follow-up calls Day 7-8, 30-day window tracking, weekly status reports in your brand.</p></div>
            <div class="service-card"><h3>Credit Repair VA Support</h3><p>Virtual assistants trained specifically for credit repair operations — Credit Repair Cloud, DisputeFox, Client Dispute Manager, GoHighLevel — handling data entry, client communication staging, document organization, and CRM hygiene for {{ $stateName }} operators.</p></div>
            <div class="service-card"><h3>DisputeFox, Credit Repair Cloud &amp; GHL Automation</h3><p>Setup, optimization, and ongoing operation of DisputeFox, Credit Repair Cloud, and GoHighLevel credit repair builds — automated dispute generation, client onboarding funnels, CRM pipelines, and dispute-stage automations tailored to the {{ $stateName }} operator's workflow.</p></div>
            <div class="service-card"><h3>Credit Repair Funnel &amp; Lead Generation</h3><p>GoHighLevel-based lead capture funnels, dispute lead nurture sequences, and credit-repair-specific landing pages built for {{ $stateName }} operators who want to scale acquisition without learning the GHL stack themselves.</p></div>
            <div class="service-card"><h3>Business Credit Setup</h3><p>Net-30 vendor setup, EIN-based tradeline placement, Dun &amp; Bradstreet profile preparation, and PAYDEX-building sequences for {{ $stateName }} consultants offering business-credit services alongside personal credit repair.</p></div>
            <div class="service-card"><h3>Credit Audit, Disputes &amp; Report Analysis</h3><p>Line-by-line credit report analysis, factual dispute identification, charge-off and collection challenge prep, inquiry disputes, and dispute-letter generation built specifically for the dispute reasons that hold up at Experian, TransUnion, Equifax, and Innovis.</p></div>
            <div class="service-card"><h3>CFPB &amp; FTC Complaint Documentation</h3><p>Complaint packages prepared where the {{ $stateName }} client file profile supports it — full evidence trail with account numbers, dispute reason codes, bureau-call history, response tracking. Operators submit under their own brand.</p></div>
            <div class="service-card"><h3>Small Bureau Freeze &amp; Specialty Bureau Work</h3><p>ChexSystems, ARS, Clarity, SageStream, LexisNexis, Innovis — specialty bureau freezes and disputes included on every {{ $stateName }} file where the profile supports it, with response tracking through 30-day windows.</p></div>
        </div>
    </div>
</section>

<section>
    <div class="container">
        <div class="section-label">04 · HOW THE WORKFLOW RUNS</div>
        <h2 class="section-title">Six steps from handoff to results in {{ $stateName }}.</h2>
        <p class="section-intro">Every {{ $stateName }} client file moves through the same documented workflow. No skipped steps, no silence between rounds.</p>
        <div class="process-steps">
            <div class="process-step"><span class="num">01</span><div><h4>Free 15-min scoping call</h4><p>Confirm your CRM (Credit Repair Cloud, DisputeFox, GHL, CDM), white-label setup, and which {{ $stateName }} files to run first. No pressure. No contract.</p></div></div>
            <div class="process-step"><span class="num">02</span><div><h4>You hand off the files</h4><p>Send the disputes, IDs, addresses, bureau access. No upfront fee. No deposit. The pay-after-results trial lets you validate on 5 files first.</p></div></div>
            <div class="process-step"><span class="num">03</span><div><h4>Day 1 certified letters</h4><p>Round 1 dispute letters mailed certified to Experian, TransUnion, Equifax, and Innovis. Tracking numbers logged in your file.</p></div></div>
            <div class="process-step"><span class="num">04</span><div><h4>Day 7-8 bureau calls</h4><p>Follow-up calls with bureau reps documented with names, ticket numbers, and timestamps. CFPB and FTC documentation prepared where appropriate.</p></div></div>
            <div class="process-step"><span class="num">05</span><div><h4>Week 4 client report</h4><p>Brand-ready Week 4 client status report — every letter, every call, every response, every escalation — delivered to you in your brand to forward to your {{ $stateName }} client.</p></div></div>
            <div class="process-step"><span class="num">06</span><div><h4>Round 2-4 escalation</h4><p>Non-deletions move to Round 2 with stronger language citing failure-to-investigate, then Round 3 and Round 4 as warranted. Documentation depth scales with each round.</p></div></div>
        </div>
    </div>
</section>

<section style="background: var(--paper);">
    <div class="container">
        <div class="section-label">05 · WHY {{ strtoupper($stateName) }} OPERATORS CHOOSE APEX</div>
        <h2 class="section-title">Documentation discipline, US-hour coverage, and a pay-after-results trial — built for {{ $stateName }} credit repair operators.</h2>
        <div class="why-grid">
            <div class="why-card"><h4>Pay After Results</h4><p>Our 5-file trial means you pay only after Week 4 results land. Built so a new {{ $stateName }} operator can validate fulfillment quality without writing a check upfront.</p></div>
            <div class="why-card"><h4>White-Label Output</h4><p>Letters, weekly status reports, and bureau-call documentation can all ship under your {{ $stateName }} credit repair business name. Your client never knows we exist.</p></div>
            <div class="why-card"><h4>US Business Hours</h4><p>Pakistan night shift aligns with US daytime, so {{ $stateName }} bureau calls and CFPB submissions happen during US business hours, not after.</p></div>
            <div class="why-card"><h4>Documentation Depth</h4><p>Every dispute round produces certified-mail proof, bureau-call notes with rep names and ticket numbers, CFPB submission confirmations, and a brand-ready Week 4 client report.</p></div>
            <div class="why-card"><h4>Multi-CRM Native</h4><p>We plug into the operator's existing stack — Credit Repair Cloud, DisputeFox, Client Dispute Manager, GoHighLevel — without forcing a tool change on the {{ $stateName }} business.</p></div>
            <div class="why-card"><h4>Round 2-4 Built In</h4><p>Non-deletions get stronger Round 2-4 language with failure-to-investigate framing. We don't stop at Round 1 like most {{ $stateName }} backends do.</p></div>
        </div>
    </div>
</section>

<section>
    <div class="container">
        <div class="section-label">06 · {{ strtoupper($stateName) }} FAQ</div>
        <h2 class="section-title">Questions {{ $stateName }} credit repair operators ask before partnering.</h2>
        <div class="faq-list">
            @foreach ($faqItems as $faq)
                <details class="faq-item">
                    <summary>{{ $faq['q'] }}</summary>
                    <div class="faq-a">{{ $faq['a'] }}</div>
                </details>
            @endforeach
        </div>
    </div>
</section>

<section class="related">
    <div class="container">
        <div class="section-label">07 · NEARBY STATES</div>
        <h2 class="section-title">Backend credit repair coverage across the {{ $region }} and the rest of the U.S.</h2>
        <p class="section-intro">Apex Growth Systems supports credit repair companies in every state. Some of the markets nearest {{ $stateName }}:</p>
        <div class="related-grid">
            @foreach ($related as $r)
                <a href="{{ route('service-areas.show', $r['slug']) }}" class="related-card">
                    <div class="abbr">{{ $r['abbr'] }}</div>
                    <div class="name">{{ $r['name'] }}</div>
                </a>
            @endforeach
            <a href="{{ route('service-areas.index') }}" class="related-card">
                <div class="abbr">ALL 50</div>
                <div class="name">View Every State</div>
            </a>
        </div>
    </div>
</section>

<section class="cta-final">
    <div class="cta-final-inner">
        <h2>Run your next 5 {{ $stateName }} files with us. <em>Pay only after results.</em></h2>
        <p>No deposit, no contract, no upfront fee. We run the certified letters, the bureau calls, the CFPB documentation, and the Week 4 brand-ready client status report. You pay once the results are in. Not before.</p>
        <a href="/trial" class="btn-gold"><span>Try 5 Test Clients</span> <span>&rarr;</span></a>
    </div>
</section>

@include('partials.footer')

<script>
(function () {
    var toggle = document.querySelector('.mobile-toggle');
    var links = document.querySelector('.nav-links');
    if (toggle && links) {
        toggle.addEventListener('click', function () {
            links.style.display = links.style.display === 'flex' ? '' : 'flex';
            links.style.flexDirection = 'column';
            links.style.position = 'absolute';
            links.style.top = '72px';
            links.style.left = '0';
            links.style.right = '0';
            links.style.background = '#fff';
            links.style.padding = '20px';
            links.style.borderBottom = '1px solid var(--bone)';
        });
    }
})();
</script>
</body>
</html>
