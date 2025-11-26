<?php

namespace App\Filament\Resources\Interviews\Schemas;

use App\Rules\NoOverlapRule;
use App\Rules\NoOverlapWithBlocks;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InterviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Título')
                ->maxLength(120),

            Forms\Components\Section::make('Detalles del Pedido')
                ->schema([
                    Forms\Components\Placeholder::make('customer')
                        ->label('Cliente')
                        ->content(fn ($record) => $record?->booking ? "{$record->booking->customer_name} ({$record->booking->customer_email})" : 'N/A'),
                    
                    Forms\Components\Placeholder::make('items')
                        ->label('Items Solicitados')
                        ->content(fn ($record) => $record?->booking?->items->map(fn($item) => "{$item->quantity}x {$item->name}")->join(', ') ?? 'N/A'),

                    Forms\Components\Placeholder::make('notes')
                        ->label('Notas del Cliente')
                        ->content(fn ($record) => $record?->booking?->notes ?? 'N/A'),
                ])
                ->collapsible()
                ->collapsed(),

            DateTimePicker::make('start_at')
                ->label('Inicio')
                ->seconds(false)
                ->required()
                // No permitir entrevistas en días anteriores al día actual
                ->minDate(fn() => Carbon::today()),

            DateTimePicker::make('end_at')
                ->label('Fin')
                ->seconds(false)
                ->required()
                // La fecha mínima de fin es el inicio (si existe) o, en su defecto, hoy
                ->minDate(fn(callable $get) => $get('start_at') ?? Carbon::today())
                ->rules([
                    // fin > inicio (una sola vez)
                    fn($get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                        $start = $get('start_at');
                        if ($start && $value && Carbon::parse($value)->lte(Carbon::parse($start))) {
                            $fail('La hora de fin debe ser posterior al inicio.');
                        }
                    },
                    fn($get, $record) => new NoOverlapRule($get('start_at'), $record?->id, 60),
                    fn($get) => new NoOverlapWithBlocks($get('start_at'), (int) env('OWNER_CAL_USER_ID', 1)),
                ]),

            Select::make('status')
                ->label('Estado')
                ->options([
                    'pending' => 'Pendiente',
                    'confirmed' => 'Confirmada',
                    'cancelled' => 'Cancelada',
                ])
                ->default('pending')
                ->required(),
        ])->columns(2);
    }
}
