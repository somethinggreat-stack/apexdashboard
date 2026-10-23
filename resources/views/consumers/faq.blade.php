@extends('consumers.layout')

@section('title', 'FAQ | Apex Growth Solutions')
@section('description', 'Answers about credit repair: timelines, guarantees, cancelling, credit monitoring, what we can and cannot remove, and how your information is protected.')

@section('content')
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">FAQ</span>
        <h1>Helpful answers to common questions</h1>
        <p>We believe in clear communication. If your question isn't here, send it to us and we'll answer it.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="faq">
            <details><summary>How long does it take to see changes?</summary><p>Every credit profile is different, so timelines vary. Some clients notice changes sooner and others see gradual improvement, depending on what's on their reports and how the bureaus and creditors respond.</p></details>
            <details><summary>Do you guarantee results?</summary><p>No. No credit repair company can lawfully guarantee a specific outcome, a removal or a score increase. We do the work carefully and keep you informed, but results depend on your file.</p></details>
            <details><summary>Can you remove accurate information from my reports?</summary><p>No. Accurate, current and verifiable information can't be removed, by us or by you. We challenge information that may be inaccurate, incomplete or unverifiable. Under federal law, the credit bureaus must reinvestigate what you dispute.</p></details>
            <details><summary>Can't I dispute things myself for free?</summary><p>Yes. You have the right to dispute inaccurate information directly with the credit bureaus at no cost. We handle the preparation, follow-up and tracking for you, which takes time and persistence.</p></details>
            <details><summary>Do I need a credit monitoring account?</summary><p>Yes. We need to see your three-bureau credit reports to review them. Credit monitoring is a separate subscription billed directly by the provider, and it isn't part of our fees.</p></details>
            <details><summary>What's the difference between the two packages?</summary><p>The Monthly Membership is $149 per month for ongoing support. The One-Time Program is a $700 one-time payment for a structured program. Both include a full three-bureau review, dispute preparation, bureau follow-up and weekly status updates. See the <a href="{{ route('consumers.packages') }}">Packages</a> page for the full lists.</p></details>
            <details><summary>Can I cancel?</summary><p>Yes. Federal law gives you the right to cancel your contract with a credit repair organization for any reason within 3 business days of signing it. You'll receive a Notice of Cancellation with your contract. Any other cancellation terms are set out in your written service agreement.</p></details>
            <details><summary>Are you a law firm?</summary><p>No. We are not a law firm and do not provide legal advice. We provide credit education, credit report analysis and dispute assistance.</p></details>
            <details><summary>What will you ask me for?</summary><p>To review your credit file we'll ask for the information needed to access and discuss your reports. We'll tell you exactly what's needed and why before you sign anything.</p></details>
            <details><summary>How is my information protected?</summary><p>We use reasonable technical and organizational safeguards and share your information only as needed to provide our services. See our <a href="{{ route('consumers.privacy') }}">Privacy Policy</a> for details.</p></details>
            <details><summary>How do I contact you?</summary><p>Use the <a href="{{ route('consumers.contact') }}">contact form</a>, message us on WhatsApp at +92 301 3061491, or email apexgrow@apexgrowthsolution.com.</p></details>
        </div>
    </div>
</section>

@include('consumers.partials.cta', ['heading' => "Still have a question?", 'text' => 'Send it through our contact form and we will get back to you.'])
@endsection
