<?php

namespace App\Filament\Resources\PrintJobs;

use App\Enums\PrintJobStatus;
use App\Filament\Concerns\ManagesKitchen;
use App\Filament\Resources\PrintJobs\Pages\ListPrintJobs;
use App\Models\Branch;
use App\Models\PrintJob;
use App\Printing\PrintQueue;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Everything sent to the printers, and whether it printed.
 */
class PrintJobResource extends Resource
{
    use ManagesKitchen;

    protected static ?string $model = PrintJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Kitchen';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Print log';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('branch_id', Branch::visibleTo(auth()->user())->select('id'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('printer.branch'))
            ->defaultSort('id', 'desc')
            ->poll('10s')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('printer.name')
                    ->label('Printer')
                    ->description(fn (PrintJob $record): ?string => $record->printer?->branch?->name),
                TextColumn::make('type')->state(fn (PrintJob $record): string => $record->typeLabel()),
                TextColumn::make('reference')->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (PrintJobStatus $state): string => $state->label())
                    ->color(fn (PrintJobStatus $state): string => $state->color())
                    ->tooltip(fn (PrintJob $record): ?string => $record->error),
                TextColumn::make('attempts')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(array_column(array_map(fn (PrintJobStatus $s): array => [$s->value, $s->label()], PrintJobStatus::cases()), 1, 0)),
                SelectFilter::make('printer')
                    ->relationship('printer', 'name'),
            ])
            ->recordActions([
                Action::make('reprint')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->action(function (PrintJob $record): void {
                        app(PrintQueue::class)->reprint($record);

                        Notification::make()->success()->title('Queued to print again')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrintJobs::route('/'),
        ];
    }
}
