<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayoutAccount;
use App\Models\CategoryPayoutAccount;
use Illuminate\Http\Request;

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
}
