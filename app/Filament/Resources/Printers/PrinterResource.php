<?php

namespace App\Filament\Resources\Printers;

use App\Enums\PrinterRole;
use App\Filament\Concerns\ManagesKitchen;
use App\Filament\Resources\Printers\Pages\ManagePrinters;
use App\Models\Branch;
use App\Models\Printer;
use App\Printing\PrintQueue;
use App\Printing\Tickets;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Network thermal printers at each branch. The branch's print gateway
 * sends RasaHub's print jobs to them.
 */
class PrinterResource extends Resource
{
    use ManagesKitchen;

    protected static ?string $model = Printer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static string|UnitEnum|null $navigationGroup = 'Kitchen';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('branch_id', Branch::visibleTo(auth()->user())->select('id'));
    }

    public static function form(Schema $schema): Schema
    {
        $role = fn (Get $get): ?PrinterRole => $get('role') instanceof PrinterRole ? $get('role') : PrinterRole::tryFrom((string) $get('role'));

        return $schema
            ->columns(2)
            ->components([
                Select::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name', fn (Builder $query): Builder => $query->withoutTrashed()->visibleTo(auth()->user()))
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->helperText('For example: Counter, Kitchen, Bar.')
                    ->required()
                    ->maxLength(255),
                Select::make('role')
                    ->label('Prints')
                    ->options(PrinterRole::options())
                    ->default(PrinterRole::Kitchen->value)
                    ->selectablePlaceholder(false)
                    ->live()
                    ->required(),
                Select::make('stations')
                    ->label('Tickets for')
                    ->helperText('The stations whose tickets this printer prints. Each station has one printer per branch.')
                    ->relationship('stations', 'name', fn (Builder $query): Builder => $query->where('is_active', true))
                    ->multiple()
                    ->preload()
                    ->visible(fn (Get $get): bool => $role($get) !== PrinterRole::Receipt),
                TextInput::make('host')
                    ->label('IP address')
                    ->helperText('The printer\'s fixed address on the branch network, for example 192.168.1.50.')
                    ->required()
                    ->maxLength(255)
                    ->rule('regex:/^[A-Za-z0-9.\-]+$/'),
                TextInput::make('port')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(65535)
                    ->default(9100)
                    ->required(),
                Select::make('paper_width')
                    ->label('Paper')
                    ->options([80 => '80 mm', 58 => '58 mm'])
                    ->default(80)
                    ->selectablePlaceholder(false)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['branch', 'stations']))
            ->columns([
                TextColumn::make('branch.name')->label('Branch'),
                TextColumn::make('name'),
                TextColumn::make('role')
                    ->label('Prints')
                    ->formatStateUsing(fn (PrinterRole $state): string => $state->label()),
                TextColumn::make('stations.name')
                    ->label('Tickets for')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('address')
                    ->state(fn (Printer $record): string => "{$record->host}:{$record->port}")
                    ->fontFamily('mono'),
                TextColumn::make('paper_width')->label('Paper')->suffix(' mm'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->recordActions([
                Action::make('test')
                    ->label('Test print')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->action(function (Printer $record): void {
                        app(PrintQueue::class)->queue($record, 'test', Tickets::test($record), 'Test');

                        Notification::make()
                            ->success()
                            ->title('Test print queued')
                            ->body('It prints when the branch\'s print gateway next collects its jobs. See Kitchen → Print log.')
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePrinters::route('/'),
        ];
    }
}
