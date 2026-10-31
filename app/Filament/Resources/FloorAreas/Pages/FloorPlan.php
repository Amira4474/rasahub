<?php

namespace App\Filament\Resources\FloorAreas\Pages;

use App\Filament\Resources\FloorAreas\FloorAreaResource;
use App\Models\DiningTable;
use App\Models\FloorArea;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * The tables of a floor area laid out as they stand in the room. Those who
 * may manage tables drag them into place; everyone else sees the layout.
 *
 * @property FloorArea $record
 */
class FloorPlan extends Page
{
    use InteractsWithRecord;

    protected static string $resource = FloorAreaResource::class;

    protected string $view = 'filament.floor-plan.plan';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(auth()->user()->can('view', $this->record), 403);
    }

    public function getTitle(): string
    {
        return "{$this->record->name} floor plan";
    }

    public function getSubheading(): ?string
    {
        return $this->record->branch->name;
    }

    public function canArrange(): bool
    {
        return auth()->user()->can('update', $this->record);
    }

    /**
     * @return list<array{id: int, name: string, seats: int, shape: string, x: int, y: int}>
     */
    public function tables(): array
    {
        return $this->record->tables()
            ->where('is_active', true)
            ->get()
            ->map(fn (DiningTable $table): array => [
                'id' => $table->id,
                'name' => $table->name,
                'seats' => $table->seats,
                'shape' => $table->shape->value,
                'x' => $table->pos_x,
                'y' => $table->pos_y,
            ])
            ->all();
    }

    /**
     * Save where each table has been dragged to, as a percentage of the
     * plan's width and height.
     *
     * @param  array<int|string, array{x: mixed, y: mixed}>  $positions
     */
    public function savePositions(array $positions): void
    {
        abort_unless($this->canArrange(), 403);

        // Only this area's tables can be moved from here.
        $tables = $this->record->tables()->whereKey(array_keys($positions))->get();

        foreach ($tables as $table) {
            $table->update([
                'pos_x' => max(0, min(100, (int) $positions[$table->id]['x'])),
                'pos_y' => max(0, min(100, (int) $positions[$table->id]['y'])),
            ]);
        }

        Notification::make()->success()->title('Layout saved')->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('Edit tables')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->visible(fn (): bool => $this->canArrange())
                ->url(fn (): string => FloorAreaResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
