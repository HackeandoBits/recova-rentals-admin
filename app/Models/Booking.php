<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_name', 'customer_email', 'customer_phone',
        'event_date', 'meeting_type', 'meeting_date', 'meeting_time_note',
        'service_type', 'notes', 'status', 'created_by_user_id',
    ];

    protected $casts = [
        'event_date' => 'date',
        'meeting_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}
