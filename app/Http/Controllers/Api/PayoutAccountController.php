<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayoutAccount;
use App\Models\CategoryPayoutAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayoutAccountController extends Controller
{
    // Listar todas las cuentas
    public function index()
    {
        $accounts = PayoutAccount::orderBy('entity_name')->get();
        return response()->json(['accounts' => $accounts]);
    }

    // Crear cuenta
    public function store(Request $request)
    {
        $request->validate([
            'entity_name'    => 'required|string|max:255',
            'entity_type' => 'required|in:asecalidad,imavicx,maintenance',
            'bank_name'      => 'nullable|string|max:255',
            'bank_code'      => 'nullable|string|max:100',
            'account_type'   => 'nullable|in:ahorros,corriente',
            'account_number' => 'required|string|max:50',
            'account_holder' => 'required|string|max:255',
            'document_number'=> 'nullable|string|max:20',
            'email' => 'nullable|string|email|max:255',
        ]);

        $account = PayoutAccount::create($request->all());
        return response()->json(['success' => true, 'account' => $account], 201);
    }

    // Actualizar cuenta
    public function update(Request $request, PayoutAccount $payoutAccount)
    {
        $request->validate([
            'entity_name'    => 'required|string|max:255',
            'entity_type' => 'required|in:asecalidad,imavicx,maintenance',
            'bank_name'      => 'nullable|string|max:255',
            'bank_code'      => 'nullable|string|max:100',
            'account_type'   => 'nullable|in:ahorros,corriente',
            'account_number' => 'required|string|max:50',
            'account_holder' => 'required|string|max:255',
            'document_number'=> 'nullable|string|max:20',
            'email'          => 'nullable|email|max:255',
            'is_active'      => 'boolean',
        ]);

        $payoutAccount->update($request->all());
        return response()->json(['success' => true, 'account' => $payoutAccount]);
    }

    // Eliminar cuenta
    public function destroy(PayoutAccount $payoutAccount)
    {
        $payoutAccount->delete();
        return response()->json(['success' => true]);
    }

    // Obtener cuentas asignadas a una categoría
    public function byCategory($categoryId)
    {
        $assigned = CategoryPayoutAccount::with('payoutAccount')
            ->where('category_id', $categoryId)
            ->get()
            ->map(fn($c) => [
                'entity_type'    => $c->entity_type,
                'payout_account' => $c->payoutAccount,
            ]);
        return response()->json(['assigned' => $assigned]);
    }

    // Asignar/actualizar cuentas a una categoría
    public function assignToCategory(Request $request, $categoryId)
    {
        $request->validate([
            'assignments'              => 'required|array',
            'assignments.*.entity_type'      => 'required|string',
            'assignments.*.payout_account_id'=> 'required|exists:payout_accounts,id',
        ]);

        // Eliminar asignaciones anteriores de esta categoría
        CategoryPayoutAccount::where('category_id', $categoryId)->delete();

        // Crear las nuevas
        foreach ($request->assignments as $a) {
            CategoryPayoutAccount::create([
                'category_id'       => $categoryId,
                'payout_account_id' => $a['payout_account_id'],
                'entity_type'       => $a['entity_type'],
            ]);
        }

        return response()->json(['success' => true]);
    }

    public function banks()
{
    $apiKey = config('wompi.payouts_api_key');
    $userId = config('wompi.payouts_user_id');
    $apiUrl = config('wompi.payouts_api_url');

    if ($apiKey && $userId) {
        try {
            $resp = Http::withoutVerifying()
                ->withHeaders([
                    'x-api-key'         => $apiKey,
                    'user-principal-id' => $userId,
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
            Log::warning('Admin banks API error: ' . $e->getMessage());
        }
    }

    // Fallback estático
    return response()->json(['banks' => [
        ['id' => 'bancolombia',   'code' => 'BANCOLOMBIA',          'name' => 'Bancolombia'],
        ['id' => 'bogota',        'code' => 'BANCO_BOGOTA',         'name' => 'Banco de Bogotá'],
        ['id' => 'davivienda',    'code' => 'DAVIVIENDA',           'name' => 'Davivienda'],
        ['id' => 'bbva',          'code' => 'BBVA',                 'name' => 'BBVA Colombia'],
        ['id' => 'popular',       'code' => 'BANCO_POPULAR',        'name' => 'Banco Popular'],
        ['id' => 'occidente',     'code' => 'BANCO_OCCIDENTE',      'name' => 'Banco de Occidente'],
        ['id' => 'av_villas',     'code' => 'AV_VILLAS',            'name' => 'Banco AV Villas'],
        ['id' => 'agrario',       'code' => 'BANCO_AGRARIO',        'name' => 'Banco Agrario'],
        ['id' => 'itau',          'code' => 'ITAU',                 'name' => 'Itaú Colombia'],
        ['id' => 'scotiabank',    'code' => 'SCOTIABANK_COLPATRIA', 'name' => 'Scotiabank Colpatria'],
        ['id' => 'colmena',       'code' => 'COLMENA',              'name' => 'Colmena'],
        ['id' => 'caja_social',   'code' => 'CAJA_SOCIAL',          'name' => 'Banco Caja Social'],
        ['id' => 'gnb_sudameris', 'code' => 'GNB_SUDAMERIS',        'name' => 'GNB Sudameris'],
        ['id' => 'cooperativa',   'code' => 'COOPCENTRAL',          'name' => 'Coopcentral'],
        ['id' => 'pichincha',     'code' => 'PICHINCHA',            'name' => 'Banco Pichincha'],
        ['id' => 'falabella',     'code' => 'FALABELLA',            'name' => 'Banco Falabella'],
        ['id' => 'finandina',     'code' => 'FINANDINA',            'name' => 'Banco Finandina'],
    ]]);
}
}
