<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="facebook-domain-verification" content="y1dk6klawxgk5dafue8fvhyj3grd02" />
<title>Apex Growth Solutions</title>
<meta name="description" content="Apex Growth Solutions — credit repair fulfillment for credit repair businesses, and credit report review and dispute assistance for individuals.">
<meta name="theme-color" content="#F5F9FF">
<link rel="icon" type="image/svg+xml" href="/favicon.svg">
<link rel="alternate icon" href="/Images/logo.png">
<link rel="apple-touch-icon" href="/Images/logo.png">
<link rel="canonical" href="https://{{ config('app.root_host') }}/">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root {
    color-scheme: light;
    --bg: #F5F9FF; --ink: #0F2043; --text: #334155; --mute: #64748B; --line: #E3EAF6;
    --blue: #1A6FC4; --blue-2: #2196F3; --navy: #0F2043; --sky: #E6F1FE; --mono: 'IBM Plex Mono', ui-monospace, Menlo, monospace;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 110%; }
body {
    font-family: 'Geist', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: var(--ink); background: var(--bg);
    min-height: 100vh; min-height: 100svh; display: flex; flex-direction: column; position: relative; overflow-x: hidden;
    -webkit-font-smoothing: antialiased;
}
a { color: inherit; text-decoration: none; }

/* Soft, bright atmosphere: pale colour washes + a faint grid behind everything */
.bg { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden;
    background: linear-gradient(180deg, #FFFFFF 0%, #F5F9FF 55%, #EEF5FF 100%); }
.bg::before { content: ''; position: absolute; inset: 0;
    background-image: linear-gradient(rgba(26,111,196,.06) 1px, transparent 1px), linear-gradient(90deg, rgba(26,111,196,.06) 1px, transparent 1px);
    background-size: 3.4rem 3.4rem; mask-image: radial-gradient(ellipse 75% 65% at 50% 38%, #000 15%, transparent 78%); }
.orb { position: absolute; border-radius: 50%; filter: blur(70px); animation: drift 20s ease-in-out infinite alternate; }
.orb.a { width: 36rem; height: 36rem; background: radial-gradient(circle, rgba(33,150,243,.34), transparent 65%); top: -13rem; right: -9rem; }
.orb.b { width: 30rem; height: 30rem; background: radial-gradient(circle, rgba(56,189,248,.30), transparent 65%); bottom: -12rem; left: -8rem; animation-delay: -7s; }
.orb.c { width: 24rem; height: 24rem; background: radial-gradient(circle, rgba(26,111,196,.22), transparent 65%); top: 30%; left: 46%; animation-delay: -12s; }
@keyframes drift { to { transform: translate(2.5rem, 1.5rem) scale(1.07); } }
@media (prefers-reduced-motion: reduce) { .orb { animation: none; } }

header, main, footer { position: relative; z-index: 1; }
header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.3rem clamp(1.25rem, 4vw, 3.5rem); }
header img { height: 3.1rem; width: auto; display: block; }
.hdr-links { display: flex; align-items: center; gap: .7rem; }
.pill { display: inline-flex; align-items: center; gap: .5rem; padding: .55rem 1.1rem; border-radius: 999px; font-size: .8rem; font-weight: 500;
    color: var(--text); border: 1px solid var(--line); background: rgba(255,255,255,.75); backdrop-filter: blur(8px);
    box-shadow: 0 .15rem .5rem rgba(11,27,63,.04); transition: all .2s ease; }
.pill:hover { color: var(--blue); border-color: rgba(33,150,243,.5); box-shadow: 0 .4rem 1rem rgba(33,150,243,.14); }

main { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: clamp(.5rem, 2vh, 1.5rem) clamp(1.25rem, 4vw, 3.5rem) clamp(1rem, 3vh, 2.5rem); text-align: center; }
.badge { display: inline-flex; align-items: center; gap: .55rem; padding: .42rem 1rem; border-radius: 999px; font-family: var(--mono); font-size: .7rem; font-weight: 500; letter-spacing: .18em; text-transform: uppercase;
    color: var(--blue); background: #fff; border: 1px solid #CFE3FA; box-shadow: 0 .25rem .8rem rgba(33,150,243,.1); }
.badge i { width: .45rem; height: .45rem; border-radius: 50%; background: var(--blue-2); box-shadow: 0 0 0 .22rem rgba(33,150,243,.18); }
h1 { margin-top: clamp(.8rem, 2.4vh, 1.4rem); font-size: clamp(2.1rem, min(5.4vw, 9vh), 4.1rem); font-weight: 700; line-height: 1.06; letter-spacing: -.035em; max-width: 66rem; color: var(--ink); text-wrap: balance; }
h1 span { position: relative; background: linear-gradient(100deg, #1A6FC4 0%, #2196F3 100%); -webkit-background-clip: text; background-clip: text; color: transparent; }
.lead { margin-top: clamp(.7rem, 2vh, 1.2rem); max-width: 38rem; font-size: clamp(.95rem, 1.5vw, 1.15rem); line-height: 1.6; color: var(--mute); }

.cards { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: clamp(1rem, 2vw, 1.6rem); width: 100%; max-width: 62rem; margin-top: clamp(1.2rem, 4.2vh, 2.8rem); text-align: left; }
.card { position: relative; display: flex; flex-direction: column; padding: clamp(1.3rem, 2.6vw, 2rem); border-radius: 1.5rem; overflow: hidden;
    background: rgba(255,255,255,.88); border: 1px solid var(--line); backdrop-filter: blur(14px);
    box-shadow: 0 .1rem .25rem rgba(11,27,63,.04), 0 1.2rem 2.6rem rgba(26,111,196,.09);
    transition: transform .25s ease, border-color .25s ease, box-shadow .25s ease; }
.card::after { content: ''; position: absolute; right: -4rem; top: -4rem; width: 12rem; height: 12rem; border-radius: 50%;
    background: radial-gradient(circle, rgba(33,150,243,.14), transparent 70%); transition: transform .4s ease; }
.card:hover { transform: translateY(-.4rem); border-color: rgba(33,150,243,.55);
    box-shadow: 0 .15rem .3rem rgba(11,27,63,.05), 0 1.8rem 3.6rem rgba(33,150,243,.2); }
.card:hover::after { transform: scale(1.35); }
.ico { position: relative; z-index: 1; width: 3.1rem; height: 3.1rem; border-radius: 1rem; display: grid; place-items: center; margin-bottom: 1.1rem;
    background: linear-gradient(135deg, #E6F1FE, #D6E8FB); border: 1px solid #C9E0F8; }
.ico svg { width: 1.5rem; height: 1.5rem; stroke: var(--blue); fill: none; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.tag { position: relative; z-index: 1; font-family: var(--mono); font-size: .68rem; font-weight: 500; letter-spacing: .2em; text-transform: uppercase; color: var(--blue); }
.card h2 { position: relative; z-index: 1; margin: .5rem 0 .6rem; font-size: clamp(1.25rem, 2.1vw, 1.6rem); font-weight: 700; letter-spacing: -.02em; line-height: 1.15; color: var(--ink); }
.card p { position: relative; z-index: 1; font-size: .9rem; line-height: 1.6; color: var(--text); }
.chips { position: relative; z-index: 1; display: flex; flex-wrap: wrap; gap: .45rem; margin: 1.1rem 0 1.5rem; }
.chips span { font-family: var(--mono); font-size: .68rem; font-weight: 400; letter-spacing: .02em; padding: .32rem .72rem; border-radius: 999px; color: #1E3A5F; background: var(--sky); border: 1px solid #D6E7FB; }
.go { position: relative; z-index: 1; margin-top: auto; display: inline-flex; align-items: center; justify-content: space-between; gap: .8rem; align-self: flex-start;
    padding: .85rem 1.3rem; border-radius: .9rem; font-size: .92rem; font-weight: 600; color: #fff;
    background: linear-gradient(135deg, #1A6FC4 0%, #0F2043 100%); box-shadow: 0 .7rem 1.5rem rgba(15,32,67,.28); transition: box-shadow .25s ease; }
.go svg { width: 1.05rem; height: 1.05rem; stroke: #fff; fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; transition: transform .25s ease; }
.card:hover .go { box-shadow: 0 .9rem 2.1rem rgba(26,111,196,.45); }
.card:hover .go svg { transform: translateX(.3rem); }

.contact { margin-top: clamp(1rem, 3.2vh, 2.2rem); display: flex; flex-wrap: wrap; justify-content: center; gap: .6rem 1.4rem; font-size: .84rem; color: var(--mute); }
.contact a { display: inline-flex; align-items: center; gap: .45rem; color: var(--text); transition: color .2s ease; }
.contact a:hover { color: var(--blue); }
.contact svg { width: 1rem; height: 1rem; stroke: var(--blue-2); fill: none; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
footer { padding: 0 1.25rem 1.3rem; text-align: center; font-size: .74rem; color: var(--mute); }

@media (max-width: 860px) {
    .cards { grid-template-columns: 1fr; max-width: 34rem; }
    .hdr-links .pill.wa { display: none; }
}
@media (max-width: 520px) { header img { height: 2.5rem; } .pill { padding: .5rem .85rem; } }
</style>
</head>
<body>
<div class="bg" aria-hidden="true"><div class="orb a"></div><div class="orb b"></div><div class="orb c"></div></div>

<header>
    <a href="/" aria-label="Apex Growth Solutions"><img src="/Images/logo.png" alt="Apex Growth Solutions" width="230" height="50"></a>
    <nav class="hdr-links" aria-label="Quick links">
        <a class="pill wa" href="https://wa.me/923013061491" target="_blank" rel="noopener">WhatsApp us</a>
        <a class="pill" href="{{ route('client.login') }}">Business Owner Login</a>
    </nav>
</header>

<main>
    <span class="badge"><i></i> Apex Growth Solutions</span>
    <h1>How can we <span>help you today?</span></h1>
    <p class="lead">Whether you run a credit repair business or want to improve your own credit, you're in the right place. Choose your path below.</p>

    <div class="cards">
        <a class="card" href="https://{{ config('app.partners_host') }}/">
            <div class="ico" aria-hidden="true">
                <svg viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M3 13h18"/></svg>
            </div>
            <span class="tag">For businesses</span>
            <h2>I run a credit repair business</h2>
            <p>Backend fulfillment for your credit repair company, so you can scale without the manual work.</p>
            <div class="chips"><span>Dispute letters</span><span>Bureau follow-ups</span><span>CFPB &amp; FTC complaints</span><span>Weekly client reports</span></div>
            <span class="go">Go to Partners
                <svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
        </a>

        <a class="card" href="https://{{ config('app.consumers_host') }}/">
            <div class="ico" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6l8-3z"/><path d="M8.5 12.2l2.4 2.4 4.6-4.9"/></svg>
            </div>
            <span class="tag">For individuals</span>
            <h2>I want to improve my credit</h2>
            <p>Professional review of your credit reports, and help disputing information that may be inaccurate, incomplete or unverifiable.</p>
            <div class="chips"><span>3-bureau review</span><span>Dispute assistance</span><span>Weekly status updates</span><span>Plans from $149/month</span></div>
            <span class="go">Go to Consumers
                <svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
        </a>
    </div>

    <div class="contact">
        <a href="https://wa.me/923013061491" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24"><path d="M21 11.5a8.4 8.4 0 0 1-12.4 7.4L3 20.5l1.6-5.4A8.4 8.4 0 1 1 21 11.5z"/></svg>WhatsApp +92 301 3061491</a>
        <a href="mailto:apexgrow@apexgrowthsolution.com">
            <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>apexgrow@apexgrowthsolution.com</a>
    </div>
</main>

<footer>&copy; {{ date('Y') }} Apex Growth Solutions. All rights reserved.</footer>
</body>
</html>
