<?php

namespace App\Services;

use App\Models\Interview;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ReportService
{
    public function generatePDF(string $from, string $to, string $format = 'full'): string
    {
        $data = $this->getReportData($from, $to);
        $data['format'] = $format;
        
        $pdf = Pdf::loadView('pdf.report', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif'
            ]);
        
        $filename = 'report_' . Carbon::parse($from)->format('Ymd') . '_to_' . Carbon::parse($to)->format('Ymd') . '.pdf';
        $path = 'reports/' . $filename;
        
        Storage::disk('public')->put($path, $pdf->output());
        
        return $path;
    }
    
    public function getReportData(string $from, string $to): array
    {
        $startDate = Carbon::parse($from)->startOfDay();
        $endDate = Carbon::parse($to)->endOfDay();
        
        // Obtener todas las entrevistas del período
        $interviews = Interview::whereBetween('created_at', [$startDate, $endDate])
            ->with(['items'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Estadísticas generales
        $totalInterviews = $interviews->count();
        $statusCounts = [
            'pending' => $interviews->where('status', 'pending')->count(),
            'confirmed' => $interviews->where('status', 'confirmed')->count(),
            'cancelled' => $interviews->where('status', 'cancelled')->count(),
            'completed' => $interviews->where('status', 'completed')->count(),
        ];
        
        // Tendencia (por día)
        $trend = [];
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dayLabel = $currentDate->format('d/m');
            $count = Interview::whereDate('created_at', $currentDate->toDateString())->count();
            $trend[] = [
                'date' => $dayLabel,
                'count' => $count
            ];
            $currentDate->addDay();
        }
        
        // Productos más solicitados
        $topProducts = Interview::whereBetween('interviews.created_at', [$startDate, $endDate])
            ->join('interview_items', 'interviews.id', '=', 'interview_items.interview_id')
            ->selectRaw('interview_items.name, SUM(interview_items.quantity) as total')
            ->groupBy('interview_items.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
        
        // Clientes frecuentes
        $topClients = Interview::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('customer_email, customer_name, COUNT(*) as total_bookings')
            ->groupBy('customer_email', 'customer_name')
            ->orderByDesc('total_bookings')
            ->limit(10)
            ->get();
        
        return [
            'period_from' => $startDate->format('d/m/Y'),
            'period_to' => $endDate->format('d/m/Y'),
            'generated_at' => Carbon::now()->format('d/m/Y H:i'),
            'total_interviews' => $totalInterviews,
            'status_counts' => $statusCounts,
            'trend' => $trend,
            'interviews' => $interviews,
            'top_products' => $topProducts,
            'top_clients' => $topClients,
        ];
    }
}
