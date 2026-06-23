<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\WompiCheckoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncPendingPayments extends Command
{
    protected $signature   = 'payments:sync-pending';
    protected $description = 'Sincroniza pagos pendientes consultando Wompi directamente';

    public function handle(WompiCheckoutService $wompi): void
    {
        $apiKey  = config('wompi.private_key');
        $apiUrl  = config('wompi.api_url');

        $payments = Payment::where('status', 'pending')
            ->whereNotNull('reference')
            ->get();

        $this->info("Sincronizando {$payments->count()} pagos pendientes...");

        foreach ($payments as $payment) {
            try {
                $resp = Http::withoutVerifying()
                    ->withToken($apiKey)
                    ->get("{$apiUrl}/transactions", ['reference' => $payment->reference]);

                if (!$resp->successful()) continue;

                $transactions = $resp->json('data') ?? [];
                if (empty($transactions)) continue;

                // Tomar la transacción más reciente
                $tx = collect($transactions)->sortByDesc('created_at')->first();
                $wompiStatus = $tx['status'] ?? null;

                if (!$wompiStatus || $wompiStatus === 'PENDING') continue;

                $wompi->handleWebhookTransaction($tx);
                $this->info("SR#{$payment->service_request_id} - {$payment->reference}: {$wompiStatus}");

            } catch (\Exception $e) {
                Log::error("SyncPendingPayments error {$payment->reference}: " . $e->getMessage());
                $this->error("Error en {$payment->reference}: " . $e->getMessage());
            }
        }

        $this->info('Sincronización completada.');
    }
}
