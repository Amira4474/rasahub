<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-super-admin')]
#[Description('Create a platform user with the Super Admin role')]
class CreateSuperAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = text('Name', required: true, validate: ['name' => 'max:255']);

        $email = text('Email address', required: true, validate: ['email' => 'email|max:255|unique:users,email']);

        $password = password('Password', required: true, validate: fn (string $value): ?string => Validator::make(
            ['password' => $value],
            ['password' => Password::default()],
        )->errors()->first('password') ?: null);

        $user = DB::transaction(function () use ($name, $email, $password): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole(Role::SuperAdmin);

            return $user;
        });

        $this->components->info("Super Admin [{$user->email}] created. Sign in at ".url('/admin'));

        return self::SUCCESS;
    }
}
