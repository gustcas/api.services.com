<?php

namespace App\Console\Commands;

use App\Models\Payout;
use App\Services\WompiPayoutsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPendingPayouts extends Command
{
    protected $signature   = 'payouts:sync-pending';
    protected $description = 'Sincroniza payouts pendientes consultando Wompi directamente';

    public function handle(WompiPayoutsService $service): void
    {
        $apiKey = config('wompi.payouts_api_key');
        $userId = config('wompi.payouts_user_id');
        $apiUrl = config('wompi.payouts_api_url');

        if (!$apiKey || !$userId) {
            $this->warn('Sin credenciales Wompi Payouts.');
            return;
        }

        $headers = [
            'x-api-key'         => $apiKey,
            'user-principal-id' => $userId,
            'Accept'            => 'application/json',
        ];

        $huerfanos = Payout::where('status', 'processing')
            ->whereNull('wompi_payout_id')
            ->get();

        if ($huerfanos->count() > 0) {
            $this->info("Recuperando ID de {$huerfanos->count()} payouts sin wompi_payout_id...");
            try {
                $listResp = Http::withoutVerifying()->withHeaders($headers)->get("{$apiUrl}/payouts");
                $wompiPayouts = collect($listResp->json('data.records') ?? []);

                foreach ($huerfanos as $payout) {
                    $match = $wompiPayouts->firstWhere('reference', $payout->reference);
                    if ($match && !empty($match['id'])) {
                        $payout->update(['wompi_payout_id' => $match['id']]);
                        $this->info("Payout #{$payout->id}: recuperado wompi_payout_id = {$match['id']}");
                        Log::info("SyncPendingPayouts: recuperado wompi_payout_id para Payout #{$payout->id} = {$match['id']}");
                    } else {
                        $this->warn("Payout #{$payout->id}: no se encontró coincidencia por reference '{$payout->reference}'.");
                    }
                }
            } catch (\Exception $e) {
                $this->error('Error recuperando IDs huérfanos: ' . $e->getMessage());
                Log::error('SyncPendingPayouts: error recuperando IDs huérfanos: ' . $e->getMessage());
            }
        }

        $payouts = Payout::where('status', 'processing')
            ->whereNotNull('wompi_payout_id')
            ->whereNotIn('wompi_status', ['SIMULATED'])
            ->get();

        $this->info("Sincronizando {$payouts->count()} payouts pendientes...");

        foreach ($payouts as $payout) {
            try {
                $resp = Http::withoutVerifying()
                    ->withHeaders($headers)
                    ->get("{$apiUrl}/payouts/{$payout->wompi_payout_id}");

                if (!$resp->successful()) {
                    $this->warn("Error consultando payout #{$payout->id}: " . $resp->status());
                    continue;
                }

                $data   = $resp->json('data') ?? $resp->json();
                $status = $data['status'] ?? null;

                if (!$status) continue;

                $payout->update([
                    'wompi_status' => $status,
                    'status'       => in_array($status, ['TOTAL_PAYMENT', 'APPROVED']) ? 'approved'
                                   : (in_array($status, ['DECLINED', 'ERROR', 'FAILED', 'PARTIAL_PAYMENT']) ? 'failed' : 'processing'),
                    'paid_at'      => in_array($status, ['TOTAL_PAYMENT', 'APPROVED']) ? now() : null,
                ]);

                $this->info("Payout #{$payout->id} SR#{$payout->service_request_id}: {$status}");
                Log::info("SyncPendingPayouts: Payout #{$payout->id} → {$status}");

            } catch (\Exception $e) {
                $this->error("Error payout #{$payout->id}: " . $e->getMessage());
                Log::error("SyncPendingPayouts error #{$payout->id}: " . $e->getMessage());
            }
        }

        $this->info('Sincronización completada.');
    }
}
