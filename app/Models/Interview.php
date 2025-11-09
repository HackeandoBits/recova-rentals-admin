<?php

namespace App\Models;

use App\Models\CalendarBlock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Interview extends Model
{
    protected $fillable = ['title', 'start_at', 'end_at', 'status', 'google_event_id'];

    // Estos casts devuelven Carbon, así podemos usar ->toDateString() y comparaciones.
    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Interview $i) {
            // 1) Fin > inicio
            if ($i->end_at && $i->start_at && $i->end_at->lte($i->start_at)) {
                throw ValidationException::withMessages([
                    'end_at' => 'La hora de fin debe ser posterior al inicio.',
                ]);
            }

            // 2) No solapar con otras entrevistas (borde-borde permitido)
            $conflict = static::query()
                ->when($i->exists, fn($q) => $q->where('id', '!=', $i->id))
                ->where('status', '!=', 'cancelled')
                ->where('end_at', '>', $i->start_at) // estricto
                ->where('start_at', '<', $i->end_at)   // estricto
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'end_at' => 'Existe otra reunión que se solapa con el período seleccionado.',
                ]);
            }

            // 3) No solapar con Calendar Blocks (borde-borde permitido)
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
}
