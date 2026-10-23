@extends('consumers.layout')

@section('title', 'Credit Score Guide: What Your Score Means | Apex Growth Solutions')
@section('description', 'What the FICO score ranges mean and the five factors that make up your score: payment history, amounts owed, length of history, new credit and credit mix.')

@section('content')
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">Score guide</span>
        <h1>What your score actually means</h1>
        <p>Your credit score is a three-digit number lenders may use to decide whether to approve you and what rate to offer.</p>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:960px;">
        <div class="section-head"><h2>FICO score ranges</h2></div>
        <div class="score-bar">
            <div style="background:#EF4444">300–579<small>Poor</small></div>
            <div style="background:#F59E0B">580–669<small>Fair</small></div>
            <div style="background:#2196F3">670–739<small>Good</small></div>
            <div style="background:#0EA5A4">740–799<small>Very good</small></div>
            <div style="background:#10B981">800–850<small>Exceptional</small></div>
        </div>
        <ul style="padding-left:20px;display:grid;gap:8px;color:#334155;">
            <li><strong>Poor:</strong> may limit approval options and lead to higher rates.</li>
            <li><strong>Fair:</strong> may mean fewer lending options and higher rates.</li>
            <li><strong>Good:</strong> may help with approvals and rates.</li>
            <li><strong>Very good:</strong> may qualify you for more favorable rates.</li>
            <li><strong>Exceptional:</strong> may qualify you for the most favorable rates and terms.</li>
        </ul>

        <div class="section-head" style="margin-top:56px;"><h2>What goes into the score</h2>
            <p>FICO publishes the general weight of each factor.</p></div>
        <div>
            <div class="factor"><b>35%</b><div><h4>Payment history</h4><p>Whether you've paid past accounts on time. Late payments, collections and charge-offs can hurt here.</p></div></div>
            <div class="factor"><b>30%</b><div><h4>Amounts owed</h4><p>How much of your available credit you're using, known as utilization.</p></div></div>
            <div class="factor"><b>15%</b><div><h4>Length of credit history</h4><p>How long your accounts have been open and how recently they've been used.</p></div></div>
            <div class="factor"><b>10%</b><div><h4>New credit</h4><p>Recent applications and hard inquiries.</p></div></div>
            <div class="factor"><b>10%</b><div><h4>Credit mix</h4><p>The variety of accounts you have, such as cards, installment loans and a mortgage.</p></div></div>
        </div>
        <p class="note" style="margin-top:22px;">Scoring models and lender requirements vary. This guide is general education, not a prediction of your score or a lending decision.</p>
    </div>
</section>

@include('consumers.partials.cta', ['heading' => 'Find out where you stand'])
@endsection
