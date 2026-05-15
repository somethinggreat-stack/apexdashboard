<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EndUser;
use App\Models\ScoreHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ScoreController extends Controller
{
    public function store(Request $request)
    {
        $clientId = session('selected_client_id');

        $endUserRule = Rule::exists('end_users', 'id')->where(fn ($q) => $q->where('client_id', $clientId));

        $data = $request->validate([
            'end_user_id' => ['required', $endUserRule],
            'score' => 'required|integer|min:300|max:850',
            'bureau' => 'required|in:experian,equifax,transunion,average',
            'recorded_at' => 'required|date',
        ]);

        ScoreHistory::create($data);

        if ($data['bureau'] === 'average') {
            EndUser::forClient($clientId)
                ->where('id', $data['end_user_id'])
                ->update(['current_score' => $data['score']]);
        }

        return back()->with('status', 'Score recorded.');
    }
}
