<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
            'budget'                 => $request->budget,
            'city_id'                => $request->city_id,
        ]);

        return response()->json([
            'message' => 'Solicitud creada correctamente',
            'data' => $serviceRequest
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
            ->where(function ($q) use ($professional) {
                $q->where('city_id', $professional->city_id)
                  ->orWhereNull('city_id');
            })
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
