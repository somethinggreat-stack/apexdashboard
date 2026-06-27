<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class NoteController extends Controller
{
    public function store(Request $request)
    {
        $clientId = session('selected_client_id');

        $endUserRule = Rule::exists('end_users', 'id')->where(fn ($q) => $q->where('client_id', $clientId));

        $data = $request->validate([
            'end_user_id' => ['required', $endUserRule],
            'note_text' => 'required|string',
        ]);

        $data['created_by_admin_id'] = Auth::guard('admin')->id();
        Note::create($data);

        return back()->with('status', 'Comment added.');
    }

    public function destroy(string $id)
    {
        Note::forClient(session('selected_client_id'))->findOrFail($id)->delete();
        return back()->with('status', 'Comment deleted.');
    }
}
