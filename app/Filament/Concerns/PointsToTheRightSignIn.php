<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * For sign-in pages: when someone gives the right password on the wrong
 * page, tell them which page is theirs. This only happens after the password
 * has been checked, so it tells nothing to someone guessing.
 */
trait PointsToTheRightSignIn
{
    protected function sendToTheRightSignIn(string $message, string $label, string $url): never
    {
        Notification::make()
            ->title('Wrong sign-in page')
            ->body($message)
            ->warning()
            ->persistent()
            ->actions([
                Action::make('rightSignIn')->label($label)->url($url)->button(),
            ])
            ->send();

        throw ValidationException::withMessages(['data.email' => $message]);
    }
}
