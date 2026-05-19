<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;
use App\Models\ServiceRequest;
use App\Mail\ServiceAcceptedMail;
use App\Mail\ServiceCompletedMail;
use Illuminate\Support\Facades\Mail;

class ServiceRequestController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'category_id'             => 'required|exists:categories,id',
            'service_id'              => 'required|exists:services,id',
            'description'             => 'nullable|string',
            'address'                 => 'nullable|string',
            'lat'                     => 'nullable|numeric',
            'lng'                     => 'nullable|numeric',
            'service_date'            => 'required|date',
            'service_time'            => 'required',
            'budget'                  => 'nullable|numeric',
            'city_id'                 => 'nullable|exists:cities,id',
            'people_count'            => 'nullable|integer|min:1',
            'people_names'            => 'nullable|array',
            'people_names.*'          => 'nullable|string|max:255',
            'people_identifications'  => 'nullable|array',
            'people_identifications.*'=> 'nullable|string|max:50',
            'company_name'            => 'nullable|string|max:255',
            'company_address'         => 'nullable|string|max:255',
            'company_owners'          => 'nullable|string|max:255',
            'company_nit'             => 'nullable|string|max:50',
            'company_phone'           => 'nullable|string|max:20',
        ]);

        // Calcular precio: precio base × cantidad de personas
        $service     = \App\Models\Service::find($request->service_id);
        $basePrice   = $service ? (float) $service->price : (float) ($request->budget ?? 0);
        $peopleCount = max(1, (int) ($request->people_count ?? 1));
        $budget      = $basePrice * $peopleCount;

        $serviceRequest = ServiceRequest::create([
            'client_id'              => $request->user()->id,
            'category_id'            => $request->category_id,
            'service_id'             => $request->service_id,
            'description'            => $request->description,
            'address'                => $request->address,
            'lat'                    => $request->lat,
            'lng'                    => $request->lng,
            'service_date'           => $request->service_date,
            'service_time'           => $request->service_time,
            'people_count'           => $request->people_count,
            'people_names'           => $request->people_names,
            'people_identifications' => $request->people_identifications,
            'company_name'           => $request->company_name,
            'company_address'        => $request->company_address,
            'company_owners'         => $request->company_owners,
            'company_nit'            => $request->company_nit,
            'company_phone'          => $request->company_phone,
            'budget'                 => $budget,
            'city_id'                => $request->city_id,
            'status'                 => 'payment_pending', // inactiva hasta confirmar pago
            'payment_status'         => 'pending_payment',
        ]);

        $serviceName = $serviceRequest->service ? $serviceRequest->service->name : 'servicio';
        \App\Models\AdminLog::record(
            $request->user(),
            'create',
            'service_request',
            $serviceRequest->id,
            "Cliente {$request->user()->name} creó solicitud #{$serviceRequest->id} ({$serviceName})"
        );

        return response()->json([
            'success'            => true,
            'message'            => 'Solicitud creada. Procede al pago para activarla.',
            'service_request_id' => $serviceRequest->id,
            'amount'             => $budget,
            'amount_formatted'   => '$' . number_format($budget, 0, ',', '.') . ' COP',
        ], 201);
    }


     public function checkStatus(Request $request, $id)
    {
        $serviceRequest = ServiceRequest::with('professional.user')
            ->where('id', $id)
            ->where('client_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'status'       => $serviceRequest->status,
            'professional' => $serviceRequest->professional ? [
                'id'    => $serviceRequest->professional->id,
                'name'  => $serviceRequest->professional->user->name,
                'phone' => $serviceRequest->professional->phone,
                'photo' => $serviceRequest->professional->photo,
            ] : null
        ]);
    }

    // 🔄 POLLING — Profesional consulta solicitudes disponibles en su ciudad/servicio
    public function available(Request $request)
    {
        $professional = $request->user()->professional;

        if (!$professional) {
            return response()->json([]);
        }

        // Soporta múltiples servicios (pivot) y fallback al campo legacy service_id
        $serviceIds = $professional->services()->pluck('services.id')->toArray();
        if (empty($serviceIds) && $professional->service_id) {
            $serviceIds = [$professional->service_id];
        }
        if (empty($serviceIds)) {
            return response()->json([]);
        }

        $requests = ServiceRequest::with('client', 'service', 'city')
            ->when($professional->city_id, function ($q) use ($professional) {
                // Si el profesional tiene ciudad, mostrar SRs de su ciudad o virtuales
                $q->where(function ($inner) use ($professional) {
                    $inner->where('city_id', $professional->city_id)
                          ->orWhereNull('city_id');
                });
            })
            // Si el profesional no tiene ciudad asignada, no filtra por ciudad (ve todos)
            ->whereIn('service_id', $serviceIds)
            ->where('status', 'pending')
            ->whereNull('professional_id')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($requests->map(function($r) {
            return [
                'id'           => $r->id,
                'description'  => $r->description,
                'address'      => $r->address,
                'lat'          => $r->lat,
                'lng'          => $r->lng,
                'service_date' => $r->service_date,
                'service_time' => $r->service_time,
                'budget'       => $r->budget,
                'people_count' => $r->people_count,
                'service_name' => $r->service ? $r->service->name : null,
                'client_name'  => $r->client ? $r->client->name : 'Cliente',
                'city_name'    => $r->city ? $r->city->name : 'Virtual',
            ];
        }));
    }

    // Profesional acepta una solicitud
    public function accept(Request $request, $id)
    {
        $professional = $request->user()->professional;

        $serviceRequest = ServiceRequest::where('id', $id)
            ->where('status', 'pending')
            ->whereNull('professional_id')
            ->firstOrFail();

        $serviceRequest->update([
            'professional_id' => $professional->id,
            'status'          => 'accepted',
        ]);

        $serviceName = optional($serviceRequest->service)->name ?? 'servicio';
        \App\Models\AdminLog::record(
            $request->user(),
            'update',
            'service_request',
            $serviceRequest->id,
            "Profesional {$request->user()->name} aceptó solicitud #{$serviceRequest->id} ({$serviceName})"
        );

        // Enviar email al cliente
        try {
            $serviceRequest->load(['client', 'service', 'professional.user']);
            Mail::to($serviceRequest->client->email)
                ->send(new ServiceAcceptedMail($serviceRequest));
        } catch (\Exception $e) {
            \Log::warning('Email accept failed: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Solicitud aceptada',
            'data'    => $serviceRequest
        ]);
    }

    // Solicitudes aceptadas por el profesional
    public function accepted(Request $request)
    {
        $professional = $request->user()->professional;

        if (!$professional) {
            return response()->json([]);
        }

        $requests = ServiceRequest::with('client', 'service', 'city')
            ->where('professional_id', $professional->id)
            ->whereIn('status', ['accepted', 'completed'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($requests->map(function ($r) {
            return [
                'id'           => $r->id,
                'description'  => $r->description,
                'address'      => $r->address,
                'service_date' => $r->service_date,
                'service_time' => $r->service_time,
                'budget'       => $r->budget,
                'status'       => $r->status,
                'people_count' => $r->people_count,
                'company_name' => $r->company_name,
                'service_name' => $r->service ? $r->service->name : null,
                'client_name'  => $r->client ? $r->client->name : 'Cliente',
                'client_phone' => $r->client ? $r->client->phone : null,
                'city_name'    => $r->city ? $r->city->name : null,
            ];
        }));
    }

    // Profesional ingresa el código para completar el trabajo
    public function verifyCode(Request $request, $id)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $professional   = $request->user()->professional;
        $serviceRequest = ServiceRequest::where('id', $id)
            ->where('professional_id', $professional->id)
            ->where('status', 'accepted')
            ->firstOrFail();

        if (!$serviceRequest->completion_code) {
            return response()->json([
                'message' => 'El cliente aún no ha generado el código'
            ], 422);
        }

        if (now()->gt($serviceRequest->completion_code_expires_at)) {
            return response()->json([
                'message' => 'El código ha expirado, pide al cliente que genere uno nuevo'
            ], 422);
        }

        if ($serviceRequest->completion_code !== $request->code) {
            return response()->json([
                'message' => 'Código incorrecto'
            ], 422);
        }

        $serviceRequest->update([
            'status'                     => 'completed',
            'completed_at'               => now(),
            'completion_code'            => null,
            'completion_code_expires_at' => null,
        ]);

        $serviceName = optional($serviceRequest->service)->name ?? 'servicio';
        \App\Models\AdminLog::record(
            $request->user(),
            'complete',
            'service_request',
            $serviceRequest->id,
            "Profesional {$request->user()->name} completó solicitud #{$serviceRequest->id} ({$serviceName}) con código"
        );

        // Notificar al cliente
        $serviceRequest->load(['client', 'service', 'professional.user']);
        $serviceName = optional($serviceRequest->service)->name ?? 'el servicio';
        $proName     = optional(optional($serviceRequest->professional)->user)->name ?? 'El profesional';
        Notification::send(
            $serviceRequest->client_id,
            'service_completed',
            'Servicio completado',
            "{$proName} completó {$serviceName}. ¡Califica tu experiencia!",
            $serviceRequest->id
        );

        // Enviar emails al cliente y al profesional
        try {
            $serviceRequest->load(['client', 'service', 'professional.user']);

            Mail::to($serviceRequest->client->email)
                ->send(new ServiceCompletedMail($serviceRequest, 'client'));

            Mail::to($serviceRequest->professional->user->email)
                ->send(new ServiceCompletedMail($serviceRequest, 'professional'));
        } catch (\Exception $e) {
            \Log::warning('Email completed failed: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Trabajo completado y aprobado por el cliente'
        ]);
    }

}
