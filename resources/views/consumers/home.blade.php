@extends('consumers.layout')

@section('title', 'Apex Growth Solutions | Credit Report Review & Dispute Assistance')
@section('description', 'We review your three-bureau credit reports and help you dispute information that may be inaccurate, incomplete or unverifiable under the FCRA and FDCPA. Packages from $149/month.')

@section('content')

<section class="hero">
    <div class="container hero-grid">
        <div>
            <span class="eyebrow">Credit report review &amp; dispute assistance</span>
            <h1>Understand your credit. <em>Challenge what's wrong.</em></h1>
            <p class="lead">Apex Growth Solutions reviews your three-bureau credit reports and helps you dispute information that may be inaccurate, incomplete or unverifiable, using the rights federal consumer law gives you.</p>
            <div class="hero-cta">
                <a href="{{ route('consumers.contact') }}" class="btn btn-primary">Get Started</a>
                <a href="{{ route('consumers.packages') }}" class="btn btn-ghost">See Packages</a>
            </div>
            <div class="trust-row">
                <span>Three-bureau review</span>
                <span>Weekly status updates</span>
                <span>Built on the FCRA &amp; FDCPA</span>
                <span>Clear, upfront pricing</span>
            </div>
        </div>

        <div class="gauge-card" aria-hidden="true">
            <svg viewBox="0 0 300 170" width="100%" role="img" aria-label="Credit score ranges illustration">
                <path d="M30 150 A120 120 0 0 1 90 46"  fill="none" stroke="#EF4444" stroke-width="22" stroke-linecap="butt"/>
                <path d="M90 46 A120 120 0 0 1 150 30"  fill="none" stroke="#F59E0B" stroke-width="22" stroke-linecap="butt"/>
                <path d="M150 30 A120 120 0 0 1 210 46" fill="none" stroke="#2196F3" stroke-width="22" stroke-linecap="butt"/>
                <path d="M210 46 A120 120 0 0 1 270 150" fill="none" stroke="#10B981" stroke-width="22" stroke-linecap="butt"/>
                <line x1="150" y1="150" x2="206" y2="68" stroke="#0F2043" stroke-width="5" stroke-linecap="round"/>
                <circle cx="150" cy="150" r="10" fill="#0F2043"/>
                <text x="30" y="168" font-size="11" fill="#64748B" text-anchor="middle">300</text>
                <text x="270" y="168" font-size="11" fill="#64748B" text-anchor="middle">850</text>
            </svg>
            <ul class="range-list">
                <li><i style="background:#EF4444"></i> 300–579 Poor</li>
                <li><i style="background:#F59E0B"></i> 580–669 Fair</li>
                <li><i style="background:#2196F3"></i> 670–739 Good</li>
                <li><i style="background:#10B981"></i> 740–850 Very good to exceptional</li>
            </ul>
            <div class="cap">Illustration of FICO score ranges. Not a result.</div>
        </div>
    </div>
</section>

<div class="ticker" aria-label="Items we review">
    <div class="ticker-track">
        @for ($i = 0; $i < 2; $i++)
            @foreach (['Collections', 'Charge-offs', 'Late payments', 'Hard inquiries', 'Repossessions', 'Bankruptcies', 'Student loans', 'Medical bills', 'Child support', 'Personal information'] as $t)
                <span>{{ $t }} reviewed</span>
            @endforeach
        @endfor
    </div>
</div>

<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">What may be affecting your credit</span>
            <h2>Eight things we look at first</h2>
            <p>Credit reports hold many kinds of accounts and records. If information is inaccurate, incomplete or unverifiable, you have the right to dispute it.</p>
        </div>
        <div class="grid grid-4">
            @foreach ($items as [$icon, $name, $text])
                <div class="card">
                    <div class="ico">{{ $icon }}</div>
                    <h3>{{ $name }}</h3>
                    <p>{{ $text }}</p>
                </div>
            @endforeach
        </div>
        <p class="note" style="margin-top:22px;">Accurate, current and verifiable information can't be removed by a credit repair company or by you. We only challenge what may be wrong.</p>
    </div>
