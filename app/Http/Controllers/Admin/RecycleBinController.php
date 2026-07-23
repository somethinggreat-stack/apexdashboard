<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\EndUser;
use App\Support\RecycleBin;
use Illuminate\Support\Facades\Auth;

/**
 * The Recycle Bin. Deleted business owners and individually-deleted clients
 * land here and stay recoverable for RecycleBin::RETENTION_DAYS before they are
 * purged for good. Super admin only (mirrors business-owner delete access).
 */
class RecycleBinController extends Controller
{
    public function index()
    {
        // Self-clean on view so the bin stays honest even if cron isn't running.
        RecycleBin::purgeExpired();

        $ownerId = Auth::guard('admin')->user()->dataOwnerId();

        $owners = Client::onlyTrashed()
            ->forAdmin($ownerId)
            ->withCount(['endUsers' => fn ($q) => $q->withTrashed()->where('deleted_with_owner', true)])
            ->with('deletedBy')
            ->orderByDesc('deleted_at')
            ->get();

        // Individually-deleted clients only — the ones binned alongside an owner
        // are shown under that owner, not as loose rows.
        $clients = EndUser::onlyTrashed()
            ->where('deleted_with_owner', false)
            ->whereHas('client', fn ($q) => $q->where('admin_id', $ownerId))
            ->with(['client', 'deletedBy'])
            ->orderByDesc('deleted_at')
            ->get();

        return view($this->adminView('admin.recycle-bin.index'), [
            'owners'        => $owners,
            'clients'       => $clients,
            'retentionDays' => RecycleBin::RETENTION_DAYS,
        ]);
    }

    public function restoreClient(string $id)
    {
        $client = Client::onlyTrashed()->forAdmin($this->ownerId())->findOrFail($id);
        $client->restore();   // restored hook brings back its binned clients

        return back()->with('status', "Business owner {$client->business_name} restored.");
    }

    public function restoreEndUser(string $id)
    {
        $user = EndUser::onlyTrashed()
            ->where('deleted_with_owner', false)
            ->whereHas('client', fn ($q) => $q->where('admin_id', $this->ownerId()))
            ->findOrFail($id);
        $user->restore();

        return back()->with('status', "Client {$user->full_name} restored.");
    }

    public function forceClient(string $id)
    {
        $client = Client::onlyTrashed()->forAdmin($this->ownerId())->findOrFail($id);
        $name = $client->business_name;
        $client->forceDelete();   // rows + all files gone for good

        return back()->with('status', "Business owner {$name} permanently deleted.");
    }

    public function forceEndUser(string $id)
    {
        $user = EndUser::onlyTrashed()
            ->where('deleted_with_owner', false)
            ->whereHas('client', fn ($q) => $q->where('admin_id', $this->ownerId()))
            ->findOrFail($id);
        $name = $user->full_name;
        $user->forceDelete();

        return back()->with('status', "Client {$name} permanently deleted.");
    }

    private function ownerId(): int
    {
        return Auth::guard('admin')->user()->dataOwnerId();
    }
}
