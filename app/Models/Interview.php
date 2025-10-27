<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Interview extends Model
{
    protected $fillable = ['title','start_at','end_at','status'];
    protected $casts = ['start_at' => 'datetime', 'end_at' => 'datetime'];

    protected static function booted(): void
    {
        static::saving(function (Interview $i) {
            if ($i->end_at && $i->start_at && $i->end_at->lte($i->start_at)) {
                throw ValidationException::withMessages([
                    'end_at' => 'La hora de fin debe ser posterior al inicio.',
                ]);
            }

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
        });
    }
}
