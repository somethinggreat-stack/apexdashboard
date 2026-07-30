<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\ProcessStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    /**
     * Bulk upload: VA drops many files at once, no per-file category or
     * description required. Everything lands as category "other" and can
     * be re-categorised later from the single-upload modal if needed.
     */
    public function bulkStore(Request $request)
    {
        $clientId = session('selected_client_id');

        // When the whole POST body is bigger than PHP's post_max_size, PHP throws
        // away $_POST and $_FILES before we ever see them, so validation would
        // wrongly report "no files". Detect it and return the actual reason.
        $postMax = $this->iniBytes((string) ini_get('post_max_size'));
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
        if ($postMax > 0 && $contentLength > $postMax && empty($_FILES) && empty($_POST)) {
            return $this->uploadError(
                $request,
                'Upload is bigger than the server accepts in one request (limit '
                . $this->humanSize($postMax) . '). Upload fewer or smaller files at a time.',
                413
            );
        }

        $endUserRule = Rule::exists('end_users', 'id')->where(fn ($q) => $q->where('client_id', $clientId));

        try {
            $data = $request->validate([
                'end_user_id'     => ['required', $endUserRule],
                'process_step_id' => 'nullable|integer',
                'files'           => 'required|array|min:1|max:50',
                'files.*'         => 'file|mimes:pdf,jpg,jpeg,png,mp3,wav,doc,docx,xls,xlsx,csv,txt|max:10240',
            ], [
                'end_user_id.exists' => 'This client no longer exists under the selected business owner.',
                'files.required'     => 'No files reached the server (they may have been blocked or stripped in transit).',
                'files.max'          => 'Too many files at once — upload up to 50 at a time.',
                'files.*.mimes'      => 'Unsupported file type. Allowed: PDF, JPG, PNG, MP3, WAV, DOC(X), XLS(X), CSV, TXT.',
                'files.*.max'        => 'File is larger than the 10 MB per-file limit.',
                'files.*.file'       => 'The upload did not arrive as a complete file (blocked or truncated in transit).',
            ]);
        } catch (ValidationException $e) {
            return $this->uploadError($request, implode(' ', array_unique($e->validator->errors()->all())), 422);
        }

        // Surface PHP's own per-file upload error codes (ini size, partial upload,
        // missing temp dir, …) instead of a silent failure later.
        foreach ($request->file('files') as $file) {
            if (! $file->isValid()) {
                return $this->uploadError(
                    $request,
                    'File "' . $file->getClientOriginalName() . '" could not be uploaded: ' . $file->getErrorMessage(),
                    422
                );
            }
        }

        if (!empty($data['process_step_id'])) {
            $stepBelongs = ProcessStep::forClient($clientId)
                ->where('id', $data['process_step_id'])
                ->where('end_user_id', $data['end_user_id'])
                ->exists();
            if (!$stepBelongs) {
                abort(404);
            }
        }

        $created = 0;

        try {
            foreach ($request->file('files') as $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                $fileType = match ($extension) {
                    'pdf'                => 'pdf',
                    'jpg', 'jpeg', 'png' => 'image',
                    'mp3', 'wav'         => 'audio',
                    default              => 'other',
                };

                $date = now()->toDateString();
                $filename = time() . '_' . bin2hex(random_bytes(3)) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
                $directory = "uploads/{$data['end_user_id']}/{$date}";
                $path = $file->storeAs($directory, $filename, 'private');

                if (! $path) {
                    throw new \RuntimeException('the server could not write it to storage — check disk space and the permissions on storage/app/private/uploads.');
                }

                Document::create([
                    'end_user_id'     => $data['end_user_id'],
                    'process_step_id' => $data['process_step_id'] ?? null,
                    'file_name'       => $file->getClientOriginalName(),
                    'file_type'       => $fileType,
                    'file_path'       => $path,
                    'category'        => 'other',
                    'description'     => null,
                ]);
                $created++;
            }
        } catch (\Throwable $e) {
            report($e);
            $prefix = $created > 0 ? "Saved {$created} file(s), then hit an error: " : 'Upload failed: ';
            return $this->uploadError($request, $prefix . $e->getMessage(), 500);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['count' => $created]);
        }

        return back()->with('status', "{$created} document(s) uploaded.");
    }

    /** JSON error for the AJAX uploader; a flash-back for a plain form post. */
    private function uploadError(Request $request, string $message, int $status)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $message], $status);
        }
        return back()->withErrors(['files' => $message]);
    }

    /** Parse a php.ini shorthand size ("8M", "512K", "1G") into bytes. */
    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') return 0;
        $num  = (int) $value;
        $unit = strtolower(substr($value, -1));
        return match ($unit) {
            'g'     => $num * 1024 * 1024 * 1024,
            'm'     => $num * 1024 * 1024,
            'k'     => $num * 1024,
            default => (int) $value,
        };
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
        if ($bytes >= 1048576)    return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)       return round($bytes / 1024) . ' KB';
        return $bytes . ' B';
    }

    public function destroy(string $id)
    {
        // Document::deleting hook removes the file from the private disk
        Document::forClient(session('selected_client_id'))->findOrFail($id)->delete();
        return back()->with('status', 'Document deleted.');
    }
}
