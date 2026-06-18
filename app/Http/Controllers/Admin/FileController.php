<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\EndUser;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function document(string $id): StreamedResponse
    {
        $clientId = session('selected_client_id');
        $document = Document::forClient($clientId)->findOrFail($id);

        return $this->stream($document->file_path, $document->file_name);
    }

    public function identity(string $endUser, string $type): StreamedResponse
    {
        $clientId = session('selected_client_id');
        $user = EndUser::forClient($clientId)->findOrFail($endUser);

        $column = match ($type) {
            'photo_id' => 'photo_id_path',
            'proof_of_address' => 'proof_of_address_path',
            'ssn_picture' => 'ssn_picture_path',
            'collage' => 'collage_path',
            default => abort(404),
        };

        $path = $user->{$column};
        if (!$path) {
            abort(404);
        }

        $filename = basename($path);
        return $this->stream($path, $filename);
    }

    private function stream(string $path, string $downloadName): StreamedResponse
    {
        $disk = Storage::disk('private');
        if (!$disk->exists($path)) {
            abort(404);
        }
        return $disk->response($path, $downloadName, [
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
