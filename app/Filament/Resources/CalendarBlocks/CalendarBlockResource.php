<?php

namespace App\Filament\Resources\CalendarBlocks;

use App\Filament\Resources\CalendarBlocks\Pages\CreateCalendarBlock;
use App\Filament\Resources\CalendarBlocks\Pages\EditCalendarBlock;
use App\Filament\Resources\CalendarBlocks\Pages\ListCalendarBlocks;
use App\Filament\Resources\CalendarBlocks\Schemas\CalendarBlockForm;
use App\Filament\Resources\CalendarBlocks\Tables\CalendarBlocksTable;
use App\Models\CalendarBlock;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;                 // <-- igual que Interview
use Filament\Support\Icons\Heroicon;         // <-- igual que Interview
use Filament\Tables\Table;
use UnitEnum;

class CalendarBlockResource extends Resource
{
    protected static ?string $model = CalendarBlock::class;

    // Navegación (mismos tipos que tu InterviewResource)
    protected static UnitEnum|string|null $navigationGroup = 'Agenda';

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?string $navigationLabel = 'Bloques de agenda';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return CalendarBlockForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CalendarBlocksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCalendarBlocks::route('/'),
            'create' => CreateCalendarBlock::route('/create'),
            'edit' => EditCalendarBlock::route('/{record}/edit'),
        ];
    }
}
