<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="A private, practical workspace for understanding what you owe, what you are owed, and what to do next.">
    <title>Clarity for your money commitments · {{ config('app.name', 'Debt Management') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root { --ink: #16201d; --muted: #68736e; --paper: #f7f8f3; --line: rgba(22, 32, 29, .12); --forest: #123c34; --mint: #d8eee3; --lime: #d9ec79; --coral: #f2b7a5; --cream: #fffdf6; }
        html { scroll-behavior: smooth; }
        body { background: var(--paper); color: var(--ink); font-family: "Instrument Sans", ui-sans-serif, system-ui, sans-serif; }
        .landing-shell { overflow: hidden; background: var(--paper); }
        .landing-container { width: min(1160px, calc(100% - 48px)); margin: 0 auto; }
        .landing-nav { border-bottom: 1px solid var(--line); background: rgba(247, 248, 243, .88); backdrop-filter: blur(18px); }
        .landing-nav-inner { display: flex; align-items: center; justify-content: space-between; min-height: 80px; gap: 24px; }
        .landing-brand { display: inline-flex; align-items: center; gap: 11px; color: var(--ink); text-decoration: none; font-weight: 700; letter-spacing: -.03em; }
        .landing-brand-mark { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 12px; background: var(--forest); color: var(--lime); box-shadow: 0 6px 16px rgba(18, 60, 52, .18); }
        .landing-brand-mark svg { width: 20px; height: 20px; }
        .landing-nav-links { display: flex; align-items: center; gap: 30px; color: var(--muted); font-size: 14px; }
        .landing-nav-links a, .landing-text-link { color: inherit; text-decoration: none; transition: color .2s ease; }
        .landing-nav-links a:hover, .landing-text-link:hover { color: var(--forest); }
        .landing-nav-actions { display: flex; align-items: center; gap: 10px; }
        .landing-button { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; padding: 12px 18px; border: 1px solid transparent; font-size: 14px; font-weight: 600; text-decoration: none; transition: transform .2s ease, box-shadow .2s ease, background .2s ease; }
        .landing-button:hover { transform: translateY(-2px); }
        .landing-button-primary { background: var(--forest); color: white; box-shadow: 0 10px 20px rgba(18, 60, 52, .16); }
        .landing-button-primary:hover { background: #1d5549; box-shadow: 0 14px 26px rgba(18, 60, 52, .22); }
        .landing-button-secondary { border-color: var(--line); color: var(--ink); background: rgba(255, 253, 246, .6); }
        .landing-button-secondary:hover { border-color: rgba(18, 60, 52, .28); background: var(--cream); }
        .landing-hero { position: relative; padding: 92px 0 86px; }
        .landing-hero::before { content: ""; position: absolute; width: 520px; height: 520px; right: -260px; top: -240px; border-radius: 50%; background: rgba(216, 238, 227, .68); filter: blur(4px); pointer-events: none; }
        .landing-hero-grid { position: relative; display: grid; grid-template-columns: minmax(0, .92fr) minmax(420px, .78fr); gap: 82px; align-items: center; }
        .landing-eyebrow { display: inline-flex; align-items: center; gap: 8px; color: var(--forest); font-size: 12px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
        .landing-eyebrow::before { content: ""; display: block; width: 24px; height: 2px; border-radius: 99px; background: var(--coral); }
        .landing-hero h1 { max-width: 700px; margin: 20px 0 24px; font-size: clamp(46px, 6vw, 78px); line-height: .98; letter-spacing: -.075em; font-weight: 600; }
        .landing-hero h1 em { color: var(--forest); font-style: normal; }
        .landing-lede { max-width: 560px; color: var(--muted); font-size: 19px; line-height: 1.65; }
        .landing-hero-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: 34px; }
        .landing-hero-note { display: flex; align-items: center; gap: 9px; margin-top: 18px; color: var(--muted); font-size: 13px; }
        .landing-hero-note svg { width: 16px; height: 16px; color: var(--forest); }
        .landing-preview-wrap { position: relative; padding: 30px 0 20px 28px; }
        .landing-preview-wrap::before { content: ""; position: absolute; left: 0; bottom: 0; width: 84%; height: 83%; border: 1px solid rgba(18, 60, 52, .2); border-radius: 34px; transform: rotate(-5deg); }
        .landing-preview { position: relative; border: 1px solid rgba(22, 32, 29, .14); border-radius: 24px; background: rgba(255, 253, 246, .92); padding: 18px; box-shadow: 0 26px 60px rgba(22, 32, 29, .13); }
        .landing-window-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; color: var(--muted); font-size: 11px; }
        .landing-window-dots { display: flex; gap: 5px; }
        .landing-window-dots span { width: 7px; height: 7px; border-radius: 50%; background: #d5d9d0; }
        .landing-window-dots span:first-child { background: var(--coral); }
        .landing-profile-pill { display: inline-flex; align-items: center; gap: 7px; padding: 6px 9px; border-radius: 99px; background: var(--mint); color: var(--forest); font-weight: 600; }
        .landing-profile-pill i { width: 7px; height: 7px; border-radius: 50%; background: #499878; }
        .landing-preview-header { display: flex; justify-content: space-between; align-items: end; gap: 16px; margin-bottom: 14px; }
        .landing-preview-kicker { margin-bottom: 5px; color: var(--muted); font-size: 12px; }
        .landing-preview-header strong { font-size: 28px; letter-spacing: -.05em; }
        .landing-preview-month { color: var(--muted); font-size: 12px; }
        .landing-preview-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; }
        .landing-preview-card { border-radius: 15px; padding: 14px; }
        .landing-preview-card span { display: block; color: rgba(22, 32, 29, .65); font-size: 11px; }
        .landing-preview-card strong { display: block; margin-top: 10px; font-size: 20px; letter-spacing: -.04em; }
        .landing-preview-card-pay { background: #f9d8cf; }
        .landing-preview-card-receive { background: var(--mint); }
        .landing-progress { margin: 14px 0 10px; border-radius: 99px; height: 8px; background: #e8e9df; overflow: hidden; }
        .landing-progress i { display: block; width: 62%; height: 100%; border-radius: inherit; background: var(--forest); }
        .landing-progress-caption { display: flex; justify-content: space-between; color: var(--muted); font-size: 11px; }
        .landing-next { margin-top: 14px; border-top: 1px solid var(--line); padding-top: 14px; }
        .landing-next-title { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 12px; font-weight: 700; }
        .landing-next-title span { color: var(--forest); font-weight: 600; }
        .landing-next-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; border-radius: 12px; padding: 11px 10px; background: #f2f3ed; }
        .landing-next-row + .landing-next-row { margin-top: 7px; }
        .landing-next-row div { display: flex; align-items: center; gap: 9px; min-width: 0; }
        .landing-next-icon { display: grid; place-items: center; width: 27px; height: 27px; border-radius: 9px; background: var(--cream); color: var(--forest); }
        .landing-next-icon svg { width: 14px; height: 14px; }
        .landing-next-row b { display: block; font-size: 12px; font-weight: 600; }
        .landing-next-row small { display: block; margin-top: 2px; color: var(--muted); font-size: 10px; }
        .landing-next-amount { white-space: nowrap; font-size: 12px; font-weight: 700; }
        .landing-example { margin-top: 13px; text-align: center; color: var(--muted); font-size: 10px; }
        .landing-trust-strip { border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
        .landing-trust-grid { display: grid; grid-template-columns: repeat(3, 1fr); }
        .landing-trust-item { display: flex; align-items: center; gap: 12px; padding: 22px 18px; color: var(--forest); }
        .landing-trust-item + .landing-trust-item { border-left: 1px solid var(--line); }
        .landing-trust-icon { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 10px; background: var(--mint); flex: 0 0 auto; }
        .landing-trust-icon svg { width: 16px; height: 16px; }
        .landing-trust-item strong { display: block; font-size: 13px; }
        .landing-trust-item > span:last-child > span { display: block; margin-top: 3px; color: var(--muted); font-size: 12px; }
        .landing-section { padding: 120px 0; }
        .landing-section-soft { background: #eef2e9; }
        .landing-section-heading { max-width: 650px; }
        .landing-section-heading h2 { margin: 16px 0; font-size: clamp(34px, 4.3vw, 56px); line-height: 1.02; letter-spacing: -.065em; font-weight: 600; }
        .landing-section-heading p { color: var(--muted); font-size: 17px; line-height: 1.65; }
        .landing-problem-grid { display: grid; grid-template-columns: 1.05fr .95fr; gap: 82px; align-items: end; }
        .landing-fragment-list { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 36px; }
        .landing-fragment { border: 1px solid var(--line); border-radius: 14px; padding: 17px; background: rgba(255, 253, 246, .56); }
        .landing-fragment small { display: block; margin-bottom: 15px; color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .1em; }
        .landing-fragment span { display: block; font-size: 15px; font-weight: 600; }
        .landing-quote { position: relative; border-radius: 25px; padding: 34px; background: var(--forest); color: white; overflow: hidden; }
        .landing-quote::after { content: ""; position: absolute; width: 190px; height: 190px; right: -70px; bottom: -90px; border: 1px solid rgba(217, 236, 121, .55); border-radius: 50%; box-shadow: 0 0 0 22px rgba(217, 236, 121, .08), 0 0 0 44px rgba(217, 236, 121, .06); }
        .landing-quote-mark { color: var(--lime); font-size: 38px; line-height: 1; }
        .landing-quote p { position: relative; z-index: 1; margin: 22px 0 28px; font-size: 23px; line-height: 1.35; letter-spacing: -.035em; }
        .landing-quote footer { position: relative; z-index: 1; color: rgba(255, 255, 255, .66); font-size: 13px; }
        .landing-feature-intro { display: flex; align-items: end; justify-content: space-between; gap: 30px; margin-bottom: 42px; }
        .landing-feature-intro .landing-section-heading { max-width: 600px; }
        .landing-feature-intro > p { max-width: 275px; margin: 0 0 4px; color: var(--muted); font-size: 14px; line-height: 1.6; }
        .landing-feature-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .landing-feature-card { min-height: 230px; border: 1px solid var(--line); border-radius: 19px; padding: 25px; background: var(--cream); }
        .landing-feature-card:nth-child(2) { background: var(--mint); }
        .landing-feature-card:nth-child(3) { background: #f8ded5; }
        .landing-feature-card:nth-child(4) { background: #e8e7b8; }
        .landing-feature-number { display: flex; align-items: center; justify-content: space-between; color: rgba(22, 32, 29, .46); font-size: 12px; }
        .landing-feature-icon { display: grid; place-items: center; width: 34px; height: 34px; border: 1px solid rgba(22, 32, 29, .13); border-radius: 11px; color: var(--forest); }
        .landing-feature-icon svg { width: 17px; height: 17px; }
        .landing-feature-card h3 { margin: 38px 0 9px; font-size: 21px; letter-spacing: -.04em; }
        .landing-feature-card p { max-width: 390px; color: rgba(22, 32, 29, .7); font-size: 14px; line-height: 1.6; }
        .landing-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px; margin-top: 48px; background: var(--line); border: 1px solid var(--line); border-radius: 18px; overflow: hidden; }
        .landing-step { min-height: 230px; padding: 26px; background: var(--paper); }
        .landing-step-num { display: grid; place-items: center; width: 37px; height: 37px; border-radius: 50%; background: var(--forest); color: var(--lime); font-size: 13px; font-weight: 700; }
        .landing-step h3 { margin: 50px 0 8px; font-size: 20px; letter-spacing: -.04em; }
        .landing-step p { color: var(--muted); font-size: 14px; line-height: 1.6; }
        .landing-principles { display: grid; grid-template-columns: .84fr 1.16fr; gap: 80px; align-items: start; }
        .landing-principles > .landing-section-heading { position: sticky; top: 30px; }
        .landing-principle-list { border-top: 1px solid var(--line); }
        .landing-principle { display: grid; grid-template-columns: 38px 1fr; gap: 18px; padding: 23px 0; border-bottom: 1px solid var(--line); }
        .landing-principle-num { color: var(--coral); font-size: 13px; font-weight: 700; }
        .landing-principle h3 { margin: 0 0 6px; font-size: 17px; letter-spacing: -.025em; }
        .landing-principle p { margin: 0; color: var(--muted); font-size: 14px; line-height: 1.6; }
        .landing-faq-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 44px; }
        .landing-faq { border: 1px solid var(--line); border-radius: 16px; padding: 22px; background: rgba(255, 253, 246, .55); }
        .landing-faq h3 { margin: 0 0 9px; font-size: 16px; letter-spacing: -.025em; }
        .landing-faq p { margin: 0; color: var(--muted); font-size: 14px; line-height: 1.6; }
        .landing-cta { position: relative; padding: 100px 0 110px; background: var(--forest); color: white; overflow: hidden; }
        .landing-cta::before, .landing-cta::after { content: ""; position: absolute; border: 1px solid rgba(217, 236, 121, .18); border-radius: 50%; }
        .landing-cta::before { width: 580px; height: 580px; top: -390px; right: -110px; }
        .landing-cta::after { width: 360px; height: 360px; bottom: -270px; left: -100px; }
        .landing-cta-inner { position: relative; z-index: 1; display: flex; align-items: end; justify-content: space-between; gap: 50px; }
        .landing-cta h2 { max-width: 660px; margin: 16px 0 0; font-size: clamp(38px, 5vw, 64px); line-height: 1; letter-spacing: -.07em; font-weight: 600; }
        .landing-cta .landing-eyebrow { color: var(--lime); }
        .landing-cta .landing-button-primary { background: var(--lime); color: var(--forest); box-shadow: none; white-space: nowrap; }
        .landing-cta .landing-button-primary:hover { background: #e6f496; }
        .landing-footer { background: #0d2d28; color: rgba(255, 255, 255, .66); }
        .landing-footer-inner { display: flex; align-items: center; justify-content: space-between; gap: 25px; padding: 28px 0; font-size: 12px; }
        .landing-footer .landing-brand { color: white; }
        .landing-footer .landing-brand-mark { background: var(--lime); color: var(--forest); box-shadow: none; }
        .landing-footer-links { display: flex; flex-wrap: wrap; gap: 18px; }
        .landing-footer a { color: inherit; text-decoration: none; }
        .landing-footer a:hover { color: white; }
        @media (max-width: 900px) { .landing-hero-grid, .landing-problem-grid, .landing-principles { grid-template-columns: 1fr; gap: 52px; } .landing-hero { padding-top: 68px; } .landing-preview-wrap { max-width: 590px; margin: 0 auto; } .landing-principles > .landing-section-heading { position: static; } }
        @media (max-width: 680px) { .landing-container { width: min(100% - 32px, 540px); } .landing-nav-inner { min-height: 70px; } .landing-nav-links { display: none; } .landing-nav-actions .landing-button-secondary { display: none; } .landing-hero { padding: 58px 0 64px; } .landing-hero h1 { font-size: clamp(42px, 13vw, 64px); } .landing-lede { font-size: 17px; } .landing-preview-wrap { padding-left: 10px; } .landing-preview-wrap::before { width: 90%; } .landing-trust-grid, .landing-feature-grid, .landing-faq-grid { grid-template-columns: 1fr; } .landing-trust-item { padding: 17px 0; } .landing-trust-item + .landing-trust-item { border-left: 0; border-top: 1px solid var(--line); } .landing-section { padding: 78px 0; } .landing-fragment-list { gap: 8px; } .landing-fragment { padding: 13px; } .landing-fragment span { font-size: 13px; } .landing-quote { padding: 26px; } .landing-quote p { font-size: 20px; } .landing-feature-intro, .landing-cta-inner, .landing-footer-inner { align-items: start; flex-direction: column; } .landing-feature-intro { gap: 20px; } .landing-feature-card { min-height: 205px; } .landing-feature-card h3 { margin-top: 30px; } .landing-steps { grid-template-columns: 1fr; } .landing-step { min-height: 0; } .landing-step h3 { margin-top: 26px; } .landing-cta { padding: 78px 0 82px; } .landing-cta-inner { gap: 28px; } .landing-cta h2 { font-size: 42px; } }
    </style>
</head>
<body>
<div class="landing-shell">
    <header class="landing-nav">
        <div class="landing-container landing-nav-inner">
            <a class="landing-brand" href="{{ route('home') }}" aria-label="{{ config('app.name', 'Debt Management') }} home">
                <span class="landing-brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 17.5 12 4l7 13.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M8.3 13.7h7.4M7 17.5h10" stroke-linecap="round"/></svg></span>
                <span>{{ config('app.name', 'Debt Management') }}</span>
            </a>
            <nav class="landing-nav-links" aria-label="Primary navigation"><a href="#why">Why this exists</a><a href="#how">How it works</a><a href="#trust">Trust & control</a></nav>
            <div class="landing-nav-actions"><a class="landing-button landing-button-secondary" href="{{ route('login') }}">Sign in</a><a class="landing-button landing-button-primary" href="{{ route('register') }}">Create account</a></div>
        </div>
    </header>

    <main>
        <section class="landing-hero">
            <div class="landing-container landing-hero-grid">
                <div>
                    <div class="landing-eyebrow">A private place for the full picture</div>
                    <h1>Clarity for what you owe <em>— and what you’re owed.</em></h1>
                    <p class="landing-lede">Debt rarely arrives as one neat number. Keep obligations, receipts, terms, payments, and next steps together so you can act with less guesswork.</p>
                    <div class="landing-hero-actions"><a class="landing-button landing-button-primary" href="{{ route('register') }}">Create a private workspace <span aria-hidden="true" style="margin-left: 8px">→</span></a><a class="landing-button landing-button-secondary" href="#how">See how it works</a></div>
                    <div class="landing-hero-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3 5 6v5c0 4.5 2.9 8.5 7 10 4.1-1.5 7-5.5 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>Start with what you know. Improve the picture over time.</div>
                </div>

                <div class="landing-preview-wrap" aria-label="Illustrative product preview">
                    <div class="landing-preview">
                        <div class="landing-window-bar"><div class="landing-window-dots"><span></span><span></span><span></span></div><span>debt-management.app</span><span aria-hidden="true">•••</span></div>
                        <div class="landing-preview-header"><div><div class="landing-preview-kicker">Financial overview</div><strong>Good morning, Aina</strong></div><span class="landing-profile-pill"><i></i>Personal</span></div>
                        <div class="landing-preview-header"><span class="landing-preview-month">September 2026</span><span class="landing-preview-month">Example workspace</span></div>
                        <div class="landing-preview-cards"><div class="landing-preview-card landing-preview-card-pay"><span>To pay</span><strong>MYR 4,280</strong></div><div class="landing-preview-card landing-preview-card-receive"><span>To receive</span><strong>MYR 1,150</strong></div></div>
                        <div class="landing-next"><div class="landing-next-title"><span>This month</span><span>MYR 900 available</span></div><div class="landing-progress"><i></i></div><div class="landing-progress-caption"><span>Planned repayment</span><span>62%</span></div></div>
                        <div class="landing-next"><div class="landing-next-title"><b>Next actions</b><span>View all</span></div><div class="landing-next-row"><div><span class="landing-next-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg></span><span><b>Review home financing</b><small>Due 12 Sep · evidence attached</small></span></div><span class="landing-next-amount">MYR 650</span></div><div class="landing-next-row"><div><span class="landing-next-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 7h14v10H5z"/><path d="m5 8 7 5 7-5"/></svg></span><span><b>Request balance confirmation</b><small>Family profile · draft ready</small></span></div><span class="landing-next-amount">Draft</span></div></div>
                        <div class="landing-example">Illustrative data only · your workspace starts empty</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="landing-trust-strip" aria-label="Product assurances"><div class="landing-container landing-trust-grid"><div class="landing-trust-item"><span class="landing-trust-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3 5 6v5c0 4.5 2.9 8.5 7 10 4.1-1.5 7-5.5 7-10V6l-7-3Z"/><path d="M9 12h6M12 9v6" stroke-linecap="round"/></svg></span><span><strong>Private by default</strong><span>Your records start with you.</span></span></div><div class="landing-trust-item"><span class="landing-trust-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 12h16M12 4v16" stroke-linecap="round"/><circle cx="12" cy="12" r="8"/></svg></span><span><strong>Start manually</strong><span>No bank connection required.</span></span></div><div class="landing-trust-item"><span class="landing-trust-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 4v16M4 12h16" stroke-linecap="round"/><path d="m7 12 3 3 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span><strong>You decide what happens next</strong><span>Recommendations never replace your say.</span></span></div></div></section>

        <section id="why" class="landing-section landing-section-soft"><div class="landing-container landing-problem-grid"><div><div class="landing-eyebrow">Why this exists</div><div class="landing-section-heading"><h2>Money conversations are hard enough without searching for the facts.</h2><p>When details live across messages, receipts, statements, and memory, the emotional weight gets heavier. This gives the facts a calm home.</p></div><div class="landing-fragment-list"><div class="landing-fragment"><small>Scattered</small><span>Receipts in three places</span></div><div class="landing-fragment"><small>Unclear</small><span>“What is the real balance?”</span></div><div class="landing-fragment"><small>Urgent</small><span>A due date you nearly missed</span></div><div class="landing-fragment"><small>Personal</small><span>A follow-up you keep avoiding</span></div></div></div><blockquote class="landing-quote"><div class="landing-quote-mark">“</div><p>Less shame. More signal. One honest view of what needs your attention.</p><footer>A practical system for the messy middle—not a scorecard.</footer></blockquote></div></section>

        <section class="landing-section"><div class="landing-container"><div class="landing-feature-intro"><div class="landing-section-heading"><div class="landing-eyebrow">What you get</div><h2>One record. Every useful detail.</h2></div><p>Designed around the way financial commitments actually evolve: incomplete at first, clearer with every piece of evidence.</p></div><div class="landing-feature-grid"><article class="landing-feature-card"><div class="landing-feature-number"><span>01</span><span class="landing-feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 6h14v13H5z"/><path d="M8 3v6M16 3v6M5 10h14"/></svg></span></div><h3>Keep the truth together</h3><p>Track what you need to pay and what you are owed, across personal, family, and business profiles.</p></article><article class="landing-feature-card"><div class="landing-feature-number"><span>02</span><span class="landing-feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 18h16M6 15l3-4 3 2 5-7" stroke-linecap="round" stroke-linejoin="round"/></svg></span></div><h3>Make a plan you can keep</h3><p>Use income, essential expenses, reserves, minimums, and clear priorities to see what is realistic.</p></article><article class="landing-feature-card"><div class="landing-feature-number"><span>03</span><span class="landing-feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 4h9l3 3v13H6z"/><path d="M14 4v4h4M9 13h6M9 16h4" stroke-linecap="round"/></svg></span></div><h3>Keep evidence close</h3><p>Attach agreements, receipts, statements, and notes privately—with visible confidence and activity history.</p></article><article class="landing-feature-card"><div class="landing-feature-number"><span>04</span><span class="landing-feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M5 6h14v12H5z"/><path d="m5 7 7 5 7-5M8 21h8" stroke-linecap="round" stroke-linejoin="round"/></svg></span></div><h3>Communicate with care</h3><p>Prepare a clear message, review it, and choose when to send. No silent messages or automatic money movement.</p></article></div></div></section>

        <section id="how" class="landing-section landing-section-soft"><div class="landing-container"><div class="landing-section-heading"><div class="landing-eyebrow">How it works</div><h2>Start small. Build a picture you can trust.</h2><p>You do not need every answer on day one. The system makes room for partial information and helps you turn it into a next step.</p></div><div class="landing-steps"><article class="landing-step"><span class="landing-step-num">01</span><h3>Capture what you know</h3><p>Add a payable or receivable, even when the details are incomplete. Label what is verified, partial, or estimated.</p></article><article class="landing-step"><span class="landing-step-num">02</span><h3>Understand the shape</h3><p>Bring together balances, terms, evidence, due dates, and payment history. See the whole record, not just a number.</p></article><article class="landing-step"><span class="landing-step-num">03</span><h3>Choose the next move</h3><p>Make a realistic plan, record what happened, and keep a respectful line of communication open.</p></article></div></div></section>

        <section id="trust" class="landing-section"><div class="landing-container landing-principles"><div class="landing-section-heading"><div class="landing-eyebrow">Trust & control</div><h2>The promise behind the product.</h2><p>Financial tools earn trust by being specific about what they protect, what they automate, and what they do not claim to know.</p></div><div class="landing-principle-list"><article class="landing-principle"><span class="landing-principle-num">01</span><div><h3>Private is the starting point</h3><p>Your records begin private. Sharing is deliberate, role-based, and limited to people you choose. A party does not need an account just to be recorded.</p></div></article><article class="landing-principle"><span class="landing-principle-num">02</span><div><h3>No surprise money movement</h3><p>Reminders never move funds. Provider execution is disabled until you explicitly authorise a schedule, provider, and amount, and every attempt is recorded.</p></div></article><article class="landing-principle"><span class="landing-principle-num">03</span><div><h3>Honest about uncertainty</h3><p>Calculations are shown with their assumptions. Incomplete terms stay marked as incomplete; an estimate is never presented as a contract-level answer.</p></div></article><article class="landing-principle"><span class="landing-principle-num">04</span><div><h3>Guidance, not judgement</h3><p>This is an organising and planning tool, not financial, legal, credit, or religious advice. You remain responsible for decisions and agreements.</p></div></article></div></div></section>

<section class="landing-section landing-section-soft"><div class="landing-container"><div class="landing-section-heading"><div class="landing-eyebrow">Before you begin</div><h2>Good questions deserve straight answers.</h2></div><div class="landing-faq-grid"><article class="landing-faq"><h3>Do I need to connect a bank?</h3><p>No. You can begin manually and keep imports or integrations out of the picture until you actually want them.</p></article><article class="landing-faq"><h3>Does someone I owe, or who owes me, need an account?</h3><p>No. A party can stay as a private record. Accounts are only needed for collaborators you intentionally invite.</p></article><article class="landing-faq"><h3>Can I share a whole profile?</h3><p>Yes, when you choose to invite someone. Roles such as editor, payment manager, viewer, and heir representative keep access purposeful.</p></article><article class="landing-faq"><h3>Will this tell me what decision to make?</h3><p>No. It can show trade-offs and explain a calculation, but you choose the plan, the message, and the action.</p></article></div></div></section>

        <section class="landing-cta"><div class="landing-container landing-cta-inner"><div><div class="landing-eyebrow">A calmer next step</div><h2>Make the picture clearer, one record at a time.</h2></div><a class="landing-button landing-button-primary" href="{{ route('register') }}">Create your private workspace <span aria-hidden="true" style="margin-left: 8px">→</span></a></div></section>
    </main>

    <footer class="landing-footer"><div class="landing-container landing-footer-inner"><a class="landing-brand" href="{{ route('home') }}"><span class="landing-brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 17.5 12 4l7 13.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M8.3 13.7h7.4M7 17.5h10" stroke-linecap="round"/></svg></span><span>{{ config('app.name', 'Debt Management') }}</span></a><div class="landing-footer-links"><a href="{{ route('legal.privacy') }}">Privacy</a><a href="{{ route('legal.terms') }}">Terms</a><a href="{{ route('legal.retention') }}">Retention</a><a href="{{ route('legal.deletion') }}">Deletion</a><a href="#how">How it works</a><a href="{{ route('login') }}">Sign in</a></div><span>Built for clear next steps.</span></div></footer>
</div>
</body>
</html>
