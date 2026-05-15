<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $client = Auth::guard('client')->user();

        $messages = $client->messages()->orderBy('created_at')->get();

        $client->messages()
            ->whereIn('sender_type', [Message::SENDER_ADMIN, Message::SENDER_SYSTEM])
            ->whereNull('client_read_at')
            ->update(['client_read_at' => now()]);

        return view('client.messages.index', [
            'messages' => $messages,
        ]);
    }

    public function store(Request $request)
    {
        $client = Auth::guard('client')->user();

        $data = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        Message::create([
            'client_id'   => $client->id,
            'sender_type' => Message::SENDER_CLIENT,
            'sender_id'   => $client->id,
            'body'        => $data['body'],
        ]);

        return redirect()->route('client.messages.index');
    }
}
