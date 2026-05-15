<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\ProcessStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function store(Request $request)
    {
        $clientId = session('selected_client_id');

        $endUserRule = Rule::exists('end_users', 'id')->where(fn ($q) => $q->where('client_id', $clientId));

        $data = $request->validate([
            'end_user_id' => ['required', $endUserRule],
            'process_step_id' => 'nullable|integer',
            'category' => 'required|in:credit_report,dispute_letter_experian,dispute_letter_equifax,dispute_letter_transunion,dispute_letter_innovis,cfpb_complaint_experian,cfpb_complaint_equifax,cfpb_complaint_transunion,cfpb_complaint_innovis,ftc_complaint,bureau_response,escalation_letter,call_recording,call_notes,tracking_receipt,other',
            'description' => 'nullable|string|max:255',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,mp3,wav|max:10240',
        ]);

        // Cross-check: process_step (if provided) must belong to the same end_user under selected BO
        if (!empty($data['process_step_id'])) {
            $stepBelongs = ProcessStep::forClient($clientId)
                ->where('id', $data['process_step_id'])
                ->where('end_user_id', $data['end_user_id'])
                ->exists();
            if (!$stepBelongs) {
                abort(404);
            }
        }

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        $fileType = match ($extension) {
            'pdf' => 'pdf',
            'jpg', 'jpeg', 'png' => 'image',
            'mp3', 'wav' => 'audio',
            default => 'other',
        };

        $date = now()->toDateString();
        $filename = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
        $directory = "uploads/{$data['end_user_id']}/{$date}";
        $path = $file->storeAs($directory, $filename, 'private');

        $document = Document::create([
            'end_user_id' => $data['end_user_id'],
            'process_step_id' => $data['process_step_id'] ?? null,
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $fileType,
            'file_path' => $path,
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['document' => $document, 'url' => $document->url]);
        }

        return back()->with('status', 'Document uploaded.');
    }

    public function destroy(string $id)
    {
        // Document::deleting hook removes the file from the private disk
        Document::forClient(session('selected_client_id'))->findOrFail($id)->delete();
        return back()->with('status', 'Document deleted.');
    }
}
