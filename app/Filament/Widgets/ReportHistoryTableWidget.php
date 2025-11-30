<?php

namespace App\Filament\Widgets;

use App\Models\ReportLog;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ReportHistoryTableWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function updateChartData(): void
    {
        // This method is called by Filament's polling mechanism
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ReportLog::query()
                    ->with('user')
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Envío')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Admin')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('report_type')
                    ->label('Tipo de Reporte')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'monthly' => 'Mensual',
                        'custom' => 'Personalizado',
                        'whatsapp' => 'WhatsApp',
                        default => ucfirst($state),
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'monthly' => 'info',
                        'custom' => 'warning',
                        'whatsapp' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('period_from')
                    ->label('Desde')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('period_to')
                    ->label('Hasta')
                    ->date('d/m/Y'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'sent' => 'Enviado',
                        'failed' => 'Falló',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download_pdf')
                    ->label('Descargar PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->action(function (ReportLog $record) {
                        // Verificar si el PDF existe
                        if (!$record->pdf_path || !\Illuminate\Support\Facades\Storage::disk('public')->exists($record->pdf_path)) {
                            // Regenerar el PDF
                            $reportService = app(\App\Services\ReportService::class);
                            $pdfPath = $reportService->generatePDF(
                                $record->period_from->toDateString(),
                                $record->period_to->toDateString(),
                                $record->report_format ?? 'full'
                            );
                            
                            // Actualizar el registro
                            $record->update(['pdf_path' => $pdfPath]);
                            
                            // Refrescar el record para obtener el valor actualizado
                            $record->refresh();
                        }
                        
                        // Descargar el PDF
                        return response()->download(
                            storage_path('app/public/' . $record->pdf_path),
                            'reporte_' . $record->period_from->format('Ymd') . '_' . $record->period_to->format('Ymd') . '.pdf'
                        );
                    }),

                Tables\Actions\Action::make('resend')
                    ->label('Reenviar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('¿Reenviar este reporte?')
                    ->modalDescription('Se creará un nuevo registro de envío con los mismos parámetros.')
                    ->action(function (ReportLog $record) {
                        // Crear nuevo registro duplicando el anterior
                        $newLog = ReportLog::create([
                            'user_id' => auth()->id(),
                            'report_type' => $record->report_type,
                            'period_from' => $record->period_from,
                            'period_to' => $record->period_to,
                            'status' => 'sent',
                            'metadata' => array_merge($record->metadata ?? [], [
                                'resent_from' => $record->id,
                                'resent_at' => now()->toDateTimeString(),
                            ]),
                        ]);

                        Notification::make()
                            ->title('Reporte Reenviado')
                            ->success()
                            ->body('El reporte se envió nuevamente exitosamente.')
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->heading('Historial de Reportes Generados')
            ->description('Todos los reportes que se han generado y enviado desde el sistema.');
    }
}
