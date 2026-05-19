<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WompiCheckoutService
{
    private string $apiUrl;
    private string $checkoutUrl;
    private string $publicKey;
    private string $privateKey;
    private string $integrityKey;
    private string $eventsSecret;

    public function __construct()
    {
        $this->apiUrl       = config('wompi.api_url');
        $this->checkoutUrl  = config('wompi.checkout_url');
        $this->publicKey    = config('wompi.public_key');
        $this->privateKey   = config('wompi.private_key');
        $this->integrityKey = config('wompi.integrity_key');
        $this->eventsSecret = config('wompi.events_secret');
    }

    /**
     * Crea un Payment pendiente y devuelve la URL de redirección a Wompi.
     * Elimina intentos pendientes anteriores para evitar duplicados en la tabla.
     */
    public function createCheckoutUrl(ServiceRequest $sr): array
    {
        $amountInCents = (int) round($sr->budget * 100);
        $reference     = 'ESERV-' . $sr->id . '-' . time();
        $redirectUrl   = rtrim(config('wompi.frontend_url'), '/') . '/cliente?payment=done';

        // Calcular firma de integridad: SHA256(reference + amountInCents + currency + integrityKey)
        $integrity = hash('sha256', $reference . $amountInCents . 'COP' . $this->integrityKey);

        // Eliminar intentos pendientes previos para este SR (widget abierto y cerrado sin pagar).
        // Los pagos aprobados o fallidos se conservan como historial.
        Payment::where('service_request_id', $sr->id)
            ->where('status', 'pending')
            ->delete();

        // Crear nuevo intento de pago
        Payment::create([
            'service_request_id' => $sr->id,
            'client_id'          => $sr->client_id,
            'reference'          => $reference,
            'amount_in_cents'    => $amountInCents,
            'currency'           => 'COP',
            'status'             => 'pending',
        ]);

        // Construir URL manualmente para que signature:integrity NO se encodee como %3A
        $params = 'public-key=' . rawurlencode($this->publicKey)
            . '&currency=COP'
            . '&amount-in-cents=' . $amountInCents
            . '&reference=' . rawurlencode($reference)
            . '&signature:integrity=' . $integrity
            . '&redirect-url=' . rawurlencode($redirectUrl);

        return [
            'reference'      => $reference,
            'checkout_url'   => $this->checkoutUrl . '?' . $params,
            'public_key'     => $this->publicKey,
            'amount_in_cents'=> $amountInCents,
            'integrity'      => $integrity,
        ];
    }

    /**
     * Obtiene el acceptance_token público de Wompi (requerido para algunas integraciones).
     */
    public function getAcceptanceToken(): ?string
    {
        try {
            $resp = Http::withoutVerifying()
                ->get("{$this->apiUrl}/v1/merchants/{$this->publicKey}");

            return $resp->json('data.presigned_acceptance.acceptance_token');
        } catch (\Exception $e) {
            Log::error('Wompi acceptance token error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Busca una transacción en Wompi por referencia y actualiza pago y SR.
     * Útil cuando el webhook no llegó (desarrollo local).
     */
    public function confirmByReference(string $reference): string
    {
        try {
            $resp = Http::withoutVerifying()
                ->withToken($this->privateKey)
                ->get("{$this->apiUrl}/v1/transactions", ['reference' => $reference]);

            $transactions = $resp->json('data') ?? [];
            if (empty($transactions)) return 'not_found';

            // Tomar la más reciente
            $tx = $transactions[0];
            $this->handleWebhookTransaction($tx);
            return strtolower($tx['status'] ?? 'unknown');
        } catch (\Exception $e) {
            Log::error('Wompi confirmByReference error: ' . $e->getMessage());
            return 'error';
        }
    }

    /**
     * Valida la firma SHA256 de un evento webhook de Wompi.
     */
    public function validateWebhookSignature(array $event): bool
    {
        $receivedChecksum = $event['signature']['checksum'] ?? '';
        $properties       = $event['signature']['properties'] ?? [];
        $timestamp        = $event['timestamp'] ?? 0;

        $payload = '';
        foreach ($properties as $property) {
            $payload .= data_get($event['data'], $property, '');
        }
        $payload .= $timestamp . $this->eventsSecret;

        return hash_equals(hash('sha256', $payload), $receivedChecksum);
    }

    /**
     * Procesa la transacción confirmada por el webhook.
     * Actualiza Payment y ServiceRequest según el estado de Wompi.
     */
    public function handleWebhookTransaction(array $transaction): void
    {
        $reference = $transaction['reference'] ?? null;
        if (!$reference) return;

        $payment = Payment::where('reference', $reference)->first();
        if (!$payment) {
            Log::warning("Wompi webhook: payment not found for reference {$reference}");
            return;
        }

        $wompiStatus = $transaction['status'] ?? 'ERROR';

        $payment->update([
            'wompi_transaction_id' => $transaction['id'] ?? null,
            'wompi_status'         => $wompiStatus,
            'wompi_data'           => $transaction,
            'status'               => $wompiStatus === 'APPROVED' ? 'approved' : 'failed',
            'paid_at'              => $wompiStatus === 'APPROVED' ? now() : null,
        ]);

        $sr = $payment->serviceRequest;
        if (!$sr) return;

        if ($wompiStatus === 'APPROVED') {
            $sr->update([
                'payment_status' => 'paid',
                'status'         => 'pending', // activa la solicitud para que el profesional la vea
            ]);
            Log::info("Payment approved for ServiceRequest #{$sr->id}");
        } else {
            $sr->update(['payment_status' => 'payment_failed']);
            Log::info("Payment {$wompiStatus} for ServiceRequest #{$sr->id}");
        }
    }
}
