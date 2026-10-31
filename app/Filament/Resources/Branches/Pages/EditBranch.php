<?php

namespace App\Filament\Resources\Branches\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Branches\BranchResource;
use App\Models\Branch;
use App\Tenancy\TenantContext;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * @property Branch $record
 */
class EditBranch extends EditRecord
{
    protected static string $resource = BranchResource::class;

    /**
     * A new gateway key, shown once straight after it is made.
     */
    public ?string $gatewayKey = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('gatewayKey')
                ->label('Print gateway key')
                ->icon(Heroicon::OutlinedKey)
                ->color('gray')
                ->visible(fn (): bool => auth()->user()->can(Permission::ManageKitchen))
                ->modalHeading('Print gateway key')
                ->modalDescription(fn (): string => ($this->record->gateway_key
                    ? 'This branch already has a key'.($this->record->gateway_seen_at ? ', last used '.$this->record->gateway_seen_at->diffForHumans() : '').'. Making a new one stops the old one working. '
                    : '')
                    .'The print gateway at the branch uses the key to collect print jobs. It is shown once.')
                ->modalSubmitActionLabel(fn (): string => $this->record->gateway_key ? 'Make a new key' : 'Make a key')
                ->action(function (): void {
                    $this->gatewayKey = $this->record->issueGatewayKey(app(TenantContext::class)->business()->acronym);
                    $this->replaceMountedAction('showGatewayKey');
                }),
            DeleteAction::make(),
        ];
    }

    public function showGatewayKeyAction(): Action
    {
        return Action::make('showGatewayKey')
            ->modalHeading('Copy the gateway key now')
            ->modalDescription('Set it as RASAHUB_GATEWAY_KEY on the computer running the print gateway at this branch. It will not be shown again.')
            ->modalContent(fn () => view('filament.gateway-key', ['key' => $this->gatewayKey, 'url' => url('/')]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Done');
    }
}
