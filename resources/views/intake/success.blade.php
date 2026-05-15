<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Thank You</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Geist', -apple-system, BlinkMacSystemFont, sans-serif;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    color: #0F2043;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 32px 16px;
}
.card { background: #fff; max-width: 520px; width: 100%; padding: 56px 40px; border-radius: 16px; text-align: center; box-shadow: 0 20px 60px rgba(15,32,67,0.08); }
.check {
    width: 72px; height: 72px;
    border-radius: 50%;
    background: linear-gradient(135deg, #16A34A, #15803D);
    color: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 36px;
    margin-bottom: 22px;
    box-shadow: 0 14px 30px rgba(22,163,74,0.3);
}
h1 { font-size: 24px; font-weight: 600; margin-bottom: 12px; letter-spacing: -0.01em; }
p { font-size: 15px; color: #475569; line-height: 1.65; margin-bottom: 8px; }
.brand { margin-top: 24px; padding-top: 18px; border-top: 1px solid #E2E8F0; font-size: 14px; font-weight: 500; color: #1A6FC4; }
.logo { max-height: 60px; max-width: 220px; margin-bottom: 10px; }
</style>
</head>
<body>
<div class="card">
    <div class="check">&check;</div>
    <h1>Submission received.</h1>
    <p>Your intake form has been submitted securely. The team will review your documents and reach out within one business day.</p>
    <p style="margin-top:14px;">You can safely close this page.</p>
    <div class="brand">
        @if ($logoUrl = $client->intakeLogoUrl())
            <img src="{{ $logoUrl }}" alt="" class="logo">
        @endif
        <div>{{ $client->intakeDisplayName() }}</div>
    </div>
</div>
</body>
</html>
