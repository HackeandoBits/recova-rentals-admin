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
                $interview = Interview::create([
                    'title' => 'Reunión con '.$validated['customer']['name'],
                    // Si hay fecha sugerida, la usamos. Si no, usamos "ahora" o null.
                    'start_at' => $validated['meeting_date']
                                    ? \Carbon\Carbon::parse($validated['meeting_date'])
                                    : now()->addDay()->setHour(9)->setMinute(0),
                    'end_at' => $validated['meeting_date']
                                    ? \Carbon\Carbon::parse($validated['meeting_date'])->addHour()
                                    : now()->addDay()->setHour(10)->setMinute(0),
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

        } catch (\Exception $e) {
            // 4. Manejo de Errores
            Log::error('Error creando booking desde API: '.$e->getMessage());

            return response()->json(['error' => 'Error interno al procesar el pedido'], 500);
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

        $date = Carbon::parse($validated['date']);

        // 1. Obtener Entrevistas (Interviews)
        $interviews = Interview::where('status', '!=', 'cancelled')
            ->whereDate('start_at', $date)
            ->get();

        // 2. Obtener Bloqueos de Calendario (CalendarBlocks)
        // Asumimos que CalendarBlock tiene starts_at y ends_at
        $blocks = \App\Models\CalendarBlock::whereNull('canceled_at')
            ->whereDate('starts_at', $date) // Simplificación: bloqueos que empiezan hoy
            ->get();
        // TODO: Si hay bloqueos multi-día, habría que mejorar el filtro de fecha,
        // pero por ahora asumimos bloqueos dentro del día o que al menos "tocan" el día.
        // Para ser más precisos con multi-día:
        // ->where(function($q) use ($date) {
        //    $q->whereDate('starts_at', '<=', $date)
        //      ->whereDate('ends_at', '>=', $date);
        // })

        $blockedSlots = [];

        // A) Procesar Entrevistas
        foreach ($interviews as $interview) {
            $meetingStart = Carbon::parse($interview->start_at);
            // La lógica del usuario: "si elige 10 hs entonces el horario 10 y 10:30 va a estar bloqueado"
            // Y además: "acordate si la logica es que se bloquee cada media hora entonces tendria que bloquear 2 veces uno adelante y otro atras"
            // Interpretación:
            // Si hay una reunión de 10:00 a 11:00.
            // Ocupa: 10:00, 10:30.
            // Para que NADIE se solape, no pueden empezar a las:
            // - 09:30 (porque terminaría 10:30, solapando 10:00-10:30) -> BLOQUEAR
            // - 10:00 (ocupado) -> BLOQUEAR
            // - 10:30 (ocupado) -> BLOQUEAR
            // - 11:00 (libre, empieza justo cuando termina la otra) -> LIBRE

            // Entonces, bloqueamos desde (Start - 30min) hasta (End - 30min)
            // Ejemplo 10:00 a 11:00:
            // Start: 10:00. End: 11:00.
            // Bloquear: 09:30, 10:00, 10:30.

            // Iteramos desde -1 (30 min antes) hasta < duración en slots
            // Duración en horas = $interview->end_at->diffInHours($interview->start_at) ?? 1;
            // Asumimos reuniones de 1h por defecto si no se calcula, pero mejor calcular.

            $meetingEnd = Carbon::parse($interview->end_at);
            $diffMinutes = abs($meetingEnd->diffInMinutes($meetingStart)); // Fix: Ensure positive
            $slotsCount = ceil($diffMinutes / 30); // Cantidad de slots de 30 min que dura la reunión

            // Empezamos 1 slot antes (-1)
            // Terminamos 1 slot antes del final (porque si termina a las 11:00, las 11:00 está libre)
            // O sea, si dura 2 slots (1h), slotsCount = 2.
            // i va de -1 a 0, 1. (3 slots totales: -30m, 0m, +30m).

            for ($i = -1; $i < $slotsCount; $i++) {
                $blockedTime = $meetingStart->copy()->addMinutes($i * 30);
                $blockedSlots[] = $blockedTime->format('H:i');
            }
        }

        // B) Procesar CalendarBlocks
        foreach ($blocks as $block) {
            $blockStart = Carbon::parse($block->starts_at);
            $blockEnd = Carbon::parse($block->ends_at);

            // Para los bloqueos manuales, ¿aplicamos la misma lógica de "buffer"?
            // El usuario dijo: "si hace click en el calendario de fecha va a ver el calendario con las fechas bloqueadas... pero en el horario si se va a marcar los horarios ocupados"
            // Si el admin bloquea de 14:00 a 15:00, es porque NO quiere reuniones ahí.
            // Si alguien quiere agendar a las 13:30 (termina 14:30), SE SOLAPA con el bloqueo.
            // Así que SÍ, deberíamos aplicar el buffer de seguridad "uno antes".

            // Ajuste: Si es "todo el día", bloqueamos todos los slots?
            if ($block->is_all_day) {
                // Podríamos devolver un flag especial o llenar todos los slots.
                // Por simplicidad, llenamos "todos" los slots típicos (09:00 a 18:00)
                $startOfDay = $date->copy()->setTime(9, 0);
                $endOfDay = $date->copy()->setTime(18, 0);

                while ($startOfDay->lte($endOfDay)) {
                    $blockedSlots[] = $startOfDay->format('H:i');
                    $startOfDay->addMinutes(30);
                }

                continue;
            }

            // Lógica normal para bloqueos parciales
            // Iteramos por cada slot de 30 min dentro del rango del bloqueo
            // Y también aplicamos el "uno antes" para evitar que alguien se meta justo antes y termine dentro.

            // Normalizamos al inicio del slot (ej 14:10 -> 14:00) para simplificar, o usamos lógica estricta?
            // Usemos lógica de slots fijos de la app (09:00, 09:30...)

            // Estrategia: Recorrer todos los slots posibles del día (09:00 a 18:00)
            // y ver si CADA slot + 1 hora (duración de la nueva reunión) solapa con el bloqueo.
            // Si solapa, ese slot de inicio está BLOQUEADO.

            // Esta estrategia es más robusta que "pintar" slots.
            // Probemos implementarla así para TODO (Interviews y Blocks), es más limpio.
            // Pero para mantener consistencia con lo de arriba, lo haré similar:

            // Bloqueo: 14:00 a 15:00.
            // Slots afectados (donde NO puede empezar una reunión de 1h):
            // 13:30 (termina 14:30 -> solapa) -> BLOQUEAR
            // 14:00 (termina 15:00 -> solapa) -> BLOQUEAR
            // 14:30 (termina 15:30 -> solapa) -> BLOQUEAR
            // 15:00 (termina 16:00 -> NO solapa, toca borde) -> LIBRE (si edgeAllowed=true)

            $diffMinutes = abs($blockEnd->diffInMinutes($blockStart)); // Fix: Ensure positive
            $slotsCount = ceil($diffMinutes / 30);

            for ($i = -1; $i < $slotsCount; $i++) {
                $blockedTime = $blockStart->copy()->addMinutes($i * 30);
                // Solo agregamos si es del mismo día (por si el bloqueo viene del día anterior)
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
        // Buscamos bloqueos desde hoy en adelante
        $today = Carbon::today();

        // 1. CalendarBlocks que son "todo el día"
        $blocks = \App\Models\CalendarBlock::whereNull('canceled_at')
            ->where('starts_at', '>=', $today)
            ->where('is_all_day', true)
            ->get();

        $blockedDates = [];

        foreach ($blocks as $block) {
            // Si es un rango de varios días, agregamos todos
            $start = Carbon::parse($block->starts_at);
            $end = Carbon::parse($block->ends_at);

            // Iteramos día por día
            $curr = $start->copy();
            while ($curr->lte($end)) {
                $blockedDates[] = $curr->format('Y-m-d');
                $curr->addDay();
            }
        }

        // 2. Podríamos agregar lógica para detectar si un día está LLENO de reuniones
        // pero eso es más complejo y costoso. Por ahora, solo bloqueos explícitos.

        $blockedDates = array_unique($blockedDates);
        sort($blockedDates);

        return response()->json([
            'blocked_dates' => array_values($blockedDates),
        ]);
    }
}
