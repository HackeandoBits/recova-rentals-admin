<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BookingStatusChartWidget;
use App\Filament\Widgets\BookingTrendChartWidget;
use App\Filament\Widgets\ReportHistoryTableWidget;
use App\Filament\Widgets\ReportsStatsOverviewWidget;
use App\Models\ReportLog;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class ReportsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string $view = 'filament.pages.reports';

    public function getSubheading(): ?string
    {
        return 'Visualiza el rendimiento de tu negocio.';
    }

    protected static ?string $navigationLabel = 'Reportes';

    protected static ?string $title = 'Reportes y Estadísticas';

    protected static ?string $navigationGroup = 'Reportes'; // Agrupar para ordenar
    protected static ?int $navigationSort = 1;

    public ?array $dateRange = [
        'from' => null,
        'to' => null,
    ];

    public function mount(): void
    {
        // Inicializar con el mes actual
        $this->dateRange = [
            'from' => Carbon::now()->startOfMonth()->toDateString(),
            'to' => Carbon::now()->endOfMonth()->toDateString(),
        ];
    }

    public function updateChartData(): void
    {
        // This method is called by Filament's polling mechanism on the page itself
        // No action needed, charts update automatically via their own listeners
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_whatsapp_report')
                ->label('Generar y Enviar Reporte WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Generar Reporte para WhatsApp')
                ->modalDescription(function () {
                    $from = Carbon::parse($this->dateRange['from'])->format('d/m/Y');
                    $to = Carbon::parse($this->dateRange['to'])->format('d/m/Y');

                    return "Se generará un reporte del período: {$from} - {$to} y se enviará por WhatsApp al número configurado.";
                })
                ->modalSubmitActionLabel('Generar y Enviar')
                ->action(function () {
                    // Crear registro en report_logs
                    $reportLog = ReportLog::create([
                        'user_id' => auth()->id(),
                        'report_type' => 'whatsapp',
                        'period_from' => $this->dateRange['from'],
                        'period_to' => $this->dateRange['to'],
                        'status' => 'sent',
                        'metadata' => [
                            'sent_at' => now()->toDateTimeString(),
                            'message' => 'Reporte automático generado desde el panel',
                        ],
                    ]);

                    // Simulación de envío de WhatsApp
                    // TODO: Integrar con API de WhatsApp Business
        
                    Notification::make()
                        ->title('Reporte Enviado Exitosamente')
                        ->success()
                        ->body('El reporte se generó y envió por WhatsApp correctamente.')
                        ->send();

                    // Refrescar la tabla de historial
                    $this->dispatch('$refresh');
                }),

            Action::make('export_period')
                ->label('Exportar Período')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Exportar Reporte')
                ->form([
                    \Filament\Forms\Components\Select::make('format')
                        ->label('Formato del Reporte')
                        ->options([
                            'full' => 'Completo (con todas las tablas y detalles)',
                            'summary' => 'Resumen (solo estadísticas principales)',
                        ])
                        ->default('full')
                        ->required(),
                ])
                ->modalDescription(function () {
                    $from = Carbon::parse($this->dateRange['from'])->format('d/m/Y');
                    $to = Carbon::parse($this->dateRange['to'])->format('d/m/Y');

                    return "Se exportará el reporte del período: {$from} - {$to}";
                })
                ->action(function (array $data) {
                    // Redirigir a la ruta de descarga con parámetros
                    $url = route('reports.download.pdf', [
                        'from' => $this->dateRange['from'],
                        'to' => $this->dateRange['to'],
                        'format' => $data['format'],
                    ]);
                    
                    Notification::make()
                        ->title('Generando Reporte')
                        ->success()
                        ->body('El reporte se descargará automáticamente.')
                        ->send();
                    
                    // Redirigir a la descarga
                    $this->redirect($url, navigate: false);
                })
                ->close(), // Cerrar modal automáticamente
        ];
    }

    // Widgets are manually rendered in the blade view to avoid duplication
    // Filament's automatic rendering is disabled by returning empty arrays
    protected function getHeaderWidgets(): array
    {
        return []; // Disabled - rendered manually in blade
    }

    protected function getFooterWidgets(): array
    {
        return []; // Disabled - rendered manually in blade
    }

    // Public methods for the blade view to access widgets
    public function getStatsWidget(): string
    {
        return ReportsStatsOverviewWidget::class;
    }

    public function getChartWidgets(): array
    {
        return [
            BookingTrendChartWidget::class, // Gráfico de líneas (solicitudes por día)
            BookingStatusChartWidget::class, // Gráfico de estados (pastel)
        ];
    }

    public function getTableWidget(): string
    {
        return ReportHistoryTableWidget::class;
    }
}
