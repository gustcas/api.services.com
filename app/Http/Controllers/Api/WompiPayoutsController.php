<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\ServiceRequest;
use App\Services\WompiPayoutsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WompiPayoutsController extends Controller
{
    private WompiPayoutsService $service;

    public function __construct(WompiPayoutsService $service)
    {
        $this->service = $service;
    }

    /**
     * Admin: dispersión manual de un service_request específico.
     */
    public function disburse(Request $request, $serviceRequestId)
    {
        $sr = ServiceRequest::with(['professional.paymentInfo', 'service'])->find($serviceRequestId);

        if (!$sr) {
            return response()->json(['message' => 'Solicitud no encontrada.'], 404);
        }

        // Bloquear si ya tiene payouts creados
        $existingPayouts = Payout::where('service_request_id', $sr->id)->count();
        if ($existingPayouts > 0) {
            return response()->json(['success' => false, 'message' => 'Esta solicitud ya tiene dispersiones registradas.'], 422);
        }

        // Marcar inmediatamente para que desaparezca de pendientes y no se pueda dispersar dos veces
        $sr->update(['payout_status' => 'payout_processing']);

        \App\Jobs\ProcessPayoutJob::dispatch($sr->id);

        return response()->json(['success' => true, 'message' => 'Dispersión en proceso.'], 200);
    }

    /**
     * Admin: listar todos los payouts con filtros opcionales.
     */
    public function index(Request $request)
    {
        $query = Payout::with(['serviceRequest', 'professional.user'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        $payouts = $query->paginate(25);

        $payouts->getCollection()->transform(function ($p) {
            return [
                'id'                  => $p->id,
                'reference'           => $p->reference,
                'amount'              => $p->amount,
                'amount_formatted'    => '$' . number_format($p->amount, 0, ',', '.'),
                'discount_amount'     => $p->discount_amount,
                'net_amount'          => $p->net_amount,
                'payment_method'      => $p->payment_method,
                'bank_name'           => $p->bank_name,
                'account_number'      => $p->account_number,
                'status'              => $p->status,
                'wompi_status'        => $p->wompi_status,
                'wompi_payout_id'     => $p->wompi_payout_id,
                'triggered_by'        => $p->triggered_by,
                'entity_type'         => $p->entity_type ?? 'professional',
                'paid_at'             => $p->paid_at ? $p->paid_at->format('d/m/Y H:i') : null,
                'created_at'          => $p->created_at->format('d/m/Y H:i'),
                'professional_name'   => optional(optional($p->professional)->user)->name ?? '—',
                'service_request_id'  => $p->service_request_id,
            ];
        });

        return response()->json($payouts);
    }

    /**
     * Admin: ver detalle de un payout específico.
     */
    public function show($id)
    {
        $payout = Payout::with(['serviceRequest.service', 'professional.user'])->find($id);

        if (!$payout) {
            return response()->json(['message' => 'Payout no encontrado.'], 404);
        }

        return response()->json([
            'id'                 => $payout->id,
            'reference'          => $payout->reference,
            'amount'             => $payout->amount,
            'amount_formatted'   => '$' . number_format($payout->amount, 0, ',', '.'),
            'payment_method'     => $payout->payment_method,
            'bank_name'          => $payout->bank_name,
            'account_type'       => $payout->account_type,
            'account_number'     => $payout->account_number,
            'status'             => $payout->status,
            'wompi_status'       => $payout->wompi_status,
            'wompi_payout_id'    => $payout->wompi_payout_id,
            'wompi_response'     => $payout->wompi_response,
            'triggered_by'       => $payout->triggered_by,
            'paid_at'            => $payout->paid_at ? $payout->paid_at->format('d/m/Y H:i') : null,
            'created_at'         => $payout->created_at->format('d/m/Y H:i'),
            'professional_name'  => optional(optional($payout->professional)->user)->name ?? '—',
            'service_name'       => optional(optional($payout->serviceRequest)->service)->name ?? '—',
            'service_request_id' => $payout->service_request_id,
        ]);
    }

    /**
     * Webhook de Wompi Payouts (público, sin autenticación).
     */
    public function webhook(Request $request)
    {
        $event = $request->all();

        Log::info('Wompi Payouts webhook recibido', ['type' => $event['event'] ?? 'unknown']);

        if (!$this->service->validateWebhookSignature($event)) {
            Log::warning('Wompi Payouts webhook: firma inválida');
            return response()->json(['message' => 'Firma inválida'], 401);
        }

        $this->service->handleWebhook($event);

        return response()->json(['message' => 'OK']);
    }

    public function dispersarTodos(Request $request)
{
    \Illuminate\Support\Facades\Artisan::call('pagos:dispersar');
    $output = \Illuminate\Support\Facades\Artisan::output();
    return response()->json(['success' => true, 'message' => 'Dispersión acumulada ejecutada.', 'output' => $output]);
}

public function resetFailed(Request $request)
{
    // SRs con payouts fallidos y sin ningún payout aprobado/processing activo
    $failedSrIds = \App\Models\Payout::where('status', 'failed')
        ->pluck('service_request_id')
        ->unique()
        ->toArray();

    // SRs en processing sin ningún payout (nunca se llegó a crear el payout)
    $processingIds = \App\Models\ServiceRequest::where('disbursement_status', 'processing')
        ->whereDoesntHave('payment', fn($q) => $q->whereIn('status', ['processing', 'approved']))
        ->pluck('id')
        ->toArray();

    $srIdsParaReset = array_unique(array_merge($failedSrIds, $processingIds));

    // Excluir los que tienen payout aprobado o processing activo
    $srIdsConActivo = \App\Models\Payout::whereIn('service_request_id', $srIdsParaReset)
        ->whereIn('status', ['processing', 'approved'])
        ->pluck('service_request_id')
        ->unique()
        ->toArray();

    $srIdsParaReset = array_diff($srIdsParaReset, $srIdsConActivo);

    if (!empty($srIdsParaReset)) {
            \App\Models\ServiceRequest::whereIn('id', $srIdsParaReset)
                ->update([
                    'disbursement_status' => 'pending',
                    'payout_status'       => 'pending_completion',
                    'disbursed_at'        => null,
                ]);
        }

    return response()->json(['success' => true, 'reset' => count($srIdsParaReset)]);
}

public function reintentar(Request $request, $payoutId)
{
    $payout = \App\Models\Payout::with(['serviceRequest.service', 'serviceRequest.professional.paymentInfo'])->findOrFail($payoutId);

    if ($payout->status !== 'failed') {
        return response()->json(['success' => false, 'message' => 'Solo se pueden reintentar pagos fallidos.'], 422);
    }

    // Resetear el payout existente — NO crear uno nuevo
    $payout->update([
        'status'          => 'processing',
        'wompi_status'    => null,
        'wompi_response'  => null,
        'wompi_payout_id' => null,
    ]);

    $payoutsService = app(\App\Services\WompiPayoutsService::class);

    // Enviar directamente a Wompi usando el payout existente
    $result = $payoutsService->enviarPayoutExistente($payout);

    if (!$result['success']) {
        $payout->update(['status' => 'failed', 'wompi_status' => 'ERROR']);
    }

    return response()->json(['success' => $result['success'], 'message' => $result['message'] ?? 'OK']);
}

}
