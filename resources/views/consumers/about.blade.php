@extends('consumers.layout')

@section('title', 'About | Apex Growth Solutions')
@section('description', 'Apex Growth Solutions helps people understand their credit reports and their rights, and dispute information that may be inaccurate, incomplete or unverifiable.')

@section('content')
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">About us</span>
        <h1>Built to help you make smarter credit decisions</h1>
        <p>We help people understand their credit reports, their rights, and the steps they can take to address inaccurate, incomplete or unverifiable information.</p>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:860px;">
        <p style="font-size:18px;color:#334155;margin-bottom:18px;">Many people don't realize that federal laws like the Fair Credit Reporting Act (FCRA) and the Fair Debt Collection Practices Act (FDCPA) give them real protections when it comes to credit reporting and dispute rights.</p>
        <p style="font-size:18px;color:#334155;">Our job is to help you use those rights: reviewing your reports, preparing and following up on disputes, and keeping you informed along the way.</p>

        <div class="grid grid-2" style="margin-top:44px;">
            <div class="card"><div class="ico">⚖️</div><h3>Consumer-law based</h3><p>Our review is informed by the federal consumer protection laws that apply to credit reporting and debt collection.</p></div>
            <div class="card"><div class="ico">💬</div><h3>Clear communication</h3><p>Weekly status updates and a team you can reach by WhatsApp or email.</p></div>
            <div class="card"><div class="ico">📄</div><h3>Clear agreements</h3><p>Written service agreements with the terms laid out plainly, before you commit to anything.</p></div>
            <div class="card"><div class="ico">🛡️</div><h3>Compliance focused</h3><p>Our services are structured around transparency, education and the rules that apply to credit repair organizations.</p></div>
        </div>
    </div>
</section>

@include('consumers.partials.cta', ['heading' => 'Take the first step'])
@endsection
