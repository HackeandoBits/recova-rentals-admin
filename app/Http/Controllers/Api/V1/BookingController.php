<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Interview;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validación (Podríamos usar un FormRequest, pero para verlo claro lo hago acá)
        $validated = $request->validate([
            'customer.name' => 'required|string',
            'customer.email' => 'required|email',
            'customer.phone' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required', // ID del producto en la BBDD Cliente
            'items.*.name' => 'required|string',
            'request_type' => 'required|in:whatsapp,reunion',
            'meeting_date' => 'nullable|date', // Puede ser null si es por WhatsApp
            'notes' => 'nullable|string',
        ]);

        // 2. Transacción de Base de Datos
        // (Usamos transaction para que si algo falla, no se guarde nada a medias)
        try {
            $result = DB::transaction(function () use ($validated) {

                // A) Crear el Booking (El Pedido)
                $booking = Booking::create([
                    'customer_name' => $validated['customer']['name'],
                    'customer_email' => $validated['customer']['email'],
                    'customer_phone' => $validated['customer']['phone'],
                    'event_date' => null, // Opcional: si el cliente mandara fecha del EVENTO
                    'meeting_type' => $validated['request_type'], // whatsapp o reunion
                    'notes' => $validated['notes'] ?? null,
                    'status' => 'pending',
                ]);

                // B) Guardar los Items (BookingItems)
                // Como el Admin no tiene tabla de productos, guardamos el nombre como texto
                foreach ($validated['items'] as $item) {
                    $booking->items()->create([
                        'product_id' => $item['id'], // Guardamos el ID del cliente como referencia
                        'name' => $item['name'],     // Guardamos el nombre para saber qué es
                        'quantity' => 1,             // O $item['quantity'] si lo mandás
                        // 'price' => ... (si mandaras precio)
                    ]);
                }

                // C) Crear la Reunión (Interview) inicial
                // Si pidieron reunión, creamos la entrevista en estado pendiente
                $interview = Interview::create([
                    'booking_id' => $booking->id,
                    'title' => 'Reunión con '.$booking->customer_name,
                    // Si hay fecha sugerida, la usamos. Si no, usamos "ahora" o null.
                    'start_at' => $validated['meeting_date']
                                    ? \Carbon\Carbon::parse($validated['meeting_date'])
                                    : now()->addDay()->setHour(9)->setMinute(0),
                    'end_at' => $validated['meeting_date']
                                    ? \Carbon\Carbon::parse($validated['meeting_date'])->addHour()
                                    : now()->addDay()->setHour(10)->setMinute(0),
                    'status' => 'pending',
                    'channel' => $validated['request_type'] === 'reunion' ? 'virtual_meeting' : 'whatsapp',
                ]);

                return $booking;
            });

            // 3. Respuesta Exitosa
            return response()->json([
                'message' => 'Solicitud recibida correctamente',
                'booking_id' => $result->id,
            ], 201);

        } catch (\Exception $e) {
            // 4. Manejo de Errores
            Log::error('Error creando booking desde API: '.$e->getMessage());

            return response()->json(['error' => 'Error interno al procesar el pedido'], 500);
        }
    }
     /**
     * Obtiene los slots de tiempo ocupados para una fecha específica
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getOccupiedTimeSlots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
        ]);
        
        $date = Carbon::parse($validated['date']);
        
        // ⚠️ CORRECCIÓN: Consultar INTERVIEWS en lugar de BOOKINGS
        $interviews = Interview::where('status', '!=', 'cancelled')
            ->whereDate('start_at', $date)
            ->get();
        
        $blockedSlots = [];
        
        foreach ($interviews as $interview) {
            $meetingStart = Carbon::parse($interview->start_at);
            
            // Bloquear 3 slots de 30min (1h reunión + 30min buffer)
            for ($i = 0; $i < 3; $i++) {
                $blockedTime = $meetingStart->copy()->addMinutes($i * 30);
                $blockedSlots[] = $blockedTime->format('H:i');
            }
        }
        
        // Eliminar duplicados y ordenar
        $blockedSlots = array_unique($blockedSlots);
        sort($blockedSlots);
        
        return response()->json([
            'date' => $date->format('Y-m-d'),
            'blocked_slots' => array_values($blockedSlots),
            'count' => count($blockedSlots),
        ]);
    }
}
