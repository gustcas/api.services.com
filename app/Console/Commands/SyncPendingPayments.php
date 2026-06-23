<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\ServiceRequest;
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
        $apiKey = config('wompi.private_key');
        $apiUrl = config('wompi.api_url');

        $payments = Payment::where('status', 'pending')
            ->whereNotNull('reference')
            ->get();

        $this->info("Sincronizando {$payments->count()} pagos pendientes...");

        foreach ($payments as $payment) {
            try {
                $resp = Http::withoutVerifying()
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Accept'        => 'application/json',
                    ])
                    ->get("{$apiUrl}/v1/transactions", [
                        'reference' => $payment->reference,
                    ]);

                if (!$resp->successful()) {
                    $this->warn("Error {$payment->reference}: " . $resp->status() . ' ' . $resp->body());
                    continue;
                }

                $transactions = $resp->json('data') ?? [];
                if (empty($transactions)) {
                    $this->warn("Sin transacciones para {$payment->reference}");
                    continue;
                }

                $tx          = collect($transactions)->sortByDesc('created_at')->first();
                $wompiStatus = $tx['status'] ?? null;

                if (!$wompiStatus || $wompiStatus === 'PENDING') continue;

                $wompi->handleWebhookTransaction($tx);

                // Asegurar que el SR salga de payment_pending si fue aprobado
                if ($wompiStatus === 'APPROVED') {
                    $sr = ServiceRequest::find($payment->service_request_id);
                    if ($sr && $sr->status === 'payment_pending') {
                        $sr->update(['status' => 'pending', 'payment_status' => 'paid']);
                        $this->info("SR#{$sr->id} movido a pendiente.");
                    }
                }

                $this->info("SR#{$payment->service_request_id} - {$payment->reference}: {$wompiStatus}");

            } catch (\Exception $e) {
                Log::error("SyncPendingPayments error {$payment->reference}: " . $e->getMessage());
                $this->error("Error en {$payment->reference}: " . $e->getMessage());
            }
        }
        // Sincronizar SRs en payment_pending que ya tienen payment aprobado en BD
        $approvedPayments = Payment::where('status', 'approved')
            ->whereHas('serviceRequest', fn($q) => $q->where('status', 'payment_pending'))
            ->with('serviceRequest')
            ->get();

        foreach ($approvedPayments as $p) {
            $sr = $p->serviceRequest;
            if ($sr) {
                $sr->update(['status' => 'pending', 'payment_status' => 'paid']);
                $this->info("SR#{$sr->id} corregido de payment_pending a pending.");
            }
        }

        $this->info('Sincronización completada.');
    }
}
