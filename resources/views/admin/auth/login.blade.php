<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Admin Login - Credit Repair</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="auth-body">
<div class="auth-card">
    <div class="auth-header">
        <h1>VA Admin Login</h1>
        <p>Credit Repair Management System</p>
    </div>
    @if ($errors->any())
        <div class="alert alert-error">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif
    <form method="POST" action="{{ route('admin.login') }}">
        @csrf
        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>
        </div>
        <div class="form-group form-check">
            <label><input type="checkbox" name="remember"> Remember me</label>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
    </form>
    <div class="auth-footer">
        <span class="muted">Restricted access &mdash; staff only.</span>
    </div>
</div>
</body>
</html>
