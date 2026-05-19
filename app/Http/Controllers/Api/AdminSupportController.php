<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\AdminLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminSupportController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportTicket::with('user:id,name,email,role')
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('subject', 'like', "%{$s}%")
                  ->orWhere('message', 'like', "%{$s}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$s}%"));
            });
        }

        $tickets = $query->paginate((int) $request->get('per_page', 20));

        $stats = [
            'total'       => SupportTicket::count(),
            'open'        => SupportTicket::where('status', 'open')->count(),
            'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            'resolved'    => SupportTicket::whereIn('status', ['resolved', 'closed'])->count(),
        ];

        return response()->json([
            'success' => true,
            'tickets' => $tickets->items(),
            'total'   => $tickets->total(),
            'pages'   => $tickets->lastPage(),
            'current' => $tickets->currentPage(),
            'stats'   => $stats,
        ]);
    }

    public function show($id)
    {
        $ticket = SupportTicket::with(['user:id,name,email,role', 'repliedByUser:id,name'])->findOrFail($id);

        return response()->json(['success' => true, 'ticket' => $ticket]);
    }

    public function reply(Request $request, $id)
    {
        $request->validate([
            'reply'  => 'required|string|min:1',
            'status' => 'sometimes|in:open,in_progress,resolved,closed',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $admin  = Auth::user();

        $ticket->update([
            'admin_reply' => $request->reply,
            'replied_at'  => now(),
            'replied_by'  => $admin->id,
            'status'      => $request->input('status', $ticket->status === 'open' ? 'in_progress' : $ticket->status),
        ]);

        AdminLog::record($admin, 'update', 'support_ticket', $ticket->id,
            "Respondió ticket #{$ticket->id}: {$ticket->subject}");

        return response()->json(['success' => true, 'ticket' => $ticket->fresh(['user:id,name,email,role'])]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status'   => 'sometimes|in:open,in_progress,resolved,closed',
            'priority' => 'sometimes|in:low,medium,high,urgent',
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->update($request->only('status', 'priority'));

        AdminLog::record(Auth::user(), 'update', 'support_ticket', $ticket->id,
            "Actualizó estado del ticket #{$ticket->id} a {$ticket->status}");

        return response()->json(['success' => true, 'ticket' => $ticket]);
    }
}
