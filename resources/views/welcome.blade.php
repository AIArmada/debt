<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('welcome.meta_description') }}">
    <title>{{ __('welcome.title', ['app' => config('app.name', 'Promise Tracker')]) }}</title>

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
        .landing-language-switch { display: inline-flex; align-items: center; gap: 2px; padding: 4px; border: 1px solid var(--line); border-radius: 999px; color: var(--muted); font-size: 11px; line-height: 1; }
        .landing-language-switch a { display: inline-flex; align-items: center; justify-content: center; min-width: 30px; min-height: 32px; border-radius: 999px; padding: 6px 7px; color: inherit; text-decoration: none; }
        .landing-language-switch a:hover { color: var(--forest); }
        .landing-language-switch a:focus-visible { outline: 2px solid var(--forest); outline-offset: 2px; }
        .landing-language-switch a.is-active { background: var(--forest); color: white; font-weight: 700; }
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
        @media (max-width: 680px) { .landing-nav-inner { gap: 8px; } .landing-nav-actions { gap: 6px; } .landing-nav-actions > .landing-button-primary { padding: 10px 11px; font-size: 12px; white-space: nowrap; } .landing-language-switch { gap: 0; padding: 3px; } .landing-language-switch a { padding: 5px; } }
        @media (max-width: 380px) { .landing-nav-inner { gap: 5px; } .landing-brand { gap: 7px; font-size: 13px; } .landing-brand-mark { width: 32px; height: 32px; border-radius: 10px; } .landing-nav-actions { gap: 4px; } .landing-nav-actions > .landing-button-primary { padding: 9px 8px; font-size: 11px; } .landing-language-switch a { padding: 5px 4px; } }
    </style>
