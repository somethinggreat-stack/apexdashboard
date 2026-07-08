<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>One more thing… - Apex Growth Solutions</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;
            color: #0f172a;
            background: #0b1220;
            background-image:
                radial-gradient(900px circle at 15% 15%, rgba(37,99,235,.28), transparent 45%),
                radial-gradient(800px circle at 85% 85%, rgba(56,189,248,.20), transparent 45%),
                linear-gradient(135deg, #0b1220 0%, #0f2140 55%, #0b1220 100%);
        }
        .shell { width: 100%; max-width: 460px; }
        .card {
            background: #fff; border-radius: 18px; padding: 40px 34px; text-align: center;
            box-shadow: 0 24px 60px rgba(0,0,0,.35);
        }
        .emoji { font-size: 44px; line-height: 1; margin-bottom: 14px; }
        .card h1 { font-size: 26px; font-weight: 800; color: #0f172a; margin-bottom: 6px; }
        .card .sub { color: #64748b; font-size: 13.5px; margin-bottom: 26px; }
        .btns { display: flex; gap: 12px; }
        .btn {
            flex: 1; padding: 14px; border: 0; border-radius: 12px; cursor: pointer;
            font-size: 15px; font-weight: 800; transition: transform .1s, box-shadow .15s, filter .15s;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn-yes { color: #fff; background: linear-gradient(135deg, #16a34a, #15803d); box-shadow: 0 10px 22px rgba(22,163,74,.35); }
        .btn-yes:hover { filter: brightness(1.05); }
        .btn-no { color: #b91c1c; background: #fff; border: 1px solid #fecaca; }
        .btn-no:hover { background: #fef2f2; }
        .foot { text-align: center; margin-top: 16px; }
        .foot small { color: #94a3b8; font-size: 12px; }
    </style>
</head>
<body>
    <div class="shell">
        <div class="card">
            <div class="emoji">😤🐔</div>
            <h1>Fuck, {{ $name }}?</h1>
            <p class="sub">Answer honestly. "No" and you're not getting in. 🐥</p>
            <form method="POST" action="{{ route('admin.login.confirm.decide') }}">
                @csrf
                <div class="btns">
                    <button type="submit" name="answer" value="yes" class="btn btn-yes">Yes, let me in</button>
                    <button type="submit" name="answer" value="no" class="btn btn-no">No</button>
                </div>
            </form>
        </div>
        <div class="foot"><small>Apex Growth Solutions · VA access</small></div>
    </div>
</body>
</html>
