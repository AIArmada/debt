<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('welcome.meta_description') }}">
    <title>{{ __('welcome.title', ['app' => 'ClearTogether']) }}</title>

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

        :root { --ink: #172522; --muted: #626e69; --paper: #fcfcf8; --line: rgba(26, 56, 47, .09); --forest: #0f4035; --mint: #e4f1e7; --lime: #d8ef72; --coral: #f9e3dc; --cream: #fff; }
        body { background: var(--paper); color: var(--ink); font-family: "Instrument Sans", ui-sans-serif, system-ui, sans-serif; font-size: 15px; }
        .landing-shell { background: var(--paper); }
        .landing-container { width: min(1120px, calc(100% - 96px)); }
        .landing-nav { border-bottom: 1px solid rgba(26, 56, 47, .055); background: rgba(252, 252, 248, .92); }
        .landing-nav-inner { min-height: 72px; gap: 32px; }
        .landing-brand { gap: 9px; font-size: 15px; letter-spacing: -.04em; }
        .landing-brand-mark { width: 28px; height: 28px; border-radius: 0; background: transparent; color: var(--forest); box-shadow: none; }
        .landing-brand-mark svg { width: 26px; height: 26px; }
        .landing-nav-links { gap: 30px; font-size: 13px; }
        .landing-nav-actions { gap: 10px; }
        .landing-language-switch { border: 0; border-radius: 0; background: none; font-size: 11px; }
        .landing-language-switch a { min-height: 30px; }
        .landing-shell a:focus-visible { outline: 2px solid var(--forest); outline-offset: 3px; }
        .landing-button { min-height: 44px; padding: 10px 18px; font-size: 13px; }
        .landing-button-primary { background: var(--forest); box-shadow: none; }
        .landing-button-primary:hover { background: #1b5748; box-shadow: none; }
        .landing-button-secondary { border-color: rgba(15, 64, 53, .18); background: transparent; }
        .landing-hero { padding: 58px 0 44px; }
        .landing-hero::before { width: 650px; height: 650px; right: -180px; top: -255px; background: radial-gradient(circle, rgba(218, 239, 222, .78), rgba(218, 239, 222, 0) 68%); filter: none; }
        .landing-hero-grid { grid-template-columns: minmax(0, .96fr) minmax(0, 1fr); gap: clamp(24px, 4vw, 56px); }
        .landing-eyebrow { gap: 0; color: #547d6e; font-size: 10px; letter-spacing: .2em; }
        .landing-eyebrow::before { display: none; }
        .landing-hero h1, .landing-section-heading h2, .landing-cta h2 { font-family: Georgia, "Times New Roman", serif; font-weight: 500; letter-spacing: -.055em; }
        .landing-hero h1 { max-width: 565px; margin: 18px 0 17px; font-size: clamp(44px, 5.1vw, 67px); line-height: .99; }
        .landing-hero h1 em { color: inherit; }
        .landing-lede { max-width: 490px; color: #46524e; font-size: 16px; line-height: 1.55; }
        .landing-hero-actions { gap: 10px; margin-top: 24px; }
        .landing-hero-note { gap: 12px; margin-top: 25px; font-size: 12px; }
        .landing-hero-note svg { flex: 0 0 auto; }
        .landing-hero-avatars { display: inline-flex; flex: 0 0 auto; padding-left: 5px; }
        .landing-hero-avatars i { display: grid; place-items: center; width: 25px; height: 25px; margin-left: -5px; border: 2px solid var(--paper); border-radius: 50%; background: #d9ab8d; color: #243c34; font-size: 8px; font-style: normal; font-weight: 700; }
        .landing-hero-avatars i:nth-child(2) { background: #9fb4a3; }
        .landing-hero-avatars i:nth-child(3) { background: #e4c68d; }
        .landing-preview-wrap { padding: 0; }
        .landing-preview-wrap::before { inset: -20px -36px -22px 12%; width: auto; height: auto; border: 0; border-radius: 50%; background: rgba(224, 243, 228, .55); transform: none; }
        .landing-preview { padding: 21px; border: 1px solid rgba(26, 56, 47, .06); border-radius: 18px; background: #fff; box-shadow: 0 20px 50px rgba(27, 56, 45, .09); }
        .landing-window-bar { margin-bottom: 20px; font-size: 11px; }
        .landing-window-dots span { width: 28px; height: 22px; border-radius: 7px; background: #e8f2e9; }
        .landing-window-dots span:first-child { background: #dcefe2; }
        .landing-profile-pill { padding: 5px 9px; background: transparent; border: 1px solid #bfd9ca; font-size: 11px; }
        .landing-preview-header { align-items: center; margin-bottom: 12px; }
        .landing-preview-kicker, .landing-preview-month { font-size: 11px; }
        .landing-preview-header strong { font-size: 22px; }
        .landing-preview-cards { gap: 10px; }
        .landing-preview-card { border-radius: 11px; padding: 13px; }
        .landing-preview-card span { font-size: 10px; }
        .landing-preview-card strong { margin-top: 5px; font-size: 18px; }
        .landing-preview-card-pay { background: #fae8e3; }
        .landing-preview-card-receive { background: #e8f3e9; }
        .landing-progress { height: 6px; }
        .landing-next { margin-top: 13px; padding-top: 12px; }
        .landing-next-title { font-size: 11px; }
        .landing-next-row { padding: 8px 3px; background: transparent; border-radius: 0; border-bottom: 1px solid rgba(26, 56, 47, .07); }
        .landing-next-icon { width: 29px; height: 29px; border-radius: 50%; background: #e8f3e9; }
        .landing-next-row b { font-size: 12px; }
        .landing-example { margin-top: 8px; font-size: 9px; }
        .landing-trust-strip { margin: 0 auto; width: min(1120px, calc(100% - 96px)); border: 1px solid rgba(26, 56, 47, .055); border-radius: 14px; background: rgba(239, 246, 238, .62); }
        .landing-trust-strip .landing-container { width: 100%; }
        .landing-trust-grid { width: 100%; }
        .landing-trust-item { gap: 13px; padding: 19px 23px; color: var(--ink); }
        .landing-trust-icon { width: 38px; height: 38px; border-radius: 50%; }
        .landing-trust-item strong { font-size: 12px; }
        .landing-trust-item > span:last-child > span { color: var(--muted); font-size: 11px; line-height: 1.4; }
        .landing-section { padding: 74px 0; }
        .landing-section-soft { background: #f1f6ef; }
        #features { background: #f2f7f0; }
        .landing-section-heading { max-width: 590px; }
        .landing-section-heading h2 { margin: 12px 0; font-size: clamp(34px, 4vw, 48px); line-height: 1.02; }
        .landing-section-heading p { max-width: 510px; color: var(--muted); font-size: 15px; line-height: 1.55; }
        .landing-problem-grid { grid-template-columns: .98fr 1.02fr; gap: 54px; align-items: center; }
        .landing-fragment-list { gap: 10px; margin-top: 23px; }
        .landing-fragment { display: flex; align-items: center; gap: 13px; min-height: 73px; border-color: rgba(26, 56, 47, .07); border-radius: 13px; padding: 12px; background: rgba(255, 255, 255, .62); }
        .landing-fragment::before { content: "↗"; display: grid; flex: 0 0 34px; place-items: center; width: 34px; height: 34px; border-radius: 50%; background: #e6f2e7; color: var(--forest); font-size: 15px; }
        .landing-fragment:nth-child(2)::before, .landing-fragment:nth-child(4)::before { background: #fae8e3; }
        .landing-fragment small { display: none; }
        .landing-fragment span { font-size: 12px; font-weight: 500; line-height: 1.35; }
        .landing-quote { min-height: 400px; display: flex; flex-direction: column; justify-content: space-between; border-radius: 17px; padding: 22px; background: #d9c6ac url('/images/promise-conversation.jpg') center / cover no-repeat; color: var(--ink); box-shadow: inset 0 0 0 1000px rgba(42, 34, 24, .08); }
        .landing-quote-mark { display: none; }
        .landing-quote p { align-self: flex-start; max-width: 245px; margin: 14px 0; border-radius: 12px 12px 12px 3px; padding: 13px 15px; background: rgba(255, 255, 255, .96); color: var(--ink); font-size: 13px; line-height: 1.42; box-shadow: 0 8px 18px rgba(34, 34, 24, .09); }
        .landing-quote footer { align-self: flex-end; max-width: 220px; border-radius: 12px 12px 3px 12px; padding: 12px 15px; background: #d8edcf; color: var(--ink); font-size: 12px; box-shadow: 0 8px 18px rgba(34, 34, 24, .08); }
        .landing-feature-intro { margin-bottom: 28px; }
        .landing-feature-intro .landing-section-heading { max-width: 700px; }
        .landing-feature-intro > p { display: none; }
        .landing-feature-grid { grid-template-columns: repeat(4, 1fr); gap: 14px; }
        .landing-feature-card { min-height: 242px; border: 1px solid rgba(26, 56, 47, .035); border-radius: 15px; padding: 20px; background: #eaf4eb; }
        .landing-feature-card:nth-child(2) { background: #faece8; }
        .landing-feature-card:nth-child(3) { background: #f5f3dd; }
        .landing-feature-card:nth-child(4) { background: #eaf4eb; }
        .landing-feature-number { color: transparent; }
        .landing-feature-icon { width: 38px; height: 38px; border: 0; border-radius: 50%; background: rgba(173, 221, 190, .5); }
        .landing-feature-card:nth-child(2) .landing-feature-icon { background: rgba(248, 189, 174, .52); }
        .landing-feature-card:nth-child(3) .landing-feature-icon { background: rgba(225, 219, 142, .48); }
        .landing-feature-card h3 { margin: 24px 0 9px; font-family: Georgia, "Times New Roman", serif; font-size: 21px; font-weight: 500; line-height: 1.12; letter-spacing: -.04em; }
        .landing-feature-card p { color: #55615c; font-size: 12px; line-height: 1.5; }
        #how { background: #fcfcf8; }
        #how .landing-container { position: relative; }
        #how .landing-section-heading { max-width: none; }
        #how .landing-section-heading p { display: none; }
        .landing-steps { position: relative; max-width: calc(100% - 210px); grid-template-columns: repeat(3, 1fr); gap: 26px; margin-top: 29px; border: 0; border-radius: 0; background: transparent; overflow: visible; }
        .landing-step { position: relative; min-height: 0; padding: 8px 22px 8px 0; background: transparent; }
        .landing-step:not(:last-child)::after { content: "→"; position: absolute; top: 10px; right: 4px; color: #c3d6c8; font-size: 24px; }
        .landing-step-num { width: 30px; height: 30px; background: var(--forest); color: #fff; font-size: 11px; }
        .landing-step h3 { margin: 14px 0 6px; font-size: 15px; }
        .landing-step p { max-width: 260px; color: var(--muted); font-size: 12px; line-height: 1.5; }
        .landing-how-people { position: absolute; right: 0; bottom: 10px; width: 205px; }
        .landing-how-people svg { display: block; width: 100%; height: auto; }
        .landing-principles { grid-template-columns: .85fr 1.15fr; gap: 72px; align-items: center; }
        .landing-principles > .landing-section-heading { position: static; }
        .landing-principle { grid-template-columns: 42px 1fr; gap: 14px; padding: 17px 0; }
        .landing-principle-num { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 50%; background: #eaf4e9; color: var(--forest); font-size: 11px; }
        .landing-principle h3 { font-size: 14px; }
        .landing-principle p { color: var(--muted); font-size: 12px; line-height: 1.5; }
        #faq { padding: 66px 0 72px; background: #f1f6ef; }
        #faq .landing-section-heading { max-width: none; }
        #faq .landing-faq-grid { grid-template-columns: repeat(2, 1fr); gap: 10px 13px; margin-top: 25px; }
        .landing-faq { position: relative; min-height: 102px; border-color: rgba(26, 56, 47, .055); border-radius: 12px; padding: 18px; background: #fff; }
        .landing-faq::after { content: "+"; position: absolute; top: 15px; right: 17px; color: var(--forest); font-size: 18px; }
        .landing-faq h3 { max-width: 90%; margin: 0 0 8px; font-size: 13px; }
        .landing-faq p { max-width: 95%; color: var(--muted); font-size: 12px; line-height: 1.45; }
        .landing-cta { padding: 45px 0 48px; background: var(--forest); }
        .landing-cta::before { width: 500px; height: 500px; top: -375px; right: -45px; }
        .landing-cta::after { width: 390px; height: 390px; bottom: -325px; left: 46%; }
        .landing-cta-inner { align-items: center; gap: 35px; }
        .landing-cta h2 { max-width: 470px; margin: 12px 0 0; font-size: clamp(34px, 4vw, 46px); line-height: 1.03; }
        .landing-cta-inner > div > p { margin: 9px 0 0; color: rgba(255, 255, 255, .72); font-size: 13px; }
        .landing-cta .landing-button-primary { min-width: 195px; background: var(--lime); color: var(--forest); }
        .landing-footer { background: var(--paper); color: #596760; }
        .landing-footer-inner { gap: 25px; padding: 20px 0; font-size: 11px; }
        .landing-footer .landing-brand { color: var(--ink); }
        .landing-footer .landing-brand-mark { background: transparent; color: var(--forest); }
        .landing-footer-links { gap: 16px; }
        .landing-footer a:hover { color: var(--forest); }
        @media (max-width: 900px) {
            .landing-hero { padding-top: 50px; }
            .landing-hero-grid { grid-template-columns: minmax(0, .9fr) minmax(330px, 1fr); gap: 30px; }
            .landing-preview { padding: 16px; }
            .landing-trust-strip { width: min(100% - 40px, 760px); }
            .landing-trust-item { padding: 16px 12px; }
            .landing-feature-grid { grid-template-columns: repeat(2, 1fr); }
            .landing-feature-card { min-height: 210px; }
            .landing-steps { max-width: calc(100% - 170px); gap: 12px; }
            .landing-how-people { width: 165px; }
        }
        @media (max-width: 680px) {
            .landing-container { width: min(100% - 36px, 540px); }
            .landing-nav-inner { min-height: 66px; }
            .landing-nav-links { display: none; }
            .landing-nav-actions .landing-button-secondary { display: inline-flex; }
            .landing-language-switch { display: none; }
            .landing-nav-actions > .landing-button-primary { padding: 9px 13px; font-size: 12px; }
            .landing-hero { padding: 45px 0 38px; }
            .landing-hero-grid, .landing-problem-grid, .landing-principles { grid-template-columns: 1fr; gap: 30px; }
            .landing-hero h1 { max-width: 500px; font-size: clamp(43px, 12vw, 58px); }
            .landing-lede { font-size: 15px; }
            .landing-preview-wrap { max-width: 500px; width: 100%; margin: 0 auto; }
            .landing-preview-wrap::before { inset: -14px -12px; }
            .landing-trust-grid { grid-template-columns: 1fr; }
            .landing-trust-strip { width: min(100% - 36px, 540px); }
            .landing-trust-item { padding: 13px 16px; }
            .landing-trust-item + .landing-trust-item { border-top: 1px solid var(--line); border-left: 0; }
            .landing-section { padding: 58px 0; }
            .landing-section-heading h2 { font-size: 37px; }
            .landing-fragment-list { margin-top: 19px; }
            .landing-quote { min-height: 340px; }
            .landing-feature-grid { grid-template-columns: repeat(2, 1fr); gap: 9px; }
            .landing-feature-card { min-height: 218px; padding: 16px; }
            .landing-feature-card h3 { margin-top: 20px; font-size: 19px; }
            .landing-steps { grid-template-columns: 1fr; gap: 12px; }
            .landing-steps { max-width: 100%; }
            .landing-how-people { display: none; }
            .landing-step { padding: 6px 0 8px; }
            .landing-step:not(:last-child)::after { content: "↓"; top: auto; right: 7px; bottom: -15px; font-size: 17px; }
            .landing-step h3 { margin-top: 10px; }
            .landing-step p { max-width: 420px; }
            .landing-principles > .landing-section-heading { position: static; }
            #faq { padding: 56px 0; }
            #faq .landing-faq-grid { grid-template-columns: 1fr; gap: 9px; }
            .landing-cta { padding: 44px 0; }
            .landing-cta-inner, .landing-footer-inner { align-items: flex-start; flex-direction: column; }
            .landing-cta-inner { gap: 24px; }
            .landing-cta h2 { font-size: 39px; }
            .landing-footer-links { row-gap: 9px; }
        }
        @media (max-width: 380px) {
            .landing-nav-inner { gap: 6px; }
            .landing-brand { gap: 6px; font-size: 13px; }
            .landing-nav-actions { gap: 6px; }
            .landing-nav-actions > .landing-button-primary { padding: 8px 9px; font-size: 11px; }
            .landing-nav-actions .landing-button-secondary { padding: 8px 10px; font-size: 11px; }
        }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .landing-shell *, .landing-shell *::before, .landing-shell *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; }
        }
        .landing-window-bar { justify-content: flex-start; }
        .landing-preview-brand { display: inline-flex; align-items: center; gap: 7px; margin-right: auto; color: var(--ink); font-size: 11px; font-weight: 700; white-space: nowrap; }
        .landing-preview-brand svg { flex: 0 0 19px; width: 19px; height: 19px; color: var(--forest); }
        .landing-preview-people { display: inline-flex; }
        .landing-preview-people i { display: grid; place-items: center; width: 20px; height: 20px; margin-left: -3px; border: 1px solid white; border-radius: 50%; background: #e5b69a; color: #35473c; font-size: 8px; font-style: normal; font-weight: 700; }
        .landing-preview-people i:nth-child(2) { background: #d6e6d8; }
        .landing-preview-share, .landing-preview-more { display: inline-flex; align-items: center; justify-content: center; }
        .landing-preview-share { border: 1px solid #bfd9ca; border-radius: 999px; padding: 5px 8px; color: var(--forest); font-size: 10px; line-height: 1; }
        .landing-preview-more { width: 22px; height: 22px; border: 1px solid rgba(26, 56, 47, .1); border-radius: 50%; font-size: 8px; line-height: 1; }
        .landing-record-heading { display: flex; }
        .landing-record-icon { display: grid; flex: 0 0 32px; place-items: center; width: 32px; height: 32px; border-radius: 50%; background: #e7f2e9; color: var(--forest); }
        .landing-record-icon svg { width: 16px; height: 16px; }
        .landing-record-heading > span:last-child { display: grid; min-width: 0; }
        .landing-record-heading strong, .landing-record-heading small { display: block; }
        .landing-record-heading small { margin-top: 2px; }
        .landing-record-icon { display: grid; }
        .landing-preview-tabs { display: flex; align-items: center; gap: 8px; margin: 13px 0 7px; color: var(--muted); font-size: 11px; }
        .landing-preview-add { margin-left: auto; }
        .landing-preview-tabs > span { display: block; }
        .landing-preview-tabs .is-active { border: 1px solid #bfd9ca; border-radius: 999px; padding: 4px 8px; color: var(--forest); }
        .landing-preview-add { border-radius: 999px; padding: 6px 9px; background: var(--forest); color: white; font-size: 10px; }
        .landing-preview-list { display: block; }
        .landing-preview-row { display: grid; grid-template-columns: 28px minmax(0, 1fr) auto minmax(52px, .42fr); align-items: center; gap: 8px; padding: 9px 0; border-bottom: 1px solid rgba(26, 56, 47, .07); }
        .landing-transaction-icon { display: grid; flex: 0 0 28px; place-items: center; width: 28px; height: 28px; border-radius: 50%; color: var(--forest); font-size: 13px; }
        .landing-transaction-icon.mint { background: #e2f0e5; }
        .landing-transaction-icon.peach { background: #f9e3dc; }
        .landing-transaction-icon.butter { background: #f5efcf; }
        .landing-transaction-name b, .landing-transaction-name small { display: block; }
        .landing-transaction-name { min-width: 0; }
        .landing-transaction-name b, .landing-transaction-amount { font-size: 11px; }
        .landing-transaction-name small { margin-top: 2px; color: var(--muted); font-size: 9px; }
        .landing-transaction-status { display: block; }
        .landing-transaction-status { color: var(--muted); font-size: 9px; text-align: right; }
        .landing-transaction-amount { white-space: nowrap; }
        .landing-fragment::before { display: none; }
        .landing-fragment { min-height: 58px; gap: 10px; padding: 9px; }
        .landing-fragment-icon { display: grid; flex: 0 0 31px; place-items: center; width: 31px; height: 31px; border-radius: 50%; background: #e1f0e5; color: var(--forest); }
        .landing-fragment:nth-child(2) .landing-fragment-icon, .landing-fragment:nth-child(4) .landing-fragment-icon { background: #f9e3dc; }
        .landing-fragment-icon svg { display: block; }
        .landing-fragment small { display: block; margin-bottom: 2px; color: var(--ink); font-size: 11px; font-weight: 600; letter-spacing: 0; text-transform: none; }
        .landing-fragment-copy { display: grid; min-width: 0; }
        .landing-fragment .landing-fragment-copy > span { color: var(--muted); font-size: 10px; font-weight: 500; line-height: 1.35; }
        .landing-quote { justify-content: flex-start; gap: 10px; min-height: 400px; padding: 26px; background-image: url('/images/promise-conversation.jpg'); box-shadow: inset 0 0 0 1000px rgba(42, 34, 24, .03); }
        .landing-quote::before { content: ""; position: absolute; inset: 0; border-radius: inherit; background: linear-gradient(180deg, rgba(255,255,255,.08), transparent 50%, rgba(40,32,22,.1)); pointer-events: none; }
        .landing-chat-bubble { position: relative; display: flex; align-items: center; gap: 9px; width: fit-content; max-width: 75%; border-radius: 12px; padding: 10px 12px; background: rgba(255, 255, 255, .95); color: var(--ink); font-size: 10px; line-height: 1.4; box-shadow: 0 5px 16px rgba(44, 34, 24, .1); }
        .landing-chat-first, .landing-chat-third { margin-left: 9px; }
        .landing-chat-second { align-self: flex-end; background: #e4f2dc; }
        .landing-chat-avatar { display: grid; flex: 0 0 26px; place-items: center; width: 26px; height: 26px; border-radius: 50%; background: #e5b69a; color: #35473c; font-size: 9px; font-weight: 700; }
        .landing-chat-avatar.avatar-two { background: #d6e6d8; }
        .landing-chat-note { position: relative; align-self: flex-start; margin: auto 0 0 5px; color: white; font-family: Georgia, "Times New Roman", serif; font-size: 16px; font-style: italic; line-height: 1.05; text-shadow: 0 1px 4px rgba(25, 35, 25, .4); transform: rotate(-5deg); }
        .landing-chat-note span { display: inline-block; margin-left: 10px; font-family: sans-serif; font-size: 22px; transform: rotate(20deg); }
        .landing-how-people { right: 0; bottom: 0; width: 220px; }
        .landing-how-people img { display: block; width: 100%; height: auto; }
        .landing-faq::after { display: none; }
        .landing-faq summary { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; cursor: pointer; list-style: none; }
        .landing-faq summary::-webkit-details-marker { display: none; }
        .landing-faq summary::after { content: "+"; flex: 0 0 auto; color: var(--forest); font-size: 18px; line-height: 1; }
        .landing-faq[open] summary::after { content: "−"; }
        @media (max-width: 680px) {
            .landing-preview-row { grid-template-columns: 24px minmax(72px, 1fr) auto minmax(42px, .45fr); gap: 6px; padding: 6px 0; }
            .landing-transaction-icon { width: 24px; height: 24px; }
            .landing-transaction-name b, .landing-transaction-amount { font-size: 10px; }
            .landing-transaction-name small, .landing-transaction-status { font-size: 8px; }
            .landing-preview-tabs { gap: 7px; font-size: 10px; }
            .landing-quote { min-height: 340px; }
            .landing-how-people { display: none; }
            .landing-nav-inner { gap: 8px; }
            .landing-brand { gap: 6px; font-size: 13px; white-space: nowrap; }
            .landing-brand-mark, .landing-brand-mark svg { width: 24px; height: 24px; }
            .landing-nav-actions .landing-button-secondary { padding: 9px 12px; white-space: nowrap; }
            .landing-hero-avatars i { width: 21px; height: 21px; }
        }
        @media (min-width: 681px) and (max-width: 760px) {
            .landing-container { width: calc(100% - 96px); }
            .landing-eyebrow { font-size: 8px; letter-spacing: .16em; }
            .landing-nav-inner { min-height: 42px; gap: 12px; }
            .landing-brand { gap: 6px; font-size: 12px; }
            .landing-brand-mark, .landing-brand-mark svg { width: 22px; height: 22px; }
            .landing-nav-links { gap: 15px; font-size: 10px; }
            .landing-nav-actions { gap: 6px; }
            .landing-nav-actions .landing-button { min-height: 32px; padding: 6px 11px; font-size: 10px; }
            .landing-language-switch { display: none; }
            .landing-hero { padding: 36px 0 22px; }
            .landing-hero-grid { grid-template-columns: minmax(0, 1.16fr) minmax(0, 1fr); gap: 32px; }
            .landing-preview-wrap { width: 100%; }
            .landing-hero h1 { max-width: 310px; margin: 12px 0; font-size: clamp(34px, 5vw, 38px); line-height: 1.02; }
            .landing-lede { font-size: 12px; line-height: 1.45; }
            .landing-hero-actions { gap: 8px; margin-top: 16px; }
            .landing-hero-actions .landing-button { min-height: 34px; padding: 7px 12px; font-size: 10px; }
            .landing-hero-note { align-items: center; gap: 8px; margin-top: 17px; font-size: 9px; line-height: 1.3; }
            .landing-hero-avatars { display: inline-flex; flex: 0 0 auto; padding-left: 5px; }
            .landing-hero-avatars i { display: grid; place-items: center; width: 23px; height: 23px; margin-left: -5px; border: 2px solid var(--paper); border-radius: 50%; background: #d9ab8d; color: #243c34; font-size: 8px; font-style: normal; font-weight: 700; }
            .landing-hero-avatars i:nth-child(2) { background: #9fb4a3; }
            .landing-hero-avatars i:nth-child(3) { background: #e4c68d; }
            .landing-preview { padding: 10px; border-radius: 15px; }
            .landing-window-bar { gap: 6px; margin-bottom: 8px; font-size: 8px; }
            .landing-preview-brand { gap: 4px; font-size: 8px; }
            .landing-preview-brand svg { width: 13px; height: 13px; }
            .landing-preview-people i { width: 15px; height: 15px; font-size: 7px; }
            .landing-preview-share, .landing-preview-more { padding: 3px 6px; font-size: 8px; }
            .landing-record-heading { gap: 8px; margin-bottom: 9px; }
            .landing-record-icon { width: 27px; height: 27px; }
            .landing-record-heading strong { font-size: 12px; }
            .landing-record-heading small { font-size: 8px; }
            .landing-preview-cards { gap: 7px; }
            .landing-preview-card { padding: 9px; }
            .landing-preview-card span { font-size: 8px; }
            .landing-preview-card strong { font-size: 13px; }
            .landing-preview-tabs { gap: 8px; margin: 8px 0 5px; font-size: 8px; }
            .landing-preview-add { padding: 4px 7px; }
            .landing-preview-row { grid-template-columns: 23px minmax(58px, 1fr) auto minmax(37px, .42fr); gap: 5px; padding: 2px 0; }
            .landing-transaction-icon { width: 22px; height: 22px; font-size: 10px; }
            .landing-transaction-name b, .landing-transaction-amount { font-size: 8px; }
            .landing-transaction-name small, .landing-transaction-status { font-size: 7px; }
            .landing-example { font-size: 7px; }
            .landing-trust-strip { width: calc(100% - 96px); }
            .landing-trust-strip .landing-container { width: 100%; }
            .landing-trust-item { gap: 8px; padding: 14px 10px; }
            .landing-trust-icon { width: 31px; height: 31px; }
            .landing-trust-item strong { font-size: 10px; }
            .landing-trust-item > span:last-child > span { font-size: 8px; }
            .landing-section { padding: 29px 0; }
            .landing-problem-grid, .landing-principles { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 30px; align-items: center; }
            .landing-section-heading h2 { margin: 8px 0; font-size: 31px; line-height: 1.02; }
            .landing-section-heading p { font-size: 11px; line-height: 1.45; }
            .landing-fragment-list { gap: 7px; margin-top: 15px; }
            .landing-fragment { min-height: 49px; gap: 6px; padding: 7px; }
            .landing-fragment-icon { flex-basis: 26px; width: 26px; height: 26px; }
            .landing-fragment-icon svg { width: 13px; height: 13px; }
            .landing-fragment small { font-size: 8px; }
            .landing-fragment .landing-fragment-copy > span { font-size: 8px; }
            .landing-quote { min-height: 296px; padding: 18px; background-position: 62% center; }
            .landing-chat-bubble { max-width: 81%; gap: 6px; padding: 8px; font-size: 8px; }
            .landing-chat-avatar { flex-basis: 22px; width: 22px; height: 22px; }
            .landing-chat-note { font-size: 13px; }
            .landing-feature-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 9px; }
            #features { padding: 22px 0; }
            #features { background: #f2f7f0; }
            .landing-feature-intro { margin-bottom: 8px; }
            .landing-feature-intro .landing-section-heading h2 { font-size: 29px; }
            .landing-feature-card { min-height: 162px; padding: 13px; }
            .landing-feature-number { justify-content: flex-start; }
            .landing-feature-number > span:first-child { display: none; }
            .landing-feature-icon { width: 31px; height: 31px; }
            .landing-feature-card h3 { margin: 14px 0 7px; font-size: 16px; line-height: 1.08; }
            .landing-feature-card p { font-size: 9px; line-height: 1.4; }
            #how { padding: 20px 0; }
            #how .landing-section-heading h2 { margin: 5px 0; font-size: 28px; }
            .landing-steps { max-width: calc(100% - 170px); gap: 8px; margin-top: 16px; }
            .landing-step { padding: 4px 13px 4px 0; }
            .landing-step h3 { margin: 10px 0 5px; font-size: 11px; line-height: 1.2; }
            .landing-step p { font-size: 8px; line-height: 1.4; }
            .landing-how-people { width: 165px; }
            #trust { padding: 28px 0; }
            .landing-principles { grid-template-columns: minmax(0, .78fr) minmax(0, 1.22fr); gap: 30px; }
            .landing-principle { grid-template-columns: 27px 1fr; gap: 8px; padding: 8px 0; }
            .landing-principle-num { width: 25px; height: 25px; font-size: 8px; }
            .landing-principle h3 { margin-bottom: 4px; font-size: 10px; }
            .landing-principle p { font-size: 8px; line-height: 1.3; }
            #faq { padding: 31px 0; }
            #faq .landing-section-heading h2 { font-size: 28px; }
            #faq .landing-faq-grid { gap: 8px; margin-top: 12px; }
            .landing-faq { min-height: 54px; padding: 6px 10px; }
            .landing-faq h3 { margin-bottom: 3px; font-size: 9px; }
            .landing-faq p { font-size: 8px; }
            .landing-cta { padding: 24px 0; }
            .landing-cta h2 { max-width: 285px; margin-top: 8px; font-size: 30px; line-height: .98; }
            .landing-cta-inner > div > p { margin-top: 6px; font-size: 12px; line-height: 1.4; }
            .landing-cta .landing-button { min-height: 34px; padding: 7px 12px; font-size: 9px; }
            .landing-cta .landing-button-primary { min-width: 120px; }
            .landing-footer-inner { gap: 12px; padding: 12px 0; font-size: 8px; }
            .landing-footer .landing-brand { font-size: 11px; }
            .landing-footer .landing-brand-mark, .landing-footer .landing-brand-mark svg { width: 21px; height: 21px; }
            .landing-footer-links { gap: 10px; }
        }
    </style>
</head>
<body>
<div class="landing-shell">
    <header class="landing-nav">
        <div class="landing-container landing-nav-inner">
            <a class="landing-brand" href="{{ route('home') }}" aria-label="{{ __('welcome.brand_home', ['app' => 'ClearTogether']) }}">
                <span class="landing-brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 14c0-5.5 4.2-9 10-9 0 5.9-3.6 10-9 10" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 19c2.7-4.5 6.1-7.4 11-10M12 8c.2 2.3 1.4 3.6 3.5 4" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <span>ClearTogether</span>
            </a>
            <nav class="landing-nav-links" aria-label="{{ __('welcome.primary_navigation') }}">
                <a href="#why">{{ __('welcome.nav_why') }}</a>
                <a href="#features">{{ __('welcome.nav_features') }}</a>
                <a href="#how">{{ __('welcome.nav_how') }}</a>
                <a href="#faq">{{ __('welcome.nav_faq') }}</a>
            </nav>
            <div class="landing-nav-actions">
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
                        <a class="landing-button landing-button-primary" href="{{ route('register') }}">{{ __('welcome.hero_create_account') }} <span aria-hidden="true" style="margin-left: 8px">→</span></a>
                        <a class="landing-button landing-button-secondary" href="#how">{{ __('welcome.see_how_it_works') }}</a>
                    </div>
                    <div class="landing-hero-note"><span class="landing-hero-avatars" aria-hidden="true"><i>A</i><i>M</i><i>J</i></span><span>{{ __('welcome.hero_note') }}</span></div>
                </div>

                <div class="landing-preview-wrap" role="group" aria-label="{{ __('welcome.preview_aria') }}">
                    <div class="landing-preview">
                        <div class="landing-window-bar"><span class="landing-preview-brand"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 14c0-5.5 4.2-9 10-9 0 5.9-3.6 10-9 10" stroke-linecap="round"/><path d="M7 19c2.7-4.5 6.1-7.4 11-10" stroke-linecap="round"/></svg>ClearTogether</span><span class="landing-preview-people" aria-label="{{ __('welcome.preview_people') }}"><i>A</i><i>M</i></span><span class="landing-preview-share">{{ __('welcome.preview_share') }}</span><span class="landing-preview-more" aria-hidden="true">•••</span></div>
                        <div class="landing-record-heading"><span class="landing-record-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 11 9-7 9 7" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.5 10v10h13V10M9.5 20v-6h5v6" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span><strong>{{ __('welcome.preview_record_title') }}</strong><small>{{ __('welcome.preview_updated') }}</small></span></div>
                        <div class="landing-preview-cards">
                            <div class="landing-preview-card landing-preview-card-pay"><span>{{ __('welcome.preview_you_owe') }}</span><strong>$120.00</strong></div>
                            <div class="landing-preview-card landing-preview-card-receive"><span>{{ __('welcome.preview_they_owe') }}</span><strong>$80.00</strong></div>
                        </div>
                        <div class="landing-preview-tabs"><span class="is-active">{{ __('welcome.preview_transactions') }}</span><span>{{ __('welcome.preview_balances') }}</span><span>{{ __('welcome.preview_categories') }}</span><span class="landing-preview-add">+ {{ __('welcome.preview_add') }}</span></div>
                        <div class="landing-preview-list">
                            <div class="landing-preview-row"><span class="landing-transaction-icon mint">⌂</span><span class="landing-transaction-name"><b>{{ __('welcome.preview_rent') }}</b><small>{{ __('welcome.preview_rent_date') }}</small></span><b class="landing-transaction-amount">$1,200.00</b><small class="landing-transaction-status">{{ __('welcome.preview_you_paid') }}</small></div>
                            <div class="landing-preview-row"><span class="landing-transaction-icon peach">⌑</span><span class="landing-transaction-name"><b>{{ __('welcome.preview_groceries') }}</b><small>{{ __('welcome.preview_groceries_date') }}</small></span><b class="landing-transaction-amount">$86.40</b><small class="landing-transaction-status">{{ __('welcome.preview_they_paid') }}</small></div>
                            <div class="landing-preview-row"><span class="landing-transaction-icon butter">ϟ</span><span class="landing-transaction-name"><b>{{ __('welcome.preview_electricity') }}</b><small>{{ __('welcome.preview_electricity_date') }}</small></span><b class="landing-transaction-amount">$72.00</b><small class="landing-transaction-status">{{ __('welcome.preview_they_paid') }}</small></div>
                            <div class="landing-preview-row"><span class="landing-transaction-icon mint">·</span><span class="landing-transaction-name"><b>{{ __('welcome.preview_dinner') }}</b><small>{{ __('welcome.preview_dinner_date') }}</small></span><b class="landing-transaction-amount">$48.00</b><small class="landing-transaction-status">{{ __('welcome.preview_they_paid') }}</small></div>
                        </div>
                        <div class="landing-example">{{ __('welcome.preview_illustrative') }}</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="landing-trust-strip" aria-label="{{ __('welcome.trust_strip_aria') }}">
            <div class="landing-container landing-trust-grid">
                <div class="landing-trust-item"><span class="landing-trust-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3.5 19c.4-3.1 2.4-5 5.5-5s5.1 1.9 5.5 5M14 15c3.5-.7 6 1 6.5 4" stroke-linecap="round"/></svg></span><span><strong>{{ __('welcome.trust_visibility') }}</strong><span>{{ __('welcome.trust_visibility_desc') }}</span></span></div>
                <div class="landing-trust-item"><span class="landing-trust-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 2.9 8.5 7 10 4.1-1.5 7-5.5 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span><strong>{{ __('welcome.trust_manual') }}</strong><span>{{ __('welcome.trust_manual_desc') }}</span></span></div>
                <div class="landing-trust-item"><span class="landing-trust-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3" stroke-linecap="round"/></svg></span><span><strong>{{ __('welcome.trust_control') }}</strong><span>{{ __('welcome.trust_control_desc') }}</span></span></div>
            </div>
        </section>

        <section id="why" class="landing-section landing-section-soft">
            <div class="landing-container landing-problem-grid">
                <div>
                    <div class="landing-eyebrow">{{ __('welcome.why_eyebrow') }}</div>
                    <div class="landing-section-heading"><h2>{{ __('welcome.why_heading') }}</h2><p>{{ __('welcome.why_description') }}</p></div>
                    <div class="landing-fragment-list">
                        <div class="landing-fragment"><span class="landing-fragment-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v11H9l-5 4V5Z"/><path d="M8 9h8M8 12h5" stroke-linecap="round"/></svg></span><span class="landing-fragment-copy"><small>{{ __('welcome.why_fragment_scattered') }}</small><span>{{ __('welcome.why_fragment_scattered_desc') }}</span></span></div>
                        <div class="landing-fragment"><span class="landing-fragment-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h4" stroke-linecap="round"/></svg></span><span class="landing-fragment-copy"><small>{{ __('welcome.why_fragment_unclear') }}</small><span>{{ __('welcome.why_fragment_unclear_desc') }}</span></span></div>
                        <div class="landing-fragment"><span class="landing-fragment-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12h18M12 3v18" stroke-linecap="round"/><circle cx="12" cy="12" r="8"/></svg></span><span class="landing-fragment-copy"><small>{{ __('welcome.why_fragment_approaching') }}</small><span>{{ __('welcome.why_fragment_approaching_desc') }}</span></span></div>
                        <div class="landing-fragment"><span class="landing-fragment-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v14H4z"/><path d="m5 7 7 5 7-5" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span class="landing-fragment-copy"><small>{{ __('welcome.why_fragment_personal') }}</small><span>{{ __('welcome.why_fragment_personal_desc') }}</span></span></div>
                    </div>
                </div>
                <blockquote class="landing-quote" aria-label="{{ __('welcome.conversation_aria') }}"><div class="landing-chat-bubble landing-chat-first"><span class="landing-chat-avatar avatar-one" aria-hidden="true">J</span><span>{{ __('welcome.conversation_question') }}</span></div><div class="landing-chat-bubble landing-chat-second"><span>{{ __('welcome.conversation_reply') }}</span><span class="landing-chat-avatar avatar-two" aria-hidden="true">M</span></div><div class="landing-chat-bubble landing-chat-third"><span class="landing-chat-avatar avatar-one" aria-hidden="true">J</span><span>{{ __('welcome.conversation_follow_up') }}</span></div><div class="landing-chat-note">{{ __('welcome.conversation_note') }}<span aria-hidden="true">↗</span></div></blockquote>
            </div>
        </section>

        <section id="features" class="landing-section">
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
                <div class="landing-how-people" aria-hidden="true"><img src="{{ asset('images/shared-people.png') }}" alt="" loading="lazy"></div>
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

        <section id="faq" class="landing-section landing-section-soft">
            <div class="landing-container">
                <div class="landing-section-heading"><div class="landing-eyebrow">{{ __('welcome.faq_eyebrow') }}</div><h2>{{ __('welcome.faq_heading') }}</h2></div>
                <div class="landing-faq-grid">
                    <details class="landing-faq" open><summary><h3>{{ __('welcome.faq_details_question') }}</h3></summary><p>{{ __('welcome.faq_details_answer') }}</p></details>
                    <details class="landing-faq" open><summary><h3>{{ __('welcome.faq_share_question') }}</h3></summary><p>{{ __('welcome.faq_share_answer') }}</p></details>
                    <details class="landing-faq" open><summary><h3>{{ __('welcome.faq_types_question') }}</h3></summary><p>{{ __('welcome.faq_types_answer') }}</p></details>
                    <details class="landing-faq" open><summary><h3>{{ __('welcome.faq_payments_question') }}</h3></summary><p>{{ __('welcome.faq_payments_answer') }}</p></details>
                </div>
            </div>
        </section>

        <section class="landing-cta">
            <div class="landing-container landing-cta-inner"><div><h2>{{ __('welcome.cta_heading') }}</h2><p>{{ __('welcome.cta_description') }}</p></div><a class="landing-button landing-button-primary" href="{{ route('register') }}">{{ __('welcome.create_account') }} <span aria-hidden="true" style="margin-left: 8px">→</span></a></div>
        </section>
    </main>

    <footer class="landing-footer">
        <div class="landing-container landing-footer-inner">
            <a class="landing-brand" href="{{ route('home') }}" aria-label="{{ __('welcome.brand_home', ['app' => 'ClearTogether']) }}"><span class="landing-brand-mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 14c0-5.5 4.2-9 10-9 0 5.9-3.6 10-9 10" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 19c2.7-4.5 6.1-7.4 11-10M12 8c.2 2.3 1.4 3.6 3.5 4" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>ClearTogether</span></a>
            <div class="landing-footer-links"><a href="#features">{{ __('welcome.nav_features') }}</a><a href="#how">{{ __('welcome.footer_how') }}</a><a href="#faq">{{ __('welcome.nav_faq') }}</a><a href="{{ route('legal.privacy') }}">{{ __('welcome.footer_privacy') }}</a><a href="{{ route('legal.terms') }}">{{ __('welcome.footer_terms') }}</a></div>
            <div class="landing-language-switch" role="group" aria-label="{{ __('welcome.language_switch') }}">
                <a href="{{ route('locale.switch', ['locale' => 'en']) }}" lang="en" hreflang="en" class="{{ app()->getLocale() === 'en' ? 'is-active' : '' }}" @if (app()->getLocale() === 'en') aria-current="page" @endif>{{ __('welcome.language_english') }}</a>
                <a href="{{ route('locale.switch', ['locale' => 'ms']) }}" lang="ms" hreflang="ms" class="{{ app()->getLocale() === 'ms' ? 'is-active' : '' }}" @if (app()->getLocale() === 'ms') aria-current="page" @endif>{{ __('welcome.language_malay') }}</a>
            </div>
            <span>{{ __('welcome.footer_tagline') }}</span>
        </div>
    </footer>
</div>
</body>
</html>
