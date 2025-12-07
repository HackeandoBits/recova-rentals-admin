<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class TwilioService
{
    protected $client;

    protected $from;

    public function __construct()
    {
        $sid = env('TWILIO_SID');
        $token = env('TWILIO_AUTH_TOKEN');
        $this->from = env('TWILIO_WHATSAPP_FROM');

        if ($sid && $token) {
            $this->client = new Client($sid, $token);
        }
    }

    /**
     * Envía un mensaje de WhatsApp
     *
     * @param  string  $to  Número de destino (debe incluir 'whatsapp:' si no lo trae, pero mejor pasarlo completo o gestionarlo aquí)
     * @param  string  $message  Cuerpo del mensaje
     */
    public function sendWhatsAppNotification(string $to, string $message): bool
    {
        if (! $this->client) {
            Log::warning('TwilioService: Credenciales no configuradas. No se envió el mensaje.');

            return false;
        }

        try {
            // Asegurar formato whatsapp:
            if (! str_starts_with($to, 'whatsapp:')) {
                $to = 'whatsapp:'.$to;
            }

            $this->client->messages->create($to, [
                'from' => str_starts_with($this->from, 'whatsapp:') ? $this->from : 'whatsapp:'.$this->from,
                'body' => $message,
            ]);

            Log::info('TwilioService: Mensaje enviado a '.$to);

            return true;

        } catch (\Exception $e) {
            Log::error('TwilioService Error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Envía una Plantilla de WhatsApp (Content API)
     *
     * @param  string  $to  Número de destino
     * @param  string  $contentSid  ID de la plantilla (starts with HX)
     * @param  array  $variables  Variables de la plantilla ['1' => 'val', '2' => 'val']
     */
    public function sendWhatsAppTemplate(string $to, string $contentSid, array $variables): bool
    {
        if (! $this->client) {
            Log::warning('TwilioService: Credenciales no configuradas.');

            return false;
        }

        try {
            if (! str_starts_with($to, 'whatsapp:')) {
                $to = 'whatsapp:'.$to;
            }

            $this->client->messages->create($to, [
                'from' => str_starts_with($this->from, 'whatsapp:') ? $this->from : 'whatsapp:'.$this->from,
                'contentSid' => $contentSid,
                'contentVariables' => json_encode($variables),
            ]);

            Log::info('TwilioService: Plantilla enviada a '.$to);

            return true;

        } catch (\Exception $e) {
            Log::error('TwilioService Template Error: '.$e->getMessage());

            return false;
        }
    }
}
