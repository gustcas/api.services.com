<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Professional;

class ProfessionalController extends Controller
{
    public function dashboard(Request $request)
    {
        $user         = $request->user();
        $professional = $user->professional;

        if ($professional) {
            $professional->service_ids = $professional->services()->pluck('services.id')->toArray();
        }

        return response()->json([
            'success'                  => true,
            'needs_profile_completion' => !$professional,
            'professional'             => $professional,
        ]);
    }

    public function storeOrUpdate(Request $request)
    {
        $user                 = $request->user();
        $existingProfessional = $user->professional;

        $request->validate([
            'category_id'    => 'required|exists:categories,id',
            'document_number'=> 'required|string',
            'service_ids'    => 'required|array|min:1',
            'service_ids.*'  => 'exists:services,id',

            'identity_card' => $existingProfessional
                ? 'nullable|file|mimes:pdf,jpg,jpeg,png'
                : 'required|file|mimes:pdf,jpg,jpeg,png',

            'professional_card' => $existingProfessional
                ? 'nullable|file|mimes:pdf,jpg,jpeg,png'
                : 'required|file|mimes:pdf,jpg,jpeg,png',

            'professional_title' => $existingProfessional
                ? 'nullable|file|mimes:pdf,jpg,jpeg,png'
                : 'required|file|mimes:pdf,jpg,jpeg,png',

            'photo' => $existingProfessional
                ? 'nullable|image|mimes:jpg,jpeg,png'
                : 'required|image|mimes:jpg,jpeg,png',

            'phone'   => 'required|string',
            'bio'     => 'nullable|string',
            'address' => 'nullable|string',
            'city_id' => 'nullable|exists:cities,id',
        ]);

        $identityPath          = $existingProfessional->identity_card    ?? null;
        $professionalCardPath  = $existingProfessional->professional_card ?? null;
        $professionalTitlePath = $existingProfessional->professional_title ?? null;
        $photoPath             = $existingProfessional->photo             ?? null;

        if ($request->hasFile('identity_card')) {
            $identityPath = $request->file('identity_card')->store('documents/identity', 'public');
        }
        if ($request->hasFile('professional_card')) {
            $professionalCardPath = $request->file('professional_card')->store('documents/professional_card', 'public');
        }
        if ($request->hasFile('professional_title')) {
            $professionalTitlePath = $request->file('professional_title')->store('documents/professional_title', 'public');
        }
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('documents/photo', 'public');
        }

        $professional = Professional::updateOrCreate(
            ['user_id' => $user->id],
            [
                'category_id'        => $request->category_id,
                'document_number'    => $request->document_number,
                'identity_card'      => $identityPath,
                'professional_card'  => $professionalCardPath,
                'professional_title' => $professionalTitlePath,
                'photo'              => $photoPath,
                'phone'              => $request->phone,
                'bio'                => $request->bio,
                'address'            => $request->address,
                'city_id'            => $request->city_id,
                'status'             => 'pending',
            ]
        );

        // Sincronizar servicios en tabla pivot
        $professional->services()->sync($request->service_ids);

        return response()->json([
            'success'      => true,
            'message'      => 'Perfil enviado correctamente. Pendiente de aprobación.',
            'professional' => $professional,
        ]);
    }

    public function earnings(Request $request)
    {
        $professional = $request->user()->professional;
        if (!$professional) {
            return response()->json(['success' => false, 'message' => 'Sin perfil profesional'], 404);
        }

        $jobs = \App\Models\ServiceRequest::with('service', 'client')
            ->where('professional_id', $professional->id)
            ->whereIn('status', ['accepted', 'completed'])
            ->orderBy('updated_at', 'desc')
            ->get();

        $commission  = 0.15;
        $completed   = $jobs->where('status', 'completed');
        $totalEarned = $completed->sum('budget') * (1 - $commission);

        $earnings = $jobs->map(function ($r) use ($commission) {
            $gross = (float) $r->budget;
            return [
                'id'           => $r->id,
                'service_name' => $r->service ? $r->service->name : 'Servicio',
                'client_name'  => $r->client ? $r->client->name : 'Cliente',
                'service_date' => $r->service_date,
                'amount'       => $gross,
                'commission'   => round($gross * $commission, 2),
                'net_amount'   => round($gross * (1 - $commission), 2),
                'status'       => $r->status,
                'completed_at' => $r->completed_at,
            ];
        });

        return response()->json([
            'success' => true,
            'summary' => [
                'total_earned'   => round($totalEarned, 2),
                'total_jobs'     => $completed->count(),
                'pending_jobs'   => $jobs->where('status', 'accepted')->count(),
                'commission_pct' => $commission * 100,
            ],
            'earnings' => $earnings,
        ]);
    }

    public function availableForClient(Request $request)
    {
        $professionals = Professional::with('user')
            ->where('city_id', $request->city_id)
            ->where('is_verified', true)
            ->whereHas('services', function ($q) use ($request) {
                $q->where('services.id', $request->service_id);
            })
            ->get();

        $result = [];
        foreach ($professionals as $p) {
            $service = \App\Models\Service::find($request->service_id);
            $result[] = [
                'id'      => $p->id,
                'name'    => $p->user->name,
                'phone'   => $p->phone,
                'photo'   => $p->photo,
                'service' => $service ? $service->name : null,
                'lat'     => null,
                'lng'     => null,
            ];
        }

        return response()->json($result);
    }
}
