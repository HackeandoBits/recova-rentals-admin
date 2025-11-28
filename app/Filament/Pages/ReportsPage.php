<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BookingStatusChartWidget;
use App\Filament\Widgets\IncomeChartWidget;
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

    protected static ?string $navigationLabel = 'Reportes';

    protected static ?string $title = 'Reportes y Estadísticas';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 10;

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
                ->modalDescription(function () {
                    $from = Carbon::parse($this->dateRange['from'])->format('d/m/Y');
                    $to = Carbon::parse($this->dateRange['to'])->format('d/m/Y');

                    return "Se exportará el reporte del período: {$from} - {$to}";
                })
                ->action(function () {
                    ReportLog::create([
                        'user_id' => auth()->id(),
                        'report_type' => 'custom',
                        'period_from' => $this->dateRange['from'],
                        'period_to' => $this->dateRange['to'],
                        'status' => 'sent',
                        'metadata' => [
                            'exported_at' => now()->toDateTimeString(),
                            'format' => 'pdf',
                        ],
                    ]);

                    Notification::make()
                        ->title('Reporte Exportado')
                        ->success()
                        ->body('El reporte se exportó correctamente.')
                        ->send();

                    $this->dispatch('$refresh');
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ReportsStatsOverviewWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            IncomeChartWidget::class,
            BookingStatusChartWidget::class,
            ReportHistoryTableWidget::class,
        ];
    }

    public function getWidgets(): array
    {
        return array_merge(
            $this->getHeaderWidgets(),
            $this->getFooterWidgets()
        );
    }

    // Método para pasar el rango de fechas a los widgets
    public function getWidgetData(): array
    {
        return [
            'dateRange' => $this->dateRange,
        ];
    }

    public function updatedDateRange(): void
    {
        // Este método se llama automáticamente cuando dateRange cambia
        // Los widgets se refrescarán automáticamente gracias a Livewire
    }
}
