<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationLabel = 'Pedidos';

    protected static UnitEnum|string|null $navigationGroup = 'Agenda';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('Información del Cliente')
                    ->schema([
                        Forms\Components\TextInput::make('customer_name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('customer_email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('customer_phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(255),
                    ])->columns(3),

                Forms\Components\Section::make('Detalles del Pedido')
                    ->schema([
                        Forms\Components\Select::make('meeting_type')
                            ->label('Tipo de Solicitud')
                            ->options([
                                'whatsapp' => 'WhatsApp',
                                'reunion' => 'Reunión',
                                'none' => 'Ninguno',
                            ])
                            ->required(),
                        Forms\Components\DatePicker::make('event_date')
                            ->label('Fecha del Evento'),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas Adicionales')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Estado')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pendiente',
                                'confirmed' => 'Confirmado',
                                'cancelled' => 'Cancelado',
                            ])
                            ->required()
                            ->default('pending'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer_email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('meeting_type')
                    ->label('Tipo')
                    ->badge()
                    ->colors([
                        'success' => 'whatsapp',
                        'info' => 'reunion',
                        'gray' => 'none',
                    ]),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recibido')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pendiente',
                        'confirmed' => 'Confirmado',
                        'cancelled' => 'Cancelado',
                    ]),
                Tables\Filters\SelectFilter::make('meeting_type')
                    ->options([
                        'whatsapp' => 'WhatsApp',
                        'reunion' => 'Reunión',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }
}
