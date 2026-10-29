<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create the first BabyStore administrator';

    public function handle(): int
    {
        if (User::where('is_admin', true)->exists()) {
            $this->error('An administrator already exists.');
            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Admin name'));
        $email = strtolower(trim((string) $this->ask('Admin email')));
        $password = (string) $this->secret('Admin password (minimum 12 characters)');
        $confirmation = (string) $this->secret('Confirm admin password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($name, $email, $password) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            $user->forceFill(['is_admin' => true])->save();

            return $user;
        });

        $this->info("Administrator created successfully: {$user->email}");

        return self::SUCCESS;
    }
}