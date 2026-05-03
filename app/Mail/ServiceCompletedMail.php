<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\ServiceRequest;

class ServiceCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public ServiceRequest $serviceRequest;
    public string $recipientType; // 'client' | 'professional'

    public function __construct(ServiceRequest $serviceRequest, string $recipientType = 'client')
    {
        $this->serviceRequest = $serviceRequest;
        $this->recipientType  = $recipientType;
    }

    public function build()
    {
        $serviceName = $this->serviceRequest->service->name ?? 'Servicio';

        return $this
            ->subject("🎉 Servicio completado: {$serviceName}")
            ->view('emails.service_completed');
    }
}