</head>
<body>
<div class="landing-shell">
    <header class="landing-nav">
        <div class="landing-container landing-nav-inner">
            <a class="landing-brand" href="{{ route('home') }}" aria-label="{{ __('welcome.brand_home', ['app' => config('app.name', 'Promise Tracker')]) }}">
                <span class="landing-brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 17.5 12 4l7 13.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M8.3 13.7h7.4M7 17.5h10" stroke-linecap="round"/></svg></span>
                <span>{{ config('app.name', 'Promise Tracker') }}</span>
            </a>
            <nav class="landing-nav-links" aria-label="{{ __('welcome.primary_navigation') }}">
                <a href="#why">{{ __('welcome.nav_why') }}</a>
                <a href="#how">{{ __('welcome.nav_how') }}</a>
                <a href="#trust">{{ __('welcome.nav_trust') }}</a>
            </nav>
            <div class="landing-nav-actions">
                <div class="landing-language-switch" role="group" aria-label="{{ __('welcome.language_switch') }}">
                    <a href="{{ route('locale.switch', ['locale' => 'en']) }}" lang="en" hreflang="en" class="{{ app()->getLocale() === 'en' ? 'is-active' : '' }}" @if (app()->getLocale() === 'en') aria-current="page" @endif>{{ __('welcome.language_english') }}</a>
                    <a href="{{ route('locale.switch', ['locale' => 'ms']) }}" lang="ms" hreflang="ms" class="{{ app()->getLocale() === 'ms' ? 'is-active' : '' }}" @if (app()->getLocale() === 'ms') aria-current="page" @endif>{{ __('welcome.language_malay') }}</a>
                </div>
                <a class="landing-button landing-button-secondary" href="{{ route('login') }}">{{ __('welcome.sign_in') }}</a>
                <a class="landing-button landing-button-primary" href="{{ route('register') }}">{{ __('welcome.create_account') }}</a>
            </div>
        </div>
    </header>

    <main>
        <section class="landing-hero">
            <div class="landing-container landing-hero-grid">
                <div>
                    <div class="landing-eyebrow">{{ __('welcome.hero_eyebrow') }}</div>
                    <h1>{{ __('welcome.hero_headline') }}</h1>
                    <p class="landing-lede">{{ __('welcome.hero_description') }}</p>
                    <div class="landing-hero-actions">
                        <a class="landing-button landing-button-primary" href="{{ route('register') }}">{{ __('welcome.create_account') }} <span aria-hidden="true" style="margin-left: 8px">→</span></a>
                        <a class="landing-button landing-button-secondary" href="#how">{{ __('welcome.see_how_it_works') }}</a>
                    </div>
                    <div class="landing-hero-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 2.9 8.5 7 10 4.1-1.5 7-5.5 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ __('welcome.hero_note') }}</div>
                </div>

                <div class="landing-preview-wrap" role="group" aria-label="{{ __('welcome.preview_aria') }}">
                    <div class="landing-preview">
                        <div class="landing-window-bar"><div class="landing-window-dots" aria-hidden="true"><span></span><span></span><span></span></div><span>{{ config('app.name', 'Promise Tracker') }}</span><span aria-hidden="true">•••</span></div>
                        <div class="landing-preview-header"><div><div class="landing-preview-kicker">{{ __('welcome.preview_overview') }}</div><strong>{{ __('welcome.preview_person') }}</strong></div><span class="landing-profile-pill"><i aria-hidden="true"></i>{{ __('welcome.preview_profile') }}</span></div>
                        <div class="landing-preview-header"><span class="landing-preview-month">{{ __('welcome.preview_record') }}</span><span class="landing-preview-month">{{ __('welcome.preview_workspace') }}</span></div>
                        <div class="landing-preview-cards">
                            <div class="landing-preview-card landing-preview-card-pay"><span>{{ __('welcome.preview_to_pay') }}</span><strong>MYR 4,280</strong></div>
                            <div class="landing-preview-card landing-preview-card-receive"><span>{{ __('welcome.preview_to_receive') }}</span><strong>MYR 1,150</strong></div>
                        </div>
                        <div class="landing-next">
                            <div class="landing-next-title"><span>{{ __('welcome.preview_recent_movement') }}</span><span>{{ __('welcome.preview_confirmed') }}</span></div>
                            <div class="landing-progress"><i></i></div>
                            <div class="landing-progress-caption"><span>{{ __('welcome.preview_progress') }}</span><span>62%</span></div>
                        </div>
                        <div class="landing-next">
                            <div class="landing-next-title"><b>{{ __('welcome.preview_promises') }}</b><span>{{ __('welcome.preview_view_all') }}</span></div>
                            <div class="landing-next-row"><div><span class="landing-next-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/></svg></span><span><b>{{ __('welcome.preview_home_costs') }}</b><small>{{ __('welcome.preview_due_you_owe') }}</small></span></div><span class="landing-next-amount">MYR 650</span></div>
                            <div class="landing-next-row"><div><span class="landing-next-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 7h14v10H5z"/><path d="m5 8 7 5 7-5"/></svg></span><span><b>{{ __('welcome.preview_family_support') }}</b><small>{{ __('welcome.preview_they_owe') }}</small></span></div><span class="landing-next-amount">MYR 1,150</span></div>
                        </div>
                        <div class="landing-example">{{ __('welcome.preview_illustrative') }}</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="landing-trust-strip" aria-label="{{ __('welcome.trust_strip_aria') }}">
            <div class="landing-container landing-trust-grid">
                <div class="landing-trust-item"><span class="landing-trust-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 2.9 8.5 7 10 4.1-1.5 7-5.5 7-10V6l-7-3Z"/><path d="M9 12h6M12 9v6" stroke-linecap="round"/></svg></span><span><strong>{{ __('welcome.trust_visibility') }}</strong><span>{{ __('welcome.trust_visibility_desc') }}</span></span></div>
                <div class="landing-trust-item"><span class="landing-trust-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 12h16M12 4v16" stroke-linecap="round"/><circle cx="12" cy="12" r="8"/></svg></span><span><strong>{{ __('welcome.trust_manual') }}</strong><span>{{ __('welcome.trust_manual_desc') }}</span></span></div>
                <div class="landing-trust-item"><span class="landing-trust-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 4v16M4 12h16" stroke-linecap="round"/><path d="m7 12 3 3 7-7" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span><strong>{{ __('welcome.trust_control') }}</strong><span>{{ __('welcome.trust_control_desc') }}</span></span></div>
            </div>
        </section>

        <section id="why" class="landing-section landing-section-soft">
            <div class="landing-container landing-problem-grid">
                <div>
                    <div class="landing-eyebrow">{{ __('welcome.why_eyebrow') }}</div>
                    <div class="landing-section-heading"><h2>{{ __('welcome.why_heading') }}</h2><p>{{ __('welcome.why_description') }}</p></div>
                    <div class="landing-fragment-list">
                        <div class="landing-fragment"><small>{{ __('welcome.why_fragment_scattered') }}</small><span>{{ __('welcome.why_fragment_scattered_desc') }}</span></div>
                        <div class="landing-fragment"><small>{{ __('welcome.why_fragment_unclear') }}</small><span>{{ __('welcome.why_fragment_unclear_desc') }}</span></div>
                        <div class="landing-fragment"><small>{{ __('welcome.why_fragment_approaching') }}</small><span>{{ __('welcome.why_fragment_approaching_desc') }}</span></div>
                        <div class="landing-fragment"><small>{{ __('welcome.why_fragment_personal') }}</small><span>{{ __('welcome.why_fragment_personal_desc') }}</span></div>
                    </div>
                </div>
                <blockquote class="landing-quote"><div class="landing-quote-mark" aria-hidden="true">“</div><p>{{ __('welcome.why_quote') }}</p><footer>{{ __('welcome.why_quote_footer') }}</footer></blockquote>
            </div>
        </section>

        <section class="landing-section">
            <div class="landing-container">
                <div class="landing-feature-intro"><div class="landing-section-heading"><div class="landing-eyebrow">{{ __('welcome.features_eyebrow') }}</div><h2>{{ __('welcome.features_heading') }}</h2></div><p>{{ __('welcome.features_intro') }}</p></div>
                <div class="landing-feature-grid">
                    <article class="landing-feature-card"><div class="landing-feature-number"><span>01</span><span class="landing-feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M5 6h14v13H5z"/><path d="M8 3v6M16 3v6M5 10h14"/></svg></span></div><h3>{{ __('welcome.feature_record_title') }}</h3><p>{{ __('welcome.feature_record_desc') }}</p></article>
                    <article class="landing-feature-card"><div class="landing-feature-number"><span>02</span><span class="landing-feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 18h16M6 15l3-4 3 2 5-7" stroke-linecap="round" stroke-linejoin="round"/></svg></span></div><h3>{{ __('welcome.feature_movements_title') }}</h3><p>{{ __('welcome.feature_movements_desc') }}</p></article>
                    <article class="landing-feature-card"><div class="landing-feature-number"><span>03</span><span class="landing-feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M6 4h9l3 3v13H6z"/><path d="M14 4v4h4M9 13h6M9 16h4" stroke-linecap="round"/></svg></span></div><h3>{{ __('welcome.feature_currencies_title') }}</h3><p>{{ __('welcome.feature_currencies_desc') }}</p></article>
                    <article class="landing-feature-card"><div class="landing-feature-number"><span>04</span><span class="landing-feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M5 6h14v12H5z"/><path d="m5 7 7 5 7-5M8 21h8" stroke-linecap="round" stroke-linejoin="round"/></svg></span></div><h3>{{ __('welcome.feature_charges_title') }}</h3><p>{{ __('welcome.feature_charges_desc') }}</p></article>
                </div>
            </div>
        </section>

        <section id="how" class="landing-section landing-section-soft">
            <div class="landing-container">
                <div class="landing-section-heading"><div class="landing-eyebrow">{{ __('welcome.how_eyebrow') }}</div><h2>{{ __('welcome.how_heading') }}</h2><p>{{ __('welcome.how_description') }}</p></div>
                <div class="landing-steps">
                    <article class="landing-step"><span class="landing-step-num">01</span><h3>{{ __('welcome.step_add_title') }}</h3><p>{{ __('welcome.step_add_desc') }}</p></article>
                    <article class="landing-step"><span class="landing-step-num">02</span><h3>{{ __('welcome.step_movement_title') }}</h3><p>{{ __('welcome.step_movement_desc') }}</p></article>
                    <article class="landing-step"><span class="landing-step-num">03</span><h3>{{ __('welcome.step_plan_title') }}</h3><p>{{ __('welcome.step_plan_desc') }}</p></article>
                </div>
            </div>
        </section>

        <section id="trust" class="landing-section">
            <div class="landing-container landing-principles">
                <div class="landing-section-heading"><div class="landing-eyebrow">{{ __('welcome.trust_eyebrow') }}</div><h2>{{ __('welcome.trust_heading') }}</h2><p>{{ __('welcome.trust_description') }}</p></div>
                <div class="landing-principle-list">
                    <article class="landing-principle"><span class="landing-principle-num">01</span><div><h3>{{ __('welcome.principle_visibility_title') }}</h3><p>{{ __('welcome.principle_visibility_desc') }}</p></div></article>
                    <article class="landing-principle"><span class="landing-principle-num">02</span><div><h3>{{ __('welcome.principle_movement_title') }}</h3><p>{{ __('welcome.principle_movement_desc') }}</p></div></article>
                    <article class="landing-principle"><span class="landing-principle-num">03</span><div><h3>{{ __('welcome.principle_currency_title') }}</h3><p>{{ __('welcome.principle_currency_desc') }}</p></div></article>
                    <article class="landing-principle"><span class="landing-principle-num">04</span><div><h3>{{ __('welcome.principle_control_title') }}</h3><p>{{ __('welcome.principle_control_desc') }}</p></div></article>
                </div>
            </div>
        </section>

        <section class="landing-section landing-section-soft">
            <div class="landing-container">
                <div class="landing-section-heading"><div class="landing-eyebrow">{{ __('welcome.faq_eyebrow') }}</div><h2>{{ __('welcome.faq_heading') }}</h2></div>
                <div class="landing-faq-grid">
                    <article class="landing-faq"><h3>{{ __('welcome.faq_details_question') }}</h3><p>{{ __('welcome.faq_details_answer') }}</p></article>
                    <article class="landing-faq"><h3>{{ __('welcome.faq_share_question') }}</h3><p>{{ __('welcome.faq_share_answer') }}</p></article>
                    <article class="landing-faq"><h3>{{ __('welcome.faq_types_question') }}</h3><p>{{ __('welcome.faq_types_answer') }}</p></article>
                    <article class="landing-faq"><h3>{{ __('welcome.faq_payments_question') }}</h3><p>{{ __('welcome.faq_payments_answer') }}</p></article>
                </div>
            </div>
        </section>

        <section class="landing-cta">
            <div class="landing-container landing-cta-inner"><div><div class="landing-eyebrow">{{ __('welcome.cta_eyebrow') }}</div><h2>{{ __('welcome.cta_heading') }}</h2></div><a class="landing-button landing-button-primary" href="{{ route('register') }}">{{ __('welcome.create_account') }} <span aria-hidden="true" style="margin-left: 8px">→</span></a></div>
        </section>
    </main>

    <footer class="landing-footer">
        <div class="landing-container landing-footer-inner">
            <a class="landing-brand" href="{{ route('home') }}" aria-label="{{ __('welcome.brand_home', ['app' => config('app.name', 'Promise Tracker')]) }}"><span class="landing-brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 17.5 12 4l7 13.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M8.3 13.7h7.4M7 17.5h10" stroke-linecap="round"/></svg></span><span>{{ config('app.name', 'Promise Tracker') }}</span></a>
            <div class="landing-footer-links"><a href="{{ route('legal.privacy') }}">{{ __('welcome.footer_privacy') }}</a><a href="{{ route('legal.terms') }}">{{ __('welcome.footer_terms') }}</a><a href="{{ route('legal.retention') }}">{{ __('welcome.footer_retention') }}</a><a href="{{ route('legal.deletion') }}">{{ __('welcome.footer_deletion') }}</a><a href="#how">{{ __('welcome.footer_how') }}</a><a href="{{ route('login') }}">{{ __('welcome.footer_sign_in') }}</a></div>
            <span>{{ __('welcome.footer_tagline') }}</span>
        </div>
    </footer>
</div>
</body>
</html>
