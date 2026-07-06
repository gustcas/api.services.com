<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientRequestController extends Controller
{
    // Solicitudes del cliente
    public function index(Request $request)
    {
        $requests = ServiceRequest::with('service.category', 'professional.user', 'city')
            ->where('client_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($requests->map(function ($r) {
            return [
                'id'                          => $r->id,
                'description'                 => $r->description,
                'address'                     => $r->address,
                'service_date'                => $r->service_date,
                'service_time'                => $r->service_time,
                'budget'                      => $r->budget,
                'status'                      => $r->status,
                'people_count'                => $r->people_count,
                'service_name'                => $r->service ? $r->service->name : null,
                'category_name'               => $r->service && $r->service->category ? $r->service->category->name : null,
                'form_type'                   => $r->service ? $r->service->form_type : null,
                'icon_key'                    => $r->service ? $r->service->icon_key : null,
                'city_name'                   => $r->city ? $r->city->name : null,
                'completion_code'             => $r->completion_code,
                'completion_code_expires_at'  => $r->completion_code_expires_at,
                'completed_at'                => $r->completed_at,
                'professional'                => $r->professional ? [
                    'id'    => $r->professional->id,
                    'name'  => $r->professional->user ? $r->professional->user->name : 'Profesional',
                    'phone' => $r->professional->phone,
                    'photo' => $r->professional->photo,
                ] : null,
                'is_rated' => \App\Models\Rating::where('service_request_id', $r->id)
                    ->where('rater_id', $r->client_id)
                    ->exists(),
            ];
        }));
    }

    // Cancelar solicitud con pago pendiente
    public function cancel(Request $request, $id)
    {
        $sr = ServiceRequest::where('id', $id)
            ->where('client_id', $request->user()->id)
            ->where('status', 'payment_pending')
            ->firstOrFail();

        \App\Models\AdminLog::record(
            $request->user(),
            'cancel',
            'service_request',
            $sr->id,
            "Cliente {$request->user()->name} canceló solicitud #{$sr->id}"
        );

        $sr->delete();

        return response()->json(['success' => true, 'message' => 'Solicitud cancelada.']);
    }

    // Generar código de 6 dígitos
    public function generateCode(Request $request, $id)
    {
        $serviceRequest = ServiceRequest::where('id', $id)
            ->where('client_id', $request->user()->id)
            ->where('status', 'accepted')
            ->firstOrFail();

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $serviceRequest->update([
            'completion_code'            => $code,
            'completion_code_expires_at' => now()->addMinutes(15),
        ]);

        return response()->json([
            'code'       => $code,
            'expires_at' => $serviceRequest->completion_code_expires_at,
        ]);
    }
}
