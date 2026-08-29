<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use App\Models\NegativeItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Manage a client's negative items (results tracking). Every action is scoped to
 * the VA's own org AND to a business owner that has results tracking enabled —
 * so the feature is invisible everywhere except the enabled owner (Clinecea).
 */
class NegativeItemController extends Controller
{
    /** Add one item to a client. */
    public function store(Request $request)
    {
        $endUser = $this->authorizedEndUser($request->input('end_user_id'));

        $data = $this->validateItem($request);
        $endUser->negativeItems()->create([
            'name'                => $data['name'],
            'detail'              => $data['detail'] ?? null,
            'category'            => $data['category'],
            'goal'                => $data['goal'],
            'bureau'              => $data['bureau'] ?? null,
            'status'              => 'reporting',
            'opened_on'           => now()->toDateString(),
            'created_by_admin_id' => Auth::guard('admin')->id(),
        ]);

        return back()->with('confirm', 'Item added.');
    }

    /** Edit an item's details. */
    public function update(Request $request, int $id)
    {
        $item = $this->authorizedItem($id);
        $data = $this->validateItem($request);

        $item->update([
            'name'     => $data['name'],
            'detail'   => $data['detail'] ?? null,
            'category' => $data['category'],
            'goal'     => $data['goal'],
            'bureau'   => $data['bureau'] ?? null,
        ]);

        return back()->with('confirm', 'Item updated.');
    }

    /** Mark an item resolved — deleted (delete-goal) or updated to positive (update-goal). */
    public function resolve(Request $request, int $id)
    {
        $item = $this->authorizedItem($id);

        $validated = $request->validate([
            'resolved_on'    => 'nullable|date',
            'resolved_round' => 'nullable|integer|min:1|max:15',
        ]);

        $date  = !empty($validated['resolved_on']) ? Carbon::parse($validated['resolved_on']) : now();
        $round = $validated['resolved_round'] ?? $item->endUser->current_round;

        $item->update([
            'status'         => $item->goal === 'update' ? 'updated' : 'deleted',
            'resolved_at'    => $date->toDateString(),
            'resolved_round' => $round,
        ]);

        \App\Models\ClientEvent::log($item->endUser, 'result', "Marked {$item->name} ({$item->bureauLabel()}) as " . $item->statusLabel());

        return back()->with('confirm', 'Marked ' . $item->statusLabel() . '.');
    }

    /** Put a resolved item back to reporting (mistake / it came back). */
    public function reopen(int $id)
    {
        $item = $this->authorizedItem($id);
        $item->update(['status' => 'reporting', 'resolved_at' => null, 'resolved_round' => null]);

        \App\Models\ClientEvent::log($item->endUser, 'result', "Reopened {$item->name} ({$item->bureauLabel()}) — back to reporting");

        return back()->with('confirm', 'Item set back to reporting.');
    }

    public function destroy(int $id)
    {
        $item = $this->authorizedItem($id);
        \App\Models\ClientEvent::log($item->endUser, 'result', "Removed result item {$item->name} ({$item->bureauLabel()})");
        $item->delete();

        return back()->with('confirm', 'Item removed.');
    }

    private function validateItem(Request $request): array
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'detail'   => 'nullable|string|max:255',
            'category' => 'required|in:' . implode(',', array_keys(NegativeItem::CATEGORIES)),
            'goal'     => 'required|in:' . implode(',', array_keys(NegativeItem::GOALS)),
            'bureau'   => 'required|in:' . implode(',', array_keys(NegativeItem::BUREAUS)),
        ]);

        // Only a Negative Account can be "updated to positive"; everything else
        // (inquiry, bankruptcy, personal information) can only be deleted.
        $data['goal']   = NegativeItem::goalForCategory($data['category'], $data['goal']);
        // Detail = account number / inquiry date / bankruptcy ref; none for personal info.
        $data['detail'] = NegativeItem::detailForCategory($data['category'], $data['detail'] ?? null);

        return $data;
    }

    /** Fetch an end user, or 404 unless it's in this org and its owner has results tracking. */
    private function authorizedEndUser($id): EndUser
    {
        $ownerId = Auth::guard('admin')->user()->dataOwnerId();

        return EndUser::whereKey($id)
            ->whereHas('client', fn ($q) => $q->where('admin_id', $ownerId)->where('results_tracking', true))
            ->firstOrFail();
    }

    private function authorizedItem(int $id): NegativeItem
    {
        $ownerId = Auth::guard('admin')->user()->dataOwnerId();

        return NegativeItem::whereKey($id)
            ->whereHas('endUser.client', fn ($q) => $q->where('admin_id', $ownerId)->where('results_tracking', true))
            ->with('endUser')
            ->firstOrFail();
    }
}
