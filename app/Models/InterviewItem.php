<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InterviewItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'interview_id', 'product_type', 'product_id',
        'name', 'category', 'description', 'quantity', 'note',
    ];

    public function interview()
    {
        return $this->belongsTo(Interview::class);
    }

    // Polimórfica manual (usa columnas product_type/product_id)
    public function product()
    {
        return $this->morphTo(__FUNCTION__, 'product_type', 'product_id');
    }
}
