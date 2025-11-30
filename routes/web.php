<?php

use App\Http\Controllers\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Login con Google (auto-registro si no existe)
Route::get('/auth/google/login', [GoogleAuthController::class, 'loginWithGoogle'])
    ->name('google.login');

// Conectar Google Calendar (requiere estar logueado)
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
    ->name('google.redirect');

Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->name('google.callback');

Route::get('/auth/google/disconnect', [GoogleAuthController::class, 'disconnect'])
    ->name('google.disconnect');

// Ruta para descargar PDFs de reportes (no depende de Live wire)
Route::get('/admin/reports/download-pdf', function () {
    $reportService = app(\App\Services\ReportService::class);
    
    $from = request('from');
    $to = request('to');
    $format = request('format', 'full');
    
    // Generar PDF
    $pdfPath = $reportService->generatePDF($from, $to, $format);
    
    // Crear registro  en report_logs
    \App\Models\ReportLog::create([
        'user_id' => auth()->id(),
        'report_type' => 'custom',
        'period_from' => $from,
        'period_to' => $to,
        'status' => 'sent',
        'pdf_path' => $pdfPath,
        'report_format' => $format,
        'metadata' => [
            'exported_at' => now()->toDateTimeString(),
            'format' => 'pdf',
        ],
    ]);
    
    return response()->download(
        storage_path('app/public/' . $pdfPath),
        'reporte_' . \Carbon\Carbon::parse($from)->format('Ymd') . '_' . \Carbon\Carbon::parse($to)->format('Ymd') . '.pdf'
    );
})->middleware('auth')->name('reports.download.pdf');

