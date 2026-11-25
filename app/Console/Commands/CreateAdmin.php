<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'agency:create-admin {email} {name} {--locale=pl}';

    protected $description = 'Tworzy konto administratora (2FA konfigurowane przy pierwszym logowaniu)';

    public function handle(): int
    {
        $password = $this->secret('Hasło (min. 10 znaków)');
        if (mb_strlen((string) $password) < 10) {
            $this->error('Hasło jest za krótkie.');

            return self::FAILURE;
        }
        User::create([
            'email' => $this->argument('email'),
            'name' => $this->argument('name'),
            'password' => $password,
            'role' => Role::Admin,
            'locale' => $this->option('locale'),
        ]);
        $this->info('Utworzono administratora.');

        return self::SUCCESS;
    }
}
