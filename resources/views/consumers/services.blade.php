@extends('consumers.layout')

@section('title', 'Credit Repair Services | Apex Growth Solutions')
@section('description', 'What we review and how we help: late payments, collections, charge-offs, repossessions, bankruptcies, student loans, child support and medical bills, plus dispute preparation and bureau follow-up.')

@section('content')

<section class="page-hero">
    <div class="container">
        <span class="eyebrow">Services</span>
        <h1>What we review and how we help</h1>
        <p>We look at the full picture on your three-bureau credit reports and challenge information that may be inaccurate, incomplete or unverifiable.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head">
            <h2>Items that can affect your credit</h2>
            <p>Each item below can be disputed if the information is inaccurate, incomplete or can't be verified.</p>
        </div>
        <div class="grid grid-4">
            @foreach ($items as [$icon, $name, $text])
                <div class="card">
                    <div class="ico">{{ $icon }}</div>
                    <h3>{{ $name }}</h3>
                    <p>{{ $text }}</p>
                    <span class="tag-ok">May be disputed if inaccurate</span>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="section alt">
    <div class="container">
        <div class="section-head">
            <h2>The work we do for you</h2>
            <p>These are the tasks our team takes off your plate.</p>
        </div>
        <div class="grid grid-3">
            <div class="card"><h3>Dispute letter preparation</h3><p>Letters to the credit bureaus challenging information that may be inaccurate, incomplete or unverifiable.</p></div>
            <div class="card"><h3>Bureau follow-up calls</h3><p>We follow up with the bureaus so your disputes keep moving.</p></div>
            <div class="card"><h3>CFPB &amp; FTC complaints</h3><p>Complaint documentation where it's appropriate for your file.</p></div>
            <div class="card"><h3>Innovis disputes</h3><p>Disputes with Innovis, one of the lesser-known credit reporting agencies.</p></div>
            <div class="card"><h3>Small-bureau freezes</h3><p>Support placing security freezes with the smaller consumer reporting agencies.</p></div>
            <div class="card"><h3>Response monitoring</h3><p>We track responses from bureaus and creditors and decide the next step.</p></div>
            <div class="card"><h3>Validation requests</h3><p>Requests that creditors and collectors validate the debts they report.</p></div>
            <div class="card"><h3>Weekly status updates</h3><p>A clear update on where your file stands, every week.</p></div>
            <div class="card"><h3>Credit-building guidance</h3><p>Practical guidance on habits and strategies that support stronger credit over time.</p></div>
        </div>
        <p class="note" style="margin-top:22px;">We are not a law firm and do not provide legal advice. We cannot remove accurate, current and verifiable information, and we do not guarantee any specific result.</p>
    </div>
</section>

@include('consumers.partials.cta')
@endsection