</section>

<section class="section alt">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">How it works</span>
            <h2>A clear process, start to finish</h2>
            <p>You'll always know where your file stands.</p>
        </div>
        <div class="steps steps-mini">
            <div class="step"><div><h3>Reach out</h3><p>Send us your details through our short contact form.</p></div></div>
            <div class="step"><div><h3>We review</h3><p>We look at your three-bureau reports and explain what's on them.</p></div></div>
            <div class="step"><div><h3>We dispute</h3><p>Dispute letters, bureau follow-ups and complaint documentation, prepared and tracked.</p></div></div>
            <div class="step"><div><h3>You stay informed</h3><p>Weekly status updates and guidance on building stronger credit.</p></div></div>
        </div>
        <p style="text-align:center;margin-top:26px;"><a href="{{ route('consumers.how-it-works') }}" class="btn btn-ghost">See the full process</a></p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">Packages</span>
            <h2>Straightforward pricing</h2>
            <p>Pick the way that suits you. No hidden extras beyond your credit monitoring subscription.</p>
        </div>
        @include('consumers.partials.packages')
        <p class="note" style="text-align:center;margin-top:20px;">Credit monitoring is billed separately by the provider and is needed so we can review your three-bureau reports.</p>
    </div>
</section>

<section class="section alt">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">Client examples</span>
            <h2>Real client credit reports</h2>
            <p>Examples of score changes from client reports. Results vary and are not guaranteed.</p>
        </div>
        <div class="results-grid">
            @foreach ([1, 2, 3] as $n)
                <div class="result-shot"><img src="/Images/test{{ $n }}.png" alt="Client credit report example {{ $n }}" loading="lazy" width="900" height="480"></div>
            @endforeach
        </div>
        <p class="disclaimer">These examples reflect individual client experiences and are not typical. Results vary based on each consumer's credit profile and circumstances. No specific score increase or outcome is guaranteed.</p>
        <p style="margin-top:20px;"><a href="{{ route('consumers.results') }}" class="btn btn-ghost">See more examples</a></p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head center">
            <span class="eyebrow">Why Apex Growth Solutions</span>
            <h2>Built on law, process and clear communication</h2>
        </div>
        <div class="grid grid-3">
            <div class="card"><div class="ico">⚖️</div><h3>Consumer-law based</h3><p>We review your reports under the Fair Credit Reporting Act and the Fair Debt Collection Practices Act and challenge information that may be inaccurate, incomplete or unverifiable.</p></div>
            <div class="card"><div class="ico">📋</div><h3>A real process</h3><p>Dispute letter preparation, bureau follow-up calls, CFPB and FTC complaint documentation, Innovis disputes and small-bureau freeze support.</p></div>
            <div class="card"><div class="ico">💬</div><h3>You're kept in the loop</h3><p>Weekly status updates on your file and a team you can reach by WhatsApp or email.</p></div>
        </div>
    </div>
</section>

<section class="section alt">
    <div class="container">
        <div class="section-head center"><span class="eyebrow">FAQ</span><h2>Quick answers</h2></div>
        <div class="faq">
            <details><summary>Do you guarantee results?</summary><p>No. No credit repair company can lawfully guarantee a specific outcome. Results depend on your credit profile and on what the bureaus and creditors can verify.</p></details>
            <details><summary>Can you remove accurate information?</summary><p>No. Accurate, current and verifiable information can't be removed by you or by any credit repair company. We challenge information that may be inaccurate, incomplete or unverifiable.</p></details>
            <details><summary>How long does it take?</summary><p>Every file is different. Timelines depend on what's on your reports and how bureaus and creditors respond.</p></details>
        </div>
        <p style="text-align:center;margin-top:24px;"><a href="{{ route('consumers.faq') }}" class="btn btn-ghost">Read all questions</a></p>
    </div>
</section>

@include('consumers.partials.cta')
@endsection
