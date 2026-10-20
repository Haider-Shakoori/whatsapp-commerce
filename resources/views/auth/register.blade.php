@extends('layouts.app')
@section('title', 'Create a business')
@section('content')
<div class="card narrow">
    <h1>Create your business</h1>
    <p class="muted">One owner account and one business workspace. No WhatsApp number is connected at this stage.</p>
    <form action="{{ route('register.store') }}" method="post">
        @csrf
        <h2>Owner details</h2>
        <div class="field"><label for="name">Full name</label><input id="name" name="name" value="{{ old('name') }}" maxlength="120" required autocomplete="name"></div>
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"></div>
        <div class="field"><label for="password">Password (at least 12 characters)</label><input id="password" type="password" name="password" minlength="12" required autocomplete="new-password"></div>
        <div class="field"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
        <h2>Business details</h2>
        <div class="field"><label for="business_name">Business name</label><input id="business_name" name="business_name" value="{{ old('business_name') }}" required maxlength="160"></div>
        <div class="field"><label for="slug">Business URL identifier (letters, numbers, hyphens)</label><input id="slug" name="slug" value="{{ old('slug') }}" pattern="[A-Za-z0-9_-]+" required maxlength="100" placeholder="my-restaurant"></div>
        <div class="field"><label for="business_type">Business type</label>
            <select name="business_type" id="business_type" required>
                @foreach(['restaurant'=>'Restaurant / cafe','retail'=>'General retail','grocery'=>'Grocery / supermarket','fashion'=>'Clothing / fashion','electronics'=>'Electronics','general'=>'Other business'] as $value=>$label)
                    <option value="{{ $value }}" @selected(old('business_type', 'restaurant')===$value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid">
            <div class="field"><label for="currency">Currency (ISO code)</label><input id="currency" name="currency" value="{{ old('currency', 'USD') }}" pattern="[A-Z]{3}" maxlength="3" required></div>
            <div class="field"><label for="timezone">Timezone</label><input id="timezone" name="timezone" value="{{ old('timezone', 'UTC') }}" required placeholder="Asia/Kabul"></div>
        </div>
        <button class="btn" type="submit">Create workspace</button>
    </form>
</div>
@endsection
