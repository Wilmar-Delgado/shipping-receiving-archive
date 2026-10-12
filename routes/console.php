<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('archive:create-admin', function () {
    $data = [
        'name' => $this->ask('Administrator name'),
        'email' => strtolower(trim($this->ask('Administrator email'))),
        'password' => $this->secret('Password (at least 12 characters)'),
        'password_confirmation' => $this->secret('Confirm password'),
    ];
    $validator = Validator::make($data, [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users'],
        'password' => ['required', 'confirmed', Password::min(12)],
    ]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }

        return 1;
    }
    User::create([...$validator->validated(), 'role' => 'admin', 'is_active' => true]);
    $this->info('Administrator created.');

    return 0;
})->purpose('Create an administrator without public registration');

Artisan::command('archive:verify-database', function () {
    $connection = DB::connection();
    $configured = $connection->getDatabaseName();
    $active = $connection->selectOne('SELECT DATABASE() AS database_name')->database_name;
    if ($configured !== 'shipping_receiving_archive' || $active !== 'shipping_receiving_archive') {
        $this->error('Archive database verification failed.');

        return 1;
    }
    $this->info('Configured database: '.$configured);
    $this->info('Active database: '.$active);
    $this->info('Dedicated database exists and is accessible.');
    $schema = $connection->getSchemaBuilder();
    foreach (['users', 'document_types', 'parties', 'documents', 'document_references', 'document_files', 'migrations'] as $table) {
        $this->line($table.': '.($schema->hasTable($table) ? 'present' : 'missing'));
    }
    $this->line('Completed migrations: '.$connection->table('migrations')->count());

    return 0;
})->purpose('Verify the dedicated archive database without exposing credentials');
