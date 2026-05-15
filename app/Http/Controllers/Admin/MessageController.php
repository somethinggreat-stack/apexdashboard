<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $client = $this->resolveSelectedClient();

        $messages = $client->messages()
            ->with('replyTo')
            ->orderBy('created_at')
            ->get();

        $client->messages()
            ->whereIn('sender_type', [Message::SENDER_CLIENT, Message::SENDER_SYSTEM])
            ->whereNull('admin_read_at')
            ->update(['admin_read_at' => now()]);

        return view('admin.messages.index', [
            'messages' => $messages,
            'client'   => $client,
        ]);
    }

    public function store(Request $request)
    {
        $client = $this->resolveSelectedClient();

        $data = $request->validate([
            'body'        => 'required|string|max:5000',
            'reply_to_id' => 'nullable|integer|exists:messages,id',
        ]);

        $replyToId = $data['reply_to_id'] ?? null;
        if ($replyToId) {
            // Make sure the parent message belongs to the same conversation
            $exists = Message::where('client_id', $client->id)
                ->where('id', $replyToId)
                ->exists();
            if (!$exists) {
                $replyToId = null;
            }
        }

        Message::create([
            'client_id'   => $client->id,
            'sender_type' => Message::SENDER_ADMIN,
            'sender_id'   => Auth::guard('admin')->id(),
            'body'        => $data['body'],
            'reply_to_id' => $replyToId,
        ]);

        return redirect()->route('admin.messages.index');
    }

    public function togglePin(string $id)
    {
        $msg = $this->findMessage($id);
        $msg->update(['pinned_at' => $msg->pinned_at ? null : now()]);

        return $this->actionResponse($msg, 'pinned_at');
    }

    public function toggleStar(string $id)
    {
        $msg = $this->findMessage($id);
        $msg->update(['starred_at' => $msg->starred_at ? null : now()]);

        return $this->actionResponse($msg, 'starred_at');
    }

    public function saveNote(Request $request, string $id)
    {
        $msg = $this->findMessage($id);
        $data = $request->validate([
            'note' => 'nullable|string|max:5000',
        ]);
        $msg->update(['note' => !empty($data['note']) ? $data['note'] : null]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'note' => $msg->note]);
        }
        return back();
    }

    private function findMessage(string $id): Message
    {
        $client = $this->resolveSelectedClient();
        return Message::where('client_id', $client->id)->findOrFail($id);
    }

    private function actionResponse(Message $msg, string $field)
    {
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'ok'    => true,
                'id'    => $msg->id,
                $field  => $msg->{$field}?->toIso8601String(),
            ]);
        }
        return back();
    }

    private function resolveSelectedClient(): Client
    {
        $adminId = Auth::guard('admin')->id();
        $clientId = session('selected_client_id');

        abort_unless($clientId, 404);

        return Client::forAdmin($adminId)->findOrFail($clientId);
    }
}
