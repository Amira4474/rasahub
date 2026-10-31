{{-- Filament does not load the website's styles, so the plan is styled inline. --}}
<x-filament-panels::page>
    @php($tables = $this->tables())
    @php($canArrange = $this->canArrange())

    <div
        x-data="{
            tables: @js($tables),
            dragging: null,
            moved: false,
            start(event, table) {
                if (! {{ $canArrange ? 'true' : 'false' }}) return;
                const el = event.currentTarget;
                const rect = el.getBoundingClientRect();
                this.dragging = { table, el, dx: event.clientX - rect.left, dy: event.clientY - rect.top };
                el.setPointerCapture(event.pointerId);
            },
            move(event) {
                if (! this.dragging) return;
                const canvas = this.$refs.canvas.getBoundingClientRect();
                const el = this.dragging.el.getBoundingClientRect();
                const left = Math.min(Math.max(event.clientX - canvas.left - this.dragging.dx, 0), canvas.width - el.width);
                const top = Math.min(Math.max(event.clientY - canvas.top - this.dragging.dy, 0), canvas.height - el.height);
                this.dragging.table.x = Math.round(left / canvas.width * 100);
                this.dragging.table.y = Math.round(top / canvas.height * 100);
                this.moved = true;
            },
            stop() { this.dragging = null; },
            save() {
                const positions = {};
                this.tables.forEach(t => positions[t.id] = { x: t.x, y: t.y });
                $wire.savePositions(positions).then(() => this.moved = false);
            },
        }"
        x-on:pointermove="move($event)"
        x-on:pointerup="stop()"
    >
        @if ($tables === [])
            <x-filament::section>
                This area has no active tables yet.
                @if ($canArrange)
                    Add them under <x-filament::link :href="\App\Filament\Resources\FloorAreas\FloorAreaResource::getUrl('edit', ['record' => $this->record])">Edit tables</x-filament::link>.
                @endif
            </x-filament::section>
        @else
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.75rem; font-size: 0.875rem; color: #6b7280;">
                <span>
                    @if ($canArrange)
                        Drag the tables into place, then save.
                    @else
                        The layout of this area. Only managers can change it.
                    @endif
                </span>
                @if ($canArrange)
                    <x-filament::button x-on:click="save()" x-bind:disabled="! moved" icon="heroicon-m-check">Save layout</x-filament::button>
                @endif
            </div>

            <div
                x-ref="canvas"
                role="img"
                aria-label="Floor plan of {{ $this->record->name }} with {{ count($tables) }} tables"
                style="position: relative; width: 100%; aspect-ratio: 16 / 10; overflow: hidden; touch-action: none; border-radius: 0.75rem; border: 1px solid rgb(107 114 128 / 0.3); background-color: rgb(107 114 128 / 0.06); background-image: linear-gradient(rgb(107 114 128 / 0.12) 1px, transparent 1px), linear-gradient(90deg, rgb(107 114 128 / 0.12) 1px, transparent 1px); background-size: 5% 8%;"
            >
                <template x-for="table in tables" :key="table.id">
                    <div
                        x-on:pointerdown.prevent="start($event, table)"
                        :style="`position: absolute; left: ${table.x}%; top: ${table.y}%; width: ${table.shape === 'rectangle' ? 14 : 8.5}%; aspect-ratio: ${table.shape === 'rectangle' ? '2 / 1' : '1 / 1'}; border-radius: ${table.shape === 'round' ? '9999px' : '0.5rem'}; cursor: {{ $canArrange ? 'grab' : 'default' }};`"
                        style="display: flex; flex-direction: column; align-items: center; justify-content: center; user-select: none; background: rgb(var(--primary-500, 245 158 11) / 0.15); border: 2px solid rgb(var(--primary-600, 217 119 6)); color: inherit; line-height: 1.1; text-align: center;"
                    >
                        <span x-text="table.name" style="font-weight: 700; font-size: 0.9rem;"></span>
                        <span x-text="`${table.seats} seats`" style="font-size: 0.7rem; opacity: 0.75;"></span>
                    </div>
                </template>
            </div>
        @endif
    </div>
</x-filament-panels::page>
