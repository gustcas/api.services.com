<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rating;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    /**
     * Cliente califica al profesional de una solicitud completada.
     */
    public function rateByClient(Request $request, $requestId)
    {
        $request->validate([
            'score'   => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        $sr = ServiceRequest::where('id', $requestId)
            ->where('client_id', $user->id)
            ->where('status', 'completed')
            ->firstOrFail();

        if (!$sr->professional_id) {
            return response()->json(['success' => false, 'message' => 'La solicitud no tiene profesional asignado.'], 422);
        }

        $professionalUser = \App\Models\User::where('id', function ($q) use ($sr) {
            $q->select('user_id')->from('professionals')->where('id', $sr->professional_id);
        })->first();

        if (!$professionalUser) {
            return response()->json(['success' => false, 'message' => 'Profesional no encontrado.'], 404);
        }

        $exists = Rating::where('service_request_id', $sr->id)
            ->where('rater_id', $user->id)
            ->where('type', 'client_to_professional')
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Ya calificaste este servicio.'], 422);
        }

        $rating = Rating::create([
            'service_request_id' => $sr->id,
            'rater_id'           => $user->id,
            'ratee_id'           => $professionalUser->id,
            'score'              => $request->score,
            'comment'            => $request->comment,
            'type'               => 'client_to_professional',
        ]);

        \App\Models\AdminLog::record(
            $user,
            'rate',
            'service_request',
            $sr->id,
            "Cliente {$user->name} calificó con {$request->score}/5 al profesional en solicitud #{$sr->id}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Calificación enviada. ¡Gracias por tu opinión!',
            'rating'  => $rating,
        ]);
    }

    /**
     * Profesional califica al cliente de una solicitud completada.
     */
    public function rateByProfessional(Request $request, $requestId)
    {
        $request->validate([
            'score'   => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        $professional = $user->professional;
        if (!$professional) {
            return response()->json(['success' => false, 'message' => 'Perfil profesional no encontrado.'], 404);
        }

        $sr = ServiceRequest::where('id', $requestId)
            ->where('professional_id', $professional->id)
            ->where('status', 'completed')
            ->firstOrFail();

        $exists = Rating::where('service_request_id', $sr->id)
            ->where('rater_id', $user->id)
            ->where('type', 'professional_to_client')
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Ya calificaste a este cliente.'], 422);
        }

        $rating = Rating::create([
            'service_request_id' => $sr->id,
            'rater_id'           => $user->id,
            'ratee_id'           => $sr->client_id,
            'score'              => $request->score,
            'comment'            => $request->comment,
            'type'               => 'professional_to_client',
        ]);

        \App\Models\AdminLog::record(
            $user,
            'rate',
            'service_request',
            $sr->id,
            "Profesional {$user->name} calificó con {$request->score}/5 al cliente en solicitud #{$sr->id}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Calificación enviada. ¡Gracias por tu opinión!',
            'rating'  => $rating,
        ]);
    }

    /**
     * Devuelve si el usuario autenticado ya calificó una solicitud y el rating existente.
     */
    public function myRating(Request $request, $requestId)
    {
        $user = $request->user();

        $rating = Rating::where('service_request_id', $requestId)
            ->where('rater_id', $user->id)
            ->first();

        return response()->json([
            'success' => true,
            'rated'   => $rating !== null,
            'rating'  => $rating,
        ]);
    }
}
