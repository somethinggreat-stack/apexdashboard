{{-- Referral / commission fields for the Add & Edit Business Owner forms.
     Pass $refClient (the client being edited) or omit it for a new one.
     $referrers is the list of commission-referrer business owners.
     "Referred by" is single-select (radios) with an explicit "Nobody" option, so
     the admin can never accidentally pick two or leave it ambiguous. --}}
@php
    $refClient = $refClient ?? null;
    $curRef    = old('referrer_id', $refClient->referrer_id ?? null);
    $isRef     = old('is_commission_referrer', $refClient->is_commission_referrer ?? false);
@endphp

<div class="ref-box">
    <div class="ref-box-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        Referral &amp; Commission
    </div>

    {{-- Is this BO a referrer? (toggle) --}}
    <label class="ref-toggle">
        <span class="ref-switch">
            <input type="checkbox" name="is_commission_referrer" value="1" {{ $isRef ? 'checked' : '' }}>
            <span class="ref-track"></span>
        </span>
        <span class="ref-toggle-txt">
            <span class="ref-toggle-title">This business owner is a commission referrer</span>
            <span class="ref-toggle-desc">Earns $5 for every client payment of the business owners they referred, and gets a Commissions tab in their own portal.</span>
        </span>
    </label>

    {{-- Who referred this BO? (single choice) --}}
    @if (!empty($referrers) && count($referrers))
        <div class="ref-refby">
            <div class="ref-refby-label">Who referred this business owner? <span class="ref-hint">Pick one — or “Nobody”.</span></div>
            <div class="ref-pills">
                <label class="ref-pill">
                    <input type="radio" name="referrer_id" value="" {{ empty($curRef) ? 'checked' : '' }}>
                    <span class="ref-pill-face"><span class="ref-dot"></span>Nobody</span>
                </label>
                @foreach ($referrers as $r)
                    @continue($refClient && $r->id === $refClient->id)
                    <label class="ref-pill">
                        <input type="radio" name="referrer_id" value="{{ $r->id }}" {{ (string) $curRef === (string) $r->id ? 'checked' : '' }}>
                        <span class="ref-pill-face"><span class="ref-dot"></span>{{ $r->business_name }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endif
</div>

@once
@push('head')
<style>
    .ref-box { margin: 8px 0; padding: 16px 18px; background: var(--surface-2); border: 1px solid var(--border); border-radius: 14px; }
    .ref-box-title { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: 14px; }
    .ref-box-title svg { width: 15px; height: 15px; color: #7c3aed; }

    /* toggle card */
    .ref-toggle { display: flex; align-items: flex-start; gap: 13px; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 13px 15px; cursor: pointer; transition: border-color .12s, box-shadow .12s; }
    .ref-toggle:hover { border-color: var(--muted); }
    .ref-switch { position: relative; flex: none; width: 46px; height: 27px; margin-top: 1px; }
    .ref-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
    .ref-track { position: absolute; inset: 0; background: var(--muted); border-radius: 999px; transition: background .18s; }
    .ref-track::before { content: ''; position: absolute; top: 3px; left: 3px; width: 21px; height: 21px; background: var(--surface); border-radius: 50%; box-shadow: 0 1px 3px rgba(15,23,42,.28); transition: transform .18s; }
    .ref-switch input:checked + .ref-track { background: linear-gradient(135deg,#34d399,#059669); }
    .ref-switch input:checked + .ref-track::before { transform: translateX(19px); }
    .ref-switch input:focus-visible + .ref-track { box-shadow: 0 0 0 3px rgba(5,150,105,.3); }
    .ref-toggle-txt { display: flex; flex-direction: column; gap: 2px; }
    .ref-toggle-title { font-weight: 700; font-size: 14px; color: var(--text); }
    .ref-toggle-desc { font-size: 12.5px; color: var(--muted); line-height: 1.4; }

    /* referred-by pills (single select) */
    .ref-refby { margin-top: 14px; }
    .ref-refby-label { font-weight: 700; font-size: 13.5px; color: var(--text); margin-bottom: 9px; }
    .ref-hint { font-weight: 400; color: var(--muted); }
    .ref-pills { display: flex; flex-wrap: wrap; gap: 9px; }
    .ref-pill { position: relative; cursor: pointer; margin: 0; }
    .ref-pill input { position: absolute; opacity: 0; width: 0; height: 0; }
    .ref-pill-face { display: inline-flex; align-items: center; gap: 9px; padding: 9px 15px 9px 12px; border: 1.5px solid var(--border); border-radius: 999px; background: var(--surface); font-weight: 600; font-size: 13.5px; color: var(--text-soft); transition: all .12s; }
    .ref-dot { width: 16px; height: 16px; border-radius: 50%; border: 2px solid var(--muted); flex: none; transition: all .12s; }
    .ref-pill:hover .ref-pill-face { border-color: #c7d2fe; }
    .ref-pill input:checked + .ref-pill-face { border-color: #4f46e5; background: var(--selected); color: var(--tint-indigo-fg); box-shadow: 0 0 0 3px rgba(79,70,229,.12); }
    .ref-pill input:checked + .ref-pill-face .ref-dot { border-color: #4f46e5; background: #4f46e5; box-shadow: inset 0 0 0 3px var(--surface); }
    .ref-pill input:focus-visible + .ref-pill-face { border-color: #4f46e5; }
</style>
@endpush
@endonce
