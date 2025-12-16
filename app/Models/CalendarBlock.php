<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class CalendarBlock extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'starts_at',
        'ends_at',
        'is_all_day',
        'kind',
        'reason',
        'owner_user_id',
        'google_event_id',
        'sync_status',
        'synced_at',
        'last_error',
        'created_by',
        'updated_by',
        'canceled_at',
    ];

    protected $casts = [
        'starts_at' => 'immutable_datetime',
        'ends_at' => 'immutable_datetime',
        'is_all_day' => 'boolean',
        'synced_at' => 'immutable_datetime',
        'canceled_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (CalendarBlock $block) {
            // 0) No permitir bloques en días anteriores al día actual (SOLO PARA MANUALES)
            // Si viene de Google (tiene google_event_id) permitimos histórico para consistencia o re-sync.
            $today = CarbonImmutable::now()->startOfDay();

            if (! $block->google_event_id && $block->starts_at && $block->starts_at->lt($today)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'El bloqueo de agenda no puede crearse antes del día actual.',
                ]);
            }

            // 1) Fin > inicio
            if ($block->ends_at && $block->starts_at && $block->ends_at->lte($block->starts_at)) {
                throw ValidationException::withMessages([
                    'ends_at' => 'La hora de fin debe ser posterior al inicio.',
                ]);
            }
        });
    }

    public function scopeActive($q)
    {
        return $q->whereNull('canceled_at');
    }

    /** Devuelve true si se solapa con [start,end) usando borde-borde permitido. */
    public function overlaps(CarbonImmutable $start, CarbonImmutable $end, bool $edgeAllowed = true): bool
    {
        if ($edgeAllowed) {
            return $this->starts_at < $end && $this->ends_at > $start;
        }

        return $this->starts_at <= $end && $this->ends_at >= $start;
    }
}
