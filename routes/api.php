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
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AccountController;

// ── Rutas públicas ─────────────────────────────────────────
Route::post('register', [AuthController::class, 'register']);
Route::post('login',    [AuthController::class, 'login']);
Route::get('/categories', function () {
    return \App\Models\Category::where('is_active', true)->get();
});

Route::get('/services', function () {
    return \App\Models\Service::where('is_active', true)->get();
});

Route::get('/professionals', function () {
    return \App\Models\Professional::where('status', 'approved')->get();
});

// Ciudades del dashboard
Route::get('/cities', [CityController::class, 'index']);
Route::get('/cities/{id}', [CityController::class, 'show']); // 👈 agregar

// ── Rutas autenticadas ─────────────────────────────────────
Route::middleware(['auth:api', 'active'])->group(function () {

    Route::get('profile',  [AuthController::class, 'profile']);
    Route::post('logout',  [AuthController::class, 'logout']);
    Route::put('account/settings', [AccountController::class, 'update']);

    // ── Rutas solo admin ───────────────────────────────────
    Route::middleware('admin')
            ->prefix('admin')
            ->group(function () {

                // Usuarios
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

                // Auditoría
                Route::get('logs', [AdminLogController::class, 'index']);

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

        });

    // Chat — accesible por cliente y profesional
    Route::get('/chat/unreads',        [ChatController::class, 'unreads']);
    Route::get('/chat/{requestId}',    [ChatController::class, 'index']);
    Route::post('/chat/{requestId}',   [ChatController::class, 'store']);

    Route::middleware('client')
        ->prefix('client')
        ->group(function() {
            Route::post('/service-request', [ServiceRequestController::class, 'store']);
            Route::get('/service-request/{id}/status', [ServiceRequestController::class, 'checkStatus']);
            Route::get('/professionals-available', [ProfessionalController::class, 'availableForClient']); 

            // Cliente — ver sus solicitudes
            Route::get('/requests', [ClientRequestController::class, 'index']);

            // Cliente — generar código de aprobación
            Route::post('/requests/{id}/generate-code', [ClientRequestController::class, 'generateCode']);


        });
});