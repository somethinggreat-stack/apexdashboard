<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Status Report — {{ $endUser->full_name }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
               max-width: 900px; margin: 24px auto; padding: 0 20px; color:#1e293b; }
        .toolbar { display:flex; gap:8px; justify-content:flex-end; margin-bottom:18px; }
        .toolbar button, .toolbar a {
            font-size:13px; padding:8px 14px; border-radius:6px;
            background:#0ea5e9; color:white; border:0; cursor:pointer;
            text-decoration:none; font-weight:600;
        }
        .toolbar a.secondary { background:#e5e7eb; color:#374151; }
        @media print {
            .toolbar { display:none; }
            body { margin:0; padding:0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('admin.end-users.show', $endUser) }}" class="secondary">← Back to Client</a>
        <button onclick="window.print()">Print / Save PDF</button>
    </div>

    @include('partials.status-report', ['endUser' => $endUser])

    <script>
        // Auto-open the browser print dialog when the page loads.
        // VA clicks "Report" in the list, this tab opens, they hit Save-as-PDF.
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 400);
        });
    </script>
</body>
</html>
