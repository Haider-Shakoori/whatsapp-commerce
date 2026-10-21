<!doctype html>
@php
    $workspace = auth()->check() && (request()->routeIs('platform.*') || request()->routeIs('merchant.*') || request()->routeIs('dashboard'));
    $platformArea = $workspace && request()->routeIs('platform.*');
    $merchant = request()->route('merchant');
    $merchantArea = $workspace && request()->routeIs('merchant.*') && $merchant instanceof \App\Models\Merchant;
    $canManage = $merchantArea && auth()->user()->merchants()->whereKey($merchant->id)->wherePivotIn('role', ['owner', 'manager'])->exists();
    $platformAdmin = auth()->check() && auth()->user()->platformAdministrator()->exists();
    $navigation = [];
    if ($platformArea) {
        $navigation = [
            'Platform' => [
                ['label' => 'Overview', 'route' => 'platform.index', 'icon' => 'overview'],
                ['label' => 'Merchants', 'route' => 'platform.merchants.index', 'icon' => 'users'],
                ['label' => 'Subscriptions', 'route' => 'platform.subscriptions.index', 'icon' => 'layers'],
            ],
            'Administration' => [
                ['label' => 'Platform settings', 'route' => 'platform.settings.index', 'icon' => 'settings'],
                ['label' => 'Audit trail', 'route' => 'platform.audit.index', 'icon' => 'activity'],
            ],
        ];
    } elseif ($merchantArea) {
        $navigation = [
            'Commerce' => [
                ['label' => 'Product catalog', 'route' => 'merchant.products.index', 'icon' => 'package'],
                ['label' => 'Orders', 'route' => 'merchant.orders.index', 'icon' => 'bag'],
                ['label' => 'Team inbox', 'route' => 'merchant.inbox.index', 'icon' => 'message'],
                ['label' => 'Customers', 'route' => 'merchant.customers.index', 'icon' => 'users'],
            ],
            'Business tools' => array_values(array_filter([
                $canManage ? ['label' => 'WhatsApp channels', 'route' => 'merchant.channels.index', 'icon' => 'link'] : null,
                $canManage ? ['label' => 'Automation', 'route' => 'merchant.automation.index', 'icon' => 'sparkles'] : null,
                $canManage ? ['label' => 'Payment settings', 'route' => 'merchant.payments.settings', 'icon' => 'wallet'] : null,
                ['label' => 'Subscription & usage', 'route' => 'merchant.subscription.index', 'icon' => 'layers'],
            ])),
        ];
    } elseif ($workspace) {
        $navigation = ['Workspace' => [['label' => 'My businesses', 'route' => 'dashboard', 'icon' => 'grid']]];
        if ($platformAdmin) {
            $navigation['Administration'] = [['label' => 'Platform dashboard', 'route' => 'platform.index', 'icon' => 'overview']];
        }
    }
    $workspaceTitle = $platformArea ? 'Platform administration' : ($merchantArea ? $merchant->name : 'Your workspaces');
@endphp
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WhatsApp Commerce') · WhatsApp Commerce</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/catalog.css') }}">
    @if($workspace)
        <link rel="stylesheet" href="{{ asset('css/workspace.css') }}?v=13">
        <script>
            try {
                document.documentElement.dataset.theme = localStorage.getItem('commerce-theme') === 'dark' ? 'dark' : 'light';
            } catch (_) { document.documentElement.dataset.theme = 'light'; }
        </script>
        <script src="{{ asset('js/workspace.js') }}?v=13" defer></script>
    @endif
