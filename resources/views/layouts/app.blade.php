<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WhatsApp Commerce') · WhatsApp Commerce</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header><nav class="nav" aria-label="Main">
    <a class="logo" href="{{ route('home') }}">Commerce<span style="color:#0f8a6b">Chat</span></a>
    <div class="nav-right">
        @auth
            <a href="{{ route('dashboard') }}">My businesses</a>
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
        <div class="errors" role="alert"><strong>Please fix these errors:</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('content')
</main>
</body></html>
