<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\ProfessionalController;
use App\Http\Controllers\Api\ServiceRequestController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\WorkEvidenceController;
use App\Http\Controllers\Api\ClientRequestController;
use App\Http\Controllers\Api\AdminLogController;
use App\Http\Controllers\Api\SubAdminController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\AdminSupportController;
use App\Http\Controllers\Api\AdminSettingController;
use App\Http\Controllers\Api\ClientSupportController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\LiveServicesController;
use App\Http\Controllers\Api\WompiCheckoutController;
use App\Http\Controllers\Api\WompiPayoutsController;
use App\Http\Controllers\Api\ProfessionalPaymentInfoController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\ClientProfileController;

// ── Wompi webhooks (públicos, sin CSRF ni autenticación) ───
Route::post('wompi/webhook',         [WompiCheckoutController::class,  'webhook']);
Route::post('wompi/payouts-webhook', [WompiPayoutsController::class,   'webhook']);

// ── Rutas públicas ─────────────────────────────────────────
Route::post('register', [AuthController::class, 'register']);
Route::post('login',    [AuthController::class, 'login']);
Route::get('/categories', function () {
    return \App\Models\Category::where('is_active', true)
        ->withCount('services')
        ->get();
});

Route::get('/services', function () {
    return \App\Models\Service::where('is_active', true)->get();
});

Route::get('/professionals', function () {
    return \App\Models\Professional::where('status', 'approved')->get();
});

// Ciudades del dashboard
Route::get('/cities', [CityController::class, 'index']);
Route::get('/cities/{id}', [CityController::class, 'show']);

// Proxy geocodificación
Route::get('/geocode', function (\Illuminate\Http\Request $request) {
    $type = $request->query('type', 'search');

    if ($type === 'reverse') {
        // Nominatim para geocodificación inversa (pin → dirección)
        $resp = \Illuminate\Support\Facades\Http::withoutVerifying()
            ->withHeaders(['Accept-Language' => 'es', 'User-Agent' => 'iService/1.0'])
            ->get('https://nominatim.openstreetmap.org/reverse', [
                'format'         => 'json',
                'lat'            => $request->query('lat'),
                'lon'            => $request->query('lng'),
                'addressdetails' => '1',
            ]);
        return response()->json($resp->json());
    }

    // Photon (Komoot) — normalizar query antes de buscar
    $raw = trim($request->query('q', ''));

    // Expandir abreviaturas colombianas y quitar # (Photon no los maneja bien)
    $normalized = $raw;
    $normalized = preg_replace('/\bCl\.?\s*/i',  'Calle ',         $normalized);
    $normalized = preg_replace('/\bCra?\.?\s*/i', 'Carrera ',       $normalized);
    $normalized = preg_replace('/\bKr\.?\s*/i',   'Carrera ',       $normalized);
    $normalized = preg_replace('/\bAv\.?\s*/i',   'Avenida ',       $normalized);
    $normalized = preg_replace('/\bDg\.?\s*/i',   'Diagonal ',      $normalized);
    $normalized = preg_replace('/\bTv\.?\s*/i',   'Transversal ',   $normalized);
    $normalized = str_replace('#', ' ', $normalized);
    $normalized = preg_replace('/\s+/', ' ', trim($normalized));

    // Agregar Colombia si no está
    if (stripos($normalized, 'colombia') === false) {
        $normalized .= ', Colombia';
    }

    $resp = \Illuminate\Support\Facades\Http::withoutVerifying()
        ->withHeaders(['User-Agent' => 'iService/1.0'])
        ->get('https://photon.komoot.io/api/', [
            'q'     => $normalized,
            'limit' => (int) $request->query('limit', 6),
        ]);

    $features = $resp->json('features') ?? [];
    $results  = array_map(function ($f) {
        $p        = $f['properties'] ?? [];
        $geo      = $f['geometry']['coordinates'] ?? [0, 0];
        $name     = $p['name']        ?? '';
        $street   = $p['street']      ?? $name;
        $housenum = $p['housenumber'] ?? '';
        $locality = $p['locality']    ?? '';
        $district = $p['district']    ?? '';
        $city     = $p['city']        ?? $p['state'] ?? '';

        // Si tiene número → "Calle X # N"; si es calle → usar name
        $road    = $housenum ? trim("$street # $housenum") : ($p['type'] === 'street' ? $name : $street);
        $suburb  = $locality ?: $district;
        $display = implode(', ', array_filter([$road, $suburb, $city]));

        return [
            'lat'          => (float) $geo[1],
            'lon'          => (float) $geo[0],
            'display_name' => $display ?: $name,
            'address'      => [
                'road'         => $road,
                'house_number' => $housenum,
                'suburb'       => $suburb,
                'city'         => $city,
            ],
        ];
    }, $features);

    return response()->json($results);
});

