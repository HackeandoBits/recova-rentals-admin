<?php

namespace App\Rules;

use App\Models\Interview;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

class NoOverlapRule implements ValidationRule
{
    public function __construct(
        protected $startAt,
        protected ?int $ignoreId = null,
        protected int $minutes = 60, // <- buffer (por defecto 60)
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($this->startAt) || blank($value)) {
            return;
        }

        $start = Carbon::parse($this->startAt);
        $end = Carbon::parse($value);

        if ($end->lessThanOrEqualTo($start)) {
            $fail('La hora de fin debe ser posterior al inicio.');

            return;
        }

        // Expande el rango con buffer
        $bufStart = $start->copy()->subMinutes($this->minutes);
        $bufEnd = $end->copy()->addMinutes($this->minutes);

        $q = Interview::query()
            // solapa si: start_at < bufEnd  y  end_at > bufStart
            ->where('start_at', '<', $bufEnd)
            ->where('end_at', '>', $bufStart)
            ->where('status', '!=', 'cancelled');

        if ($this->ignoreId) {
            $q->where('id', '!=', $this->ignoreId);
        }

        if ($q->exists()) {
            $fail("Existe otra reunión dentro de {$this->minutes} minutos del período seleccionado.");
        }
    }
}
