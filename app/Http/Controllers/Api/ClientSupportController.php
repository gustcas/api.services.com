<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientSupportController extends Controller
{
    public function index()
    {
        $tickets = SupportTicket::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get(['id', 'subject', 'type', 'status', 'priority', 'admin_reply', 'replied_at', 'created_at']);

        return response()->json(['success' => true, 'tickets' => $tickets]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:10',
            'type'    => 'sometimes|in:general,payment,professional,technical',
            'priority'=> 'sometimes|in:low,medium,high,urgent',
        ]);

        $ticket = SupportTicket::create([
            'user_id'  => Auth::id(),
            'subject'  => $request->subject,
            'message'  => $request->message,
            'type'     => $request->input('type', 'general'),
            'priority' => $request->input('priority', 'medium'),
            'status'   => 'open',
        ]);

        return response()->json(['success' => true, 'ticket' => $ticket], 201);
    }
}
