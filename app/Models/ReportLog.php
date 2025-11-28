<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportLog extends Model
{
    protected $fillable = [
        'user_id',
        'report_type',
        'period_from',
        'period_to',
        'status',
        'metadata',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'metadata' => 'array',
    ];

    /**
     * Relación con el usuario que generó el reporte
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Reportes enviados exitosamente
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope: Reportes fallidos
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope: Reportes en un período
     */
    public function scopeBetweenDates($query, $from, $to)
    {
        return $query->whereBetween('period_from', [$from, $to]);
    }

    /**
     * Scope: Por tipo de reporte
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('report_type', $type);
    }
}
