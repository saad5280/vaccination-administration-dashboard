<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Vaccination Administration') · {{ config('app.name', 'ImmuniCare') }}</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
    @livewireStyles
</head>
<body>
<div class="app-shell">
    <button class="sidebar-backdrop" type="button" aria-label="Close navigation" data-sidebar-close></button>
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}" wire:navigate><span class="brand-mark"><img src="{{ asset('images/mother-child-vaccination-shield.png') }}" alt=""></span><span>ImmuniCare<small>VACCINATION ADMIN</small></span></a>
        <div class="nav-label">WORKSPACE</div>
        <nav class="main-nav" id="primary-navigation">
            @can('dashboard.view')<a href="{{ route('dashboard') }}" wire:navigate class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><span>▦</span> Overview</a>@endcan
            @can('users.manage')<a href="{{ route('users.index') }}" wire:navigate class="{{ request()->routeIs('users.*') ? 'active' : '' }}"><span>♙</span> User management</a>@endcan
            @can('roles.manage')<a href="{{ route('roles.index') }}" wire:navigate class="{{ request()->routeIs('roles.*') ? 'active' : '' }}"><span>◈</span> Roles & permissions</a>@endcan
            @can('children.view')<a href="{{ route('children.index') }}" wire:navigate class="{{ request()->routeIs('children.*') ? 'active' : '' }}"><span>♡</span> Children</a><a href="{{ route('guardians.index') }}" wire:navigate class="{{ request()->routeIs('guardians.*') ? 'active' : '' }}"><span>♧</span> Guardians</a>@endcan
            @can('due-register.view')<a href="{{ route('due-register.index') }}" wire:navigate class="{{ request()->routeIs('due-register.*') ? 'active' : '' }}"><span>◷</span> Due register</a>@endcan
            @can('reports.view')<a href="{{ route('reports.index') }}" wire:navigate class="{{ request()->routeIs('reports.*') ? 'active' : '' }}"><span>▧</span> Reports</a>@endcan
            @can('audit.view')<a href="{{ route('audit.index') }}" wire:navigate class="{{ request()->routeIs('audit.*') ? 'active' : '' }}"><span>≋</span> Audit log</a>@endcan
            @can('appointments.manage')<a href="{{ route('appointments.index') }}" wire:navigate class="{{ request()->routeIs('appointments.*') ? 'active' : '' }}"><span>▣</span> Appointments</a>@endcan
            @can('reminders.manage')<a href="{{ route('reminders.index') }}" wire:navigate class="{{ request()->routeIs('reminders.*') ? 'active' : '' }}"><span>✉</span> Reminder queue</a><a href="{{ route('follow-ups.index') }}" wire:navigate class="{{ request()->routeIs('follow-ups.*') ? 'active' : '' }}"><span>↻</span> Follow-ups</a>@endcan
            @can('inventory.manage')<a href="{{ route('inventory.index') }}" wire:navigate class="{{ request()->routeIs('inventory.*') ? 'active' : '' }}"><span>▤</span> Inventory</a>@endcan
            @can('settings.manage')<a href="{{ route('settings.index') }}" wire:navigate class="{{ request()->routeIs('settings.*') ? 'active' : '' }}"><span>⚙</span> System settings</a><a href="{{ route('centres.index') }}" wire:navigate class="{{ request()->routeIs('centres.*') ? 'active' : '' }}"><span>⌂</span> Centres</a>@endcan
            @can('catalogue.view')<a href="{{ route('vaccines.index') }}" wire:navigate class="{{ request()->routeIs('vaccines.*') ? 'active' : '' }}"><span>✚</span> Vaccine catalogue</a>@endcan
            @can('schedules.view')<a href="{{ route('schedules.index') }}" wire:navigate class="{{ request()->routeIs('schedules.*') ? 'active' : '' }}"><span>◷</span> Immunization schedules</a>@endcan
        </nav>
        <div class="sidebar-foot"><span class="online-dot"></span> Secure clinical workspace</div>
    </aside>
    <main class="main-panel">
        <header class="topbar">
            <button class="nav-toggle" type="button" aria-label="Open navigation" aria-expanded="false" aria-controls="primary-navigation" data-sidebar-toggle><span></span><span></span><span></span></button>
            <div class="topbar-caption">Vaccination services <span>/</span> @yield('crumb', 'Overview')</div>
            <div class="account-menu"><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->roles->pluck('label')->join(', ') }}</small></div><form method="POST" action="{{ route('logout') }}">@csrf<button class="link-button" type="submit">Sign out</button></form></div>
        </header>
        <section class="content-area">
            @if (session('status'))<div class="notice success">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="notice danger"><strong>Please review the form.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </section>
    </main>
</div>
<script>
    if (!document.documentElement.dataset.navigationScriptReady) {
        document.documentElement.dataset.navigationScriptReady = 'true';

        const setSidebarOpen = (isOpen) => {
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggle = document.querySelector('[data-sidebar-toggle]');

            sidebar?.classList.toggle('is-open', isOpen);
            sidebarToggle?.setAttribute('aria-expanded', String(isOpen));
            document.body.classList.toggle('navigation-open', isOpen);
        };

        document.addEventListener('click', (event) => {
            if (event.target.closest('[data-sidebar-toggle]')) {
                const isOpen = document.querySelector('.sidebar')?.classList.contains('is-open') ?? false;
                setSidebarOpen(!isOpen);
            } else if (event.target.closest('[data-sidebar-close]') || event.target.closest('.main-nav a')) {
                setSidebarOpen(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setSidebarOpen(false);
            }
        });
    }
</script>
@livewireScripts
</body>
</html>
