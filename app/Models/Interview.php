<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class Interview extends Model
{
    /**
     * Los campos que vienen de AMBOS modelos
     */
    protected $fillable = [
        'title',
        'start_at',
        'end_at',
        'status',
        'google_event_id',
        // 'booking_id', // Removed
        'channel',
        'location_note',
        // New fields
        'customer_name',
        'customer_email',
        'customer_phone',
        'event_date',
        'service_type',
        'order_notes',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'event_date' => 'date',
    ];

    /**
     * Toda tu lógica de validación de solapamiento
     * (Esto está perfecto, no se toca)
     */
    protected static function booted(): void
    {
        static::saving(function (Interview $i) {
            // 0) No permitir entrevistas en días anteriores al día actual
            $today = Carbon::now()->startOfDay();

            if ($i->start_at && $i->start_at->lt($today)) {
                throw ValidationException::withMessages([
                    'start_at' => 'La entrevista no puede agendarse antes del día actual.',
                ]);
            }

            // 1) Fin > inicio
            if ($i->end_at && $i->start_at && $i->end_at->lte($i->start_at)) {
                throw ValidationException::withMessages([
                    'end_at' => 'La hora de fin debe ser posterior al inicio.',
                ]);
            }

            // 2) No solapar con otras entrevistas
            $conflict = static::query()
                ->when($i->exists, fn ($q) => $q->where('id', '!=', $i->id))
                ->where('status', '!=', 'cancelled')
                ->where('end_at', '>', $i->start_at)
                ->where('start_at', '<', $i->end_at)
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'end_at' => 'Existe otra reunión que se solapa con el período seleccionado.',
                ]);
            }

            // 3) No solapar con Calendar Blocks
            $ownerId = (int) env('OWNER_CAL_USER_ID', 1);

            $blocked = CalendarBlock::query()
                ->whereNull('canceled_at')
                ->where('owner_user_id', $ownerId)
                ->where('starts_at', '<', $i->end_at)
                ->where('ends_at', '>', $i->start_at)
                ->exists();

            if ($blocked) {
                throw ValidationException::withMessages([
                    'end_at' => 'Existe un Calendar Block activo que bloquea ese horario.',
                ]);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    /**
     * Items solicitados en este pedido/reunión.
     */
    public function items()
    {
        return $this->hasMany(InterviewItem::class);
    }

    /**
     * El miembro del staff asignado a esta reunión.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes (Robados de Appointment)
    |--------------------------------------------------------------------------
    */

    /**
     * Filtra reuniones para un usuario en un rango de tiempo.
     */
    public function scopeForUserBetween($q, int $userId, $from, $to)
    {
        return $q->where('assigned_user_id', $userId)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from);
    }

    /**
     * Filtra reuniones en una fecha específica.
     */
    public function scopeOnDate($q, Carbon|string $date)
    {
        $d = Carbon::parse($date);

        return $q->whereDate('starts_at', $d->toDateString());
    }
}
