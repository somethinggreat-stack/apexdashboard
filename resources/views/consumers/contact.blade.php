@extends('consumers.layout')

@section('title', 'Contact Us | Apex Growth Solutions')
@section('description', 'Tell us about your credit situation and we will get back to you. WhatsApp +92 301 3061491 or apexgrow@apexgrowthsolution.com.')

@section('content')
<section class="page-hero">
    <div class="container">
        <span class="eyebrow">Contact</span>
        <h1>Tell us about your situation</h1>
        <p>Fill in the form and we'll get back to you. There's no obligation.</p>
    </div>
</section>

<section class="section">
    <div class="container contact-grid">
        <div class="form-card">
            @if (session('consumer_sent'))
                <div class="alert alert-ok" id="thanks" role="status">
                    <strong>Thank you!</strong> We've received your details and will be in touch soon. If it's urgent, you can also message us on WhatsApp at +92 301 3061491.
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-err" role="alert">Please check the highlighted fields and try again.</div>
            @endif

            <form method="POST" action="{{ route('consumers.contact.submit') }}" novalidate>
                @csrf
                <div class="hp" aria-hidden="true">
                    <label for="website">Leave this field empty</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="first_name">First name</label>
                        <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" required>
                        @error('first_name')<div class="err">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="last_name">Last name</label>
                        <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" required>
                        @error('last_name')<div class="err">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="form-row">
                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                        @error('email')<div class="err">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="phone">Phone or WhatsApp number</label>
                        <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" autocomplete="tel" placeholder="e.g. +1 555 123 4567" required>
                        @error('phone')<div class="err">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="field">
                    <label for="state">Your state</label>
                    <select id="state" name="state" required>
                        <option value="">Choose your state…</option>
                        @foreach ($states as $code => $name)
                            <option value="{{ $code }}" @selected(old('state') === $code)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('state')<div class="err">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <span class="label">What do you need help with? <span class="note">(tick all that apply)</span></span>
                    <div class="checks">
                        @foreach ($needs as $key => $label)
                            <label class="check"><input type="checkbox" name="needs[]" value="{{ $key }}" @checked(in_array($key, (array) old('needs', [])))> {{ $label }}</label>
                        @endforeach
                    </div>
                </div>

                @php $pick = old('package', request('package')); @endphp
                <div class="field">
                    <label for="package">Which package interests you?</label>
                    <select id="package" name="package">
                        <option value="">Choose one (optional)</option>
                        @foreach ($packages as $key => $label)
                            <option value="{{ $key }}" @selected($pick === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="message">Anything else we should know? <span class="note">(optional)</span></label>
                    <textarea id="message" name="message" maxlength="3000">{{ old('message') }}</textarea>
                    @error('message')<div class="err">{{ $message }}</div>@enderror
                </div>

                <div class="field consent">
                    <label class="check">
                        <input type="checkbox" name="consent" value="1" @checked(old('consent')) required>
                        <span>{{ \App\Http\Controllers\ConsumerSiteController::CONSENT_TEXT }} See our <a href="{{ route('consumers.privacy') }}">Privacy Policy</a>.</span>
                    </label>
                    @error('consent')<div class="err">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;">Send my details</button>
                <p class="note" style="margin-top:14px;">We do not guarantee the removal of accurate information or any specific credit score increase. Results vary.</p>
            </form>
        </div>

        <aside>
            <div class="side-card">
                <h3>WhatsApp</h3>
                <p><a href="https://wa.me/923013061491" target="_blank" rel="noopener">+92 301 3061491</a><br><span class="note">WhatsApp only</span></p>
            </div>
            <div class="side-card">
                <h3>Email</h3>
                <p><a href="mailto:apexgrow@apexgrowthsolution.com">apexgrow@apexgrowthsolution.com</a></p>
            </div>
            <div class="side-card">
                <h3>What happens next</h3>
                <p>We review your details, then reach out to talk about your credit profile and explain your options. You won't be asked to sign anything or pay anything on this form.</p>
            </div>
        </aside>
    </div>
</section>
@endsection
