<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Admin\EndUserController;
use App\Http\Controllers\Controller;
use App\Models\EndUser;
use App\Services\Jarvis\Mutation;
use Illuminate\Http\Request;

/**
 * JARVIS write endpoints — slice 1: buckets and holds.
 *
 * Every action here is a **proxy**: it calls the single-purpose controller method
 * the dashboard button calls. Those methods cannot write a personal field no matter
 * what they are sent, which is what makes proxying them safe. (Contrast the fields
 * whose only write path is the profile form handler — those need a narrow write and
 * are NOT in this slice. See docs/JARVIS_WRITE_MANIFEST.md.)
 *
 * The proxied method's own response is discarded: it returns a redirect meant for a
 * browser. What matters is that it ran, with its validation, scoping and model
 * events intact.
 */
class JarvisWriteController extends Controller
{
    public function __construct(private Mutation $mutation)
    {
    }

    public function toDone(Request $r, int $id)
    {
        return $this->proxy($r, 'clients.to-done', $id, fn ($c) => $c->moveToDone((string) $id));
    }

    public function toErrors(Request $r, int $id)
    {
        return $this->proxy($r, 'clients.to-errors', $id, fn ($c) => $c->moveToErrors($r, (string) $id));
    }

    public function toRoundError(Request $r, int $id)
    {
        return $this->proxy($r, 'clients.to-round-error', $id, fn ($c) => $c->moveToRoundError($r, (string) $id));
    }

    public function resolveRoundError(Request $r, int $id)
    {
        return $this->proxy($r, 'clients.resolve-round-error', $id, fn ($c) => $c->resolveRoundError((string) $id));
    }

    public function toNewClients(Request $r, int $id)
    {
        return $this->proxy($r, 'clients.to-new-clients', $id, fn ($c) => $c->moveToNewClients((string) $id));
    }

    public function hold(Request $r, int $id)
    {
        return $this->proxy($r, 'clients.hold', $id, fn ($c) => $c->hold($r, (string) $id));
    }

    public function resume(Request $r, int $id)
    {
        return $this->proxy($r, 'clients.resume', $id, fn ($c) => $c->resume((string) $id));
    }

    /**
     * The approval flow is Clinecea-only: these three go through
     * resultsScopedEndUser(), which requires clients.results_tracking. For any other
     * owner the lookup simply finds nothing, so the caller is told plainly rather
     * than handed a bare 404.
     */
    public function requestApproval(Request $r, int $id)
    {
        $this->assertResultsTracking($id);

        return $this->proxy($r, 'clients.request-approval', $id, fn ($c) => $c->requestRoundApproval($r, (string) $id));
    }

    public function approveRound(Request $r, int $id)
    {
        $this->assertResultsTracking($id);

        return $this->proxy($r, 'clients.approve-round', $id, fn ($c) => $c->approveRound((string) $id));
    }

    public function clearApproval(Request $r, int $id)
    {
        $this->assertResultsTracking($id);

        return $this->proxy($r, 'clients.clear-approval', $id, fn ($c) => $c->clearRoundApproval((string) $id));
    }

    /** Run a dashboard action as the assistant, with the diff the approval card shows. */
    private function proxy(Request $request, string $endpoint, int $id, callable $call)
    {
        $snapshot = fn () => $this->state($id);

        return response()->json($this->mutation->run($request, $endpoint, function () use ($call, $id) {
            $this->selectOwnerOf($id);

            $controller = app(EndUserController::class);
            $call($controller);   // its redirect response is for a browser; we discard it

            return ['client_id' => $id];
        }, $snapshot));
    }

    /**
     * Point the session at this client's business owner, the way choosing one on the
     * Select Business Owner screen does, then act.
     *
     * Most of these actions scope themselves through `EndUser::forClient(session(
     * 'selected_client_id'))` — and that helper checks ONLY the session value, not
     * whose owner it is. On the dashboard that is safe because the selector never
     * offers an owner outside your org. Here there is no selector, so the org check
     * is ours to make: without it, setting the session from an arbitrary id would
     * reach into another org's clients entirely.
     */
    private function selectOwnerOf(int $id): void
    {
        $assistantOrg = $this->mutation->actAsAssistant()->dataOwnerId();

        $clientId = EndUser::whereKey($id)
            ->whereHas('client', fn ($q) => $q->where('admin_id', $assistantOrg))
            ->value('client_id');

        abort_unless($clientId, 404, 'No such client in this organisation.');

        session(['selected_client_id' => $clientId]);
    }

    /** The fields these actions can move — what the diff is taken over. */
    private function state(int $id): array
    {
        $e = EndUser::query()
            ->select(['id', 'first_name', 'last_name', 'intake_status', 'held_at',
                'round_approval_status', 'round_approval_round', 'listed_at'])
            ->find($id);

        if (! $e) {
            return [];
        }

        return [
            'name'                  => trim($e->first_name . ' ' . $e->last_name),
            'intake_status'         => $e->intake_status,
            'held'                  => $e->held_at !== null,
            'round_approval_status' => $e->round_approval_status,
            'round_approval_round'  => $e->round_approval_round,
        ];
    }

    /** A plain refusal beats a bare 404 when the feature simply isn't on for that owner. */
    private function assertResultsTracking(int $id): void
    {
        $on = EndUser::whereKey($id)
            ->whereHas('client', fn ($q) => $q->where('results_tracking', true))
            ->exists();

        abort_unless($on, 409, 'The approval workflow is not enabled for this client\'s business owner.');
    }
}
