@extends($adminLayout ?? 'layouts.admin-pro')

@section('title', 'Mailboxes')
@section('subtitle', 'Throwaway email addresses for CFPB one-time codes — create one, read the code in webmail, delete it when you are done.')

@section('content')

@if (! $configured)
    <div class="pro-panel" style="margin-bottom:16px; border-left:4px solid #f59e0b;">
        <div style="padding:16px 18px;">
            <strong>Mailbox creation is not switched on for this server yet.</strong>
            <p class="muted" style="margin:6px 0 0; font-size:13px;">
                The cPanel settings are missing from this server's <code>.env</code>
                (<code>CPANEL_HOST</code>, <code>CPANEL_USER</code>, <code>CPANEL_API_TOKEN</code>).
                Existing mailboxes are listed below, but nothing can be created or deleted until those are set.
            </p>
        </div>
    </div>
@endif

<div class="pro-panel" style="margin-bottom:16px;">
    <div class="pro-panel-head" style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <div class="pro-panel-title">
            <span class="pro-panel-chip" style="background:linear-gradient(140deg,#818cf8,#4338ca);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="M3 7l9 6 9-6"/></svg>
            </span>
            <h2>Mailboxes</h2>
            <span class="pro-panel-count" style="background:#e0e7ff; color:#3730a3;">
                {{ $mailboxes->total() }} active
                @if ($totalUsedMb > 0) · {{ $totalUsedMb }} MB used @endif
            </span>
        </div>
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <span class="muted" style="font-size:12.5px;">
                {{ $remaining }} of {{ $dailyLimit }} left today
            </span>
            <a class="btn btn-sm" href="{{ $webmailUrl }}" target="_blank" rel="noopener">Open Webmail ↗</a>
            @if ($configured && $remaining > 0)
                <button type="button" class="btn btn-primary btn-sm" data-mb-open>+ Create Mailbox</button>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div style="margin:0 18px 12px; padding:10px 12px; border-radius:8px; background:#dcfce7; color:#166534; font-size:13.5px;">
            {{ session('status') }}
        </div>
    @endif
    @error('mailbox')
        <div style="margin:0 18px 12px; padding:10px 12px; border-radius:8px; background:#fee2e2; color:#991b1b; font-size:13.5px;">
            {{ $message }}
        </div>
    @enderror

    <div class="table-scroll"><table class="data-table">
        <thead>
            <tr>
                <th>Client</th>
                <th>Email address</th>
                <th>Password</th>
                <th>Created</th>
                <th>Size</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($mailboxes as $mb)
            @php
                $u    = $usage[strtolower($mb->address)] ?? null;
                $used = $u['used_mb'] ?? null;
            @endphp
            <tr>
                <td>
                    @if ($mb->endUser)
                        <strong>{{ $mb->endUser->full_name }}</strong>
                    @else
                        <span class="muted">—</span>
                    @endif
                    @if ($mb->createdBy)
                        <div class="muted" style="font-size:12px;">by {{ $mb->createdBy->full_name }}</div>
                    @endif
                </td>

                <td class="no-link">
                    <code style="font-size:13px;">{{ $mb->address }}</code>
                    <button type="button" class="btn btn-sm" data-mb-copy="{{ $mb->address }}">Copy</button>
                </td>

                {{-- Hidden until asked for: the password should not be sitting on screen
                     while a VA shares their window. --}}
                <td class="no-link">
                    <code class="mb-pw" data-mb-pw style="font-size:13px;">••••••••••</code>
                    <span hidden data-mb-secret>{{ $mb->password }}</span>
                    <button type="button" class="btn btn-sm" data-mb-reveal>Show</button>
                    <button type="button" class="btn btn-sm" data-mb-copy-pw>Copy</button>
                </td>

                <td>{{ $mb->created_at?->format('M j, Y') }}</td>

                <td>
                    @if ($used !== null)
                        {{ $used }} MB <span class="muted">/ {{ $mb->quota_mb }}</span>
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>

                <td class="no-link">
                    <div class="u-actions">
                        <a class="btn btn-sm" href="{{ $webmailUrl }}" target="_blank" rel="noopener">Webmail</a>
                        @if ($configured)
                            <form method="POST" action="{{ route('admin.mailboxes.destroy', $mb->id) }}"
                                  data-confirm-delete
                                  data-confirm-message="Delete {{ $mb->address }}? Everything in it is destroyed on the mail server and cannot be recovered.">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="muted" style="padding:22px; text-align:center;">
                    No mailboxes yet. Create one for a client and the address is saved to their CFPB login automatically.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table></div>

    @if ($mailboxes->hasPages())
        <div style="padding:12px 18px;">{{ $mailboxes->links() }}</div>
    @endif
