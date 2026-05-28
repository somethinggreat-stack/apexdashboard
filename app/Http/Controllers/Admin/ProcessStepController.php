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
        $allowedSteps = array_keys(ProcessStep::stepTypesByWeek()[$week] ?? []);

        $endUserRule = Rule::exists('end_users', 'id')->where(fn ($q) => $q->where('client_id', $clientId));

        // Backward-compat: a singular step_type still works as a one-element array.
        if ($request->filled('step_type') && !$request->has('step_types')) {
            $request->merge(['step_types' => [$request->input('step_type')]]);
        }

        $data = $request->validate([
            'end_user_id'                  => ['required', $endUserRule],
            'round'                        => 'required|integer|between:1,4',
            'week'                         => 'required|integer|between:1,4',
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
        ]);

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

            if ($type === 'record_deletions' && (int) $data['week'] === 4) {
                $this->advanceRoundFor((int) $data['end_user_id'], (int) $data['round']);
            }
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
        $labelMap  = [
            1 => '1st Round', 2 => '2nd Round', 3 => '3rd Round',
            4 => '4th Round', 5 => '5th Round',
        ];
        $nextLabel = $labelMap[$nextRound] ?? null;
        if (!$nextLabel) return;

        $endUser = EndUser::find($endUserId);
        if (!$endUser) return;

        $existing = $endUser->rounds ?? [];
        if (in_array($nextLabel, $existing, true)) return;

        $existing[] = $nextLabel;
        $endUser->update(['rounds' => $existing]);
    }

    private function validatedPayload(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes|required';
        $week = $request->integer('week');
        $allowedSteps = array_keys(ProcessStep::stepTypesByWeek()[$week] ?? []);
        $clientId = session('selected_client_id');

        // end_user_id must belong to the currently selected business owner
        $endUserRule = Rule::exists('end_users', 'id')->where(fn ($q) => $q->where('client_id', $clientId));

        return $request->validate([
            'end_user_id' => $creating ? ['required', $endUserRule] : ['sometimes', $endUserRule],
            'round' => "$required|integer|between:1,4",
            'week' => "$required|integer|between:1,4",
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
