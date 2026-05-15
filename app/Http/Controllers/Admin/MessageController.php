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

        $messages = $client->messages()->orderBy('created_at')->get();

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
            'body' => 'required|string|max:5000',
        ]);

        Message::create([
            'client_id'   => $client->id,
            'sender_type' => Message::SENDER_ADMIN,
            'sender_id'   => Auth::guard('admin')->id(),
            'body'        => $data['body'],
        ]);

        return redirect()->route('admin.messages.index');
    }

    private function resolveSelectedClient(): Client
    {
        $adminId = Auth::guard('admin')->id();
        $clientId = session('selected_client_id');

        abort_unless($clientId, 404);

        return Client::forAdmin($adminId)->findOrFail($clientId);
    }
}
