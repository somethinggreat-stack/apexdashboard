@extends('consumers.layout')

@section('title', 'Packages & Pricing | Apex Growth Solutions')
@section('description', 'Choose a Monthly Membership at $149 per month or a One-Time Program at a $700 one-time payment. Credit monitoring is billed separately by the provider.')

@section('content')
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">Packages</span>
        <h1>Choose how you want to work with us</h1>
        <p>Two ways to get the same careful review and dispute work.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        @include('consumers.partials.packages')

        <div class="callout" style="max-width:920px;margin:34px auto 0;">
            <strong>Before you start:</strong> you'll need an active credit monitoring account so we can review your three-bureau reports. It's a separate subscription billed directly by the provider and isn't part of our fees.
            Before you sign, you'll receive your <a href="{{ route('consumers.credit-rights') }}">Consumer Credit File Rights</a>, a written service agreement, and a <a href="{{ route('consumers.cancellation') }}">Notice of Cancellation</a> giving you 3 business days to cancel.
        </div>
        <p class="disclaimer" style="margin-inline:auto;text-align:center;">We do not guarantee the removal of accurate information or any specific credit score increase. Results vary.</p>
    </div>
</section>

<section class="section alt">
    <div class="container">
        <div class="section-head center"><h2>Not sure which is right for you?</h2>
            <p>Tell us about your situation and we'll explain both options. There's no pressure.</p></div>
        <p style="text-align:center;"><a href="{{ route('consumers.contact', ['package' => 'not_sure']) }}" class="btn btn-primary">Talk to us</a></p>
    </div>
</section>
@endsection
