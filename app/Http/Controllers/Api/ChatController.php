<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Notification;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    private function getServiceRequest(Request $request, $requestId)
    {
        $serviceRequest = ServiceRequest::findOrFail($requestId);
        $user = $request->user();

        $isClient = $serviceRequest->client_id === $user->id;
        $isProfessional = $user->professional &&
            $serviceRequest->professional_id === $user->professional->id;

        if (!$isClient && !$isProfessional) {
            abort(403, 'No autorizado');
        }

        return $serviceRequest;
    }

    private function getOtherUserId(ServiceRequest $sr, $user)
    {
        if ($sr->client_id === $user->id) {
            if ($sr->professional && $sr->professional->user_id) {
                return $sr->professional->user_id;
            }
            return null;
        }
        return $sr->client_id;
    }

    private function isOnline($userId)
    {
        if (!$userId) return false;
        $threshold = \Carbon\Carbon::now('UTC')->subSeconds(8)->toDateTimeString();
        return \DB::table('users')
            ->where('id', $userId)
            ->where('last_seen_at', '>=', $threshold)
            ->exists();
    }

    public function index(Request $request, $requestId)
    {
        $serviceRequest = $this->getServiceRequest($request, $requestId);
        $user = $request->user();

        // Actualizar presencia del usuario actual
        \DB::table('users')->where('id', $user->id)->update(['last_seen_at' => \Carbon\Carbon::now('UTC')->toDateTimeString()]);

        // Marcar mensajes del otro como leídos
        ChatMessage::where('service_request_id', $requestId)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $userId = $user->id;
        $messages = ChatMessage::with('sender:id,name,role')
            ->where('service_request_id', $requestId)
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($msg) use ($userId) {
                return [
                    'id'          => $msg->id,
                    'message'     => $msg->message,
                    'sender_id'   => $msg->sender_id,
                    'sender_name' => $msg->sender->name,
                    'is_mine'     => $msg->sender_id === $userId,
                    'created_at'  => $msg->created_at->setTimezone('America/Bogota')->format('H:i'),
                ];
            });

        $otherUserId = $this->getOtherUserId($serviceRequest, $user);

        return response()->json([
            'messages'     => $messages,
            'other_name'   => $this->getOtherName($serviceRequest, $user),
            'other_online' => $this->isOnline($otherUserId),
        ]);
    }

    public function store(Request $request, $requestId)
    {
        $request->validate(['message' => 'required|string|max:1000']);

        $serviceRequest = $this->getServiceRequest($request, $requestId);
        $user = $request->user();

        // Actualizar presencia al enviar
        $user->last_seen_at = now();
        $user->save();

        $msg = ChatMessage::create([
            'service_request_id' => $requestId,
            'sender_id'          => $user->id,
            'message'            => $request->message,
        ]);

        // Notificar al receptor del mensaje
        $receiverId = $this->getOtherUserId($serviceRequest, $user);
        if ($receiverId) {
            Notification::send(
                $receiverId,
                'new_message',
                'Nuevo mensaje',
                $user->name . ' te envió un mensaje.',
                (int) $requestId
            );
        }

        return response()->json([
            'id'          => $msg->id,
            'message'     => $msg->message,
            'sender_id'   => $msg->sender_id,
            'sender_name' => $user->name,
            'is_mine'     => true,
            'created_at'  => $msg->created_at->setTimezone('America/Bogota')->format('H:i'),
        ], 201);
    }

    // Conteo de no leídos agrupado por service_request_id
    public function unreads(Request $request)
    {
        $user = $request->user();

        $query = ServiceRequest::where('client_id', $user->id);
        if ($user->professional) {
            $profId = $user->professional->id;
            $query->orWhere('professional_id', $profId);
        }
        $requestIds = $query->pluck('id');

        $rows = ChatMessage::whereIn('service_request_id', $requestIds)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->groupBy('service_request_id')
            ->selectRaw('service_request_id, count(*) as cnt')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row->service_request_id] = (int) $row->cnt;
        }

        return response()->json($counts);
    }

    private function getOtherName(ServiceRequest $sr, $user)
    {
        if ($sr->client_id === $user->id) {
            $sr->load('professional.user');
            if ($sr->professional && $sr->professional->user) {
                return $sr->professional->user->name;
            }
            return 'Profesional';
        }
        $sr->load('client');
        return $sr->client ? $sr->client->name : 'Cliente';
    }
}
