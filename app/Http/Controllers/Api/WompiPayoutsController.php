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

        $result = $this->service->disburse($sr, 'manual');

        $status = $result['success'] ? 200 : 422;
        return response()->json($result, $status);
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
                'payment_method'      => $p->payment_method,
                'bank_name'           => $p->bank_name,
                'account_number'      => $p->account_number,
                'status'              => $p->status,
                'wompi_status'        => $p->wompi_status,
                'wompi_payout_id'     => $p->wompi_payout_id,
                'triggered_by'        => $p->triggered_by,
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
}
