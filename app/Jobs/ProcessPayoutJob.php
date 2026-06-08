<?php

namespace App\Jobs;

use App\Models\ServiceRequest;
use App\Services\WompiPayoutsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPayoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 3;
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min entre reintentos

    protected int $serviceRequestId;

    public function __construct(int $serviceRequestId)
    {
        $this->serviceRequestId = $serviceRequestId;
    }

    public function handle(WompiPayoutsService $payoutsService): void
    {
        $sr = ServiceRequest::with([
            'service.category',
            'professional.paymentInfo',
            'professional.user',
        ])->find($this->serviceRequestId);

        if (!$sr) {
            Log::warning("ProcessPayoutJob: ServiceRequest #{$this->serviceRequestId} no encontrado.");
            return;
        }

        // Evitar doble dispersión
        if (in_array($sr->disbursement_status, ['processing', 'paid'])) {
            Log::info("ProcessPayoutJob: #{$this->serviceRequestId} ya está en {$sr->disbursement_status}. Omitiendo.");
            return;
        }

        // Resetear payout_status si quedó atascado en payout_processing
        if ($sr->payout_status === 'payout_processing') {
            $sr->update(['payout_status' => 'pending_completion']);
            $sr->refresh();
        }

        // Verificar con Wompi si ya existe un payout para esta solicitud
        $existingPayout = \App\Models\Payout::where('service_request_id', $sr->id)
            ->whereIn('status', ['paid', 'approved'])
            ->first();

        if ($existingPayout) {
            Log::info("ProcessPayoutJob: Payout ya existe para #{$sr->id} — marcando como pagado sin reintentar.");
            $sr->update([
                'disbursement_status' => 'paid',
                'disbursed_at'        => now(),
            ]);
            return;
        }

        // Marcar como procesando para evitar duplicados
        $sr->update([
            'disbursement_status'   => 'processing',
            'disbursement_attempts' => $sr->disbursement_attempts + 1,
        ]);

        try {
            $result = $payoutsService->disburse($sr, 'auto');

            Log::info("ProcessPayoutJob: resultado para #{$this->serviceRequestId}: " . json_encode($result));

            if ($result['success']) {
                $updated = $sr->update([
                    'disbursement_status' => 'paid',
                    'disbursed_at'        => now(),
                    'disbursement_error'  => null,
                ]);
                Log::info("ProcessPayoutJob: update result: " . ($updated ? 'OK' : 'FAIL') . " para #{$this->serviceRequestId}.");
                Log::info("ProcessPayoutJob: Dispersión exitosa para #{$this->serviceRequestId}.");
            } else {
                throw new \Exception($result['message'] ?? 'Error desconocido en dispersión');
            }
        } catch (\Exception $e) {
            Log::error("ProcessPayoutJob: Error en #{$this->serviceRequestId}: " . $e->getMessage());

            $sr->update([
                'disbursement_status' => 'pending',
                'disbursement_error'  => $e->getMessage(),
            ]);

            // Relanzar para que la queue reintente
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessPayoutJob: Falló definitivamente #{$this->serviceRequestId} después de {$this->tries} intentos: " . $exception->getMessage());

        $sr = ServiceRequest::find($this->serviceRequestId);
        if ($sr) {
            $sr->update([
                'disbursement_status' => 'failed',
                'disbursement_error'  => $exception->getMessage(),
            ]);
        }
    }
}
