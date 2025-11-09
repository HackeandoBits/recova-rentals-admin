<?php

namespace App\Rules;

use App\Models\CalendarBlock;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

class NoOverlapWithBlocks implements ValidationRule
{
    public function __construct(
        protected string|null $startAt,           // ← lo inyectamos desde el form
        protected ?int $ownerUserId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->startAt || !$value) {
            return;
        }

        // No hacemos conversiones de TZ: comparamos en la misma base que guardás en DB.
        $start = Carbon::parse($this->startAt);
        $end   = Carbon::parse($value);

        if ($end->lte($start)) {
            return; // otra regla ya marca "fin > inicio"
        }

        $ownerId = $this->ownerUserId ?? (int) env('OWNER_CAL_USER_ID', 1);

        $overlaps = CalendarBlock::query()
            ->whereNull('canceled_at')
            ->where('owner_user_id', $ownerId)
            ->where('starts_at', '<', $end)
            ->where('ends_at',   '>', $start)
            ->exists();

        if ($overlaps) {
            $fail('La fecha está bloqueada.');
        }
    }
}