</div>

{{-- ---------------- Create ---------------- --}}
<div id="mbModal" hidden
     style="position:fixed; inset:0; z-index:1000; background:rgba(15,23,42,.55); display:flex; align-items:center; justify-content:center; padding:20px;">
    <div style="background:#fff; border-radius:14px; width:100%; max-width:480px; box-shadow:0 24px 60px rgba(0,0,0,.28);">
        <form method="POST" action="{{ route('admin.mailboxes.store') }}">
            @csrf
            <div style="padding:18px 20px 6px;">
                <h3 style="margin:0 0 4px;">Create a mailbox</h3>
                <p class="muted" style="margin:0 0 16px; font-size:13px;">
                    Created on <strong>{{ $domain }}</strong> with a {{ $quotaMb }} MB limit.
                    The address and password appear in the list straight away.
                </p>

                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px;">Client</label>
                <select name="end_user_id" style="width:100%; padding:9px 10px; border:1px solid #d7dbe7; border-radius:8px; margin-bottom:14px;">
                    <option value="">— none (a spare mailbox) —</option>
                    @foreach ($clients as $c)
                        <option value="{{ $c->id }}">
                            {{ $c->full_name }}@if ($c->client) · {{ $c->client->business_name }}@endif @if ($c->cfpb_email) (has {{ $c->cfpb_email }})@endif
                        </option>
                    @endforeach
                </select>

                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px;">
                    Mailbox name <span class="muted" style="font-weight:400;">— optional</span>
                </label>
                <input type="text" name="local_part" maxlength="60" placeholder="leave blank to use the client's name"
                       style="width:100%; padding:9px 10px; border:1px solid #d7dbe7; border-radius:8px;">
                <p class="muted" style="margin:6px 0 0; font-size:12px;">
                    Four digits are added on the end so two clients with the same name never clash.
                </p>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; padding:16px 20px 18px;">
                <button type="button" class="btn btn-sm" data-mb-close>Cancel</button>
                <button class="btn btn-primary btn-sm">Create Mailbox</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('mbModal');

    function open()  { if (modal) modal.hidden = false; }
    function close() { if (modal) modal.hidden = true; }

    // A flash that does not depend on the clipboard permission dialog landing.
    function flash(btn, word) {
        var was = btn.textContent;
        btn.textContent = word;
        setTimeout(function () { btn.textContent = was; }, 1200);
    }

    function copy(text, btn) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () { flash(btn, 'Copied'); },
                                                     function () { fallback(text, btn); });
        } else {
            fallback(text, btn);
        }
    }

    // Older WebView / http contexts have no async clipboard.
    function fallback(text, btn) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); flash(btn, 'Copied'); } catch (e) { flash(btn, 'Press Ctrl+C'); }
        ta.remove();
    }

    document.addEventListener('click', function (e) {
        var t = e.target;

        if (t.closest('[data-mb-open]'))  { open();  return; }
        if (t.closest('[data-mb-close]')) { close(); return; }

        var copyBtn = t.closest('[data-mb-copy]');
        if (copyBtn) { copy(copyBtn.getAttribute('data-mb-copy'), copyBtn); return; }

        var cell = t.closest('td');

        var reveal = t.closest('[data-mb-reveal]');
        if (reveal && cell) {
            var pw     = cell.querySelector('[data-mb-pw]');
            var secret = cell.querySelector('[data-mb-secret]');
            var shown  = reveal.textContent.trim() === 'Hide';
            pw.textContent = shown ? '••••••••••' : secret.textContent;
            reveal.textContent = shown ? 'Show' : 'Hide';
            return;
        }

        var copyPw = t.closest('[data-mb-copy-pw]');
        if (copyPw && cell) {
            copy(cell.querySelector('[data-mb-secret]').textContent, copyPw);
            return;
        }
    });

    // Click the backdrop, or press Escape, to close.
    if (modal) {
        modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
    }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
@endsection
