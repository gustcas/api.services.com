<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LiveServicesController extends Controller
{
    // Umbral para considerar usuario "conectado" (60 segundos)
    private function onlineThreshold()
    {
        return Carbon::now('UTC')->subSeconds(60)->toDateTimeString();
    }

    // ── Summary / KPIs ─────────────────────────────────────────
    public function summary()
    {
        $threshold = $this->onlineThreshold();
        $today     = Carbon::today('UTC')->toDateTimeString();

        $clientsOnline = \DB::table('users')
            ->where('role', 'client')
            ->where('is_active', true)
            ->where('last_seen_at', '>=', $threshold)
            ->count();

        $professionalsOnline = \DB::table('users')
            ->where('role', 'professional')
            ->where('is_active', true)
            ->where('last_seen_at', '>=', $threshold)
            ->count();

        $requestsToday = ServiceRequest::where('created_at', '>=', $today)->count();

        $requestsCompleted = ServiceRequest::where('status', 'completed')
            ->where('updated_at', '>=', $today)
            ->count();

        $requestsPending = ServiceRequest::where('status', 'pending')->count();

        $professionalsActive = ServiceRequest::where('status', 'accepted')
            ->whereNotNull('professional_id')
            ->distinct('professional_id')
            ->count('professional_id');

        // Chats activos = conversaciones con mensajes en las últimas 24h
        $chatThreshold = Carbon::now('UTC')->subHours(24)->toDateTimeString();
        $activeChats = ChatMessage::where('created_at', '>=', $chatThreshold)
            ->distinct('service_request_id')
            ->count('service_request_id');

        // Incidencias = solicitudes pendientes > 30 min sin profesional
        $incidentThreshold = Carbon::now('UTC')->subMinutes(30)->toDateTimeString();
        $incidents = ServiceRequest::where('status', 'pending')
            ->whereNull('professional_id')
            ->where('created_at', '<=', $incidentThreshold)
            ->count();

        return response()->json([
            'requests_today'        => $requestsToday,
            'clients_online'        => $clientsOnline,
            'professionals_online'  => $professionalsOnline,
            'professionals_active'  => $professionalsActive,
            'requests_completed'    => $requestsCompleted,
            'requests_pending'      => $requestsPending,
            'active_chats'          => $activeChats,
            'incidents'             => $incidents,
        ]);
    }

    // ── Requests ───────────────────────────────────────────────
    public function requests(Request $request)
    {
        $query = ServiceRequest::with([
            'client:id,name,email,last_seen_at',
            'professional.user:id,name,email,last_seen_at',
            'service:id,name',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->whereHas('client', function ($c) use ($search) {
                    $c->where('name', 'like', $search)->orWhere('email', 'like', $search);
                })->orWhere('id', 'like', $search);
            });
        }

        $threshold = $this->onlineThreshold();

        $requests = $query->orderBy('updated_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($sr) use ($threshold) {
                $profUser = $sr->professional ? $sr->professional->user : null;
                $clientOnline = $sr->client && $sr->client->last_seen_at
                    && $sr->client->last_seen_at >= $threshold;
                $profOnline = $profUser && $profUser->last_seen_at
                    && $profUser->last_seen_at >= $threshold;

                return [
                    'id'                => $sr->id,
                    'service_name'      => $sr->service ? $sr->service->name : 'Servicio',
                    'client_name'       => $sr->client ? $sr->client->name : '—',
                    'client_email'      => $sr->client ? $sr->client->email : '—',
                    'client_online'     => $clientOnline,
                    'professional_name' => $profUser ? $profUser->name : null,
                    'professional_email'=> $profUser ? $profUser->email : null,
                    'professional_online' => $profOnline,
                    'status'            => $sr->status,
                    'budget'            => $sr->budget,
                    'description'       => $sr->description,
                    'address'           => $sr->address,
                    'service_date'      => $sr->service_date,
                    'service_time'      => $sr->service_time,
                    'people_count'      => $sr->people_count,
                    'created_at'        => $sr->created_at ? $sr->created_at->setTimezone('America/Bogota')->format('d/m H:i') : '—',
                    'updated_at'        => $sr->updated_at ? $sr->updated_at->setTimezone('America/Bogota')->format('d/m H:i') : '—',
                    'elapsed'           => $sr->created_at ? $this->elapsed($sr->created_at) : '—',
                ];
            });

        return response()->json(['requests' => $requests]);
    }

    // ── Connected users ────────────────────────────────────────
    public function connectedUsers()
    {
        $threshold = $this->onlineThreshold();

        // Clientes online — una sola query para las solicitudes activas
        $clientRows = \DB::table('users')
            ->where('role', 'client')
            ->where('is_active', true)
            ->where('last_seen_at', '>=', $threshold)
            ->select('id', 'name', 'email', 'last_seen_at')
            ->orderBy('last_seen_at', 'desc')
            ->limit(50)
            ->get();

        $clientIds = $clientRows->pluck('id')->all();
        $clientRequests = ServiceRequest::whereIn('client_id', $clientIds)
            ->whereIn('status', ['pending', 'accepted'])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->keyBy('client_id');

        $clients = $clientRows->map(function ($u) use ($clientRequests) {
            $activeRequest = $clientRequests->get($u->id);
            return [
                'id'             => $u->id,
                'name'           => $u->name,
                'email'          => $u->email,
                'last_seen_at'   => $u->last_seen_at ? Carbon::parse($u->last_seen_at)->setTimezone('America/Bogota')->format('H:i:s') : '—',
                'active_request' => $activeRequest ? $activeRequest->id : null,
                'request_status' => $activeRequest ? $activeRequest->status : null,
            ];
        });

        // Profesionales online — pre-carga masiva
        $profRows = \DB::table('users')
            ->where('role', 'professional')
            ->where('is_active', true)
            ->where('last_seen_at', '>=', $threshold)
            ->select('id', 'name', 'email', 'last_seen_at')
            ->orderBy('last_seen_at', 'desc')
            ->limit(50)
            ->get();

        $profUserIds = $profRows->pluck('id')->all();
        $profsMap = \App\Models\Professional::whereIn('user_id', $profUserIds)
            ->get()->keyBy('user_id');

        $profIds = $profsMap->pluck('id')->all();
        $profRequests = ServiceRequest::whereIn('professional_id', $profIds)
            ->where('status', 'accepted')
            ->get()->keyBy('professional_id');

        $professionals = $profRows->map(function ($u) use ($profsMap, $profRequests) {
            $prof          = $profsMap->get($u->id);
            $activeRequest = $prof ? $profRequests->get($prof->id) : null;
            return [
                'id'             => $u->id,
                'name'           => $u->name,
                'email'          => $u->email,
                'last_seen_at'   => $u->last_seen_at ? Carbon::parse($u->last_seen_at)->setTimezone('America/Bogota')->format('H:i:s') : '—',
                'active_request' => $activeRequest ? $activeRequest->id : null,
                'status'         => $prof ? $prof->status : 'unknown',
                'is_busy'        => $activeRequest ? true : false,
            ];
        });

        return response()->json([
            'clients'       => $clients,
            'professionals' => $professionals,
        ]);
    }

    // ── Chats activos ──────────────────────────────────────────
    public function chats()
    {
        // Todas las conversaciones con al menos un mensaje, ordenadas por actividad reciente
        $rows = \DB::table('chat_messages')
            ->select('service_request_id', \DB::raw('MAX(id) as last_msg_id'), \DB::raw('COUNT(*) as total'))
            ->groupBy('service_request_id')
            ->orderBy('last_msg_id', 'desc')
            ->limit(100)
            ->get();

        // Carga masiva: evita N+1 queries
        $srIds      = $rows->pluck('service_request_id')->all();
        $lastMsgIds = $rows->pluck('last_msg_id')->all();

        $serviceRequests = ServiceRequest::with([
            'client:id,name,email',
            'professional.user:id,name,email',
        ])->whereIn('id', $srIds)->get()->keyBy('id');

        $lastMessages = ChatMessage::with('sender:id,name')
            ->whereIn('id', $lastMsgIds)->get()->keyBy('id');

        $result = [];
        foreach ($rows as $row) {
            $sr      = $serviceRequests->get($row->service_request_id);
            if (!$sr) continue;

            $lastMsg  = $lastMessages->get($row->last_msg_id);
            $profUser = $sr->professional ? $sr->professional->user : null;

            $result[] = [
                'request_id'        => $sr->id,
                'client_name'       => $sr->client ? $sr->client->name : '—',
                'professional_name' => $profUser ? $profUser->name : '—',
                'last_message'      => $lastMsg ? $lastMsg->message : '—',
                'last_message_time' => $lastMsg ? $lastMsg->created_at->setTimezone('America/Bogota')->format('H:i') : '—',
                'sender_name'       => $lastMsg && $lastMsg->sender ? $lastMsg->sender->name : '—',
                'total_messages'    => (int) $row->total,
                'status'            => $sr->status,
            ];
        }

        return response()->json(['chats' => $result]);
    }

    // ── Incidencias ────────────────────────────────────────────
    public function incidents()
    {
        // Solicitudes pendientes sin profesional por más de 30 min
        $threshold = Carbon::now('UTC')->subMinutes(30)->toDateTimeString();

        $pending = ServiceRequest::with(['client:id,name,email', 'service:id,name'])
            ->where('status', 'pending')
            ->whereNull('professional_id')
            ->where('created_at', '<=', $threshold)
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get()
            ->map(function ($sr) {
                return [
                    'id'           => $sr->id,
                    'type'         => 'sin_profesional',
                    'label'        => 'Sin profesional asignado',
                    'service_name' => $sr->service ? $sr->service->name : 'Servicio',
                    'client_name'  => $sr->client ? $sr->client->name : '—',
                    'client_email' => $sr->client ? $sr->client->email : '—',
                    'created_at'   => $sr->created_at->setTimezone('America/Bogota')->format('d/m H:i'),
                    'elapsed'      => $this->elapsed($sr->created_at),
                    'status'       => $sr->status,
                ];
            });

        return response()->json(['incidents' => $pending]);
    }

    // ── Ver mensajes de cualquier chat (solo admin) ────────────
    public function chatMessages($requestId)
    {
        $sr = ServiceRequest::with([
            'client:id,name',
            'professional.user:id,name',
        ])->findOrFail($requestId);

        $messages = ChatMessage::with('sender:id,name')
            ->where('service_request_id', $requestId)
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($msg) {
                return [
                    'id'          => $msg->id,
                    'message'     => $msg->message,
                    'sender_id'   => $msg->sender_id,
                    'sender_name' => $msg->sender ? $msg->sender->name : '—',
                    'created_at'  => $msg->created_at->setTimezone('America/Bogota')->format('H:i'),
                ];
            });

        $profUser = $sr->professional ? $sr->professional->user : null;

        return response()->json([
            'messages'             => $messages,
            'client_id'            => $sr->client_id,
            'professional_user_id' => $profUser ? $profUser->id : null,
            'client_name'          => $sr->client ? $sr->client->name : '—',
            'professional_name'    => $profUser ? $profUser->name : '—',
        ]);
    }

    // ── Profesionales disponibles para reasignar ───────────────
    public function availableProfessionals($requestId)
    {
        $sr = ServiceRequest::findOrFail($requestId);

        // Profesionales verificados con el servicio habilitado (sin restricción de online)
        $professionals = \App\Models\Professional::with('user')
            ->where('is_verified', true)
            ->where('status', 'approved')
            ->whereHas('services', function ($q) use ($sr) {
                $q->where('services.id', $sr->service_id);
            })
            ->get()
            ->map(function ($p) {
                $threshold = Carbon::now('UTC')->subSeconds(60)->toDateTimeString();
                $isOnline  = $p->user && $p->user->last_seen_at && $p->user->last_seen_at >= $threshold;
                return [
                    'id'        => $p->id,
                    'name'      => $p->user ? $p->user->name : '—',
                    'is_online' => $isOnline,
                    'phone'     => $p->phone ?? null,
                ];
            });

        return response()->json(['professionals' => $professionals]);
    }

    // ── Reasignar solicitud: profesional, fecha y hora ─────────
    public function reassign(\Illuminate\Http\Request $request, $requestId)
    {
        $request->validate([
            'professional_id' => 'nullable|exists:professionals,id',
            'service_date'    => 'nullable|date',
            'service_time'    => 'nullable|string',
        ]);

        // Permitir reasignar en estado pending O accepted
        $sr = ServiceRequest::where('id', $requestId)
            ->whereIn('status', ['pending', 'accepted'])
            ->firstOrFail();

        $changes  = [];
        $logParts = [];

        // Reasignar profesional
        if ($request->filled('professional_id')) {
            $professional = \App\Models\Professional::with('user')
                ->where('is_verified', true)
                ->findOrFail($request->professional_id);

            $serviceIds = $professional->services()->pluck('services.id')->toArray();
            if (!empty($serviceIds) && !in_array($sr->service_id, $serviceIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El profesional no tiene habilitado este servicio.',
                ], 422);
            }

            $changes['professional_id'] = $professional->id;
            $changes['status']          = 'accepted';
            $logParts[] = "profesional → {$professional->user->name}";
        }

        // Reasignar fecha
        if ($request->filled('service_date')) {
            $changes['service_date'] = $request->service_date;
            $logParts[] = "fecha → {$request->service_date}";
        }

        // Reasignar hora
        if ($request->filled('service_time')) {
            $changes['service_time'] = $request->service_time;
            $logParts[] = "hora → {$request->service_time}";
        }

        if (empty($changes)) {
            return response()->json(['success' => false, 'message' => 'No se indicaron cambios.'], 422);
        }

        $sr->update($changes);

        \App\Models\AdminLog::record(
            $request->user(), 'reassign', 'service-request', $sr->id,
            "Reasignó solicitud #{$requestId}: " . implode(', ', $logParts)
        );

        return response()->json(['success' => true, 'message' => 'Solicitud actualizada correctamente.']);
    }

    // ── Helper: tiempo transcurrido ────────────────────────────
    private function elapsed($createdAt)
    {
        $diff = Carbon::now('UTC')->diffInMinutes($createdAt);
        if ($diff < 60)  return $diff . ' min';
        if ($diff < 1440) return floor($diff / 60) . ' h';
        return floor($diff / 1440) . ' d';
    }
}
