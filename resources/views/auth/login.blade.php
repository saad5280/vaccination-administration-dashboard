<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ config('app.name', 'ImmuniCare') }}</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
</head>
<body class="auth-page">
<div class="auth-aside"><a class="brand light" href="{{ route('login') }}" wire:navigate><span class="brand-mark"><img src="{{ asset('images/mother-child-vaccination-shield.png') }}" alt=""></span><span>ImmuniCare<small>VACCINATION ADMIN</small></span></a><div class="auth-message"><span class="eyebrow">CARE, COORDINATED</span><h1>Every dose.<br>Every child.<br>In one place.</h1><p>A secure workspace for vaccination teams to coordinate records, appointments and follow-up.</p></div><div class="auth-aside-foot">For authorized clinic and programme personnel</div></div>
<main class="auth-main"><div class="auth-card"><div class="auth-icon">↗</div><p class="eyebrow">WELCOME BACK</p><h2>Sign in to your workspace</h2><p class="muted">Use the account provided by your administrator.</p>
    @if (session('status'))<div class="notice success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="notice danger">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('login.store') }}" class="form-stack">@csrf
        <label for="email">Work email</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        <div class="label-row"><label for="password">Password</label><a href="{{ route('password.request') }}" wire:navigate>Forgot password?</a></div><input id="password" name="password" type="password" autocomplete="current-password" required>
        <label class="check-line"><input name="remember" type="checkbox" value="1"> Keep me signed in</label>
        <button class="button primary full" type="submit">Sign in <span>→</span></button>
    </form><p class="privacy-note">Your account activity is protected and audited.</p></div><div class="auth-mobile-footer">Authorized personnel only · Secure access</div></main>
</body>
</html>
