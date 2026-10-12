<?php

namespace App\Providers;

use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $connection = config('database.default');
        $configuration = (new ConfigurationUrlParser)->parseConfiguration(config('database.connections.'.$connection, []));
        $this->ensureArchiveDatabase($configuration['driver'] ?? '', $configuration['database'] ?? '');

        // Also guard explicit alternate connections, such as migrate --database=mariadb.
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event) {
            $this->ensureArchiveDatabase($event->connection->getDriverName(), $event->connection->getDatabaseName());
        });
    }

    private function ensureArchiveDatabase(string $driver, string $database): void
    {
        if (in_array($driver, ['mysql', 'mariadb'], true) && $database !== 'shipping_receiving_archive') {
            throw new RuntimeException('This standalone app requires the dedicated shipping_receiving_archive database. Check DB_DATABASE and DB_URL before continuing.');
        }
    }
}
