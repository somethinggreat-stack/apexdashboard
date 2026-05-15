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
        $data = $this->validatedPayload($request, true);
        $data['created_by_admin_id'] = Auth::guard('admin')->id();
        $step = ProcessStep::create($data);

        if ($request->wantsJson()) {
            return response()->json(['process_step' => $step]);
        }

        return back()->with('status', 'Process step logged.')->with('new_step_id', $step->id);
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
