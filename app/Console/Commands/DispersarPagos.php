<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPayoutJob;
use App\Models\ServiceRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispersarPagos extends Command
{
    protected $signature   = 'pagos:dispersar';
    protected $description = 'Dispersa los pagos pendientes a profesionales cada martes';

    public function handle(): void
    {
        $this->info('Iniciando dispersión de pagos...');

        // Buscar solicitudes completadas y pagadas pendientes de dispersión
        $solicitudes = ServiceRequest::where('status', 'completed')
            ->where('payment_status', 'paid')
            ->whereIn('disbursement_status', ['pending'])
            ->whereNull('disbursed_at')
            ->get()
            ->groupBy('professional_id');

        $total = 0;

        foreach ($solicitudes as $professionalId => $requests) {
            foreach ($requests as $sr) {
                // Despachar job para cada solicitud
                ProcessPayoutJob::dispatch($sr->id)
                    ->onQueue('payouts');

                $sr->update([
                    'disbursement_scheduled_at' => now(),
                ]);

                $total++;
                Log::info("DispersarPagos: Job despachado para solicitud #{$sr->id} (profesional #{$professionalId})");
            }
        }

        $this->info("Dispersión iniciada: {$total} solicitudes en cola.");
        Log::info("DispersarPagos: {$total} jobs despachados.");
    }
}
