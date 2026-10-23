@extends('consumers.layout')

@section('title', 'How It Works | Apex Growth Solutions')
@section('description', 'Our five-step credit review and dispute process, from your first message to weekly status updates and credit-building guidance.')

@section('content')
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">How it works</span>
        <h1>A clear process, with no guesswork</h1>
        <p>You'll know what's happening with your file at every step.</p>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:900px;">
        <div class="steps">
            <div class="step"><div>
                <h3>Reach out</h3>
                <p>Send us your details through the contact form, or message us on WhatsApp. Tell us what you're dealing with and what you're hoping to achieve, like a mortgage, a car loan or simply a cleaner report.</p>
            </div></div>
            <div class="step"><div>
                <h3>Credit report review</h3>
                <p>You'll set up a credit monitoring account so we can see your three-bureau reports. We go through them with you, identify what's appearing, and explain which items may be affecting your credit.</p>
            </div></div>
            <div class="step"><div>
                <h3>Your plan</h3>
                <p>We decide which items to challenge first, based on what looks inaccurate, incomplete or unverifiable, and what is likely to matter most for your goals.</p>
            </div></div>
            <div class="step"><div>
                <h3>Disputes and follow-up</h3>
                <p>We prepare and send dispute letters to the bureaus, follow up with them by phone, request validation from creditors and collectors, and document complaints where appropriate. We track every response.</p>
            </div></div>
            <div class="step"><div>
                <h3>Updates and guidance</h3>
                <p>You get a status update on your file every week, plus practical guidance on credit-building habits so your progress lasts.</p>
            </div></div>
        </div>

        <div class="callout" style="margin-top:34px;">
            <strong>What you can expect from us:</strong> clear written terms before you sign anything, the right to cancel within 3 business days, and honest communication. We can't promise any specific result, and we can't remove accurate information.
            <a href="{{ route('consumers.credit-rights') }}">Read your credit file rights</a>.
        </div>
    </div>
</section>

@include('consumers.partials.cta', ['heading' => 'Ready for step one?'])
@endsection
