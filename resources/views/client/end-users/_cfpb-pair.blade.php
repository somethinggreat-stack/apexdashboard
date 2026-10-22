{{-- One CFPB login (email + password) with Show/Copy, same behaviour as the admin profile. --}}
<div class="info-grid">
    <div>
        <label>CFPB Login Email</label>
        <div class="cfpb-cell">
            <span>{{ $email ?? '—' }}</span>
            @if ($email)
                <button type="button" class="btn btn-sm" data-copy="{{ $email }}" data-copy-msg="CFPB email copied">Copy</button>
            @endif
        </div>
    </div>
    <div>
        <label>CFPB Password</label>
        <div class="cfpb-cell">
            <span class="cfpb-mask" data-secret="{{ $password }}">@if ($password) ••••••••• @else — @endif</span>
            @if ($password)
                <button type="button" class="btn btn-sm" onclick="toggleCfpbPassword(this)">Show</button>
                <button type="button" class="btn btn-sm" data-copy="{{ $password }}" data-copy-msg="CFPB password copied">Copy</button>
            @endif
        </div>
    </div>
</div>
