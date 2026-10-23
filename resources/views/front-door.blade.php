<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="facebook-domain-verification" content="y1dk6klawxgk5dafue8fvhyj3grd02" />
<title>Apex Growth Solutions</title>
<meta name="description" content="Apex Growth Solutions — credit repair fulfillment for credit repair businesses, and credit guidance for individuals.">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="alternate icon" href="/Images/logo.png">
<link rel="apple-touch-icon" href="/Images/logo.png">
<link rel="canonical" href="https://{{ config('app.root_host') }}/">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    color-scheme: light;
    --ink: #0F2043; --blue: #1A6FC4; --blue-light: #2196F3; --smoke: #475569; --ash: #64748B;
    --paper: #F8FAFC; --line: #E2E8F0; --white: #FFFFFF;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Geist', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: var(--paper); color: var(--ink); min-height: 100vh;
    display: flex; flex-direction: column;
}
a { color: inherit; text-decoration: none; }
header { padding: 28px 24px 0; text-align: center; }
header img { height: 64px; width: auto; }
main { flex: 1; display: flex; flex-direction: column; align-items: center; padding: 40px 24px 56px; }
h1 { font-size: clamp(30px, 5vw, 48px); font-weight: 700; letter-spacing: -.02em; text-align: center; line-height: 1.1; }
.lead { margin: 14px auto 0; max-width: 560px; text-align: center; color: var(--smoke); font-size: 17px; line-height: 1.55; }
.cards { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 22px; width: 100%; max-width: 900px; margin-top: 44px; }
.card {
    display: flex; flex-direction: column; background: var(--white); border: 1px solid var(--line);
    border-radius: 18px; padding: 32px 30px; box-shadow: 0 1px 2px rgba(15,32,67,.04);
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.card:hover { transform: translateY(-4px); box-shadow: 0 18px 40px rgba(15,32,67,.10); border-color: var(--blue); }
.card .tag { font-size: 12px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--blue); }
.card h2 { font-size: 24px; font-weight: 700; margin: 10px 0 10px; letter-spacing: -.01em; }
.card p { color: var(--smoke); font-size: 15px; line-height: 1.55; flex: 1; }
.card .go {
    margin-top: 24px; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; font-size: 15px;
    color: var(--white); background: linear-gradient(135deg, var(--blue), var(--ink)); padding: 12px 20px; border-radius: 10px; align-self: flex-start;
}
.contact { margin-top: 42px; text-align: center; color: var(--ash); font-size: 14px; line-height: 1.8; }
.contact a { color: var(--ink); font-weight: 500; border-bottom: 1px solid var(--line); }
.contact a:hover { border-color: var(--blue); }
footer { padding: 22px 24px 30px; text-align: center; color: var(--ash); font-size: 13px; }
footer a { margin: 0 10px; }
footer a:hover { color: var(--ink); }
@media (max-width: 720px) { .cards { grid-template-columns: 1fr; } .card { padding: 26px 22px; } }
</style>
</head>
<body>
<header>
    <a href="/"><img src="/Images/logo.png" alt="Apex Growth Solutions" width="240" height="64"></a>
</header>

<main>
    <h1>Welcome to Apex Growth Solutions</h1>
    <p class="lead">Choose where you'd like to go.</p>

    <div class="cards">
        <a class="card" href="https://{{ config('app.partners_host') }}/">
            <span class="tag">For businesses</span>
            <h2>I run a credit repair business</h2>
            <p>Backend fulfillment for your credit repair company: dispute letters, bureau follow-ups, CFPB and FTC complaints, and weekly client reports, so you can scale without the manual work.</p>
            <span class="go">Go to Partners &rarr;</span>
        </a>

        <a class="card" href="https://{{ config('app.consumers_host') }}/">
            <span class="tag">For individuals</span>
            <h2>I want to improve my credit</h2>
            <p>Professional review of your credit reports and guidance on challenging information that may be inaccurate, incomplete or unverifiable.</p>
            <span class="go">Go to Consumers &rarr;</span>
        </a>
    </div>

    <div class="contact">
        Questions? WhatsApp <a href="https://wa.me/923013061491" target="_blank" rel="noopener">+92 301 3061491</a>
        &middot; <a href="mailto:apexgrow@apexgrowthsolution.com">apexgrow@apexgrowthsolution.com</a>
    </div>
</main>

<footer>
    <a href="{{ route('client.login') }}">Business Owner Login</a>
    <div style="margin-top:10px;">&copy; {{ date('Y') }} Apex Growth Solutions. All rights reserved.</div>
</footer>
</body>
</html>
