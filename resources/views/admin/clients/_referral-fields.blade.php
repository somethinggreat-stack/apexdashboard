{{-- Referral / commission fields for the Add & Edit Business Owner forms.
     Pass $refClient (the client being edited) or omit it for a new one.
     $referrers is the list of commission-referrer business owners. --}}
@php $refClient = $refClient ?? null; @endphp

<div class="form-group">
    <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer; font-weight:600;">
        <input type="checkbox" name="is_commission_referrer" value="1" style="width:18px; height:18px; margin-top:2px;"
               {{ old('is_commission_referrer', $refClient->is_commission_referrer ?? false) ? 'checked' : '' }}>
        <span>Commission referrer
            <span class="muted" style="font-weight:400;">— earns $5 for each client payment of the business owners they referred, and gets a Commissions tab in their portal.</span>
        </span>
    </label>
</div>

@if (!empty($referrers) && count($referrers))
    @php $curRef = old('referrer_id', $refClient->referrer_id ?? null); @endphp
    <div class="form-group">
        <div style="font-weight:600; margin-bottom:8px;">Referred by
            <span class="muted" style="font-weight:400;">— who referred this business owner? (pick one)</span>
        </div>
        <div style="display:flex; flex-direction:column; gap:8px;">
            @foreach ($referrers as $r)
                @continue($refClient && $r->id === $refClient->id)
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" class="ref-check" name="referrer_id" value="{{ $r->id }}" style="width:18px; height:18px;"
                           {{ (string) $curRef === (string) $r->id ? 'checked' : '' }}>
                    <span>Referred by {{ $r->business_name }}</span>
                </label>
            @endforeach
        </div>
    </div>
@endif
