<section class="cta-band">
    <div class="container">
        <h2>{{ $heading ?? 'Ready to understand your credit?' }}</h2>
        <p>{{ $text ?? "Tell us a little about your situation. We'll review your credit profile and explain your options, with no pressure." }}</p>
        <div class="row">
            <a href="{{ route('consumers.contact') }}" class="btn btn-light">Get Started</a>
            <a href="https://wa.me/923013061491" target="_blank" rel="noopener" class="btn btn-outline-light">Message us on WhatsApp</a>
        </div>
    </div>
</section>