// ── Rutas autenticadas ─────────────────────────────────────
Route::middleware(['auth:api', 'active'])->group(function () {

    Route::get('profile',   [AuthController::class, 'profile']);
    Route::post('logout',   [AuthController::class, 'logout']);
    Route::put('account/settings', [AccountController::class, 'update']);
    Route::get('heartbeat', fn() => response()->json(['ok' => true]));

    // ── Rutas solo admin ───────────────────────────────────
    Route::middleware('admin')
            ->prefix('admin')
            ->group(function () {

                // Usuarios
                Route::get('users/export',                     [AdminUserController::class, 'export']);
                Route::get('users/specialties',                [AdminUserController::class, 'specialties']);
                Route::get('users',                            [AdminUserController::class, 'index']);
                Route::get('users/{user}',                     [AdminUserController::class, 'show']);
                Route::put('users/{user}',                     [AdminUserController::class, 'update']);
                Route::patch('users/{user}/toggle-status',     [AdminUserController::class, 'toggleStatus']);
                Route::post('users/bulk',                      [AdminUserController::class, 'bulk']);

                // Stats del dashboard
                Route::get('stats',                            [AdminUserController::class, 'stats']);

                // Verificar profesional
                Route::patch('users/{user}/verify-professional', [AdminUserController::class, 'verifyProfessional']);

                // Sub-admins
                Route::get('sub-admins',          [SubAdminController::class, 'index']);
                Route::post('sub-admins',         [SubAdminController::class, 'store']);
                Route::put('sub-admins/{user}',   [SubAdminController::class, 'update']);
                Route::delete('sub-admins/{user}',[SubAdminController::class, 'destroy']);

                // Reportes
                Route::get('reports', [AdminReportController::class, 'index']);

                // Configuración
                Route::get('settings',  [AdminSettingController::class, 'index']);
                Route::put('settings',  [AdminSettingController::class, 'update']);

                // Soporte
                Route::get('support',                    [AdminSupportController::class, 'index']);
                Route::get('support/{id}',               [AdminSupportController::class, 'show']);
                Route::patch('support/{id}/reply',       [AdminSupportController::class, 'reply']);
                Route::patch('support/{id}/status',      [AdminSupportController::class, 'updateStatus']);

                // Auditoría
                Route::get('logs', [AdminLogController::class, 'index']);

                // Pagos al cliente (checkout)
                Route::get('payments',          [WompiCheckoutController::class, 'adminPayments']);
                Route::get('payments/stats',    [WompiCheckoutController::class, 'paymentStats']);
                Route::get('payments/pending-payouts', [WompiCheckoutController::class, 'pendingPayouts']);
                // Solo DEV: simular pago aprobado en una solicitud
                Route::post('payments/{serviceRequestId}/simulate', [WompiCheckoutController::class, 'simulatePayment']);

                // Dispersiones al profesional (payouts)
                Route::prefix('payouts')->group(function () {
                    Route::get('/',                   [WompiPayoutsController::class, 'index']);
                    Route::get('/{id}',               [WompiPayoutsController::class, 'show']);
                    Route::post('/{serviceRequestId}/disburse', [WompiPayoutsController::class, 'disburse']);
                });

                // Servicios en Vivo
                Route::prefix('live-services')->group(function () {
                    Route::get('summary',                    [LiveServicesController::class, 'summary']);
                    Route::get('requests',                   [LiveServicesController::class, 'requests']);
                    Route::get('connected-users',            [LiveServicesController::class, 'connectedUsers']);
                    Route::get('chats',                      [LiveServicesController::class, 'chats']);
                    Route::get('incidents',                  [LiveServicesController::class, 'incidents']);
                    Route::get('chat/{requestId}/messages',       [LiveServicesController::class, 'chatMessages']);
                    Route::get('requests/{requestId}/available-professionals', [LiveServicesController::class, 'availableProfessionals']);
                    Route::post('requests/{requestId}/reassign', [LiveServicesController::class, 'reassign']);
                    Route::get('requests/{requestId}/evidences', [WorkEvidenceController::class, 'index']);
                });

                // Categoria del dashboard
                Route::get('/categories',[CategoryController::class,'index']);

                Route::post('/categories',[CategoryController::class,'store']);

                Route::put('/categories/{category}',[CategoryController::class,'update']);

                Route::delete('/categories/{category}',[CategoryController::class,'destroy']);

                // Servicios del dashboard
                Route::post('/services',[ServiceController::class,'store']);

                Route::put('/services/{service}',[ServiceController::class,'update']);

                Route::delete('/services/{service}',[ServiceController::class,'destroy']);

                
            });

    Route::middleware('professional')
        ->prefix('professional')
        ->group(function () {

            Route::get('/dashboard', [ProfessionalController::class, 'dashboard']);
            Route::get('/earnings',  [ProfessionalController::class, 'earnings']);
            Route::post('/profile', [ProfessionalController::class, 'storeOrUpdate']);

            // Datos bancarios para dispersión de pagos
            Route::get('/payment-info',  [ProfessionalPaymentInfoController::class, 'show']);
            Route::post('/payment-info', [ProfessionalPaymentInfoController::class, 'store']);
            Route::get('/banks',         [ProfessionalPaymentInfoController::class, 'banks']);

            Route::get('/requests/available', [ServiceRequestController::class, 'available']);
            Route::post('/requests/{id}/accept', [ServiceRequestController::class, 'accept']);

            // Solicitudes aceptadas del profesional
            Route::get('/requests/accepted', [ServiceRequestController::class, 'accepted']);

            // Evidencias
            Route::get('/requests/{id}/evidences',    [WorkEvidenceController::class, 'index']);
            Route::post('/requests/{id}/evidences',   [WorkEvidenceController::class, 'store']);
            Route::post('/requests/{id}/complete',    [WorkEvidenceController::class, 'complete']);

            // Profesional — verificar código e ingresar como completado
            Route::post('/requests/{id}/verify-code', [ServiceRequestController::class, 'verifyCode']);

            // Generar documentos Word de capacitación
            Route::get('/requests/{id}/document/{doc}', [DocumentController::class, 'generate']);

            // Calificaciones — profesional califica al cliente
            Route::post('/requests/{id}/rate-client', [RatingController::class, 'rateByProfessional']);
            Route::get('/requests/{id}/my-rating', [RatingController::class, 'myRating']);

        });

    // Chat — accesible por cliente y profesional
    Route::get('/chat/unreads',        [ChatController::class, 'unreads']);
    Route::get('/chat/{requestId}',    [ChatController::class, 'index']);
    Route::post('/chat/{requestId}',   [ChatController::class, 'store']);

    Route::middleware('client')
        ->prefix('client')
        ->group(function() {
            // Perfil del cliente
            Route::get('/profile',  [ClientProfileController::class, 'show']);
            Route::put('/profile',  [ClientProfileController::class, 'update']);
            Route::put('/password', [ClientProfileController::class, 'changePassword']);

            // Notificaciones
            Route::get('/notifications',          [ClientProfileController::class, 'notifications']);
            Route::post('/notifications/read-all',[ClientProfileController::class, 'notificationsReadAll']);
            Route::get('/unread-counts',           [ClientProfileController::class, 'unreadCounts']);

            // Dashboard
            Route::get('/featured-professionals', [ClientProfileController::class, 'featuredProfessionals']);
            Route::get('/balance',                [ClientProfileController::class, 'balance']);

            // Tarjeta guardada (stub)
            Route::get('/saved-card', [ClientProfileController::class, 'savedCard']);

            // Favoritos
            Route::get('/favorites', [ClientProfileController::class, 'favorites']);

            // OTP para pago
            Route::post('/payment/send-otp',        [ClientProfileController::class, 'sendOtp']);
            Route::post('/payment/verify-otp',       [ClientProfileController::class, 'verifyOtp']);
            Route::post('/payment/charge-saved-card',[ClientProfileController::class, 'chargeSavedCard']);

            Route::post('/service-request', [ServiceRequestController::class, 'store']);
            Route::get('/service-request/{id}/status', [ServiceRequestController::class, 'checkStatus']);
            Route::get('/professionals-available', [ProfessionalController::class, 'availableForClient']);

            // Cliente — ver sus solicitudes
            Route::get('/requests', [ClientRequestController::class, 'index']);

            // Cliente — ver evidencias de una solicitud
            Route::get('/requests/{id}/evidences', [WorkEvidenceController::class, 'clientIndex']);

            // Cliente — cancelar solicitud con pago pendiente
            Route::delete('/requests/{id}', [ClientRequestController::class, 'cancel']);

            // Cliente — generar código de aprobación
            Route::post('/requests/{id}/generate-code', [ClientRequestController::class, 'generateCode']);

            // Wompi — iniciar pago y consultar estado
            Route::post('/payment/init',    [WompiCheckoutController::class, 'initPayment']);
            Route::post('/payment/confirm', [WompiCheckoutController::class, 'confirmPayment']);
            Route::get('/payment/status',   [WompiCheckoutController::class, 'checkPayment']);
            Route::get('/payment/acceptance-token', [WompiCheckoutController::class, 'acceptanceToken']);

            // Soporte — cliente crea y consulta sus tickets
            Route::get('/support',  [ClientSupportController::class, 'index']);
            Route::post('/support', [ClientSupportController::class, 'store']);

            // Calificaciones — cliente califica al profesional
            Route::post('/requests/{id}/rate', [RatingController::class, 'rateByClient']);
            Route::get('/requests/{id}/my-rating', [RatingController::class, 'myRating']);

            // Acta de capacitación — descarga para el cliente
            Route::get('/requests/{id}/document/{doc}', [DocumentController::class, 'generateForClient']);
        });
});