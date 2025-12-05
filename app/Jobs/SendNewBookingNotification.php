<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendNewBookingNotification implements ShouldQueue
{
    use Queueable;

    public $bookingData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $bookingData)
    {
        $this->bookingData = $bookingData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $ownerPhone = env('TWILIO_WHATSAPP_TO');
            if ($ownerPhone) {
                // $this->bookingData needs 'customer_name', 'request_type', 'start_at'
                $customerName = $this->bookingData['customer_name'] ?? 'Cliente';
                $customerPhone = $this->bookingData['customer_phone'] ?? '';
                $requestType = $this->bookingData['request_type'] ?? 'reunion';
                $startAt = $this->bookingData['start_at'] ?? now();
                
                // Limpiar teléfono para url (solo números)
                $phoneClean = preg_replace('/[^0-9]/', '', $customerPhone);
                $whatsappLink = $phoneClean ? "https://wa.me/{$phoneClean}" : '';

                $msg = '';

                if ($requestType === 'whatsapp') {
                    $msg = "🔔 *Nueva Solicitud de Pedido*\n\nHola, *{$customerName}* ha enviado una solicitud de pedido.\n\n📱 *Acción recomendada:* Revisa el panel de administración para ver el detalle de los productos.\n\n💬 Si necesitas contactar al cliente directo:\n{$whatsappLink}";
                } else {
                    // Formatear fecha para lectura humana
                    $dateObj = \Carbon\Carbon::parse($startAt)->setTimezone(config('app.timezone'));
                    $dateStr = $dateObj->format('d/m/Y H:i');
                    $msg = "📅 *Nueva Solicitud de Reunión*\n\nHola, *{$customerName}* quiere reunirse contigo el *{$dateStr}*.\n\n📱 *Acción recomendada:* Revisa el panel para confirmar la cita.\n\n💬 Chat directo con cliente:\n{$whatsappLink}";
                }

                // Usamos app() para resolver el servicio sin cambiar la firma del método
                $twilioService = app(\App\Services\TwilioService::class);
                $twilioService->sendWhatsAppNotification($ownerPhone, $msg);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error enviando WhatsApp desde Job: '.$e->getMessage());
        }
    }
}
