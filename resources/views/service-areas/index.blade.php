@php
    $allStates = collect($states)->sortBy('name')->values();
    $byRegion = $allStates->groupBy('region');
    $seoTitle = "Backend Credit Repair Support in All 50 U.S. States | Apex Growth Systems";
    $seoDescription = "Apex Growth Systems is the backend credit repair fulfillment partner for credit repair companies, dispute specialists, and credit consultants in every U.S. state. Certified letters, bureau follow-up calls, CFPB documentation, white-label weekly reporting.";
    $hubFaqs = [
        ['q' => 'Does Apex Growth Systems support credit repair companies in every U.S. state?', 'a' => "Yes. Service is delivered remotely from a US-hours-aligned operations team, so coverage is uniform across all 50 states. Choose your state below for state-specific market context, regulatory notes, and FAQs."],
        ['q' => 'What does \"backend credit repair support\" actually mean?', 'a' => "We act as the silent fulfillment partner behind the operator's brand. Day 1 certified dispute letters to all four bureaus, Day 7-8 bureau follow-up calls, CFPB and FTC complaint documentation where appropriate, response monitoring through 30-day windows, and a Week 4 client status report delivered in the operator's brand."],
        ['q' => 'What tools and CRMs do you work with?', 'a' => "DisputeFox, Credit Repair Cloud, Client Dispute Manager, GoHighLevel (including custom GHL credit repair builds), and similar credit repair CRMs. We plug into the operator's existing stack."],
        ['q' => 'Do you cover credit repair regulation differences across states?', 'a' => "Each state landing page summarizes that state's credit-services registration, bonding, and contract requirements. We are not a law firm and do not provide legal advice — the operator is responsible for state-specific registration, contracts, and CROA compliance."],
        ['q' => 'How does the pay-after-results trial work?', 'a' => "Five files, full Round 1-4 workflow, you only pay after the Week 4 results land. Built so a new partner can validate fulfillment quality on live files before scaling."],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="{{ $seoDescription }}" />
<link rel="canonical" href="{{ url('/service-areas') }}" />
<meta property="og:type" content="website" />
<meta property="og:title" content="{{ $seoTitle }}" />
<meta property="og:description" content="{{ $seoDescription }}" />
<meta property="og:url" content="{{ url('/service-areas') }}" />
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
    { "@type": "ListItem", "position": 2, "name": "Service Areas", "item": "{{ url('/service-areas') }}" }
  ]
}
</script>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "ItemList",
  "name": "Apex Growth Systems Service Areas — All 50 U.S. States",
  "itemListElement": [
    @foreach ($allStates as $i => $s)
    {
      "@type": "ListItem",
      "position": {{ $i + 1 }},
      "url": "{{ url('/service-areas/'.$s['slug']) }}",
      "name": "Backend Credit Repair Support in {{ $s['name'] }}"
    }@if (!$loop->last),@endif
    @endforeach
  ]
}
</script>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    @foreach ($hubFaqs as $faq)
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
em { background: linear-gradient(135deg, var(--gold-light), var(--gold) 40%, var(--ink)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-style: normal; }

.nav { position: sticky; top: 0; z-index: 100; background: rgba(255,255,255,0.92); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(226,232,240,0.6); }
.nav-inner { max-width: 1200px; margin: 0 auto; padding: 0 40px; display: flex; align-items: center; justify-content: space-between; height: 72px; gap: 16px; }
.logo-img { height: 70px; }
.nav-links { list-style: none; display: flex; gap: 28px; align-items: center; }
.nav-links a { font-size: 14px; color: var(--smoke); }
.nav-links a:hover { color: var(--ink); }
.btn { display: inline-flex; align-items: center; gap: 8px; font: inherit; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.25s var(--ease); border: none; }
.btn-primary { background: linear-gradient(135deg, var(--gold-light), var(--gold) 60%, var(--gold-deep)); color: #fff; padding: 11px 20px; font-size: 14px; box-shadow: 0 8px 20px rgba(26,111,196,0.25); }
.btn-primary:hover { transform: translateY(-2px); }
.mobile-toggle { display: none; flex-direction: column; gap: 5px; cursor: pointer; padding: 8px; }
.mobile-toggle span { width: 22px; height: 2px; background: var(--ink); }

.crumbs { max-width: 1200px; margin: 0 auto; padding: 18px 40px 0; font-size: 12px; color: var(--ash); font-family: var(--mono); letter-spacing: 0.04em; }
.crumbs a { color: var(--gold); }
.crumbs span { margin: 0 8px; color: var(--dust); }

.hero { padding: 60px 40px 80px; background: linear-gradient(180deg, var(--paper), var(--white)); position: relative; overflow: hidden; }
.hero::before { content: ''; position: absolute; top: -160px; right: -160px; width: 700px; height: 700px; background: radial-gradient(circle, rgba(33,150,243,0.10), transparent 60%); pointer-events: none; }
.hero-inner { max-width: 1100px; margin: 0 auto; position: relative; z-index: 1; }
.hero-eyebrow { font-family: var(--mono); font-size: 12px; letter-spacing: 0.16em; text-transform: uppercase; color: var(--gold); margin-bottom: 18px; }
.hero h1 { font-size: clamp(34px, 5vw, 54px); font-weight: 600; line-height: 1.12; letter-spacing: -0.02em; max-width: 920px; margin-bottom: 22px; }
.hero p.lede { font-size: 18px; color: var(--smoke); max-width: 760px; margin-bottom: 28px; }
.hero-ctas { display: flex; gap: 12px; flex-wrap: wrap; }
.hero-ctas .btn-ghost { background: #fff; color: var(--ink); padding: 11px 20px; font-size: 14px; font-weight: 600; border: 1.5px solid var(--bone); border-radius: 8px; }
.hero-ctas .btn-ghost:hover { border-color: var(--gold); color: var(--gold); }

section { padding: 96px 24px; }
.container { max-width: 1440px; margin: 0 auto; padding: 0 24px; }
.container-narrow { max-width: 1100px; margin: 0 auto; }
.hero { padding: 80px 24px 96px; }
.hero-inner { max-width: 1440px; margin: 0 auto; padding: 0 24px; }
.crumbs { max-width: 1440px; margin: 0 auto; padding: 20px 48px 0; }
.section-label { font-family: var(--mono); font-size: 12px; letter-spacing: 0.16em; text-transform: uppercase; color: var(--gold); margin-bottom: 12px; }
.section-title { font-size: clamp(26px, 3.4vw, 38px); font-weight: 600; line-height: 1.2; letter-spacing: -0.02em; margin-bottom: 18px; max-width: 880px; }
.section-intro { font-size: 16px; color: var(--smoke); max-width: 820px; margin-bottom: 32px; }

.intro-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 16px; }
.intro-card { padding: 22px 24px; background: var(--paper); border-radius: 12px; }
.intro-card h4 { font-size: 15px; margin-bottom: 8px; color: var(--ink); }
.intro-card p { font-size: 13.5px; color: var(--smoke); line-height: 1.65; }

.region-block { margin-bottom: 56px; }
.region-block h3 { font-size: 18px; font-family: var(--mono); letter-spacing: 0.1em; text-transform: uppercase; color: var(--gold); margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid var(--bone); }
.state-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
.state-card { background: var(--paper); border: 1px solid var(--bone); border-radius: 8px; padding: 14px 16px; transition: all 0.2s; display: flex; justify-content: space-between; align-items: center; }
.state-card:hover { border-color: var(--gold); background: #fff; transform: translateY(-2px); box-shadow: 0 8px 18px rgba(15,32,67,0.06); }
.state-card .name { font-size: 14px; font-weight: 600; color: var(--ink); }
.state-card .abbr { font-family: var(--mono); font-size: 11px; color: var(--gold); letter-spacing: 0.08em; }

.service-links { background: var(--paper); }
.service-link-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
.service-link { background: #fff; border: 1px solid var(--bone); border-radius: 10px; padding: 16px 20px; font-size: 14px; }
.service-link strong { display: block; color: var(--ink); margin-bottom: 2px; }
.service-link span { color: var(--smoke); font-size: 13px; }

.faq-list { display: flex; flex-direction: column; gap: 14px; }
.faq-item { background: var(--paper); border: 1px solid var(--bone); border-radius: 10px; }
.faq-item summary { padding: 18px 22px; cursor: pointer; font-weight: 600; font-size: 15px; list-style: none; display: flex; justify-content: space-between; align-items: center; }
.faq-item summary::-webkit-details-marker { display: none; }
.faq-item summary::after { content: '+'; font-size: 22px; color: var(--gold); }
.faq-item[open] summary::after { content: '−'; }
.faq-item .faq-a { padding: 0 22px 20px; font-size: 14px; color: var(--smoke); line-height: 1.75; }

.cta-final { background: linear-gradient(135deg, var(--gold-light), var(--gold) 50%, var(--gold-deep)); color: #fff; }
.cta-final-inner { max-width: 900px; margin: 0 auto; padding: 80px 40px; text-align: center; }
.cta-final h2 { font-size: clamp(28px, 4vw, 42px); font-weight: 600; margin-bottom: 16px; }
.cta-final h2 em { background: linear-gradient(135deg, #fff, #DBEAFE); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.cta-final p { font-size: 17px; color: rgba(255,255,255,0.85); max-width: 700px; margin: 0 auto 28px; }
.cta-final .btn-gold { background: #fff; color: var(--ink); padding: 14px 28px; font-size: 15px; font-weight: 600; border-radius: 10px; display: inline-flex; align-items: center; gap: 10px; }
.cta-final .btn-gold:hover { transform: translateY(-2px); }

@media (max-width: 900px) {
    .nav-inner { padding: 0 20px; }
    .nav-links { display: none; }
    .mobile-toggle { display: flex; }
    .hero { padding: 40px 24px 60px; }
    section { padding: 56px 24px; }
    .crumbs { padding: 14px 24px 0; }
    .intro-grid, .service-link-grid { grid-template-columns: 1fr; gap: 14px; }
    .cta-final-inner { padding: 56px 24px; }
}
</style>
</head>
<body>

@include('partials.nav')

<nav class="crumbs" aria-label="Breadcrumb">
    <a href="/">Home</a><span>/</span>
    Service Areas
</nav>

<header class="hero">
    <div class="hero-inner">
        <div class="hero-eyebrow">Service Areas · All 50 U.S. States</div>
        <h1>Backend credit repair support for credit repair companies in <em>every U.S. state.</em></h1>
        <p class="lede">Apex Growth Systems is the silent fulfillment partner behind credit repair companies, dispute specialists, credit consultants, and financial-services agencies nationwide. We handle the dispute workload — certified letters, bureau follow-up calls, CFPB and FTC documentation, response monitoring, and Week 4 brand-ready client status reports — so operators can scale without staffing up a dispute team.</p>
        <div class="hero-ctas">
            <a href="/trial" class="btn btn-primary"><span>Try 5 Test Clients</span> <span>&rarr;</span></a>
            <a href="/contact" class="btn-ghost"><span>Book A Fulfillment Call</span></a>
        </div>
    </div>
</header>

<section>
    <div class="container">
        <div class="section-label">01 · WHAT WE DO NATIONWIDE</div>
        <h2 class="section-title">One fulfillment bench, 50 state markets, every dispute round documented.</h2>
        <p class="section-intro">Service is delivered remotely on US business hours from a Pakistan night-shift operations team aligned with US daytime. Coverage is uniform across all 50 states. Every dispute round produces certified-mail tracking proof, bureau-call notes with rep names and ticket numbers, CFPB submission confirmations where appropriate, and a Week 4 client status report your business can forward to the end client under your own brand.</p>
        <div class="intro-grid">
            <div class="intro-card"><h4>Backend Credit Repair Specialist</h4><p>Day 1 certified letters, Day 7-8 bureau follow-up calls, CFPB and FTC documentation, 30-day window tracking, and Round 1-4 escalation built in.</p></div>
            <div class="intro-card"><h4>Credit Repair VA Support</h4><p>Virtual assistants trained for Credit Repair Cloud, DisputeFox, Client Dispute Manager, and GoHighLevel credit repair workflows.</p></div>
            <div class="intro-card"><h4>Automation &amp; Funnel Setup</h4><p>GoHighLevel credit repair funnel builds, lead-generation campaigns, dispute automation, and CRM optimization for credit repair companies.</p></div>
            <div class="intro-card"><h4>Business Credit Setup</h4><p>Net-30 vendor placement, EIN-based tradelines, D&amp;B profile setup, and PAYDEX-building workflows for consultants offering business-credit alongside personal repair.</p></div>
            <div class="intro-card"><h4>Credit Audit &amp; Report Analysis</h4><p>Line-by-line credit report review, factual dispute identification, charge-off and collection challenge preparation across Experian, TransUnion, Equifax, and Innovis.</p></div>
            <div class="intro-card"><h4>White-Label Client Reporting</h4><p>Brand-ready Week 4 client status reports for the operator's end client — every action, every response, every escalation documented.</p></div>
        </div>
    </div>
</section>

<section style="background: var(--paper);">
    <div class="container">
        <div class="section-label">02 · CHOOSE YOUR STATE</div>
        <h2 class="section-title">State-specific market context, regulator notes, and FAQs for every U.S. state.</h2>
        <p class="section-intro">Each state landing page below covers that state's credit-services registration and contract environment, top metros we serve, the regional market dynamics that drive credit-repair demand, and the operator-specific scenarios we see most often. Click any state for full detail.</p>

        @foreach (['Northeast', 'Southeast', 'Midwest', 'Southwest', 'West', 'Pacific'] as $region)
            @if (isset($byRegion[$region]))
                <div class="region-block">
                    <h3>{{ $region }}</h3>
                    <div class="state-grid">
                        @foreach ($byRegion[$region]->sortBy('name') as $s)
                            <a href="{{ route('service-areas.show', $s['slug']) }}" class="state-card">
                                <span class="name">{{ $s['name'] }}</span>
                                <span class="abbr">{{ $s['abbr'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</section>

<section class="service-links">
    <div class="container">
        <div class="section-label">03 · CORE SERVICES</div>
        <h2 class="section-title">Service pages most {{ $allStates->count() }}-state operators visit next.</h2>
        <p class="section-intro">Looking for a specific service rather than a state? These pages cover what we run end-to-end behind your brand.</p>
        <div class="service-link-grid">
            <a href="/trial" class="service-link"><strong>Pay-After-Results Trial</strong><span>Run 5 test files. Pay only after Week 4 results land.</span></a>
            <a href="/#services" class="service-link"><strong>Backend Fulfillment Stack</strong><span>Certified letters, bureau calls, CFPB documentation, weekly reporting.</span></a>
            <a href="/#process" class="service-link"><strong>The 4-Week Process</strong><span>Day 1 letters, Day 7-8 calls, Week 4 brand-ready status report.</span></a>
            <a href="/results" class="service-link"><strong>Business Results</strong><span>How operators scale fulfillment without staffing up a dispute team.</span></a>
            <a href="/about" class="service-link"><strong>About Apex Growth Systems</strong><span>Who we are, how we operate, and why operators trust us.</span></a>
            <a href="/contact" class="service-link"><strong>Book A Fulfillment Call</strong><span>15-minute call to scope your CRM, files, and white-label setup.</span></a>
        </div>
    </div>
</section>

<section>
    <div class="container">
        <div class="section-label">04 · FREQUENTLY ASKED</div>
        <h2 class="section-title">Common questions about nationwide credit repair backend support.</h2>
        <div class="faq-list">
            @foreach ($hubFaqs as $faq)
                <details class="faq-item">
                    <summary>{{ $faq['q'] }}</summary>
                    <div class="faq-a">{{ $faq['a'] }}</div>
                </details>
            @endforeach
        </div>
    </div>
</section>

<section class="cta-final">
    <div class="cta-final-inner">
        <h2>Wherever you operate, <em>we run the fulfillment behind it.</em></h2>
        <p>From Florida to California, New York to Texas, and every state in between — Apex Growth Systems is the backend dispute team behind credit repair companies, dispute specialists, and financial-services consultants across the United States.</p>
        <a href="/trial" class="btn-gold"><span>Try 5 Test Clients</span> <span>&rarr;</span></a>
    </div>
</section>

@include('partials.footer')

</body>
</html>
