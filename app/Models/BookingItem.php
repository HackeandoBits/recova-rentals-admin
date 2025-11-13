<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id', 'product_type', 'product_id',
        'name', 'category', 'description', 'quantity', 'note',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    // Polimórfica manual (usa columnas product_type/product_id)
    public function product()
    {
        return $this->morphTo(__FUNCTION__, 'product_type', 'product_id');
    }
}
