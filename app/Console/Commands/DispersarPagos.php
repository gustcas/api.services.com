<?php

namespace App\Console\Commands;

use App\Models\PayoutAccount;
use App\Models\ServiceRequest;
use App\Services\WompiPayoutsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispersarPagos extends Command
{
    protected $signature   = 'pagos:dispersar';
    protected $description = 'Dispersa los pagos acumulados a todas las cuentas cada martes';

    public function handle(WompiPayoutsService $payoutsService): void
    {
        $this->info('Iniciando dispersión acumulada de pagos...');
        Log::info('DispersarPagos: Iniciando.');

        // Buscar todas las solicitudes completadas, pagadas y pendientes de dispersión
        $solicitudes = ServiceRequest::with(['service.category', 'professional.paymentInfo', 'professional.user'])
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->where('disbursement_status', 'pending')
            ->get();

        if ($solicitudes->isEmpty()) {
            $this->info('No hay solicitudes pendientes de dispersar.');
            Log::info('DispersarPagos: Sin solicitudes pendientes.');
            return;
        }

        // ── Anti doble pago: excluir SRs que ya tienen payout en cualquier estado ──
        $srIds = $solicitudes->pluck('id')->toArray();
        $srIdsConPayout = \App\Models\Payout::whereIn('service_request_id', $srIds)
            ->whereIn('status', ['processing', 'approved', 'failed'])
            ->pluck('service_request_id')
            ->unique()
            ->toArray();

        if (!empty($srIdsConPayout)) {
            $this->warn('Excluyendo ' . count($srIdsConPayout) . ' SRs que ya tienen payouts registrados.');
            $solicitudes = $solicitudes->whereNotIn('id', $srIdsConPayout);
        }

        if ($solicitudes->isEmpty()) {
            $this->info('Todos los pendientes ya tienen payouts registrados.');
            return;
        }

        // ── Acumular por PROFESIONAL ─────────────────────────────────
        $porProfesional = [];
        foreach ($solicitudes as $sr) {
            $profId = $sr->professional_id;
            if (!$profId || !$sr->professional) continue;
            $budget    = (float) $sr->budget;
            $allies    = $sr->service ? (float) $sr->service->allies_percentage : 0;
            $neto      = round($budget * $allies / 100, 2);
            if (!isset($porProfesional[$profId])) {
                $porProfesional[$profId] = ['paymentInfo' => $sr->professional->paymentInfo, 'total' => 0, 'ids' => []];
            }
            $porProfesional[$profId]['total'] += $neto;
            $porProfesional[$profId]['ids'][]  = $sr->id;
        }

        // ── Acumular por ASECALIDAD (por cuenta asignada a la categoría) ──
        $porAsecalidad = [];
        foreach ($solicitudes as $sr) {
            $category  = $sr->service ? ($sr->service->category ?? null) : null;
            $accountId = $category ? $category->asecalidad_account_id : null;
            if (!$accountId) continue;
            $budget    = (float) $sr->budget;
            $pct       = $sr->service ? (float) $sr->service->asecalidad_commission : 0;
            $monto     = round($budget * $pct / 100, 2);
            if (!isset($porAsecalidad[$accountId])) {
                $porAsecalidad[$accountId] = ['account_id' => $accountId, 'total' => 0, 'ids' => []];
            }
            $porAsecalidad[$accountId]['total'] += $monto;
            $porAsecalidad[$accountId]['ids'][]  = $sr->id;
        }

        // ── Acumular IMAVICX ─────────────────────────────────────────
        $totalImavicx = 0;
        $idsImavicx   = [];
        foreach ($solicitudes as $sr) {
            $budget = (float) $sr->budget;
            $pct    = $sr->service ? (float) $sr->service->imavicx_commission : 0;
            $totalImavicx += round($budget * $pct / 100, 2);
            $idsImavicx[]  = $sr->id;
        }

        // ── Acumular MANTENIMIENTO ───────────────────────────────────
        $totalMante = 0;
        $idsMante   = [];
        foreach ($solicitudes as $sr) {
            $budget = (float) $sr->budget;
            $pct    = $sr->service ? (float) $sr->service->maintenance_percentage : 0;
            $totalMante += round($budget * $pct / 100, 2);
            $idsMante[]  = $sr->id;
        }

        // ── Marcar todas como processing ─────────────────────────────
        ServiceRequest::whereIn('id', $solicitudes->pluck('id')->toArray())
            ->update(['disbursement_status' => 'processing', 'payout_status' => 'payout_processing']);

        // ── Dispersar a cada PROFESIONAL ─────────────────────────────
        foreach ($porProfesional as $profId => $data) {
            if ($data['total'] <= 0 || !$data['paymentInfo']) {
                Log::warning("DispersarPagos: Profesional #{$profId} sin datos bancarios o monto cero.");
                continue;
            }
            $result = $payoutsService->disburseAccumulated(
                $data['ids'], $data['paymentInfo'], $data['total'], 'professional', 'auto'
            );
            $this->info("Profesional #{$profId}: \${$data['total']} — " . ($result['success'] ? 'OK' : $result['message']));
            Log::info("DispersarPagos: Profesional #{$profId} \${$data['total']}: " . json_encode($result));
        }

        // ── Dispersar a ASECALIDAD ───────────────────────────────────
        foreach ($porAsecalidad as $accountId => $data) {
            if ($data['total'] <= 0) continue;
            $account = PayoutAccount::find($accountId);
            if (!$account || !$account->is_active) {
                Log::warning("DispersarPagos: Cuenta ASECALIDAD #{$accountId} no encontrada o inactiva.");
                continue;
            }
            $result = $payoutsService->disburseAccumulatedToAccount(
                $data['ids'], $account, $data['total'], 'asecalidad', 'auto'
            );
            $this->info("ASECALIDAD #{$accountId}: \${$data['total']} — " . ($result['success'] ? 'OK' : $result['message']));
            Log::info("DispersarPagos: ASECALIDAD \${$data['total']}: " . json_encode($result));
        }

        // ── Dispersar a IMAVICX ──────────────────────────────────────
        if ($totalImavicx > 0) {
            $imavicxAccount = PayoutAccount::where('entity_type', 'imavicx')->where('is_active', true)->first();
            if ($imavicxAccount) {
                $result = $payoutsService->disburseAccumulatedToAccount(
                    $idsImavicx, $imavicxAccount, $totalImavicx, 'imavicx', 'auto'
                );
                $this->info("IMAVICX: \${$totalImavicx} — " . ($result['success'] ? 'OK' : $result['message']));
                Log::info("DispersarPagos: IMAVICX \${$totalImavicx}: " . json_encode($result));
            } else {
                Log::warning("DispersarPagos: Sin cuenta IMAVICX activa.");
                $this->warn("Sin cuenta IMAVICX activa.");
            }
        }

        // ── Dispersar a MANTENIMIENTO ────────────────────────────────
        if ($totalMante > 0) {
            $manteAccount = PayoutAccount::where('entity_type', 'maintenance')->where('is_active', true)->first();
            if ($manteAccount) {
                $result = $payoutsService->disburseAccumulatedToAccount(
                    $idsMante, $manteAccount, $totalMante, 'maintenance', 'auto'
                );
                $this->info("MANTENIMIENTO: \${$totalMante} — " . ($result['success'] ? 'OK' : $result['message']));
                Log::info("DispersarPagos: MANTENIMIENTO \${$totalMante}: " . json_encode($result));
            } else {
                Log::warning("DispersarPagos: Sin cuenta MANTENIMIENTO activa.");
                $this->warn("Sin cuenta MANTENIMIENTO activa.");
            }
        }

        // ── Marcar todas como pagadas ─────────────────────────────────
        ServiceRequest::whereIn('id', $solicitudes->pluck('id')->toArray())
            ->update([
                'disbursement_status' => 'paid',
                'payout_status'       => 'payout_approved',
                'disbursed_at'        => now(),
                'disbursement_error'  => null,
            ]);

        $this->info("Dispersión completada: {$solicitudes->count()} solicitudes procesadas.");
        Log::info("DispersarPagos: Completado. {$solicitudes->count()} solicitudes.");
    }
}
