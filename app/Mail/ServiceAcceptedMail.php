<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\ServiceRequest;

class ServiceAcceptedMail extends Mailable
{
    use Queueable, SerializesModels;

    public ServiceRequest $serviceRequest;

    public function __construct(ServiceRequest $serviceRequest)
    {
        $this->serviceRequest = $serviceRequest;
    }

    public function build()
    {
        $clientName = $this->serviceRequest->client->name ?? 'Cliente';
        $serviceName = $this->serviceRequest->service->name ?? 'Servicio';

        return $this
            ->subject("✅ Tu solicitud de {$serviceName} fue aceptada")
            ->view('emails.service_accepted');
    }
}
