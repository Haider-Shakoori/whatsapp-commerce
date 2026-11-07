@extends('layouts.app')
@section('title', 'Sign in')
@section('content')
<div class="card narrow">
    <h1>Sign in</h1>
    <p class="muted">Access your businesses and products.</p>
    <form method="post" action="{{ route('login.store') }}">
        @csrf
        <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></div>
        <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
        <button class="btn" type="submit">Sign in</button>
    </form>
</div>
@endsection
