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
        protected ?int $ignoreId = null
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($this->startAt) || blank($value)) return;

        $start = Carbon::parse($this->startAt);
        $end   = Carbon::parse($value);

        if ($end->lessThanOrEqualTo($start)) {
            $fail('La hora de fin debe ser posterior al inicio.');
            return;
        }

        $q = Interview::query()
            ->where('end_at', '>', $start)
            ->where('start_at', '<', $end)
            ->where('status', '!=', 'cancelled');

        if ($this->ignoreId) $q->where('id', '!=', $this->ignoreId);

        if ($q->exists()) {
            $fail('Existe otra reunión que se solapa con el período seleccionado.');
        }
    }
}
