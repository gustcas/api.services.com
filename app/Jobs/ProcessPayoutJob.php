<?php

namespace App\Jobs;

use App\Models\CategoryPayoutAccount;
use App\Models\PayoutAccount;
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
    public $backoff = [60, 300, 900];

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

        if ($sr->disbursement_status === 'paid') {
            Log::info("ProcessPayoutJob: #{$this->serviceRequestId} ya está pagado. Omitiendo.");
            return;
        }

        if ($sr->payout_status === 'payout_processing') {
            $sr->update(['payout_status' => 'pending_completion']);
            $sr->refresh();
        }

        $existingPayout = \App\Models\Payout::where('service_request_id', $sr->id)
            ->whereIn('status', ['paid', 'approved'])
            ->first();

        if ($existingPayout) {
            Log::info("ProcessPayoutJob: Payout ya existe para #{$sr->id} — marcando como pagado.");
            $sr->update(['disbursement_status' => 'paid', 'disbursed_at' => now()]);
            return;
        }

        $sr->update([
            'disbursement_status'   => 'processing',
            'disbursement_attempts' => $sr->disbursement_attempts + 1,
        ]);

        try {
            // 1. Dispersión al profesional (allies_percentage)
            $resultProf = $payoutsService->disburse($sr, 'auto');
            Log::info("ProcessPayoutJob: profesional resultado #{$this->serviceRequestId}: " . json_encode($resultProf));
            if (!$resultProf['success']) {
                throw new \Exception($resultProf['message'] ?? 'Error en dispersión al profesional');
            }

            // 2. Dispersión a ASECALIDAD (asecalidad_commission%)
            $service       = $sr->service;
            $budget        = (float) $sr->budget;
            $categoryId    = $service ? $service->category_id : null;
            $asecalidadPct = $service ? (float) $service->asecalidad_commission : 0;
            $imavicxPct    = $service ? (float) $service->imavicx_commission : 0;

            if ($asecalidadPct > 0) {
                $category = $sr->service->category ?? null;
                $asecalidadAccountId = $category ? $category->asecalidad_account_id : null;
                $asecalidadAccount = $asecalidadAccountId
                    ? PayoutAccount::where('id', $asecalidadAccountId)->where('is_active', true)->first()
                    : null;

                if ($asecalidadAccount) {
                    $amount = round($budget * $asecalidadPct / 100, 2);
                    $resultAsec = $payoutsService->disburseToAccount($sr, $asecalidadAccount, $amount, 'asecalidad', 'auto');
                    Log::info("ProcessPayoutJob: ASECALIDAD resultado #{$this->serviceRequestId}: " . json_encode($resultAsec));
                } else {
                    Log::warning("ProcessPayoutJob: Sin cuenta ASECALIDAD activa para SR#{$sr->id}");
                }
            }

            // 3. Dispersión a IMAVICX (imavicx_commission%)
            $maintPct = $service ? (float) $service->maintenance_percentage : 0;
            if ($maintPct > 0) {
                $manteAccount = PayoutAccount::where('entity_type', 'maintenance')
                    ->where('is_active', true)
                    ->first();
                if ($manteAccount) {
                    $amount = round($budget * $maintPct / 100, 2);
                    $resultMante = $payoutsService->disburseToAccount($sr, $manteAccount, $amount, 'maintenance', 'auto');
                    Log::info("ProcessPayoutJob: MANTENIMIENTO resultado #{$this->serviceRequestId}: " . json_encode($resultMante));
                } else {
                    Log::warning("ProcessPayoutJob: Sin cuenta MANTENIMIENTO para SR#{$sr->id}");
                }
            }

            if ($imavicxPct > 0) {
                $imavicxAccount = PayoutAccount::where('entity_type', 'imavicx')
                    ->where('is_active', true)
                    ->first();

                if ($imavicxAccount) {
                    $amount = round($budget * $imavicxPct / 100, 2);
                    $resultImavicx = $payoutsService->disburseToAccount($sr, $imavicxAccount, $amount, 'imavicx', 'auto');
                    Log::info("ProcessPayoutJob: IMAVICX resultado #{$this->serviceRequestId}: " . json_encode($resultImavicx));
                } else {
                    Log::warning("ProcessPayoutJob: Sin cuenta IMAVICX registrada para SR#{$sr->id}");
                }
            }

            $sr->update([
                'disbursement_status' => 'paid',
                'payout_status'       => 'payout_approved',
                'disbursed_at'        => now(),
                'disbursement_error'  => null,
            ]);
            Log::info("ProcessPayoutJob: Dispersión completa para #{$this->serviceRequestId}.");

        } catch (\Exception $e) {
            Log::error("ProcessPayoutJob: Error en #{$this->serviceRequestId}: " . $e->getMessage());
            $sr->update([
                'disbursement_status' => 'pending',
                'disbursement_error'  => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessPayoutJob: Falló definitivamente #{$this->serviceRequestId}: " . $exception->getMessage());
        $sr = ServiceRequest::find($this->serviceRequestId);
        if ($sr) {
            $sr->update([
                'disbursement_status' => 'failed',
                'disbursement_error'  => $exception->getMessage(),
            ]);
        }
    }
}
