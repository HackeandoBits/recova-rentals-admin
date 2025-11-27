<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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

                // A) Crear la Reunión (Interview) que ahora contiene los datos del pedido
                $title = $validated['request_type'] === 'whatsapp'
                    ? 'Solicitud WhatsApp: '.$validated['customer']['name']
                    : 'Reunión con '.$validated['customer']['name'];

                $interview = Interview::create([
                    'title' => $title,
                    // Si hay fecha sugerida, la usamos. Si no, usamos "ahora" o null.
                    // Si es WhatsApp, la fecha de inicio es AHORA.
                    // Si es Reunión, usamos la fecha sugerida.
                    'start_at' => $validated['request_type'] === 'whatsapp'
                                    ? now()
                                    : ($validated['meeting_date']
                                        ? \Carbon\Carbon::parse($validated['meeting_date'])
                                        : now()->addDay()->setHour(9)->setMinute(0)),
                    
                    'end_at' => $validated['request_type'] === 'whatsapp'
                                    ? now()->addHour()
                                    : ($validated['meeting_date']
                                        ? \Carbon\Carbon::parse($validated['meeting_date'])->addHour()
                                        : now()->addDay()->setHour(10)->setMinute(0)),
                    'status' => 'pending',
                    'channel' => $validated['request_type'] === 'reunion' ? 'physical_meeting' : 'whatsapp',

                    // Datos del Cliente y Pedido
                    'customer_name' => $validated['customer']['name'],
                    'customer_email' => $validated['customer']['email'],
                    'customer_phone' => $validated['customer']['phone'],
                    'event_date' => null, // Opcional: si el cliente mandara fecha del EVENTO
                    'service_type' => null, // Opcional
                    'order_notes' => $validated['notes'] ?? null,
                ]);

                // B) Guardar los Items (InterviewItems)
                foreach ($validated['items'] as $item) {
                    $interview->items()->create([
                        'product_id' => $item['id'], // Guardamos el ID del cliente como referencia
                        'name' => $item['name'],     // Guardamos el nombre para saber qué es
                        'quantity' => 1,             // O $item['quantity'] si lo mandás
                        // 'price' => ... (si mandaras precio)
                    ]);
                }

                return $interview;
            });

            // 3. Respuesta Exitosa
            return response()->json([
                'message' => 'Solicitud recibida correctamente',
                'booking_id' => $result->id, // Mantenemos booking_id por compatibilidad con frontend si es necesario, o cambiamos a interview_id
                'interview_id' => $result->id,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            // 4. Manejo de Errores
            Log::error('Error creando booking desde API: '.$e->getMessage());

            return response()->json(['error' => 'Error interno al procesar el pedido: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene los slots de tiempo ocupados para una fecha específica
     */
    public function getOccupiedTimeSlots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $tz = config('app.timezone', 'America/Argentina/Buenos_Aires');
        $date = Carbon::parse($validated['date'], $tz);

        // 1. Obtener Entrevistas (Interviews)
        // Filtramos por fecha local convertida a UTC para la query, o usamos whereDate si la DB está en UTC
        // whereDate usa la fecha del servidor SQL, que suele ser UTC o la del sistema.
        // Lo más seguro es traer un rango amplio y filtrar en PHP, o confiar en whereDate si la DB está alineada.
        // Para ser precisos con Timezones, convertimos el rango del día local a UTC.
        $startOfDayUtc = $date->copy()->startOfDay()->setTimezone('UTC');
        $endOfDayUtc   = $date->copy()->endOfDay()->setTimezone('UTC');

        $interviews = Interview::where('status', '!=', 'cancelled')
            ->where(function ($q) use ($startOfDayUtc, $endOfDayUtc) {
                $q->whereBetween('start_at', [$startOfDayUtc, $endOfDayUtc])
                  ->orWhereBetween('end_at', [$startOfDayUtc, $endOfDayUtc])
                  // También incluir los que envuelven el día (empiezan antes y terminan después)
                  ->orWhere(function ($q2) use ($startOfDayUtc, $endOfDayUtc) {
                      $q2->where('start_at', '<', $startOfDayUtc)
                         ->where('end_at', '>', $endOfDayUtc);
                  });
            })
            ->get();

        // 2. Obtener Bloqueos de Calendario (CalendarBlocks)
        $blocks = \App\Models\CalendarBlock::whereNull('canceled_at')
            ->where(function ($q) use ($startOfDayUtc, $endOfDayUtc) {
                $q->whereBetween('starts_at', [$startOfDayUtc, $endOfDayUtc])
                  ->orWhereBetween('ends_at', [$startOfDayUtc, $endOfDayUtc])
                  // También incluir los que envuelven el día (empiezan antes y terminan después)
                  ->orWhere(function ($q2) use ($startOfDayUtc, $endOfDayUtc) {
                      $q2->where('starts_at', '<', $startOfDayUtc)
                         ->where('ends_at', '>', $endOfDayUtc);
                  });
            })
            ->get();

        $blockedSlots = [];

        // A) Procesar Entrevistas
        foreach ($interviews as $interview) {
            // Convertir a Timezone Local
            $meetingStart = Carbon::parse($interview->start_at)->setTimezone($tz);
            $meetingEnd   = Carbon::parse($interview->end_at)->setTimezone($tz);

            $diffMinutes = abs($meetingEnd->diffInMinutes($meetingStart));
            $slotsCount = ceil($diffMinutes / 30);

            for ($i = -1; $i < $slotsCount; $i++) {
                $blockedTime = $meetingStart->copy()->addMinutes($i * 30);
                // Solo si cae en el día solicitado (en horario local)
                if ($blockedTime->isSameDay($date)) {
                    $blockedSlots[] = $blockedTime->format('H:i');
                }
            }
        }

        // B) Procesar CalendarBlocks
        foreach ($blocks as $block) {
            $blockStart = Carbon::parse($block->starts_at)->setTimezone($tz);
            $blockEnd   = Carbon::parse($block->ends_at)->setTimezone($tz);

            if ($block->is_all_day) {
                // Si es todo el día, bloqueamos todo el rango operativo (ej 09:00 a 21:00)
                // O simplemente devolvemos un flag, pero para mantener compatibilidad llenamos slots.
                // Verificamos si este bloque "toca" el día solicitado.
                // Como ya filtramos en SQL, asumimos que sí.
                // Pero ojo con los multi-día.
                
                // Si el bloque cubre todo el día solicitado:
                if ($blockStart->lte($date->copy()->endOfDay()) && $blockEnd->gte($date->copy()->startOfDay())) {
                     $startOfDay = $date->copy()->setTime(9, 0);
                     $endOfDay = $date->copy()->setTime(21, 0); // Extendemos a 21:00 por si acaso

                     while ($startOfDay->lte($endOfDay)) {
                         $blockedSlots[] = $startOfDay->format('H:i');
                         $startOfDay->addMinutes(30);
                     }
                }
                continue;
            }

            // Bloqueos parciales
            $diffMinutes = abs($blockEnd->diffInMinutes($blockStart));
            $slotsCount = ceil($diffMinutes / 30);

            for ($i = -1; $i < $slotsCount; $i++) {
                $blockedTime = $blockStart->copy()->addMinutes($i * 30);
                if ($blockedTime->isSameDay($date)) {
                    $blockedSlots[] = $blockedTime->format('H:i');
                }
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

    /**
     * Obtiene las fechas que están TOTALMENTE bloqueadas (todo el día)
     * para pintarlas en el calendario.
     */
    public function getBlockedDates(Request $request): JsonResponse
    {
        $tz = config('app.timezone', 'America/Argentina/Buenos_Aires');
        $today = Carbon::today($tz); // Hoy en local

        // 1. CalendarBlocks que son "todo el día"
        // Deben terminar DESPUÉS de hoy (o ser hoy).
        // Y deben ser is_all_day.
        $blocks = \App\Models\CalendarBlock::whereNull('canceled_at')
            ->where('is_all_day', true)
            ->where('ends_at', '>=', $today->copy()->setTimezone('UTC')) // Convertimos a UTC para comparar con DB
            ->get();

        $blockedDates = [];

        foreach ($blocks as $block) {
            // Convertir a Timezone Local para iterar fechas correctas
            $start = Carbon::parse($block->starts_at)->setTimezone($tz);
            $end   = Carbon::parse($block->ends_at)->setTimezone($tz);

            // Iteramos día por día
            $curr = $start->copy()->startOfDay();
            $endDay = $end->copy()->endOfDay();

            while ($curr->lte($endDay)) {
                // Solo agregamos si es futuro o hoy
                if ($curr->gte($today)) {
                    $blockedDates[] = $curr->format('Y-m-d');
                }
                $curr->addDay();
            }
        }

        $blockedDates = array_unique($blockedDates);
        sort($blockedDates);

        return response()->json([
            'blocked_dates' => array_values($blockedDates),
        ]);
    }
}
