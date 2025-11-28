<?php

namespace App\Filament\Resources\CalendarBlocks;

use App\Filament\Resources\CalendarBlocks\Pages\CreateCalendarBlock;
use App\Filament\Resources\CalendarBlocks\Pages\EditCalendarBlock;
use App\Filament\Resources\CalendarBlocks\Pages\ListCalendarBlocks;
use App\Filament\Resources\CalendarBlocks\Schemas\CalendarBlockForm;
use App\Filament\Resources\CalendarBlocks\Tables\CalendarBlocksTable;
use App\Models\CalendarBlock;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class CalendarBlockResource extends Resource
{
    protected static ?string $model = CalendarBlock::class;

    // Navegación (mismos tipos que tu InterviewResource)
    protected static ?string $navigationGroup = 'Agenda';

    protected static ?string $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationLabel = 'Bloqueos de agenda';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return 'Bloqueo';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Bloqueos';
    }

    public static function form(Form $form): Form
    {
        return CalendarBlockForm::configure($form);
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
