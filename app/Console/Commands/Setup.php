<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class Setup extends Command
{
    protected $signature = 'app:setup';
    protected $description = 'Run import command and set up admin and lab users after database refresh';

    public function handle()
    {
        // Step 1: Run the app:import-allergens command
        $this->info('Running app:import-allergens...');
        try {
            Artisan::call('app:import-allergens');
            $this->info('Import command completed successfully.');
        } catch (\Exception $e) {
            $this->error('Failed to run import command: ' . $e->getMessage());
            return 1;
        }

        // Step 2: Create or update admin user
        $this->info('Setting up admin user...');
        User::updateOrCreate(
            ['email' => 'admin@rezept-butler.com'],
            [
                'name' => 'admin',
                'password' => Hash::make('admin@rezept-butler.com'),
                'role' => 'admin',
            ]
        );
        // Step 2: Create or update admin user
        $this->info('Setting up admin user...');
        User::updateOrCreate(
            ['email' => 'hagl@pixelhoch.de'],
            [
                'name' => 'Hagl',
                'password' => Hash::make('hagl@pixelhoch.de'),
                'role' => 'admin',
            ]
        );
        $this->info('Admin user created/updated successfully.');

        // Step 3: Create or update ifm user
        $this->info('Setting up ifm user...');
        User::updateOrCreate(
            ['email' => 'ifm@rezept-butler.com'],
            [
                'name' => 'ifm',
                'password' => Hash::make('ifm@rezept-butler.com'),
                'role' => 'lab',
            ]
        );
        $this->info('IFM user created/updated successfully.');

        $this->info('Setup completed successfully.');
        return 0;
    }
}
