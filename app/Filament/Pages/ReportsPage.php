<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BookingStatusChartWidget;
use App\Filament\Widgets\BookingTrendChartWidget;
use App\Filament\Widgets\ReportHistoryTableWidget;
use App\Filament\Widgets\ReportsStatsOverviewWidget;
use App\Models\ReportLog;
use Filament\Actions\Action;
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
                    $from = $this->dateRange['from'] ? Carbon::parse($this->dateRange['from'])->format('d/m/Y') : 'N/A';
                    $to = $this->dateRange['to'] ? Carbon::parse($this->dateRange['to'])->format('d/m/Y') : 'N/A';

                    return "Se generará un reporte del período: {$from} - {$to} y se enviará por WhatsApp al número configurado.";
                })
                ->modalSubmitActionLabel('Generar y Enviar')
                ->action(function () {
                    // 1. Obtener datos del periodo seleccionado
                    $from = $this->dateRange['from'] ? Carbon::parse($this->dateRange['from']) : Carbon::now()->startOfMonth();
                    $to = $this->dateRange['to'] ? Carbon::parse($this->dateRange['to']) : Carbon::now()->endOfMonth();

                    // Query base para el periodo actual
                    $query = \App\Models\Interview::whereBetween('created_at', [$from, $to]);

                    // Estadísticas completas
                    $stats = [
                        'total' => (clone $query)->count(),
                        'pending' => (clone $query)->where('status', 'pending')->count(),
                        'confirmed' => (clone $query)->where('status', 'confirmed')->count(),
                        'completed' => (clone $query)->where('status', 'completed')->count(),
                        'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
                        'new_clients' => (clone $query)->distinct('customer_email')->count('customer_email'),
                    ];

                    // Comparativa con periodo anterior (mismo rango de días hacia atrás)
                    $daysDiff = $from->diffInDays($to) + 1;
                    $prevFrom = $from->copy()->subDays($daysDiff);
                    $prevTo = $to->copy()->subDays($daysDiff);

                    $prevTotal = \App\Models\Interview::whereBetween('created_at', [$prevFrom, $prevTo])->count();

                    // Calcular porcentaje de crecimiento
                    $growth = 0;
                    if ($prevTotal > 0) {
                        $growth = (($stats['total'] - $prevTotal) / $prevTotal) * 100;
                    } elseif ($stats['total'] > 0) {
                        $growth = 100; // Crecimiento infinito si antes era 0
                    }
                    $growthSign = $growth > 0 ? '+' : '';
                    $growthIcon = $growth > 0 ? '📈' : ($growth < 0 ? '📉' : '➖');

                    // 2. Formatear mensaje COMPLETO para WhatsApp
                    $periodo = $from->format('d/m/Y').' - '.$to->format('d/m/Y');

                    $message = "📊 *REPORTE DETALLADO RECOVA RENTALS*\n\n";
                    $message .= "🗓 *Período:* {$periodo}\n";
                    $message .= "{$growthIcon} *Tendencia:* {$growthSign}".number_format($growth, 1)."% vs periodo anterior\n\n";

                    $message .= "🔢 *Resumen General:*\n";
                    $message .= "➤ *Total Solicitudes:* {$stats['total']}\n";
                    $message .= "➤ *Nuevos Clientes:* {$stats['new_clients']}\n\n";

                    $message .= "📌 *Desglose por Estado:*\n";
                    $message .= "🟡 *Pendientes:* {$stats['pending']}\n";
                    $message .= "🟢 *Confirmadas:* {$stats['confirmed']}\n";
                    $message .= "🔵 *Completadas:* {$stats['completed']}\n";
                    $message .= "🔴 *Canceladas:* {$stats['cancelled']}\n\n";

                    $message .= '_Reporte generado automáticamente desde el panel de administración._';

                    // 3. Enviar (Dual: Template o Texto Texto)
                    try {
                        $recipient = env('TWILIO_WHATSAPP_TO');
                        $templateSid = env('TWILIO_REPORT_TEMPLATE_SID'); // SID de la plantilla (HX...)

                        if (! $recipient) {
                            throw new \Exception('No hay número de destinatario configurado (TWILIO_WHATSAPP_TO).');
                        }

                        /** @var \App\Services\TwilioService $twilio */
                        $twilio = app(\App\Services\TwilioService::class);
                        $sent = false;

                        if ($templateSid) {
                            // --- MODO PLANTILLA (Bypass 24h window) ---
                            // Variables: 1=Periodo, 2=Tendencia, 3=Total, 4=Nuevos, 5=Pend, 6=Conf, 7=Comp, 8=Canc
                            $variables = [
                                '1' => $periodo,
                                '2' => $growthSign.number_format($growth, 1).'%',
                                '3' => (string) $stats['total'],
                                '4' => (string) $stats['new_clients'],
                                '5' => (string) $stats['pending'],
                                '6' => (string) $stats['confirmed'],
                                '7' => (string) $stats['completed'],
                                '8' => (string) $stats['cancelled'],
                            ];

                            $sent = $twilio->sendWhatsAppTemplate($recipient, $templateSid, $variables);
                            $reportType = 'whatsapp_template';
                            $contentRef = "Plantilla: {$templateSid}";

                        } else {
                            // --- MODO TEXTO LIBRE (Solo funciona con sesión activa) ---
                            $sent = $twilio->sendWhatsAppNotification($recipient, $message);
                            $reportType = 'whatsapp_text';
                            $contentRef = substr($message, 0, 100).'...';
                        }

                        if (! $sent) {
                            throw new \Exception('El servicio de Twilio falló al enviar el mensaje.');
                        }

                        // 4. Registrar log exitoso
                        ReportLog::create([
                            'user_id' => auth()->id(),
                            'report_type' => $reportType ?? 'whatsapp',
                            'period_from' => $from->toDateString(),
                            'period_to' => $to->toDateString(),
                            'status' => 'sent',
                            'metadata' => [
                                'sent_at' => now()->toDateTimeString(),
                                'recipient' => $recipient,
                                'content_ref' => $contentRef,
                            ],
                        ]);

                        Notification::make()
                            ->title('Reporte enviado por WhatsApp')
                            ->success()
                            ->body("Se envió el resumen al número {$recipient}")
                            ->send();

                    } catch (\Exception $e) {
                        // Registrar log fallido
                        ReportLog::create([
                            'user_id' => auth()->id(),
                            'report_type' => 'whatsapp',
                            'period_from' => $from->toDateString(),
                            'period_to' => $to->toDateString(),
                            'status' => 'failed',
                            'metadata' => [
                                'error' => $e->getMessage(),
                                'attempted_at' => now()->toDateTimeString(),
                            ],
                        ]);

                        Notification::make()
                            ->title('Error al enviar reporte')
                            ->danger()
                            ->body($e->getMessage())
                            ->send();
                    }

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
                    $from = $this->dateRange['from'] ? Carbon::parse($this->dateRange['from'])->format('d/m/Y') : 'N/A';
                    $to = $this->dateRange['to'] ? Carbon::parse($this->dateRange['to'])->format('d/m/Y') : 'N/A';

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

    public function updatedDateRange(): void
    {
        // Este método se llama automáticamente cuando dateRange cambia
        // Los widgets se refrescarán automáticamente gracias a Livewire
        $this->dispatch('updateChartData');
    }
}
