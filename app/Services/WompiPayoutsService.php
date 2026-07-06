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

    private const NEQUI_CODES     = ['NEQUI', 'nequi'];
    private const DAVIPLATA_CODES = ['DAVIPLATA', 'daviplata'];

    public function __construct()
    {
        $this->apiUrl       = config('wompi.payouts_api_url');
        $this->apiKey       = config('wompi.payouts_api_key');
        $this->userId       = config('wompi.payouts_user_id');
        $this->eventsSecret = config('wompi.payouts_events_secret') ?: config('wompi.events_secret');
    }

    public function calculateNetAmount(ServiceRequest $sr): float
    {
        $service   = $sr->service;
        $gross     = (float) ($sr->budget ?? 0);
        $alliesPct = $service ? (float) $service->allies_percentage : 0;
        return round($gross * $alliesPct / 100, 2);
    }

    private function getAccountId(): ?string
    {
        try {
            $resp     = Http::withoutVerifying()->withHeaders($this->headers())->get("{$this->apiUrl}/accounts");
            $accounts = $resp->json('data') ?? [];
            return $accounts[0]['id'] ?? null;
        } catch (\Exception $e) {
            Log::error('Wompi Payouts getAccountId error: ' . $e->getMessage());
            return null;
        }
    }

    private function resolveWalletBankId(string $method): ?string
    {
        try {
            $resp  = Http::withoutVerifying()->withHeaders($this->headers())->get("{$this->apiUrl}/banks");
            $banks = $resp->json('data') ?? [];
            $codes = $method === 'nequi' ? self::NEQUI_CODES : self::DAVIPLATA_CODES;
            foreach ($banks as $bank) {
                $code = strtoupper($bank['bankCode'] ?? $bank['code'] ?? '');
                foreach ($codes as $c) {
                    if (str_contains($code, strtoupper($c))) return $bank['id'];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Wompi Payouts resolveWalletBankId error: ' . $e->getMessage());
        }
        return null;
    }

    private function resolveBankIdByCode(string $bankCode): ?string
    {
        try {
            $resp  = Http::withoutVerifying()->withHeaders($this->headers())->get("{$this->apiUrl}/banks");
            $banks = $resp->json('data') ?? [];
            foreach ($banks as $bank) {
                $code = strtoupper($bank['bankCode'] ?? $bank['code'] ?? '');
                if ($code === strtoupper($bankCode)) return $bank['id'];
            }
        } catch (\Exception $e) {
            Log::warning("resolveBankIdByCode error: " . $e->getMessage());
        }
        return null;
    }

    /**
     * Dispersión individual al profesional (usada por botón manual en admin).
     */
    public function disburse(ServiceRequest $sr, string $triggeredBy = 'auto'): array
    {
        if ($sr->payout_status === 'payout_approved')   return ['success' => false, 'message' => 'Este servicio ya fue pagado al profesional.'];
        if ($sr->payout_status === 'payout_processing') return ['success' => false, 'message' => 'El pago ya está en proceso.'];
        if ($sr->payment_status !== 'paid')              return ['success' => false, 'message' => 'El cliente aún no ha completado el pago.'];

        $professional = $sr->professional;
        if (!$professional) return ['success' => false, 'message' => 'Sin profesional asignado.'];

        $paymentInfo = $professional->paymentInfo;
        if (!$paymentInfo) return ['success' => false, 'message' => 'El profesional no tiene datos bancarios registrados.'];

        $netAmount = $this->calculateNetAmount($sr);
        if ($netAmount <= 0) return ['success' => false, 'message' => 'El monto neto calculado es cero.'];

        $reference = 'PAYOUT-' . $sr->id . '-' . time();

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
            'entity_type'        => 'professional',
        ]);

        $sr->update(['payout_status' => 'payout_processing']);

        if (!$this->apiKey || !$this->userId) {
            Log::warning("Wompi Payouts: sin credenciales. Simulando payout #{$payout->id}");
            $payout->update(['status' => 'processing', 'wompi_status' => 'SIMULATED']);
            return ['success' => true, 'message' => 'Payout simulado.', 'payout' => $payout, 'simulated' => true];
        }

        $accountId = $this->getAccountId();
        if (!$accountId) {
            $payout->update(['status' => 'failed', 'wompi_status' => 'NO_ACCOUNT']);
            $sr->update(['payout_status' => 'payout_failed']);
            return ['success' => false, 'message' => 'No se pudo obtener la cuenta Wompi Payouts.'];
        }

        $transaction = $this->buildTransaction($paymentInfo, $netAmount, $reference);
        if (!$transaction) {
            $payout->update(['status' => 'failed', 'wompi_status' => 'INVALID_METHOD']);
            $sr->update(['payout_status' => 'payout_failed']);
            return ['success' => false, 'message' => 'Método de pago no soportado.'];
        }

        try {
            $resp = Http::withoutVerifying()
                ->withHeaders(array_merge($this->headers(), ['idempotency-key' => $reference]))
                ->post("{$this->apiUrl}/payouts", [
                    'reference'    => $reference,
                    'accountId'    => $accountId,
                    'paymentType'  => 'PAYROLL',
                    'transactions' => [$transaction],
                ]);

            $body = $resp->json();
            if ($resp->successful()) {
                $wompiId = $body['data']['id'] ?? $body['id'] ?? null;
                $payout->update(['wompi_payout_id' => $wompiId, 'wompi_status' => 'PENDING', 'wompi_response' => $body, 'status' => 'processing']);
                return ['success' => true, 'message' => 'Dispersión enviada a Wompi.', 'payout' => $payout];
            } else {
                $errorMsg = $body['error']['message'] ?? 'Error desconocido';
                $payout->update(['status' => 'failed', 'wompi_status' => 'ERROR', 'wompi_response' => $body]);
                $sr->update(['payout_status' => 'payout_failed']);
                return ['success' => false, 'message' => "Wompi rechazó el payout: {$errorMsg}"];
            }
        } catch (\Exception $e) {
            $payout->update(['status' => 'failed', 'wompi_status' => 'EXCEPTION']);
            $sr->update(['payout_status' => 'payout_failed']);
            return ['success' => false, 'message' => 'Error de conexión con Wompi Payouts.'];
        }
    }

    /**
     * Dispersión acumulada al profesional (suma de varios SRs el martes).
     */
    public function disburseAccumulated(array $srIds, $paymentInfo, float $amount, string $entityType, string $triggeredBy = 'auto'): array
    {
        if ($amount <= 0) return ['success' => false, 'message' => 'Monto cero.'];

        $reference = 'PAYOUT-' . strtoupper($entityType) . '-' . implode('-', array_slice($srIds, 0, 3)) . '-' . time();

        $payout = Payout::create([
            'service_request_id' => $srIds[0],
            'professional_id'    => $paymentInfo->professional_id ?? null,
            'reference'          => $reference,
            'amount'             => $amount,
            'payment_method'     => $paymentInfo->payment_method,
            'bank_name'          => $paymentInfo->bank_name,
            'account_type'       => $paymentInfo->account_type,
            'account_number'     => $paymentInfo->account_number,
            'status'             => 'processing',
            'triggered_by'       => $triggeredBy,
            'entity_type'        => $entityType,
        ]);

        if (!$this->apiKey || !$this->userId) {
            $payout->update(['status' => 'processing', 'wompi_status' => 'SIMULATED']);
            return ['success' => true, 'simulated' => true, 'payout' => $payout];
        }

        $accountId = $this->getAccountId();
        if (!$accountId) {
            $payout->update(['status' => 'failed', 'wompi_status' => 'NO_ACCOUNT']);
            return ['success' => false, 'message' => 'No se pudo obtener cuenta Wompi.'];
        }

        $transaction = $this->buildTransaction($paymentInfo, $amount, $reference);
        if (!$transaction) {
            $payout->update(['status' => 'failed', 'wompi_status' => 'INVALID_METHOD']);
            return ['success' => false, 'message' => 'Método de pago no soportado.'];
        }

        try {
            $resp = Http::withoutVerifying()
                ->withHeaders(array_merge($this->headers(), ['idempotency-key' => $reference]))
                ->post("{$this->apiUrl}/payouts", [
                    'reference'    => $reference,
                    'accountId'    => $accountId,
                    'paymentType'  => 'PAYROLL',
                    'transactions' => [$transaction],
                ]);

            $body = $resp->json();
            if ($resp->successful()) {
                $wompiId = $body['data']['id'] ?? $body['id'] ?? null;
                $payout->update(['wompi_payout_id' => $wompiId, 'wompi_status' => 'PENDING', 'wompi_response' => $body, 'status' => 'processing']);
                return ['success' => true, 'payout' => $payout];
            } else {
                $errorMsg = $body['error']['message'] ?? 'Error desconocido';
                $payout->update(['status' => 'failed', 'wompi_status' => 'ERROR', 'wompi_response' => $body]);
                return ['success' => false, 'message' => $errorMsg];
            }
        } catch (\Exception $e) {
            $payout->update(['status' => 'failed', 'wompi_status' => 'EXCEPTION']);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Dispersión acumulada a cuenta PayoutAccount (ASECALIDAD, IMAVICX, Mantenimiento).
     */
    public function disburseAccumulatedToAccount(array $srIds, \App\Models\PayoutAccount $account, float $amount, string $entityType, string $triggeredBy = 'auto'): array
    {
        if ($amount <= 0) return ['success' => false, 'message' => "Monto cero para {$entityType}."];

        $reference = 'PAYOUT-' . strtoupper($entityType) . '-' . implode('-', array_slice($srIds, 0, 3)) . '-' . time();

        $payout = Payout::create([
            'service_request_id' => $srIds[0],
            'professional_id'    => null,
            'reference'          => $reference,
            'amount'             => $amount,
            'payment_method'     => 'bank_transfer',
            'bank_name'          => $account->bank_name,
            'account_type'       => $account->account_type,
            'account_number'     => $account->account_number,
            'status'             => 'processing',
            'triggered_by'       => $triggeredBy,
            'entity_type'        => $entityType,
        ]);

        if (!$this->apiKey || !$this->userId) {
            $payout->update(['status' => 'processing', 'wompi_status' => 'SIMULATED']);
            return ['success' => true, 'simulated' => true, 'payout' => $payout];
        }

        $accountId = $this->getAccountId();
        if (!$accountId) {
            $payout->update(['status' => 'failed', 'wompi_status' => 'NO_ACCOUNT']);
            return ['success' => false, 'message' => 'No se pudo obtener cuenta Wompi.'];
        }

        $bankId = $this->resolveBankIdByCode($account->bank_code);
        if (!$bankId) {
            $payout->update(['status' => 'failed', 'wompi_status' => 'NO_BANK']);
            return ['success' => false, 'message' => "Banco no encontrado: {$account->bank_code}"];
        }

        $transaction = [
            'legalIdType'   => 'CC',
            'legalId'       => $account->document_number,
            'name'          => $account->account_holder,
            'email'         => $account->email ?? 'pagos@e-service.com.co',
            'amount'        => (int) round($amount * 100), // centavos
            'reference' => substr(preg_replace('/[^a-zA-Z0-9\-]/', '-', $reference . '-T1'), 0, 40),
            'bankId'        => $bankId,
            'accountType'   => strtoupper($account->account_type),
            'accountNumber' => $account->account_number,
        ];

        try {
            $resp = Http::withoutVerifying()
                ->withHeaders(array_merge($this->headers(), ['idempotency-key' => $reference]))
                ->post("{$this->apiUrl}/payouts", [
                    'reference'    => $reference,
                    'accountId'    => $accountId,
                    'paymentType'  => 'PAYROLL',
                    'transactions' => [$transaction],
                ]);

            $body = $resp->json();
            if ($resp->successful()) {
                $wompiId = $body['data']['id'] ?? $body['id'] ?? null;
                $payout->update(['wompi_payout_id' => $wompiId, 'wompi_status' => 'PENDING', 'wompi_response' => $body, 'status' => 'processing']);
                return ['success' => true, 'payout' => $payout];
            } else {
                $errorMsg = $body['error']['message'] ?? 'Error desconocido';
                $payout->update(['status' => 'failed', 'wompi_status' => 'ERROR', 'wompi_response' => $body]);
                return ['success' => false, 'message' => $errorMsg];
            }
        } catch (\Exception $e) {
            $payout->update(['status' => 'failed', 'wompi_status' => 'EXCEPTION']);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Dispersión a cuenta PayoutAccount individual (usada por ProcessPayoutJob manual).
     */
    public function disburseToAccount(ServiceRequest $sr, \App\Models\PayoutAccount $account, float $amount, string $entityType, string $triggeredBy = 'auto'): array
    {
        if ($amount <= 0) return ['success' => false, 'message' => "Monto cero para {$entityType}."];

        $reference = 'PAYOUT-' . strtoupper($entityType) . '-' . $sr->id . '-' . time();

        $payout = Payout::create([
            'service_request_id' => $sr->id,
            'professional_id'    => $sr->professional_id,
            'reference'          => $reference,
            'amount'             => $amount,
            'payment_method'     => 'bank_transfer',
            'bank_name'          => $account->bank_name,
            'account_type'       => $account->account_type,
            'account_number'     => $account->account_number,
            'status'             => 'processing',
            'triggered_by'       => $triggeredBy,
            'entity_type'        => $entityType,
        ]);

        if (!$this->apiKey || !$this->userId) {
            $payout->update(['status' => 'processing', 'wompi_status' => 'SIMULATED']);
            return ['success' => true, 'simulated' => true, 'payout' => $payout];
        }

        $accountId = $this->getAccountId();
        if (!$accountId) {
            $payout->update(['status' => 'failed', 'wompi_status' => 'NO_ACCOUNT']);
            return ['success' => false, 'message' => 'No se pudo obtener cuenta Wompi Payouts.'];
        }

        $bankId = $this->resolveBankIdByCode($account->bank_code);
        if (!$bankId) {
            $payout->update(['status' => 'failed', 'wompi_status' => 'NO_BANK']);
            return ['success' => false, 'message' => "Banco no encontrado: {$account->bank_code}"];
        }

        $transaction = [
            'legalIdType'   => 'CC',
            'legalId'       => $account->document_number,
            'name'          => $account->account_holder,
            'email'         => $account->email ?? 'pagos@e-service.com.co',
            'amount'        => (int) round($amount * 100), // centavos
            'reference' => substr(preg_replace('/[^a-zA-Z0-9\-]/', '-', $reference . '-T1'), 0, 40),
            'bankId'        => $bankId,
            'accountType'   => strtoupper($account->account_type),
            'accountNumber' => $account->account_number,
        ];

        try {
            $resp = Http::withoutVerifying()
                ->withHeaders(array_merge($this->headers(), ['idempotency-key' => $reference]))
                ->post("{$this->apiUrl}/payouts", [
                    'reference'    => $reference,
                    'accountId'    => $accountId,
                    'paymentType'  => 'PAYROLL',
                    'transactions' => [$transaction],
                ]);

            $body = $resp->json();
            if ($resp->successful()) {
                $wompiId = $body['data']['id'] ?? $body['id'] ?? null;
                $payout->update(['wompi_payout_id' => $wompiId, 'wompi_status' => 'PENDING', 'wompi_response' => $body, 'status' => 'processing']);
                return ['success' => true, 'payout' => $payout];
            } else {
                $errorMsg = $body['error']['message'] ?? 'Error desconocido';
                $payout->update(['status' => 'failed', 'wompi_status' => 'ERROR', 'wompi_response' => $body]);
                return ['success' => false, 'message' => $errorMsg];
            }
        } catch (\Exception $e) {
            $payout->update(['status' => 'failed', 'wompi_status' => 'EXCEPTION']);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Webhook de Wompi Payouts.
     */
    public function handleWebhook(array $event): void
    {
        $txData  = $event['data']['transaction'] ?? $event['data']['payout'] ?? null;
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
            'status'         => in_array($status, ['APPROVED', 'TOTAL_PAYMENT']) ? 'approved'
                              : (in_array($status, ['DECLINED', 'ERROR', 'FAILED']) ? 'failed' : 'processing'),
            'paid_at'        => in_array($status, ['APPROVED', 'TOTAL_PAYMENT']) ? now() : null,
        ]);

        $sr = $payout->serviceRequest;
        if (!$sr) return;

        if (in_array($status, ['APPROVED', 'TOTAL_PAYMENT'])) {
            $sr->update(['payout_status' => 'payout_approved', 'disbursement_status' => 'paid']);
            Log::info("Payout aprobado para SR#{$sr->id}");
        } elseif (in_array($status, ['DECLINED', 'ERROR', 'FAILED'])) {
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
        // Wompi Payouts envía timestamp en milisegundos, convertir a segundos
        $ts        = $event['timestamp'] ?? 0;
        $timestamp = $ts > 9999999999 ? (int)($ts / 1000) : $ts;

        $payload = '';
        foreach ($properties as $prop) {
            $payload .= data_get($event['data'], $prop, '');
        }
        $payload .= $timestamp . $this->eventsSecret;

        return hash_equals(hash('sha256', $payload), $receivedChecksum);
    }

    private function buildTransaction($info, float $amount, string $ref): ?array
    {
        $method    = $info->payment_method;
        // Wompi Payouts usa CENTAVOS (documentación oficial: $10,000 COP = 1000000)
        $amountInt = (int) round($amount * 100);

        $base = [
            'legalIdType' => $info->id_type,
            'legalId'     => $info->id_number,
            'name'        => $info->full_name,
            'email'       => $info->email,
            'amount'      => $amountInt,
            'reference'   => $ref . '-T1',
        ];

        if ($method === 'bank_transfer') {
            return array_merge($base, [
                'bankId'        => $info->bank_id,
                'accountType'   => $info->account_type,
                'accountNumber' => $info->account_number,
            ]);
        }

        $walletBankId = $this->resolveWalletBankId($method);
        if (!$walletBankId) return null;

        return array_merge($base, [
            'bankId'        => $walletBankId,
            'accountType'   => strtoupper($method),
            'accountNumber' => $info->account_number,
        ]);
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
    /**
 * Reintenta enviar un payout existente a Wompi sin crear uno nuevo.
 */
public function enviarPayoutExistente(\App\Models\Payout $payout): array
{
    if (!$this->apiKey || !$this->userId) {
        $payout->update(['status' => 'processing', 'wompi_status' => 'SIMULATED']);
        return ['success' => true, 'simulated' => true, 'message' => 'Simulado.'];
    }

    $accountId = $this->getAccountId();
    if (!$accountId) {
        $payout->update(['status' => 'failed', 'wompi_status' => 'NO_ACCOUNT']);
        return ['success' => false, 'message' => 'No se pudo obtener cuenta Wompi.'];
    }

    // Construir referencia única para el reintento
    $reference = substr(preg_replace('/[^a-zA-Z0-9\-]/', '-', $payout->reference . '-R' . time()), 0, 40);

    // Construir transacción según entity_type
    if ($payout->entity_type === 'professional') {
        $sr          = $payout->serviceRequest;
        $paymentInfo = $sr ? optional($sr->professional)->paymentInfo : null;
        if (!$paymentInfo) {
            return ['success' => false, 'message' => 'Sin datos bancarios del profesional.'];
        }
        $transaction = $this->buildTransaction($paymentInfo, $payout->amount, $reference);
        if (!$transaction) {
            return ['success' => false, 'message' => 'Método de pago no soportado.'];
        }
    } else {
        $account = \App\Models\PayoutAccount::where('entity_type', $payout->entity_type)
            ->where('is_active', true)->first();
        if (!$account) {
            return ['success' => false, 'message' => "Cuenta {$payout->entity_type} no encontrada."];
        }
        $bankId = $this->resolveBankIdByCode($account->bank_code);
        if (!$bankId) {
            return ['success' => false, 'message' => "Banco no encontrado: {$account->bank_code}"];
        }
        $transaction = [
            'legalIdType'   => 'CC',
            'legalId'       => $account->document_number,
            'name'          => $account->account_holder,
            'email'         => $account->email ?? 'pagos@e-service.com.co',
            'amount'        => (int) round($payout->amount * 100),
            'reference' => substr(preg_replace('/[^a-zA-Z0-9\-]/', '-', $reference . '-T1'), 0, 40),
            'bankId'        => $bankId,
            'accountType'   => strtoupper($account->account_type),
            'accountNumber' => $account->account_number,
        ];
    }

    try {
        $resp = Http::withoutVerifying()
            ->withHeaders(array_merge($this->headers(), ['idempotency-key' => $reference]))
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
                'reference'       => $reference,
            ]);
            return ['success' => true, 'message' => 'Reintento enviado a Wompi.'];
        } else {
            $errorMsg = $body['error']['message'] ?? ($body['message'][0] ?? 'Error desconocido');
            $payout->update(['status' => 'failed', 'wompi_status' => 'ERROR', 'wompi_response' => $body]);
            return ['success' => false, 'message' => $errorMsg];
        }
    } catch (\Exception $e) {
        $payout->update(['status' => 'failed', 'wompi_status' => 'EXCEPTION']);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
}
