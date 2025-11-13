<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
