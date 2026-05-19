<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Professional;
use App\Models\Rating;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class ClientProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'name'  => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $request->user()->id,
            'phone' => 'sometimes|nullable|string|max:30',
            'city'  => 'sometimes|nullable|string|max:100',
        ]);

        $user = $request->user();
        $user->fill($request->only(['name', 'email', 'phone', 'city']));
        $user->save();

        return response()->json(['user' => $user]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Contraseña actual incorrecta'], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }

    public function notifications(Request $request)
    {
        $list = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'body'       => $n->body,
                'related_id' => $n->related_id,
                'read_at'    => $n->read_at,
                'created_at' => $n->created_at,
            ]);

        return response()->json(['data' => $list]);
    }

    public function notificationsReadAll(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function savedCard(Request $request)
    {
        return response()->json(null);
    }

    public function favorites(Request $request)
    {
        return response()->json(['data' => []]);
    }

    public function unreadCounts(Request $request)
    {
        $userId = $request->user()->id;

        $unreadNotifications = Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        $unreadMessages = ChatMessage::whereHas(
            'serviceRequest',
            fn($q) => $q->where('client_id', $userId)
        )
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->count();

        $pendingRequests = ServiceRequest::where('client_id', $userId)
            ->where('status', 'payment_pending')
            ->count();

        return response()->json([
            'notifications'   => $unreadNotifications,
            'messages'        => $unreadMessages,
            'pending_requests'=> $pendingRequests,
        ]);
    }

    public function featuredProfessionals(Request $request)
    {
        $pros = Professional::with(['user', 'services'])
            ->where('status', 'active')
            ->get()
            ->map(function ($pro) {
                $avgRating = Rating::where('ratee_id', $pro->user_id)
                    ->where('type', 'client_to_professional')
                    ->avg('score');
                $completedJobs = ServiceRequest::where('professional_id', $pro->id)
                    ->where('status', 'completed')
                    ->count();
                return [
                    'id'        => $pro->id,
                    'name'      => $pro->user?->name ?? 'Profesional',
                    'specialty' => $pro->services->first()?->name ?? '',
                    'rating'    => $avgRating ? round((float) $avgRating, 1) : 0.0,
                    'jobs'      => $completedJobs,
                    'photo'     => $pro->photo ?? null,
                ];
            })
            ->sortByDesc('jobs')
            ->values()
            ->take(5);

        return response()->json(['data' => $pros]);
    }

    public function balance(Request $request)
    {
        $totalPaid = Payment::where('client_id', $request->user()->id)
            ->where('status', 'approved')
            ->sum('amount_in_cents');

        return response()->json([
            'balance'   => 0,
            'total_paid'=> (int) $totalPaid,
        ]);
    }

    public function sendOtp(Request $request)
    {
        $user = $request->user();

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->otp_code       = $code;
        $user->otp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        $html = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:32px 24px;background:#f8fafc;border-radius:16px">'
            . '<h2 style="color:#0f172a;margin-bottom:4px">Confirmar pago</h2>'
            . '<p style="color:#64748b;margin-bottom:24px">Usa el siguiente código para confirmar tu pago en <strong>e-Service</strong>. Expira en <strong>10 minutos</strong>.</p>'
            . '<div style="background:#fff;border:2px solid #e2e8f0;border-radius:12px;padding:24px;text-align:center;letter-spacing:12px;font-size:36px;font-weight:900;color:#2563ff">'
            . $code
            . '</div>'
            . '<p style="color:#94a3b8;font-size:12px;margin-top:24px">Si no realizaste esta acción, ignora este correo.</p>'
            . '</div>';

        Mail::html($html, function ($message) use ($user) {
            $message->to($user->email, $user->name)
                    ->subject('Tu código de verificación — e-Service');
        });

        return response()->json(['ok' => true, 'message' => 'Código enviado al correo']);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        $user = $request->user();

        if (!$user->otp_code || !$user->otp_expires_at) {
            return response()->json(['message' => 'No hay un código activo. Solicita uno nuevo.'], 422);
        }

        if (Carbon::now()->isAfter($user->otp_expires_at)) {
            $user->otp_code = null; $user->otp_expires_at = null; $user->save();
            return response()->json(['message' => 'El código ha expirado. Solicita uno nuevo.'], 422);
        }

        if ($user->otp_code !== $request->code) {
            return response()->json(['message' => 'Código incorrecto. Verifica e intenta de nuevo.'], 422);
        }

        $user->otp_code = null; $user->otp_expires_at = null; $user->save();

        return response()->json(['ok' => true]);
    }

    public function chargeSavedCard(Request $request)
    {
        return response()->json(['message' => 'No hay tarjeta guardada'], 422);
    }
}
