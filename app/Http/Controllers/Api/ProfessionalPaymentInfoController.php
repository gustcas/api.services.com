<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalPaymentInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProfessionalPaymentInfoController extends Controller
{
    /** Bancos colombianos de respaldo (por si la API de Wompi Payouts no está disponible aún) */
    private const BANKS_FALLBACK = [
        ['id' => 'bancolombia',    'code' => 'BANCOLOMBIA',          'name' => 'Bancolombia'],
        ['id' => 'bogota',         'code' => 'BANCO_BOGOTA',         'name' => 'Banco de Bogotá'],
        ['id' => 'davivienda',     'code' => 'DAVIVIENDA',           'name' => 'Davivienda'],
        ['id' => 'bbva',           'code' => 'BBVA',                 'name' => 'BBVA Colombia'],
        ['id' => 'popular',        'code' => 'BANCO_POPULAR',        'name' => 'Banco Popular'],
        ['id' => 'occidente',      'code' => 'BANCO_OCCIDENTE',      'name' => 'Banco de Occidente'],
        ['id' => 'av_villas',      'code' => 'AV_VILLAS',            'name' => 'Banco AV Villas'],
        ['id' => 'agrario',        'code' => 'BANCO_AGRARIO',        'name' => 'Banco Agrario'],
        ['id' => 'itau',           'code' => 'ITAU',                 'name' => 'Itaú Colombia'],
        ['id' => 'scotiabank',     'code' => 'SCOTIABANK_COLPATRIA', 'name' => 'Scotiabank Colpatria'],
        ['id' => 'colmena',        'code' => 'COLMENA',              'name' => 'Colmena'],
        ['id' => 'caja_social',    'code' => 'CAJA_SOCIAL',          'name' => 'Banco Caja Social'],
        ['id' => 'gnb_sudameris',  'code' => 'GNB_SUDAMERIS',        'name' => 'GNB Sudameris'],
        ['id' => 'cooperativa',    'code' => 'COOPCENTRAL',          'name' => 'Coopcentral'],
        ['id' => 'pichincha',      'code' => 'PICHINCHA',            'name' => 'Banco Pichincha'],
        ['id' => 'falabella',      'code' => 'FALABELLA',            'name' => 'Banco Falabella'],
        ['id' => 'finandina',      'code' => 'FINANDINA',            'name' => 'Banco Finandina'],
    ];

    /**
     * Devuelve la lista de bancos disponibles.
     * Intenta obtenerlos de la API de Wompi Payouts; si falla usa la lista estática.
     */
    public function banks()
    {
        $apiKey = config('wompi.payouts_api_key');
        $userId = config('wompi.payouts_user_id');
        $apiUrl = config('wompi.payouts_api_url');

        if ($apiKey && $userId) {
            try {
                $resp = Http::withoutVerifying()
                    ->withHeaders([
                        'x-api-key'           => $apiKey,
                        'user-principal-id'   => $userId,
                    ])
                    ->get("{$apiUrl}/banks", ['status' => 'ACTIVE']);

                if ($resp->successful()) {
                    $banks = collect($resp->json('data') ?? [])
                        ->map(fn($b) => [
                            'id'   => $b['id'],
                            'code' => $b['bankCode'] ?? $b['code'] ?? '',
                            'name' => $b['name'],
                        ])
                        ->values()
                        ->toArray();

                    if (!empty($banks)) {
                        return response()->json(['banks' => $banks]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Wompi banks API error: ' . $e->getMessage());
            }
        }

        return response()->json(['banks' => self::BANKS_FALLBACK]);
    }

    /**
     * Muestra los datos de pago del profesional autenticado.
     */
    public function show(Request $request)
    {
        $professional = $request->user()->professional;

        if (!$professional) {
            return response()->json(['success' => false, 'message' => 'Sin perfil profesional'], 404);
        }

        return response()->json([
            'success'      => true,
            'payment_info' => $professional->paymentInfo,
        ]);
    }

    /**
     * Guarda o actualiza los datos de pago del profesional.
     */
    public function store(Request $request)
    {
        $professional = $request->user()->professional;

        if (!$professional) {
            return response()->json(['success' => false, 'message' => 'Sin perfil profesional'], 404);
        }

        $method = $request->payment_method ?? 'bank_transfer';

        $rules = [
            'payment_method' => 'required|in:bank_transfer,nequi,daviplata',
            'id_type'        => 'required|in:CC,NIT,CE',
            'id_number'      => 'required|string|max:20',
            'full_name'      => 'required|string|max:150',
            'email'          => 'required|email',
            'account_number' => 'required|string|max:30',
        ];

        if ($method === 'bank_transfer') {
            $rules['bank_id']      = 'required|string';
            $rules['bank_name']    = 'required|string';
            $rules['account_type'] = 'required|in:AHORROS,CORRIENTE';
        }

        $request->validate($rules);

        $data = [
            'payment_method' => $method,
            'id_type'        => $request->id_type,
            'id_number'      => $request->id_number,
            'full_name'      => $request->full_name,
            'email'          => $request->email,
            'account_number' => $request->account_number,
            'bank_id'        => $method === 'bank_transfer' ? $request->bank_id   : null,
            'bank_code'      => $method === 'bank_transfer' ? $request->bank_code : null,
            'bank_name'      => $method === 'bank_transfer' ? $request->bank_name : null,
            'account_type'   => $method === 'bank_transfer' ? $request->account_type : null,
        ];

        $info = ProfessionalPaymentInfo::updateOrCreate(
            ['professional_id' => $professional->id],
            $data
        );

        return response()->json([
            'success'      => true,
            'message'      => 'Datos de pago guardados correctamente.',
            'payment_info' => $info,
        ]);
    }
}