</head>
<body @class(['workspace-mode' => $workspace])>
@if($workspace)
    <svg aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden" xmlns="http://www.w3.org/2000/svg">
        <symbol id="ws-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></symbol>
        <symbol id="ws-overview" viewBox="0 0 24 24"><path d="M3 12 12 4l9 8v8a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 21v-7h6v7"/></symbol>
        <symbol id="ws-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2zM16 5a3 3 0 0 1 0 6M17 15a5 5 0 0 1 4 5"/></symbol>
        <symbol id="ws-layers" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5zM3 12l9 5 9-5M3 16l9 5 9-5"/></symbol>
        <symbol id="ws-settings" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="m10 2-.5 2.3-2.3 1-2-.9-2 3.5 1.6 1.5-.1 2.6-1.7 1.4 2 3.5 2.2-1 2.3 1 .5 2.6h4l.5-2.6 2.3-1 2.2 1 2-3.5-1.7-1.4-.1-2.6 1.6-1.5-2-3.5-2 .9-2.3-1L14 2z"/></symbol>
        <symbol id="ws-activity" viewBox="0 0 24 24"><path d="M3 12h4l3-7 4 14 3-7h4"/></symbol>
        <symbol id="ws-package" viewBox="0 0 24 24"><path d="m12 2 9 5v10l-9 5-9-5V7zM3 7l9 5 9-5M12 12v10M7.5 4.5l9 5"/></symbol>
        <symbol id="ws-bag" viewBox="0 0 24 24"><path d="M4 8h16l-1 13H5zM9 9V6a3 3 0 0 1 6 0v3"/></symbol>
        <symbol id="ws-message" viewBox="0 0 24 24"><path d="M20 11.5a8.5 8.5 0 0 1-8.5 8.5 9 9 0 0 1-4-.9L3 21l1.9-4.5A8.5 8.5 0 1 1 20 11.5z"/><path d="M7.5 11.5h9M7.5 15h5"/></symbol>
        <symbol id="ws-link" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7 .5l3-3a5 5 0 0 0-7-7l-1.8 1.8M14 11a5 5 0 0 0-7-.5l-3 3a5 5 0 0 0 7 7l1.8-1.8"/></symbol>
        <symbol id="ws-sparkles" viewBox="0 0 24 24"><path d="m12 2 2.5 7.5L22 12l-7.5 2.5L12 22l-2.5-7.5L2 12l7.5-2.5z"/></symbol>
        <symbol id="ws-wallet" viewBox="0 0 24 24"><path d="M4 5h15a2 2 0 0 1 2 2v12H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z"/><path d="M2 9h19M16 14h3"/></symbol>
        <symbol id="ws-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>
        <symbol id="ws-close" viewBox="0 0 24 24"><path d="m5 5 14 14M19 5 5 19"/></symbol>
        <symbol id="ws-moon" viewBox="0 0 24 24"><path d="M20 15.5A8 8 0 0 1 8.5 4 8.5 8.5 0 1 0 20 15.5z"/></symbol>
        <symbol id="ws-arrow" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></symbol>
    </svg>
    <div class="ws-backdrop" data-sidebar-close aria-hidden="true"></div>
    <div class="ws-shell">
        <aside id="workspace-sidebar" class="ws-sidebar" aria-label="Workspace navigation">
            <div class="ws-brand">
                <a href="{{ route('dashboard') }}" class="ws-brand-link">
                    <span class="ws-brand-mark">C</span>
                    <span class="ws-brand-text"><strong>CommerceChat</strong><small>WhatsApp Commerce</small></span>
                </a>
                <button type="button" class="ws-icon-button ws-mobile-close" data-sidebar-close aria-label="Close navigation"><svg class="ws-icon"><use href="#ws-close"/></svg></button>
            </div>
            <div class="ws-switcher">
                <span class="ws-switcher-label">{{ $platformArea ? 'PLATFORM CONSOLE' : 'CURRENT WORKSPACE' }}</span>
                <strong>{{ $workspaceTitle }}</strong>
                @if($merchantArea)
                    <span class="ws-switcher-meta">{{ strtoupper($merchant->currency) }} · {{ ucfirst($merchant->business_type) }}</span>
                @elseif($platformArea)
                    <span class="ws-switcher-meta">Global administration</span>
                @else
                    <span class="ws-switcher-meta">Business management</span>
                @endif
            </div>
            <nav class="ws-navigation" aria-label="{{ $platformArea ? 'Platform' : 'Merchant' }} sections">
                @foreach($navigation as $group => $items)
                    @if(count($items))
                        <div class="ws-nav-group">
                            <p class="ws-nav-heading">{{ $group }}</p>
                            @foreach($items as $item)
                                @php
                                    $active = request()->routeIs($item['route']) || (! in_array($item['route'], ['platform.index', 'dashboard'], true) && str_ends_with($item['route'], '.index') && request()->routeIs(str_replace('.index', '.*', $item['route'])));
                                    $destination = route($item['route'], $merchantArea ? $merchant : []);
                                @endphp
                                <a href="{{ $destination }}" @class(['ws-nav-link', 'is-active' => $active]) @if($active) aria-current="page" @endif>
                                    <svg class="ws-icon" aria-hidden="true"><use href="#ws-{{ $item['icon'] }}"/></svg>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </nav>
            <div class="ws-sidebar-bottom">
                @if($merchantArea)
                    <a href="{{ route('dashboard') }}" class="ws-secondary-link">Switch business <svg class="ws-icon" aria-hidden="true"><use href="#ws-arrow"/></svg></a>
                @elseif($platformArea)
                    <a href="{{ route('dashboard') }}" class="ws-secondary-link">My workspaces <svg class="ws-icon" aria-hidden="true"><use href="#ws-arrow"/></svg></a>
                @endif
                <span class="ws-environment"><span class="ws-status-dot"></span> Commerce workspace</span>
            </div>
        </aside>
        <div class="ws-main-column">
            <header class="ws-header">
                <div class="ws-header-leading">
                    <button class="ws-icon-button ws-menu-button" type="button" data-sidebar-open aria-controls="workspace-sidebar" aria-expanded="false" aria-label="Open navigation"><svg class="ws-icon"><use href="#ws-menu"/></svg></button>
                    <div><span class="ws-header-kicker">{{ $platformArea ? 'Platform' : 'Commerce' }}</span><strong>{{ $workspaceTitle }}</strong></div>
                </div>
                <div class="ws-header-actions">
                    @if($platformAdmin && !$platformArea)
                        <a href="{{ route('platform.index') }}" class="ws-header-link">Platform console</a>
                    @endif
                    <button class="ws-icon-button" type="button" data-theme-toggle aria-label="Switch color theme" title="Switch color theme"><svg class="ws-icon"><use href="#ws-moon"/></svg></button>
                    <span class="ws-user-avatar" aria-hidden="true">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="ws-user-name">{{ auth()->user()->name }}</span>
                    <form method="post" action="{{ route('logout') }}">@csrf<button class="ws-logout" type="submit">Sign out</button></form>
                </div>
            </header>
            <main class="ws-content" id="main-content">
                @if(session('status'))<div class="alert" role="status">{{ session('status') }}</div>@endif
                @if($errors->any())
                    <div class="errors" role="alert"><strong>Please fix these errors:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                @yield('content')
            </main>
            <footer class="ws-footer"><span>CommerceChat · Workspace</span><span>Secure merchant-scoped commerce</span></footer>
        </div>
    </div>
@else
    <header class="site-header"><nav class="nav" aria-label="Main">
        <a class="logo" href="{{ route('home') }}">Commerce<span style="color:#0f8a6b">Chat</span></a>
        <div class="nav-right">
            @auth
                <a href="{{ route('dashboard') }}">My businesses</a>
                @if(auth()->user()->platformAdministrator()->exists())<a href="{{ route('platform.index') }}">Platform</a>@endif
                <span class="muted">{{ auth()->user()->name }}</span>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-light" type="submit">Sign out</button></form>
            @else
                <a href="{{ route('login') }}">Sign in</a>
                <a class="btn" href="{{ route('register') }}">Create business</a>
            @endauth
        </div>
    </nav></header>
    <main>
        @if(session('status'))<div class="alert" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())
            <div class="errors" role="alert"><strong>Please fix these errors:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </main>
@endif
</body>
</html>
