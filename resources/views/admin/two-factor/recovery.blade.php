@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'Your recovery codes')

@section('content')
<div class="tf-wrap">
    <div class="card tf-card">
        <h2 class="tf-h">Save your recovery codes</h2>
        <p class="tf-sub"><strong>This is the only time they are shown.</strong> If you lose your phone, each code lets you sign in once. Store them somewhere safe, and not only on the phone you just set up.</p>

        <div class="tf-codes" id="codes">
            @foreach ($codes as $c)
                <code>{{ $c }}</code>
            @endforeach
        </div>

        <div class="tf-actions">
            <button type="button" class="btn" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('codes').innerText.trim().split(/\s+/).join('\n')).then(function(){ this.textContent='Copied'; }.bind(this))">Copy</button>
            <button type="button" class="btn" onclick="window.print()">Print</button>
            <a href="{{ route('admin.two-factor.manage') }}" class="btn btn-primary">I've saved them, continue</a>
        </div>
    </div>
</div>

@push('head')
<style>
    .tf-wrap { max-width:600px; margin:10px auto; }
    .tf-card { padding:28px 30px; }
    .tf-h { margin:0 0 6px; font-size:21px; }
    .tf-sub { margin:0 0 18px; color:var(--pro-muted,#64748b); font-size:14px; line-height:1.55; }
    .tf-codes { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; padding:16px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:14px; }
    .tf-codes code { font-size:16px; letter-spacing:.06em; padding:8px 10px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; text-align:center; color:#0f172a; }
    .tf-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:20px; }
    @media print { .tf-actions, nav, header, .sidebar { display:none !important; } }
</style>
@endpush
@endsection
