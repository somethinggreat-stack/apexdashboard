{{-- Move to Inactive — asks for the reason (required; it's what the owner reads). --}}
<form method="POST" action="{{ route('admin.end-users.inactive', $eu->id) }}"
      onsubmit="var r = prompt('Move {{ addslashes($eu->full_name) }} to Inactive — why? (e.g. Not paid per Sheet, Old client — stopped service)'); if (!r || !r.trim()) return false; this.reason.value = r.trim();">
    @csrf
    <input type="hidden" name="reason" value="">
    <button class="pro-act inactive" title="Gone / not paying — removed from the EOD">Inactive</button>
</form>
