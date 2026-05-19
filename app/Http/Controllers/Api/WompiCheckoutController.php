<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Services\WompiCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WompiCheckoutController extends Controller
{
    private WompiCheckoutService $wompi;

    public function __construct(WompiCheckoutService $wompi)
    {
        $this->wompi = $wompi;
    }

    /**
     * Crea la solicitud de servicio y devuelve la URL de pago de Wompi.
     * Reemplaza el store() normal: guarda con status='pending_payment' y no se activa hasta confirmar pago.
     */
    public function initPayment(Request $request)
    {
        $request->validate([
            'service_request_id' => 'required|exists:service_requests,id',
        ]);

        $sr = ServiceRequest::with('service')
            ->where('id', $request->service_request_id)
            ->where('client_id', $request->user()->id)
            ->whereIn('payment_status', ['pending_payment', 'payment_failed'])
            ->firstOrFail();

        // El budget ya fue calculado correctamente en store() (precio × personas)
        $result = $this->wompi->createCheckoutUrl($sr);

        return response()->json([
            'success'           => true,
            'reference'         => $result['reference'],
            'checkout_url'      => $result['checkout_url'],
            'public_key'        => $result['public_key'],
            'amount_in_cents'   => $result['amount_in_cents'],
            'integrity'         => $result['integrity'],
            'currency'          => 'COP',
            'service_name'      => $sr->service ? $sr->service->name : '—',
        ]);
    }

    /**
     * Webhook público de Wompi — confirma o rechaza el pago.
     * NO requiere autenticación ni CSRF.
     */
    public function webhook(Request $request)
    {
        $event = $request->json()->all();

        Log::info('Wompi webhook received', ['event' => $event['event'] ?? 'unknown']);

        // Validar firma
        if (!$this->wompi->validateWebhookSignature($event)) {
            Log::warning('Wompi webhook: invalid signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Solo procesar transacciones
        if (($event['event'] ?? '') === 'transaction.updated') {
            $this->wompi->handleWebhookTransaction(
                $event['data']['transaction'] ?? []
            );
        }

        // Siempre responder 200 para que Wompi no reintente
        return response()->json(['ok' => true], 200);
    }

    /**
     * Consulta el estado de un pago por referencia.
     * El cliente la llama al regresar de Wompi para mostrar resultado.
     */
    public function checkPayment(Request $request)
    {
        $reference = $request->query('reference');

        $payment = Payment::with('serviceRequest')
            ->where('reference', $reference)
            ->where('client_id', $request->user()->id)
            ->first();

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Pago no encontrado'], 404);
        }

        return response()->json([
            'success'        => true,
            'status'         => $payment->status,           // pending | approved | failed
            'wompi_status'   => $payment->wompi_status,
            'amount'         => $payment->amount_in_cents / 100,
            'reference'      => $payment->reference,
            'paid_at'        => $payment->paid_at,
            'service_request_id' => $payment->service_request_id,
        ]);
    }

    /**
     * Confirma un pago consultando Wompi directamente.
     * Útil cuando el webhook no llega (desarrollo local / ngrok apagado).
     */
    public function confirmPayment(Request $request)
    {
        $request->validate(['reference' => 'required|string']);

        $payment = Payment::where('reference', $request->reference)
            ->where('client_id', $request->user()->id)
            ->first();

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Pago no encontrado'], 404);
        }

        if ($payment->status === 'approved') {
            return response()->json(['success' => true, 'status' => 'approved']);
        }

        // Si el frontend envió los datos de la transacción del widget, procesarlos directamente.
        // Esto evita depender de la llave privada para consultar la API de Wompi.
        if ($request->filled('transaction')) {
            $this->wompi->handleWebhookTransaction((array) $request->transaction);
        } else {
            // Fallback: consultar Wompi con la llave privada
            $this->wompi->confirmByReference($payment->reference);
        }

        return response()->json([
            'success' => true,
            'status'  => $payment->fresh()->status,
        ]);
    }

    /**
     * Devuelve el acceptance_token (útil para integraciones widget).
     */
    public function acceptanceToken()
    {
        $token = $this->wompi->getAcceptanceToken();
        return response()->json(['acceptance_token' => $token]);
    }

    /**
     * Admin: listar todos los cobros al cliente.
     * Oculta los registros "pending" duplicados de SRs que ya tienen un pago aprobado.
     */
    public function adminPayments(Request $request)
    {
        // SRs que ya tienen al menos un pago aprobado → sus "pending" históricos se omiten
        $approvedSrIds = Payment::where('status', 'approved')
            ->pluck('service_request_id')
            ->unique()
            ->toArray();

        $query = Payment::with(['serviceRequest.service', 'client'])
            ->where(function ($q) use ($approvedSrIds) {
                $q->where('status', '!=', 'pending')
                  ->orWhereNotIn('service_request_id', $approvedSrIds);
            })
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $payments = $query->paginate(25);

        $payments->getCollection()->transform(function ($p) {
            return [
                'id'                 => $p->id,
                'reference'          => $p->reference,
                'amount'             => $p->amount_in_cents / 100,
                'amount_formatted'   => '$' . number_format($p->amount_in_cents / 100, 0, ',', '.'),
                'status'             => $p->status,
                'wompi_status'       => $p->wompi_status,
                'wompi_id'           => $p->wompi_transaction_id,
                'paid_at'            => $p->paid_at ? $p->paid_at->format('d/m/Y H:i') : null,
                'created_at'         => $p->created_at->format('d/m/Y H:i'),
                'client_name'        => optional($p->client)->name ?? '—',
                'service_name'       => optional(optional($p->serviceRequest)->service)->name ?? '—',
                'service_request_id' => $p->service_request_id,
            ];
        });

        return response()->json($payments);
    }

    /**
     * Admin: solicitudes completadas con pago recibido y dispersión pendiente.
     */
    public function pendingPayouts(Request $request)
    {
        $rows = ServiceRequest::with(['professional.user', 'service', 'payout'])
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->whereIn('payout_status', ['pending_payout', 'payout_failed'])
            ->orderByDesc('updated_at')
            ->paginate(25);

        $rows->getCollection()->transform(function ($sr) {
            return [
                'id'               => $sr->id,
                'service_name'     => optional($sr->service)->name ?? '—',
                'professional_name'=> optional(optional($sr->professional)->user)->name ?? '—',
                'budget'           => $sr->budget,
                'budget_formatted' => '$' . number_format($sr->budget, 0, ',', '.'),
                'payout_status'    => $sr->payout_status,
                'payout_id'        => optional($sr->payout)->id,
                'payout_error'     => optional($sr->payout)->wompi_status,
                'updated_at'       => $sr->updated_at->format('d/m/Y H:i'),
            ];
        });

        return response()->json($rows);
    }

    /**
     * Admin DEV: simula un pago aprobado para pruebas sin Wompi.
     * Solo disponible cuando APP_ENV=local.
     */
    public function simulatePayment($serviceRequestId)
    {
        if (config('app.env') !== 'local') {
            return response()->json(['message' => 'Solo disponible en entorno local.'], 403);
        }

        $sr = ServiceRequest::with('service')->find($serviceRequestId);
        if (!$sr) {
            return response()->json(['message' => 'Solicitud no encontrada.'], 404);
        }

        $reference = 'SIM-' . $sr->id . '-' . time();
        $amount    = (int) round(($sr->budget ?? 0) * 100);

        Payment::updateOrCreate(
            ['service_request_id' => $sr->id],
            [
                'client_id'           => $sr->client_id,
                'reference'           => $reference,
                'amount_in_cents'     => $amount,
                'currency'            => 'COP',
                'wompi_transaction_id'=> 'SIMULATED',
                'wompi_status'        => 'APPROVED',
                'status'              => 'approved',
                'paid_at'             => now(),
            ]
        );

        $sr->update([
            'payment_status' => 'paid',
            'status'         => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Pago simulado para SR#{$sr->id}. Estado actualizado a 'paid'.",
        ]);
    }

    /**
     * Admin: KPIs del módulo de pagos.
     */
    public function paymentStats()
    {
        $approvedSrIds = Payment::where('status', 'approved')
            ->pluck('service_request_id')->unique()->toArray();

        $totalCobrado    = Payment::where('status', 'approved')->sum(DB::raw('amount_in_cents / 100'));
        $totalPendiente  = Payment::where('status', 'pending')
            ->whereNotIn('service_request_id', $approvedSrIds)
            ->sum(DB::raw('amount_in_cents / 100'));
        $totalFallido    = Payment::where('status', 'failed')->count();
        $cobrosAprobados = Payment::where('status', 'approved')->count();

        $totalDispersado = \App\Models\Payout::where('status', 'approved')->sum('amount');
        $dispersPendientes = ServiceRequest::where('status', 'completed')
            ->where('payment_status', 'paid')
            ->whereIn('payout_status', ['pending_payout', 'payout_failed'])
            ->count();

        return response()->json([
            'total_cobrado'        => (float) $totalCobrado,
            'total_cobrado_fmt'    => '$' . number_format($totalCobrado, 0, ',', '.'),
            'cobros_aprobados'     => $cobrosAprobados,
            'cobros_pendientes'    => (float) $totalPendiente,
            'cobros_fallidos'      => $totalFallido,
            'total_dispersado'     => (float) $totalDispersado,
            'total_dispersado_fmt' => '$' . number_format($totalDispersado, 0, ',', '.'),
            'dispersiones_pendientes' => $dispersPendientes,
        ]);
    }
}
