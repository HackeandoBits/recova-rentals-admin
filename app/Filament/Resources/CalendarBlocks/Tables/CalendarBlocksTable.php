<?php

namespace App\Filament\Resources\CalendarBlocks\Tables;

use App\Models\CalendarBlock;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CalendarBlocksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('kind')
                    ->label('Tipo')
                    ->colors([
                        'primary' => 'manual',
                        'warning' => 'mantenimiento',
                        'success' => 'feriado',
                        'secondary' => 'otro',
                    ]),

                IconColumn::make('is_all_day')
                    ->boolean()
                    ->label('Día completo'),

                TextColumn::make('starts_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Desde')
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Hasta')
                    ->sortable(),

                TextColumn::make('sync_status')
                    ->badge(),

                TextColumn::make('synced_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Sincronizado'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
