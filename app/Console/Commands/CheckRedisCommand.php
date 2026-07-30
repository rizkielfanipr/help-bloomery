<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Throwable;

class CheckRedisCommand extends Command
{
    protected $signature = 'app:check-redis';

    protected $description = 'Verify the Redis connections required by cache, session, and queue';

    public function handle(): int
    {
        $connections = array_values(array_unique([
            (string) config('database.redis.default.name', 'default'),
            (string) config('cache.stores.redis.connection', 'cache'),
            (string) config('queue.connections.redis.connection', 'default'),
        ]));

        foreach ($connections as $connection) {
            try {
                $response = Redis::connection($connection)->command('ping');

                if (! in_array(strtoupper((string) $response), ['PONG', '1'], true)) {
                    $this->components->error("Redis [{$connection}] memberi respons yang tidak valid.");

                    return self::FAILURE;
                }

                $this->components->info("Redis [{$connection}] terhubung.");
            } catch (Throwable $exception) {
                $this->components->error("Redis [{$connection}] gagal: {$exception->getMessage()}");

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->components->info('Redis siap digunakan untuk cache, session, dan queue.');

        return self::SUCCESS;
    }
}
