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
            $professional->service_ids  = $professional->services()->pluck('services.id')->toArray();
            $professional->category_ids = $professional->categories()->pluck('categories.id')->toArray();
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
            'category_ids'   => 'required|array|min:1',
            'category_ids.*' => 'exists:categories,id',
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
                'category_id'        => $request->category_ids[0], // cache del primer
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
                'is_verified'        => false,
                'verified_at'        => null,
            ]
        );

        $professional->categories()->sync($request->category_ids);
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

        $completed = $jobs->where('status', 'completed');

        $earnings = $jobs->map(function ($r) {
            $gross       = (float) $r->budget;
            $alliesPct   = $r->service ? (float) $r->service->allies_percentage : 0;
            $totalPct    = $r->service
                ? ($alliesPct
                    + (float) $r->service->payment_gateway_commission
                    + (float) $r->service->imavicx_commission
                    + (float) $r->service->asecalidad_commission
                    + (float) $r->service->maintenance_percentage)
                : 100;

            if (abs($totalPct - 100) > 0.01) {
                \Log::warning("Service {$r->service_id} percentages sum to {$totalPct}%, expected 100%");
            }

            $netAmount = round($gross * $alliesPct / 100, 2);

            return [
                'id'           => $r->id,
                'service_name' => $r->service ? $r->service->name : 'Servicio',
                'client_name'  => $r->client ? $r->client->name : 'Cliente',
                'service_date' => $r->service_date,
                'amount'       => $gross,
                'allies_pct'   => $alliesPct,
                'net_amount'   => $netAmount,
                'status'       => $r->status,
                'completed_at' => $r->completed_at,
            ];
        });

        $totalEarned = $earnings->where('status', 'completed')->sum('net_amount');

        return response()->json([
            'success' => true,
            'summary' => [
                'total_earned' => round($totalEarned, 2),
                'total_jobs'   => $completed->count(),
                'pending_jobs' => $jobs->where('status', 'accepted')->count(),
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
