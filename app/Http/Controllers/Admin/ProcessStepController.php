<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use App\Models\ProcessStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProcessStepController extends Controller
{
    public function store(Request $request)
    {
        $clientId = session('selected_client_id');
        $week = $request->integer('week');
        // The owner's round cycle decides how many weeks a round has and which
        // steps belong to each (20-day → 3 weeks, 30-day → 4).
        $cycle        = (int) (\App\Models\Client::find($clientId)?->roundCycleDays() ?? 30);
        $weekCount    = ProcessStep::weekCount($cycle);
        $allowedSteps = array_keys(ProcessStep::stepTypesByWeek($cycle)[$week] ?? []);

        $endUserRule = Rule::exists('end_users', 'id')->where(fn ($q) => $q->where('client_id', $clientId));

        // Backward-compat: a singular step_type still works as a one-element array.
        if ($request->filled('step_type') && !$request->has('step_types')) {
            $request->merge(['step_types' => [$request->input('step_type')]]);
        }

        // Round-outcome numbers are always optional — a VA can log a step without
        // them and fill them in whenever they have the figures.
        $countRule = 'nullable|integer|min:0';
        $scoreRule = 'nullable|integer|min:300|max:850';

        $data = $request->validate([
            'end_user_id'                  => ['required', $endUserRule],
            'round'                        => 'required|integer|between:1,15',
            'week'                         => "required|integer|between:1,{$weekCount}",
            'step_types'                   => 'required|array|min:1',
            'step_types.*'                 => ['string', Rule::in($allowedSteps ?: array_keys(ProcessStep::allStepTypes()))],
            'step_date'                    => 'required|date',
            'experian_accounts_disputed'   => 'nullable|integer|min:0',
            'experian_inquiries_disputed'  => 'nullable|integer|min:0',
            'transunion_accounts_disputed' => 'nullable|integer|min:0',
            'transunion_inquiries_disputed' => 'nullable|integer|min:0',
            'equifax_accounts_disputed'    => 'nullable|integer|min:0',
            'equifax_inquiries_disputed'   => 'nullable|integer|min:0',
            'previous_credit_score'        => 'nullable|integer|min:300|max:850',
            'credit_score_now'             => 'nullable|integer|min:300|max:850',
            // Round outcome metrics — required when closing out a round.
            'total_deletions'              => $countRule,
            'updated_to_positive'          => $countRule,
            'updated_to_negative'          => $countRule,
            'items_added'                  => $countRule,
            'experian_score_before'        => $scoreRule,
            'experian_score_now'           => $scoreRule,
            'transunion_score_before'      => $scoreRule,
            'transunion_score_now'         => $scoreRule,
            'equifax_score_before'         => $scoreRule,
            'equifax_score_now'            => $scoreRule,
        ], [
            'integer' => 'Numbers only.',
        ]);

        // Strict sequential lock: no round/week can be worked until every earlier
        // week — and every earlier round in full, closeout steps included — is
        // complete. Applies to everyone, VA and super admin alike.
        $endUser = EndUser::find($data['end_user_id']);
        if ($endUser && $reason = $endUser->sequentialBlockReason((int) $data['round'], (int) $data['week'])) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $reason, 'errors' => ['step_types' => [$reason]]], 422);
            }
            return back()->withErrors(['step_types' => $reason])->withInput();
        }

        $shared = [
            'end_user_id'                   => $data['end_user_id'],
            'round'                         => $data['round'],
            'week'                          => $data['week'],
            'step_date'                     => $data['step_date'],
            'created_by_admin_id'           => Auth::guard('admin')->id(),
            'experian_accounts_disputed'    => $data['experian_accounts_disputed'] ?? null,
            'experian_inquiries_disputed'   => $data['experian_inquiries_disputed'] ?? null,
            'transunion_accounts_disputed'  => $data['transunion_accounts_disputed'] ?? null,
            'transunion_inquiries_disputed' => $data['transunion_inquiries_disputed'] ?? null,
            'equifax_accounts_disputed'     => $data['equifax_accounts_disputed'] ?? null,
            'equifax_inquiries_disputed'    => $data['equifax_inquiries_disputed'] ?? null,
            'previous_credit_score'         => $data['previous_credit_score'] ?? null,
            'credit_score_now'              => $data['credit_score_now'] ?? null,
            'total_deletions'               => $data['total_deletions'] ?? null,
            'updated_to_positive'           => $data['updated_to_positive'] ?? null,
            'updated_to_negative'           => $data['updated_to_negative'] ?? null,
            'items_added'                   => $data['items_added'] ?? null,
            'experian_score_before'         => $data['experian_score_before'] ?? null,
            'experian_score_now'            => $data['experian_score_now'] ?? null,
            'transunion_score_before'       => $data['transunion_score_before'] ?? null,
            'transunion_score_now'          => $data['transunion_score_now'] ?? null,
            'equifax_score_before'          => $data['equifax_score_before'] ?? null,
            'equifax_score_now'             => $data['equifax_score_now'] ?? null,
        ];

        $created = 0;
        $skipped = 0;

        foreach (array_unique($data['step_types']) as $type) {
            $exists = ProcessStep::where('end_user_id', $data['end_user_id'])
                ->where('round', $data['round'])
                ->where('week', $data['week'])
                ->where('step_type', $type)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            ProcessStep::create($shared + ['step_type' => $type]);
            $created++;

            // Closeout lives in the cycle's LAST week — Week 3 for a 20-day owner,
            // Week 4 for a 30-day owner — so key on $weekCount, not a literal 4,
            // or 20-day clients never auto-advance to the next round.
            if ($type === 'record_deletions' && (int) $data['week'] === $weekCount) {
                $this->advanceRoundFor((int) $data['end_user_id'], (int) $data['round']);
            }
        }

        // Logging any step for a round marks that round as started — its start
        // date is the earliest logged step (read live by the model), so all the
        // day-counts for the round begin from here.
        if ($created > 0) {
            $this->markRoundStarted((int) $data['end_user_id'], (int) $data['round']);
        }

        $msg = match (true) {
            $created > 0 && $skipped > 0 => "Process step(s) logged. {$created} created, {$skipped} skipped (already existed for this round & week).",
            $created > 0                 => 'Process step(s) logged.',
            default                      => 'No new steps created — all selected steps already exist for this round & week.',
        };

        if ($request->wantsJson()) {
            return response()->json(['created' => $created, 'skipped' => $skipped]);
        }

        // No document-upload prompt. Logging a step only creates the
        // timeline entry and shows a success message.
        return back()->with('status', $msg);
    }

    public function update(Request $request, string $id)
    {
        $step = ProcessStep::forClient(session('selected_client_id'))->findOrFail($id);
        $data = $this->validatedPayload($request, false);
        $step->update($data);
        return back()->with('status', 'Process step updated.');
    }

    public function destroy(string $id)
    {
        ProcessStep::forClient(session('selected_client_id'))->findOrFail($id)->delete();
        return back()->with('status', 'Process step deleted.');
    }

    /**
     * When Week 4 record_deletions is logged for round N, append the
     * "(N+1)th Round" label to the end user's rounds array so the new
     * round appears in their profile and the VA knows to start logging it.
     */
    private function advanceRoundFor(int $endUserId, int $completedRound): void
    {
        $nextRound = $completedRound + 1;
        $nextLabel = EndUser::ROUND_OPTIONS[$nextRound - 1] ?? null;
        if (!$nextLabel) return;

        $endUser = EndUser::find($endUserId);
        if (!$endUser) return;

        $existing = $endUser->rounds ?? [];
        if (in_array($nextLabel, $existing, true)) return;

        $existing[] = $nextLabel;
        $endUser->update(['rounds' => $existing]);
    }

    /**
     * Add a round's label to the client's rounds array the first time a step is
     * logged for it, in canonical order. This is what "marks" the round; the
     * start date itself is derived live from the earliest logged step.
     */
    private function markRoundStarted(int $endUserId, int $round): void
    {
        $label = EndUser::ROUND_OPTIONS[$round - 1] ?? null;
        if (! $label) return;

        $endUser = EndUser::find($endUserId);
        if (! $endUser) return;

        $rounds = $endUser->rounds ?? [];
        if (in_array($label, $rounds, true)) return;

        $rounds[] = $label;
        $endUser->update([
            'rounds' => array_values(array_intersect(EndUser::ROUND_OPTIONS, $rounds)),
        ]);
    }

    private function validatedPayload(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes|required';
        $week = $request->integer('week');
        $clientId = session('selected_client_id');
        $cycle        = (int) (\App\Models\Client::find($clientId)?->roundCycleDays() ?? 30);
        $weekCount    = ProcessStep::weekCount($cycle);
        $allowedSteps = array_keys(ProcessStep::stepTypesByWeek($cycle)[$week] ?? []);

        // end_user_id must belong to the currently selected business owner
        $endUserRule = Rule::exists('end_users', 'id')->where(fn ($q) => $q->where('client_id', $clientId));

        return $request->validate([
            'end_user_id' => $creating ? ['required', $endUserRule] : ['sometimes', $endUserRule],
            'round' => "$required|integer|between:1,15",
            'week' => "$required|integer|between:1,{$weekCount}",
            'step_type' => [$creating ? 'required' : 'sometimes', 'string', Rule::in($allowedSteps ?: array_keys(ProcessStep::allStepTypes()))],
            'step_date' => "$required|date",
            'experian_accounts_disputed' => 'nullable|integer|min:0',
            'experian_inquiries_disputed' => 'nullable|integer|min:0',
            'transunion_accounts_disputed' => 'nullable|integer|min:0',
            'transunion_inquiries_disputed' => 'nullable|integer|min:0',
            'equifax_accounts_disputed' => 'nullable|integer|min:0',
            'equifax_inquiries_disputed' => 'nullable|integer|min:0',
            'previous_credit_score' => 'nullable|integer|min:300|max:850',
            'credit_score_now' => 'nullable|integer|min:300|max:850',
        ]);
    }
}
