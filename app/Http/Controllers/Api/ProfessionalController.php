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

        $stats = ['reservas_hoy' => 0, 'ingresos_hoy' => 0, 'servicios_activos' => 0, 'promedio_reseñas' => 0];

        if ($professional) {
            $today = now()->toDateString();

            $stats['reservas_hoy'] = \App\Models\ServiceRequest::where('professional_id', $professional->id)
                ->whereDate('service_date', $today)
                ->whereIn('status', ['accepted', 'completed'])
                ->count();

            $stats['ingresos_hoy'] = \App\Models\ServiceRequest::where('professional_id', $professional->id)
                ->whereDate('completed_at', $today)
                ->where('status', 'completed')
                ->where('payment_status', 'paid')
                ->with('service')
                ->get()
                ->sum(fn($r) => round((float)$r->budget * ((float)optional($r->service)->allies_percentage / 100), 2));

            $stats['servicios_activos'] = \App\Models\ServiceRequest::where('professional_id', $professional->id)
                ->where('status', 'accepted')
                ->count();

            $stats['promedio_reseñas'] = \App\Models\Rating::where('ratee_id', $professional->user_id)
                ->avg('score') ?? 0;
            $stats['promedio_reseñas'] = round($stats['promedio_reseñas'], 1);
            $stats['avg_rating']       = $stats['promedio_reseñas'];
            $stats['review_count']     = \App\Models\Rating::where('ratee_id', $professional->user_id)->count();
        }

        return response()->json([
            'success'                  => true,
            'needs_profile_completion' => !$professional,
            'professional'             => $professional,
            'stats'                    => $stats,
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
                'service_date' => $r->completed_at ?? $r->updated_at ?? $r->service_date,
                'amount'       => $gross,
                'allies_pct'   => $alliesPct,
                'net_amount'   => $netAmount,
                'status'       => $r->status,
                'completed_at' => $r->completed_at ?? $r->updated_at,
                'disbursement_status' => $r->disbursement_status ?? 'pending',
            ];
        });

        $totalEarned = $earnings->where('status', 'completed')->sum('net_amount');

        $startOfMonth = now()->startOfMonth();
        $sevenDaysAgo = now()->subDays(7);

        $totalThisMonth = 0;
        $totalThisWeek  = 0;
        $totalPending   = 0;

        foreach ($earnings as $e) {
            if ($e['status'] === 'completed') {
                $completedAt = $e['completed_at'] ? new \Carbon\Carbon($e['completed_at']) : null;
                // Solo contar si tiene fecha de completado en este mes
                if ($completedAt && $completedAt >= $startOfMonth) {
                    $totalThisMonth += $e['net_amount'];
                }
                if ($completedAt && $completedAt >= $sevenDaysAgo) {
                    $totalThisWeek += $e['net_amount'];
                }
            }
            if ($e['status'] === 'accepted' || ($e['status'] === 'completed' && ($e['disbursement_status'] ?? '') === 'pending')) {
                $totalPending += $e['net_amount'];
            }
        }
        return response()->json([
            'success' => true,
            'summary' => [
                'total_earned'    => round($totalEarned, 2),
                'total_jobs'      => $completed->count(),
                'pending_jobs'    => $jobs->where('status', 'accepted')->count(),
                'total_this_month'=> round($totalThisMonth, 2),
                'total_this_week' => round($totalThisWeek, 2),
                'total_pending'   => round($totalPending, 2),
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
    // Servicios asignados al profesional
    public function myServices(Request $request)
    {
        $professional = $request->user()->professional;

        if (!$professional) {
            return response()->json([]);
        }

        $services = $professional->services()->with('category')->get();

        return response()->json($services->map(function ($s) {
            return [
                'id'            => $s->id,
                'name'          => $s->name,
                'description'   => $s->description ?? '',
                'price'         => $s->price,
                'category_name' => $s->category ? $s->category->name : '—',
                'active'        => (bool) $s->is_active,
                'rating'        => null,
            ];
        }));
    }

    public function updatePhoto(Request $request)
{
    $request->validate(['photo' => 'required|image|max:5120']);
    $professional = $request->user()->professional;
    if (!$professional) return response()->json(['message' => 'Perfil no encontrado.'], 404);

    $path = $request->file('photo')->store('documents/photo', 'public');
    $professional->update(['photo' => $path]);

    return response()->json([
        'success' => true,
        'photo_url' => asset('storage/' . $path),
    ]);
}
}
