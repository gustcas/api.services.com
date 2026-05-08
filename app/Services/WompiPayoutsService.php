<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\ServiceRequest;
use App\Models\Professional;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WompiPayoutsService
{
    private string $apiUrl;
    private string $apiKey;
    private string $userId;
    private string $eventsSecret;

    // IDs de Nequi y Daviplata en Wompi Payouts (se actualizan desde /banks)
    // Estos son los más comunes; si Wompi devuelve IDs distintos, se usan los reales
    private const NEQUI_CODES     = ['NEQUI', 'nequi'];
    private const DAVIPLATA_CODES = ['DAVIPLATA', 'daviplata'];

    public function __construct()
    {
        $this->apiUrl       = config('wompi.payouts_api_url');
        $this->apiKey       = config('wompi.payouts_api_key');
        $this->userId       = config('wompi.payouts_user_id');
        $this->eventsSecret = config('wompi.events_secret');
    }

    /**
     * Calcula el monto neto que recibe el profesional.
     * Usa allies_percentage del servicio (el % que va al aliado/profesional).
     */
    public function calculateNetAmount(ServiceRequest $sr): float
    {
        $service    = $sr->service;
        $gross      = (float) ($sr->budget ?? 0);
        $alliesPct  = $service ? (float) $service->allies_percentage : 0;
        return round($gross * $alliesPct / 100, 2);
    }

    /**
     * Obtiene el accountId de la cuenta Wompi Payouts desde la API.
     */
    private function getAccountId(): ?string
    {
        try {
            $resp = Http::withoutVerifying()
                ->withHeaders($this->headers())
                ->get("{$this->apiUrl}/accounts");

            $accounts = $resp->json('data') ?? [];
            return $accounts[0]['id'] ?? null;
        } catch (\Exception $e) {
            Log::error('Wompi Payouts getAccountId error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Resuelve el bankId de Wompi para Nequi o Daviplata consultando /banks.
     */
    private function resolveWalletBankId(string $method): ?string
    {
        try {
            $resp  = Http::withoutVerifying()
                ->withHeaders($this->headers())
                ->get("{$this->apiUrl}/banks");

            $banks = $resp->json('data') ?? [];
            $codes = $method === 'nequi' ? self::NEQUI_CODES : self::DAVIPLATA_CODES;

            foreach ($banks as $bank) {
                $code = strtoupper($bank['bankCode'] ?? $bank['code'] ?? '');
                foreach ($codes as $c) {
                    if (str_contains($code, strtoupper($c))) {
                        return $bank['id'];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Wompi Payouts resolveWalletBankId error: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Punto de entrada principal — crea un payout para un service_request.
     * Puede ser llamado automáticamente o por el admin (manual).
     */
    public function disburse(ServiceRequest $sr, string $triggeredBy = 'auto'): array
    {
        // Validaciones previas
        if ($sr->payout_status === 'payout_approved') {
            return ['success' => false, 'message' => 'Este servicio ya fue pagado al profesional.'];
        }
        if ($sr->payout_status === 'payout_processing') {
            return ['success' => false, 'message' => 'El pago ya está en proceso.'];
        }
        if ($sr->payment_status !== 'paid') {
            return ['success' => false, 'message' => 'El cliente aún no ha completado el pago.'];
        }

        $professional = $sr->professional;
        if (!$professional) {
            return ['success' => false, 'message' => 'Sin profesional asignado.'];
        }

        $paymentInfo = $professional->paymentInfo;
        if (!$paymentInfo) {
            return ['success' => false, 'message' => 'El profesional no tiene datos bancarios registrados.'];
        }

        $netAmount = $this->calculateNetAmount($sr);
        if ($netAmount <= 0) {
            return ['success' => false, 'message' => 'El monto neto calculado es cero. Revisa el porcentaje de aliados del servicio.'];
        }

        // Construir referencia única
        $reference = 'PAYOUT-' . $sr->id . '-' . time();

        // Crear registro en BD con estado processing
        $payout = Payout::create([
            'service_request_id' => $sr->id,
            'professional_id'    => $professional->id,
            'reference'          => $reference,
            'amount'             => $netAmount,
            'payment_method'     => $paymentInfo->payment_method,
            'bank_name'          => $paymentInfo->bank_name,
            'account_type'       => $paymentInfo->account_type,
            'account_number'     => $paymentInfo->account_number,
            'status'             => 'processing',
            'triggered_by'       => $triggeredBy,
        ]);

        $sr->update(['payout_status' => 'payout_processing']);

        // Si no hay credenciales de Payouts configuradas → modo simulación
        if (!$this->apiKey || !$this->userId) {
            Log::warning("Wompi Payouts: credenciales no configuradas. Simulando payout #{$payout->id}");
            $payout->update(['status' => 'processing', 'wompi_status' => 'SIMULATED']);
            return [
                'success' => true,
                'message' => 'Payout registrado (sin credenciales Payouts — configurar WOMPI_PAYOUTS_API_KEY).',
                'payout'  => $payout,
                'simulated' => true,
            ];
        }

        // Obtener cuenta de origen
        $accountId = $this->getAccountId();
        if (!$accountId) {
            $payout->update(['status' => 'failed', 'wompi_status' => 'NO_ACCOUNT']);
            $sr->update(['payout_status' => 'payout_failed']);
            return ['success' => false, 'message' => 'No se pudo obtener la cuenta Wompi Payouts.'];
        }

        // Construir transacción según el método de pago
        $transaction = $this->buildTransaction($paymentInfo, $netAmount, $reference);
        if (!$transaction) {
            $payout->update(['status' => 'failed', 'wompi_status' => 'INVALID_METHOD']);
            $sr->update(['payout_status' => 'payout_failed']);
            return ['success' => false, 'message' => 'Método de pago no soportado o banco no encontrado.'];
        }

        // Llamar API de Wompi Payouts
        try {
            $resp = Http::withoutVerifying()
                ->withHeaders(array_merge($this->headers(), [
                    'idempotency-key' => $reference,
                ]))
                ->post("{$this->apiUrl}/payouts", [
                    'reference'    => $reference,
                    'accountId'    => $accountId,
                    'paymentType'  => 'PAYROLL',
                    'transactions' => [$transaction],
                ]);

            $body = $resp->json();

            if ($resp->successful()) {
                $wompiId = $body['data']['id'] ?? $body['id'] ?? null;
                $payout->update([
                    'wompi_payout_id' => $wompiId,
                    'wompi_status'    => 'PENDING',
                    'wompi_response'  => $body,
                    'status'          => 'processing',
                ]);
                Log::info("Wompi Payout creado: {$wompiId} para SR#{$sr->id}");
                return ['success' => true, 'message' => 'Dispersión enviada a Wompi.', 'payout' => $payout];
            } else {
                $errorMsg = $body['error']['message'] ?? 'Error desconocido';
                $payout->update([
                    'status'         => 'failed',
                    'wompi_status'   => 'ERROR',
                    'wompi_response' => $body,
                ]);
                $sr->update(['payout_status' => 'payout_failed']);
                Log::error("Wompi Payout error para SR#{$sr->id}: {$errorMsg}");
                return ['success' => false, 'message' => "Wompi rechazó el payout: {$errorMsg}"];
            }
        } catch (\Exception $e) {
            $payout->update(['status' => 'failed', 'wompi_status' => 'EXCEPTION']);
            $sr->update(['payout_status' => 'payout_failed']);
            Log::error("Wompi Payout exception SR#{$sr->id}: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error de conexión con Wompi Payouts.'];
        }
    }

    /**
     * Construye el objeto de transacción para la API de Wompi Payouts.
     */
    private function buildTransaction($info, float $amount, string $ref): ?array
    {
        $method = $info->payment_method;

        // Monto en centavos (Wompi Payouts usa pesos enteros, no centavos)
        // Verificar docs: algunos endpoints usan pesos, otros centavos
        // Wompi Payouts Colombia usa PESOS COLOMBIANOS (enteros)
        $amountInt = (int) round($amount);

        $base = [
            'legalIdType'   => $info->id_type,
            'legalId'       => $info->id_number,
            'name'          => $info->full_name,
            'email'         => $info->email,
            'amount'        => $amountInt,
            'reference'     => $ref . '-T1',
        ];

        if ($method === 'bank_transfer') {
            return array_merge($base, [
                'bankId'        => $info->bank_id,
                'accountType'   => $info->account_type,
                'accountNumber' => $info->account_number,
            ]);
        }

        // Nequi o Daviplata — buscar bankId
        $walletBankId = $this->resolveWalletBankId($method);
        if (!$walletBankId) {
            return null;
        }

        return array_merge($base, [
            'bankId'        => $walletBankId,
            'accountType'   => strtoupper($method),  // NEQUI | DAVIPLATA
            'accountNumber' => $info->account_number, // teléfono
        ]);
    }

    /**
     * Procesa el webhook de Wompi Payouts (payout.updated / transaction.updated).
     */
    public function handleWebhook(array $event): void
    {
        $txData = $event['data']['transaction'] ?? $event['data']['payout'] ?? null;
        if (!$txData) return;

        $wompiId = $txData['id'] ?? null;
        $status  = $txData['status'] ?? 'ERROR';

        $payout = Payout::where('wompi_payout_id', $wompiId)->first();
        if (!$payout) {
            Log::warning("Wompi Payouts webhook: payout no encontrado para ID {$wompiId}");
            return;
        }

        $payout->update([
            'wompi_status'   => $status,
            'wompi_response' => array_merge($payout->wompi_response ?? [], ['webhook' => $txData]),
            'status'         => $status === 'APPROVED' ? 'approved' : ($status === 'DECLINED' || $status === 'ERROR' ? 'failed' : 'processing'),
            'paid_at'        => $status === 'APPROVED' ? now() : null,
        ]);

        $sr = $payout->serviceRequest;
        if (!$sr) return;

        if ($status === 'APPROVED') {
            $sr->update(['payout_status' => 'payout_approved']);
            Log::info("Payout aprobado para SR#{$sr->id} — profesional #{$payout->professional_id}");
        } elseif (in_array($status, ['DECLINED', 'ERROR'])) {
            $sr->update(['payout_status' => 'payout_failed']);
            Log::warning("Payout {$status} para SR#{$sr->id}");
        }
    }

    /**
     * Valida la firma del webhook de Wompi Payouts.
     */
    public function validateWebhookSignature(array $event): bool
    {
        $receivedChecksum = $event['signature']['checksum'] ?? '';
        $properties       = $event['signature']['properties'] ?? [];
        $timestamp        = $event['timestamp'] ?? 0;

        $payload = '';
        foreach ($properties as $prop) {
            $payload .= data_get($event['data'], $prop, '');
        }
        $payload .= $timestamp . $this->eventsSecret;

        return hash_equals(hash('sha256', $payload), $receivedChecksum);
    }

    private function headers(): array
    {
        return [
            'x-api-key'         => $this->apiKey,
            'user-principal-id' => $this->userId,
            'Content-Type'      => 'application/json',
            'Accept'            => 'application/json',
        ];
    }
}
